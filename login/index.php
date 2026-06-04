<?php
  require __DIR__ . '/../bootstrap.php';
  
  $bootstrapData = bootstrapAccounts();

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Login
  function checkRememberMeCookiePresence(): bool {
    return isset($_COOKIE['rememberMe']) && !empty($_COOKIE['rememberMe']);
  }

  function rememberAuthenticatedSession(string $userCode, string $sessionID): void {
    global $rememberMeEncryptionKey;

    $cookieValue = encryptTokenPayload(
      ['purpose' => 'remember_me', 
      'usercode' => $userCode, 
      'session_id' => $sessionID, 
      'exp' => time() + (7 * 86400)
      ], $rememberMeEncryptionKey);

    if (is_string($cookieValue) && $cookieValue !== '') {
      createCookie('rememberMe', $cookieValue, 7);
    }
  }

  function completeLogin(PDO $db1, string $role, array $userRecord, string $rememberMe): bool {
    $roleMap = getRoleDatabaseMap($role);

    if ($roleMap === null) {
      return false;
    }

    $generatedSessionID = generateSessionID();

    $success = setupUserSession($role, $userRecord, $generatedSessionID) && saveDeviceDetails(
        $db1,
        $roleMap['prefix'],
        [
          $userRecord[$roleMap['id_column']],
          $userRecord[$roleMap['usercode_column']],
          $userRecord[$roleMap['email_column']],
        ],
        $rememberMe,
        $generatedSessionID
      );

    if (!$success) {
      return false;
    }

    if (checkForEquality($rememberMe, 'on', 'strict')) {
      rememberAuthenticatedSession($userRecord[$roleMap['usercode_column']], $generatedSessionID);
    }

    session_regenerate_id(true);
    return true;
  }

  function reactivateStudentAccount(PDO $db1, PDO $db2, array $userRecord): bool {
    $currentAttemptForActivatingUser = 0;
    $maxRetriesForActivatingUser = 3;

    while ($currentAttemptForActivatingUser < $maxRetriesForActivatingUser) {
      try {
        $db1->beginTransaction();

        $updateActivationStatus = $db1->prepare(
          'UPDATE student_configurations
           SET student_account_activation_status = :activationStatus
           WHERE student_usercode = :usercode
           LIMIT 1'
        );
        $updateActivationStatus->bindValue(':activationStatus', 1, PDO::PARAM_INT);
        $updateActivationStatus->bindValue(':usercode', $userRecord['student_usercode'], PDO::PARAM_STR);
        $updateActivationStatus->execute();

        $clearDeactivationTimestamp = $db1->prepare(
          'UPDATE student_timestamps
           SET student_account_deactivation_timestamp = :deactivationTimestamp
           WHERE student_usercode = :usercode
           LIMIT 1'
        );
        $clearDeactivationTimestamp->bindValue(':deactivationTimestamp', null, PDO::PARAM_NULL);
        $clearDeactivationTimestamp->bindValue(':usercode', $userRecord['student_usercode'], PDO::PARAM_STR);
        $clearDeactivationTimestamp->execute();

        $db1->commit();
        return true;
      }
      catch (PDOException $ex) {
        if ($db1->inTransaction()) {
          $db1->rollBack();
        }

        if (!isRetryablePdoException($ex)) {
          logAppError(
            $db2,
            $userRecord['student_usercode'],
            getCurrentURL(),
            'DATABASE',
            'Error occured while Activating User Account: ' . $ex->getMessage()
          );
          return false;
        }

        $currentAttemptForActivatingUser++;
        sleep(5);
      }
    }

    return false;
  }

  function queueLoginAttemptNotification(PDO $db2, string $role, array $userRecord): void {
    $roleMap = getRoleDatabaseMap($role);

    if ($roleMap === null) {
      return;
    }

    $userCode = $userRecord[$roleMap['usercode_column']] ?? null;
    $email = $userRecord[$roleMap['email_column']] ?? null;

    if (!is_string($userCode) || $userCode === '' || !is_string($email) || $email === '') {
      return;
    }

    try {
      if (!queueNewLoginAttemptEmail($db2, $userCode, $email, getUserLoginEnvironment())) {
        logAppError($db2, $userCode, getCurrentURL(), 'MAIL_QUEUE', 'Unable to queue new login attempt email.');
      }
    }
    catch (Throwable $ex) {
      logAppError($db2, $userCode, getCurrentURL(), 'MAIL_QUEUE', 'Error occured while queueing new login attempt email: ' . substr($ex->getMessage(), 0, 170));
    }
  }

  $loginRateLimitKey = 'accounts_login';

  if (!checkLoginStatus($db1)) {
    if (isset($_POST['loginUserBtn'])) {
      $enteredEmail    = escapeOutput($_POST['email']) ?? null;
      $enteredPassword = escapeOutput($_POST['password']) ?? null;
      $csrfToken       = escapeOutput($_POST['csrf_token']) ?? null;
      $userRole        = escapeOutput($_POST['chosen_userRole']) ?? null;
      $rememberMe      = $_POST['optForRememberMe'] ?? 'off';
      $loginFailed = false;

      $rateLimit = getRateLimitStatus($loginRateLimitKey, 5, 900, 300);
      if (($rateLimit['limited'] ?? false) === true) {
        $retryAfter = max(1, (int) ($rateLimit['retry_after'] ?? 0));
        setToast('Too many sign-in attempts. Please wait ' . $retryAfter . ' seconds before trying again.', 'danger', 7000);
      }
      else {
        if (validateCsrfToken($csrfToken)) {
          unsetCsrfToken();

          if (validateEmail($enteredEmail)) {
            $roleMap = getRoleDatabaseMap((string) $userRole);

            if (!checkForEquality($roleMap, null, 'strict')) {
              $userRecord = fetchUserRecordByRoleAndLookup($db1, $userRole, $roleMap['email_column'], $enteredEmail, true);

              if (!checkForEquality($userRecord, null, 'strict')) {
                $passwordColumn = $roleMap['password_column'];
                $storedPassword = $userRecord[$passwordColumn] ?? null;
                
                if (checkForEquality($userRole, 'student', 'strict') || checkForEquality($userRole, 'admin', 'strict')) {
                  if(validatePassword($enteredPassword)) { 
                    $continueLoginWithPasswordVerification = true; 
                  }
                  else {
                    $continueLoginWithPasswordVerification = false;
                    $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);

                    setToast('Please enter a valid password.', 'danger', 7000);

                    $passwordValidationStatus = 'is-invalid';
                    $passwordHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                          <span class="material-symbols-outlined me-1">info</span>
                                          Invalid Password Entered.
                                          </span>';
                  }
                }
                elseif (checkForEquality($userRole, 'faculty', 'strict')) { $continueLoginWithPasswordVerification = true; }
                else $continueLoginWithPasswordVerification = false;

                if ($continueLoginWithPasswordVerification) {
                  if (password_verify($enteredPassword, $storedPassword)) {
                    if (checkForEquality($userRole, 'student', 'strict') && checkForEquality((int) ($userRecord['student_account_activation_status'] ?? 0), 0, 'strict')) { // Student Account Deactivated. Attempt for Reactivation under 30-day window.
                      if (getSecondsPassed($userRecord['student_account_deactivation_timestamp'] ?? '') <= 2592000) {
                        if (!reactivateStudentAccount($db1, $db2, $userRecord)) { // Error Activating Student Account
                          setToast('Failed to reactivate student account.', 'danger', 7000);
                        }
                        else { // Student Account Reactivated
                          if(completeLogin($db1, $userRole, $userRecord, $rememberMe)) {
                            queueLoginAttemptNotification($db2, $userRole, $userRecord);
                            clearRateLimit($loginRateLimitKey);
                            setToast('Login successful. Taking you to Dashboard.', 'success', 7000);
                            redirectTo('../dashboard/', 3);
                          }
                          else { // Login Attempt couldn't be completed
                            setToast('Error occured while creating your login session. Contact Admin.', 'danger', 7000);
                            $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);
                          }
                        }
                      }
                      else { // Student Account Scheduled for Permanent Deletion
                        setToast('Student account is scheduled for deletion. The account will be permanently deleted soon.', 'danger', 7000);
                      }
                    }
                    else {
                      if (completeLogin($db1, $userRole, $userRecord, $rememberMe)) {
                        queueLoginAttemptNotification($db2, $userRole, $userRecord);
                        clearRateLimit($loginRateLimitKey);
                        setToast('Login successful. Taking you to Dashboard.', 'success', 7000);
                        redirectTo('../dashboard/', 3);
                      }
                      else {
                        setToast('Error occured while creating your login session. Contact Admin.', 'danger', 7000);
                        $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);
                      }
                    }
                  }
                  else { // Invalid Password
                    setToast('Invalid password. Check your credentials and try again.', 'danger', 7000);
                    $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);

                    $passwordValidationStatus = 'is-invalid';
                    $passwordHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                          <span class="material-symbols-outlined me-1">info</span>
                                          Invalid Password Entered
                                        </span>';
                  }
                }
              }
              else { // No User Exists for Provided E-Mail
                setToast('No user found for the provided email address.', 'danger', 7000);
                $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);

                $emailValidationStatus = 'is-invalid';
                $emailHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                      <span class="material-symbols-outlined me-1">info</span>
                                      No user exists for the provided email address.
                                    </span>';
              }
            }
          }
          else { // Either of User Fields can't be validated
            $retryAfter = registerRateLimitFailure($loginRateLimitKey, 5, 900, 300);

            setToast('Please enter a valid email address.', 'danger', 7000);

            $emailValidationStatus = 'is-invalid';
            $emailHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                  <span class="material-symbols-outlined me-1">info</span>
                                  Invalid Email Entered.
                                </span>';
          }
        }
        else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      }
    }

    if (checkRememberMeCookiePresence()) {
      $rememberMePayload = parseRememberMeCookieValue($_COOKIE['rememberMe'], $rememberMeEncryptionKey);

      if (!is_array($rememberMePayload)) { // Remember Me Cookie Data Corrupted
        setToast('Corrupted Remember Me data cleared.', 'danger', 7000);
        destroyCookie('rememberMe');
      } 
      else {
        $rememberMeUsercode = $rememberMePayload['usercode'] ?? null;
        $rememberMeSessionID = $rememberMePayload['session_id'] ?? null;
        $rememberMeUserRole = getUserRoleUsingUsercode($rememberMeUsercode) ?? null;

        if (!is_null($rememberMeUserRole) && !is_null($rememberMeSessionID) && !is_null($rememberMeUsercode)) {
          $roleMap = getRoleDatabaseMap($rememberMeUserRole);

          $rememberMeUserRecord = fetchUserRecordByRoleAndLookup($db1, ($rememberMeUserRole ?? null), $roleMap['usercode_column'], ($rememberMeUsercode ?? null), true) ?? null;

          $rememberMeEmailColumn = $roleMap['email_column'];
          $rememberMeEmail = $rememberMeUserRecord[$rememberMeEmailColumn] ?? null;

          if (!is_null($rememberMeUserRecord) && !is_null($rememberMeEmail)) {

            // Current Active Session ID must match Current Saved Remember Me Session ID
            if (checkForEquality($rememberMeSessionID, $rememberMeUserRecord[$roleMap['current_session_column']], 'strict')) {
              if (isset($_POST['loginUsingSavedUserCookie'])) {
                $success = handleRememberMeAutoLogin($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID, $rememberMeUserRecord);

                if ($success) {
                  setToast('Login successful. Taking you to Dashboard.', 'success', 7000);
                }
                else setToast('Some error occurred during login. Taking you to Dashboard', 'danger', 7000);

                queueLoginAttemptNotification($db2, $rememberMeUserRole, $rememberMeUserRecord);
                clearRateLimit($loginRateLimitKey);
                redirectTo('../dashboard/', 3);
              }
              elseif (isset($_POST['deleteUserCookie'])) {
                $success = deleteRememberMeData($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID);

                if ($success) {
                  setToast('Remember Me data cleared.', 'danger', 7000);
                }
                else setToast('Some error occurred while clearing Remember Me data. Remember Me data cleared.', 'danger', 7000);

                deleteRememberMeData($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID);
                redirectTo('./', 0);
              }
            }
            else { // Account was logged in somewhere else
              setToast('This account is already logged in elsewhere. Remember Me Data Cleared.', 'danger', 7000);
              removeRememberMeDevice($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID);
              redirectTo('./', 0);
            }
          } 
          else {
            setToast('Corrupted Remember Me data cleared.', 'danger', 7000);
            deleteRememberMeData($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID);
            redirectTo('./', 0);
          }
        }
        else {
          setToast('Corrupted Remember Me data cleared.', 'danger', 7000);
          deleteRememberMeData($db1, $db2, $rememberMeUserRole, $rememberMeUsercode, $rememberMeSessionID);
          redirectTo('./', 0);
        }
      }
    }
  }
?>

<?php // Headers 
  $page_title = "Login | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
?>

<?php if (checkForEquality(checkLoginStatus($db1), false, 'strict')): ?>
  <?php if (checkForEquality(checkRememberMeCookiePresence(), true, 'strict') && $rememberMeEmail !== null): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-md-9 col-lg-6 py-8 py-md-11 px-5 px-sm-0">
            <h1 class="mb-7 fw-bold text-center">
              Login using Saved User Details
            </h1>
            <form class="mb-6" method="POST" action="./">
              <div class="form-group">
                <button class="btn btn-primary w-100" 
                        name="loginUsingSavedUserCookie" 
                        type="submit">
                  Login using 
                  <?php 
                    echo htmlspecialchars(" (" . $rememberMeEmail . ")");
                  ?>
                </button>
              </div>
              <div class="form-group mb-5">
                <button class="btn btn-danger w-100"
                        name="deleteUserCookie"
                        type="submit">
                  Remove Login Data
                </button>
              </div>
            </form>
            <p class="mb-0 lead text-center text-body-secondary">
              Don't have an account yet? <br> <a href="../register/">Register</a>.
            </p>
          </div>
        </div>
      </div>
    </section>

  <?php elseif (checkForEquality(checkRememberMeCookiePresence(), false, 'strict')): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-lg-6 col-md-9 px-8 px-md-5 py-8 py-md-8">
            <h1 class="mb-2 fw-bold text-center">
              Login
            </h1>
            <p class="text-lead mb-7 text-center text-body-secondary">
              Login using Account Credentials.
            </p>
            <form class="mb-6" method="POST" action="./">
              <div class="form-group mb-7 ms-5">
                <div id="userRoleHelp" class="col-auto form-label ms-n4">
                  Choose User Type: 
                </div>
                <div class="col-auto d-flex row">
                  <div class="col col-4 form-check">
                    <input type="radio" 
                          name="chosen_userRole"
                          value="student"
                          class="form-check-input" 
                          id="student_check"
                          checked>
                    <label class="form-check-label" for="student_check">Student</label>
                  </div>
                  <div class="col col-4 form-check">
                    <input type="radio" 
                          name="chosen_userRole"
                          value="faculty"
                          class="form-check-input" 
                          id="faculty_check">
                    <label class="form-check-label" for="faculty_check">Faculty</label>
                  </div>
                  <div class="col col-4 form-check">
                    <input type="radio" 
                          name="chosen_userRole"
                          value="admin"
                          class="form-check-input" 
                          id="admin_check">
                    <label class="form-check-label" for="admin_check">Admin</label>
                  </div>
                </div>
              </div>

              <div class="form-group mb-5">
                <label class="visually-hidden" for="email">Email Address</label>
                <input class="form-control <?php echo $emailValidationStatus ?? null; ?>" 
                      id="email" 
                      type="email" 
                      name="email"
                      aria-describedby="email"
                      placeholder="Enter your Email Address..." 
                      required />
                <div id="email" class="form-text">
                  <?php echo $emailHelpText ?? 'Enter your Registered E-Mail.'; ?>
                </div>
              </div>

              <div class="form-group mb-5">
                <label class="visually-hidden" for="password">Password</label>
                <input class="form-control <?php echo $passwordValidationStatus ?? null; ?>" 
                      id="password" 
                      type="password"
                      name="password"
                      aria-describedby="password"
                      placeholder="Enter your Password..." 
                      required/>
                <div id="password" class="form-text">
                  <?php echo $passwordHelpText ?? 'Enter your Account Password.'; ?>
                </div>
              </div>

              <div class="form-group mb-5">
                <input type="checkbox" 
                      id="optForRememberMe"
                      name="optForRememberMe"
                      checked />
                <label for="optForRememberMe">&nbsp;&nbsp;Remember your Credentials</label>
              </div>

              <input type="hidden" 
                     name="csrf_token" 
                     value="<?php echo htmlspecialchars(generateCsrfToken()); ?>"
              />

              <div class="form-group mb-5">
                <button class="btn btn-primary w-100" 
                        name="loginUserBtn" 
                        type="submit">
                  Login to Dashboard
                </button>
              </div>
            </form>

            <p class="mb-2 text-center text-body-secondary">
              Don't have an account yet? <a href="../register/">Register</a>.
            </p>

            <p class="mb-2 text-center text-body-secondary">
             Forgot your Password? <a href="../changePassword/">Forgot Password</a>.
            </p>

          </div>
        </div>
      </div>
    </section>

  <?php endif; ?>


<?php elseif (checkForEquality(checkLoginStatus($db1), true, 'strict')): ?>
  <section class="section-border border-primary ff-inter">
    <div class="container d-flex flex-column">
      <div class="row align-items-center justify-content-center gx-0 min-vh-100">
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Access Denied
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            This page is accessible only for users who are not logged in. Head back to Dashboard to continue using our services.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../dashboard/">
              Back to Dashboard Page
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif ?>

<?php // Footers
  require_once '../components/footer.php'; 
?>
