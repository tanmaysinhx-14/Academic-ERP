<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => false,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Change Password
  function clearPasswordResetChallenge(bool $preserveFormStatus = false): void {
    unset(
      $_SESSION['passwordResetChallenge'],
      $_SESSION['passwordResetUserRole'],
      $_SESSION['passwordResetUserCode'],
      $_SESSION['passwordResetEmail'],
      $_SESSION['OTP']
    );

    if (!$preserveFormStatus) {
      $_SESSION['changePasswordFormStatus'] = 'OTP_REQUEST_PENDING';
    }
  }

  function getPasswordResetChallenge(): ?array {
    $challenge = $_SESSION['passwordResetChallenge'] ?? null;

    if (!is_array($challenge)) {
      return null;
    }

    $requiredKeys = [
      'role',
      'email',
      'known_identity',
      'otp_hash',
      'otp_expires_at',
      'attempts',
      'max_attempts',
      'verified',
    ];

    foreach ($requiredKeys as $key) {
      if (!array_key_exists($key, $challenge)) {
        clearPasswordResetChallenge();
        return null;
      }
    }

    return $challenge;
  }

  function storePasswordResetChallenge(array $challenge): void {
    $_SESSION['passwordResetChallenge'] = $challenge;
  }

  function isPasswordResetChallengeExpired(array $challenge): bool {
    return (int) ($challenge['otp_expires_at'] ?? 0) <= time();
  }

  function buildPasswordResetChallenge(string $role, ?string $userCode, string $email, int $otp, bool $knownIdentity, int $ttlSeconds = 600): array {
    return [
      'role' => $role,
      'usercode' => $userCode,
      'email' => $email,
      'known_identity' => $knownIdentity,
      'otp_hash' => password_hash((string) $otp, PASSWORD_DEFAULT),
      'otp_expires_at' => time() + max(120, $ttlSeconds),
      'attempts' => 0,
      'max_attempts' => 5,
      'verified' => false,
    ];
  }

  function getPasswordResetIdentity(PDO $db1, ?array $currentUserRecord = null): ?array {
    if (checkLoginStatus($db1)) {
      $role = getUserRoleUsingUsercode($_SESSION['usercode'] ?? null);

      if ($role === null) {
        return null;
      }

      return [
        'role' => $role,
        'usercode' => $_SESSION['usercode'],
        'email' => $_SESSION['email'],
        'record' => $currentUserRecord ?? getAuthenticatedUserRecord($db1),
      ];
    }

    $challenge = getPasswordResetChallenge();

    if (
      $challenge === null ||
      ($challenge['known_identity'] ?? false) !== true ||
      empty($challenge['usercode'])
    ) {
      return null;
    }

    $role = $challenge['role'];
    $roleMap = getRoleDatabaseMap((string) $role);

    if ($roleMap === null) {
      clearPasswordResetChallenge();
      return null;
    }

    $record = fetchUserRecordByRoleAndLookup(
      $db1,
      $role,
      $roleMap['usercode_column'],
      $challenge['usercode'],
      true
    );

    if ($record === null) {
      clearPasswordResetChallenge();
      return null;
    }

    return [
      'role' => $role,
      'usercode' => $challenge['usercode'],
      'email' => $challenge['email'],
      'record' => $record,
    ];
  }

  function sendPasswordResetOtp(string $email, int $otp): bool {
    $mail = createConfiguredMailer();
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Password Change OTP | Career Institute';
    $mail->Body = '
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <title>Password Change OTP</title>
      </head>
      <body style="margin:0; padding:0; background-color:#f4f4f4;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;">
          <tr>
            <td align="center" style="padding:24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-collapse:collapse;">
                <tr>
                  <td align="center" style="background-color:#007BFF; padding:22px;">
                    <h1 style="margin:0; font-family:Arial, sans-serif; font-size:24px; color:#ffffff;">
                      Career Institute
                    </h1>
                  </td>
                </tr>
                <tr>
                  <td style="padding:30px; font-family:Arial, sans-serif; color:#333333;">
                    <p style="margin:0 0 14px 0; font-size:14px; line-height:1.6;">
                      A password reset request was received for <strong>' . escapeOutput($email) . '</strong>.
                    </p>
                    <p style="margin:0 0 18px 0; font-size:14px; line-height:1.6;">
                      Use the following One-Time Password (OTP) to continue:
                    </p>
                    <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:22px auto;">
                      <tr>
                        <td align="center" style="padding:14px 26px; font-size:20px; letter-spacing:3px; font-weight:bold; font-family:Courier New, monospace; color:#333333; background-color:#f8f9fb; border:2px dashed #007BFF;">
                          ' . $otp . '
                        </td>
                      </tr>
                    </table>
                    <p style="margin:0; font-size:13px; line-height:1.6;">
                      If you did not request this change, you can safely ignore this email.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </body>
      </html>
    ';
    $mail->AltBody = 'Password reset OTP for Career Institute: ' . $otp;

    return $mail->send();
  }

  $allowedStages = [
    'requestOTP' => 'OTP_REQUEST_PENDING',
    'verifyOTP' => 'OTP_VERIFICATION_PENDING',
    'changePassword' => 'CHANGE_PASSWORD_PENDING',
  ];

  $requestedStage = $_GET['stage'] ?? 'requestOTP';
  if (!isset($allowedStages[$requestedStage])) {
    $requestedStage = 'requestOTP';
  }

  if (!isset($_SESSION['changePasswordFormStatus'])) {
    $_SESSION['changePasswordFormStatus'] = 'OTP_REQUEST_PENDING';
  }

  $passwordResetChallenge = getPasswordResetChallenge();

  if (
    $passwordResetChallenge !== null &&
    isPasswordResetChallengeExpired($passwordResetChallenge) &&
    $_SESSION['changePasswordFormStatus'] !== 'OTP_REQUEST_PENDING'
  ) {
    clearPasswordResetChallenge();
    setToast('Your reset code has expired. Please request a new OTP.', 'warning', 7000);
    redirectTo('./?stage=requestOTP', 0);
  }

  if (
    $_SESSION['changePasswordFormStatus'] !== 'OTP_REQUEST_PENDING' &&
    $passwordResetChallenge === null
  ) {
    clearPasswordResetChallenge();
    redirectTo('./?stage=requestOTP', 0);
  }

  if (
    $_SESSION['changePasswordFormStatus'] === 'CHANGE_PASSWORD_PENDING' &&
    (($passwordResetChallenge['verified'] ?? false) !== true)
  ) {
    clearPasswordResetChallenge();
    redirectTo('./?stage=requestOTP', 0);
  }

  if ($allowedStages[$requestedStage] !== $_SESSION['changePasswordFormStatus']) {
    $requestedStage = match ($_SESSION['changePasswordFormStatus']) {
      'OTP_VERIFICATION_PENDING' => 'verifyOTP',
      'CHANGE_PASSWORD_PENDING' => 'changePassword',
      default => 'requestOTP',
    };
    redirectTo('./?stage=' . $requestedStage, 0);
  }

  $resetIdentity = getPasswordResetIdentity($db1, $userRecord ?? null);
  $passwordResetRequestRateLimitKey = 'accounts_password_reset_request';
  $passwordResetVerifyRateLimitKey = 'accounts_password_reset_verify';

  if (isset($_POST['generateOTPButton'])) {
    $csrfToken = escapeOutput($_POST['csrf_token']) ?? null;

    if (validateCsrfToken($csrfToken)) {
      unsetCsrfToken();

      $requestRateLimit = getRateLimitStatus($passwordResetRequestRateLimitKey, 4, 900, 300);
      if (($requestRateLimit['limited'] ?? false) === true) {
        setToast(
          'Too many OTP requests. Please wait ' . max(1, (int) ($requestRateLimit['retry_after'] ?? 0)) . ' seconds before trying again.',
          'danger',
          7000
        );
      }
      else {
        $challengeRole = null;
        $challengeEmail = null;
        $knownIdentity = $resetIdentity !== null;

        if (!checkLoginStatus($db1)) {
          $enteredEmail = escapeOutput($_POST['email']) ?? null;
          $requestedRole = escapeOutput($_POST['chosen_userRole']) ?? null;
          $roleMap = getRoleDatabaseMap((string) $requestedRole);

          $challengeRole = is_string($requestedRole) ? $requestedRole : '';
          $challengeEmail = is_string($enteredEmail) ? $enteredEmail : '';

          if (!validateEmail($enteredEmail)) {
            $emailValidationStatus = 'is-invalid';
            $emailHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                <span class="material-symbols-outlined me-1">info</span>
                                Enter a valid registered email address.
                              </span>';
            setToast('Please enter a valid registered email address.', 'danger', 7000);
          }
          elseif ($roleMap === null) {
            setToast('Please choose the account type for password reset.', 'danger', 7000);
          }
          else {
            $resetRecord = fetchUserRecordByRoleAndLookup(
              $db1,
              $requestedRole,
              $roleMap['email_column'],
              $enteredEmail,
              true
            );

            if ($resetRecord !== null) {
              $resetIdentity = [
                'role' => $requestedRole,
                'usercode' => $resetRecord[$roleMap['usercode_column']],
                'email' => $enteredEmail,
                'record' => $resetRecord,
              ];
              $knownIdentity = true;
            }
          }
        }
        else {
          $challengeRole = $resetIdentity['role'] ?? null;
          $challengeEmail = $resetIdentity['email'] ?? null;
        }

        if ($challengeRole === null && $resetIdentity !== null) {
          $challengeRole = $resetIdentity['role'];
        }

        if ($challengeEmail === null && $resetIdentity !== null) {
          $challengeEmail = $resetIdentity['email'];
        }

        if ($resetIdentity !== null) {
          $userrole = $resetIdentity['role'];
          $currentTimestamp = getCurrentTimestamp();
          $lastOtpTimestamp = $resetIdentity['record'][$userrole . '_last_OTP_request_timestamp'] ?? '01/01/2000 00:00:00';

          if (getSecondsPassed($lastOtpTimestamp) <= 60) {
            setToast('Please wait before requesting another OTP. Cooldown for 60 seconds.', 'warning', 7000);
          }
          else {
            $generatedOTP = random_int(100000, 999999);
            $currentAttemptForSendingPasswordChangeEmail = 0;
            $maxRetriesForSendingPasswordChangeEmail = 3;

            while ($currentAttemptForSendingPasswordChangeEmail < $maxRetriesForSendingPasswordChangeEmail) {
              try {
                if (
                  sendPasswordResetOtp($resetIdentity['email'], $generatedOTP) &&
                  updateOTPRequestTimestamp($db1, $userrole, $resetIdentity['usercode'], $currentTimestamp)
                ) {
                  storePasswordResetChallenge(buildPasswordResetChallenge(
                    $resetIdentity['role'],
                    $resetIdentity['usercode'],
                    $resetIdentity['email'],
                    $generatedOTP,
                    true
                  ));
                  $_SESSION['changePasswordFormStatus'] = 'OTP_VERIFICATION_PENDING';
                  clearRateLimit($passwordResetRequestRateLimitKey);

                  $message = checkLoginStatus($db1)
                    ? 'OTP has been sent to your registered email address.'
                    : 'If the account details match our records, an OTP has been sent to the registered email address.';
                  setToast($message, 'success', 7000);
                  redirectTo('./?stage=verifyOTP', 0);
                }
              }
              catch (Exception $ex) {
                logAppError($db2, $resetIdentity['usercode'], getCurrentURL(), 'MAIL', 'Error occured while sending OTP Email: ' . $ex->getMessage());

                if ($currentAttemptForSendingPasswordChangeEmail >= $maxRetriesForSendingPasswordChangeEmail - 1) {
                  setToast('Problem occurred while sending OTP Email. Contact Admin.', 'danger', 7000);
                }
              }

              $currentAttemptForSendingPasswordChangeEmail++;
              sleep(5);
            }
          }
        }
        elseif (is_string($challengeRole) && $challengeRole !== '' && is_string($challengeEmail) && $challengeEmail !== '') {
          storePasswordResetChallenge(buildPasswordResetChallenge(
            $challengeRole,
            null,
            $challengeEmail,
            random_int(100000, 999999),
            false
          ));
          $_SESSION['changePasswordFormStatus'] = 'OTP_VERIFICATION_PENDING';
          clearRateLimit($passwordResetRequestRateLimitKey);
          setToast('If the account details match our records, an OTP has been sent to the registered email address.', 'success', 7000);
          redirectTo('./?stage=verifyOTP', 0);
        }
      }
    }
    else {
      setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }
  }

  elseif (isset($_POST['verifyOTPButton'])) {
    $enteredOTP = escapeOutput($_POST['OTP'] ?? null);
    $csrfToken  = escapeOutput($_POST['csrf_token']) ?? null;

    if (validateCsrfToken($csrfToken)) {
      unsetCsrfToken();

      $passwordResetChallenge = getPasswordResetChallenge();
      $verifyRateLimit = getRateLimitStatus($passwordResetVerifyRateLimitKey, 5, 600, 120);

      if (($verifyRateLimit['limited'] ?? false) === true) {
        setToast(
          'Too many OTP attempts. Please wait ' . max(1, (int) ($verifyRateLimit['retry_after'] ?? 0)) . ' seconds before trying again.',
          'danger',
          7000
        );
      }
      elseif ($passwordResetChallenge === null) {
        clearPasswordResetChallenge();
        setToast('Your reset session expired. Please request a new OTP.', 'warning', 7000);
        redirectTo('./?stage=requestOTP', 0);
      }
      elseif (isPasswordResetChallengeExpired($passwordResetChallenge)) {
        clearPasswordResetChallenge();
        setToast('Your reset code has expired. Please request a new OTP.', 'warning', 7000);
        redirectTo('./?stage=requestOTP', 0);
      }
      elseif ((int) ($passwordResetChallenge['attempts'] ?? 0) >= (int) ($passwordResetChallenge['max_attempts'] ?? 5)) {
        clearPasswordResetChallenge();
        setToast('Too many incorrect OTP attempts. Please request a new OTP.', 'danger', 7000);
        redirectTo('./?stage=requestOTP', 0);
      }
      elseif (
        !is_numeric($enteredOTP) ||
        !password_verify((string) $enteredOTP, (string) ($passwordResetChallenge['otp_hash'] ?? ''))
      ) {
        $passwordResetChallenge['attempts'] = (int) ($passwordResetChallenge['attempts'] ?? 0) + 1;
        storePasswordResetChallenge($passwordResetChallenge);
        $retryAfter = registerRateLimitFailure($passwordResetVerifyRateLimitKey, 5, 600, 120);

        if ((int) $passwordResetChallenge['attempts'] >= (int) ($passwordResetChallenge['max_attempts'] ?? 5)) {
          clearPasswordResetChallenge();
          setToast('Too many incorrect OTP attempts. Please request a new OTP.', 'danger', 7000);
          redirectTo('./?stage=requestOTP', 0);
        }

        $remainingAttempts = max(0, (int) ($passwordResetChallenge['max_attempts'] ?? 5) - (int) $passwordResetChallenge['attempts']);
        $message = 'Incorrect OTP entered. Please try again.';

        if ($retryAfter > 0) {
          $message .= ' Wait ' . $retryAfter . ' seconds before your next attempt.';
        }

        setToast($message, 'danger', 7000);
        $OTPValidationStatus = 'is-invalid';
        $OTPHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                          <span class="material-symbols-outlined me-1">info</span>
                          Incorrect OTP entered. Remaining attempts: ' . $remainingAttempts . '.
                        </span>';
      }
      else {
        clearRateLimit($passwordResetVerifyRateLimitKey);

        if (($passwordResetChallenge['known_identity'] ?? false) !== true || empty($passwordResetChallenge['usercode'])) {
          clearPasswordResetChallenge();
          setToast('Your reset session expired. Please request a new OTP.', 'warning', 7000);
          redirectTo('./?stage=requestOTP', 0);
        }

        $passwordResetChallenge['verified'] = true;
        $passwordResetChallenge['attempts'] = 0;
        $passwordResetChallenge['otp_expires_at'] = time() + 900;
        storePasswordResetChallenge($passwordResetChallenge);

        $_SESSION['changePasswordFormStatus'] = 'CHANGE_PASSWORD_PENDING';
        setToast('OTP verified successfully. You can now change your password.', 'success', 7000);
        redirectTo('./?stage=changePassword', 0);
      }
    }
    else {
      setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }
  }

  elseif (isset($_POST['submitPasswordChanges'])) {
    $updatedNewPassword     = escapeOutput($_POST['new_password']) ?? null;
    $updatedConfirmPassword = escapeOutput($_POST['confirm_password']) ?? null;
    $csrfToken              = escapeOutput($_POST['csrf_token']) ?? null;

    if (validateCsrfToken($csrfToken)) {
      unsetCsrfToken();

      $passwordResetChallenge = getPasswordResetChallenge();
      $resetIdentity = getPasswordResetIdentity($db1, $userRecord ?? null);

      if (!validatePassword($updatedNewPassword) || !validatePassword($updatedConfirmPassword)) {
        setToast('Password does not meet the required criteria. Please try again.', 'danger', 7000);
        $newPasswordValidationStatus = 'is-invalid';
        $newPasswordHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                  <span class="material-symbols-outlined me-1">info</span>
                                  Incorrect Password Entered!
                                </span>';
      }
      elseif (!checkForEquality($updatedNewPassword, $updatedConfirmPassword, 'strict')) {
        setToast('Passwords do not match. Please re-enter the same password.', 'danger', 7000);
        $confirmPasswordValidationStatus = 'is-invalid';
        $confirmPasswordHelpText = '<span class="text-danger d-flex align-items-center justify-content-center my-3">
                                      <span class="material-symbols-outlined me-1">info</span>
                                      Passwords do not match. Please re-enter the same password.
                                    </span>';
      }
      elseif (
        $passwordResetChallenge === null ||
        ($passwordResetChallenge['verified'] ?? false) !== true ||
        ($passwordResetChallenge['known_identity'] ?? false) !== true ||
        isPasswordResetChallengeExpired($passwordResetChallenge)
      ) {
        clearPasswordResetChallenge();
        setToast('Your reset session expired. Please request a new OTP.', 'warning', 7000);
        redirectTo('./?stage=requestOTP', 0);
      }
      elseif ($resetIdentity === null) {
        clearPasswordResetChallenge();
        setToast('Your reset session expired. Please request a new OTP.', 'warning', 7000);
        redirectTo('./?stage=requestOTP', 0);
      }
      else {
        $roleMap = getRoleDatabaseMap($resetIdentity['role']);
        $hashedPassword = password_hash($updatedNewPassword, PASSWORD_DEFAULT);

        $updateUserPassword = $db1->prepare(
          "UPDATE {$roleMap['details_table']}
           SET {$roleMap['password_column']} = :updatedPassword
           WHERE {$roleMap['usercode_column']} = :usercode
           LIMIT 1"
        );

        $updateUserPassword->bindValue(':updatedPassword', $hashedPassword, PDO::PARAM_STR);
        $updateUserPassword->bindValue(':usercode', $resetIdentity['usercode'], PDO::PARAM_STR);

        $currentAttemptForUpdatingUserPassword = 0;
        $maxRetriesForUpdatingUserPassword = 3;

        while ($currentAttemptForUpdatingUserPassword < $maxRetriesForUpdatingUserPassword) {
          try {
            if ($updateUserPassword->execute()) {
              clearPasswordResetChallenge();
              clearRateLimit($passwordResetVerifyRateLimitKey);

              if (!checkLoginStatus($db1)) {
                setToast('Your password has been updated successfully. You can now sign in.', 'success', 7000);
                redirectTo('../login/', 0);
              }

              setToast('Your password has been updated successfully.', 'success', 7000);
              redirectTo('./?stage=requestOTP', 0);
            }
          }
          catch (PDOException $ex) {
            if (!isRetryablePdoException($ex)) {
              setToast('Error occurred while updating your password. Contact Admin.', 'danger', 7000);
              logAppError($db2, $resetIdentity['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while updating user password: ' . $ex->getMessage());
              break;
            }
          }

          $currentAttemptForUpdatingUserPassword++;
          sleep(5);
        }

        if ($currentAttemptForUpdatingUserPassword >= $maxRetriesForUpdatingUserPassword) {
          logAppError($db2, $resetIdentity['usercode'], getCurrentURL(), 'DATABASE', 'Max retries reached while updating user password.');
        }
      }
    }
    else {
      setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }
  }
?>

<?php // Headers 
  $page_title = "Change Password | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $passwordResetBackUrl = checkForEquality(checkLoginStatus($db1), true, 'strict') ? '../dashboard/' : '../login/';
  $passwordResetBackLabel = checkForEquality(checkLoginStatus($db1), true, 'strict') ? 'Dashboard' : 'Login';
  if (checkForEquality(checkLoginStatus($db1), true, 'strict')) {
    $breadcrumb_url_1 = '../dashboard/';
    $breadcrumb_title_1 = 'Dashboard';
  }
  elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')) {
    $breadcrumb_url_1 = '../login/';
    $breadcrumb_title_1 = 'Login';
  }

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Change Password';
  
  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality($_SESSION['changePasswordFormStatus'], 'OTP_REQUEST_PENDING', 'strict')): ?>
  <?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User Logged In?>
    <section>
      <div class="container d-flex flex-column">
        <div class="row gx-0 align-items-start justify-content-center">
          <div class="col-12 col-lg-6 col-md-8 px-8 py-8">
            <h1 class="mb-0 fw-bolder text-center">Reset Password</h1>
            <p class="lead mb-7 text-center text-body-secondary">
              To update your account password, we first need to verify your identity.
            </p>
            <p class="text-center text-body-secondary">
              Click the button below to receive a One-Time Password (OTP) on your registered email address.
            </p>
            <form class="my-5 mt-7" method="POST" action="./?stage=requestOTP">
              <button class="btn btn-primary w-100"
                      name="generateOTPButton"
                      type="submit">
                Request an OTP on <?= escapeOutput($_SESSION['email'] ?? '') ?>
              </button>

              <input type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            </form>
            <p class="text-center text-body-secondary mb-0">
              Back to <a href="<?php echo escapeOutput($passwordResetBackUrl); ?>"><?php echo escapeOutput($passwordResetBackLabel); ?></a>.
            </p>
          </div>
        </div>
      </div>
    </section>

  <?php else: // User Logged Out ?>
    <section>
      <div class="container d-flex flex-column">
        <div class="row gx-0 align-items-center justify-content-center">
          <div class="col-12 col-lg-6 col-md-8 px-8 py-8">
            <h1 class="mb-0 fw-bolder text-center">Reset Password</h1>
            <p class="lead mb-7 text-center text-body-secondary">
              To update your account password, we first need to verify your identity.
            </p>
            <p class="text-center text-body-secondary">
              Choose your account type, enter your registered email address, and request an OTP.
            </p>
            <form class="my-5 mt-7" method="POST" action="./?stage=requestOTP">
              <div class="my-4">
                <div class="form-label">Choose User Type</div>
                <div class="d-flex gap-4">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="chosen_userRole" id="reset_student" value="student" checked>
                    <label class="form-check-label" for="reset_student">Student</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="chosen_userRole" id="reset_faculty" value="faculty">
                    <label class="form-check-label" for="reset_faculty">Faculty</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="chosen_userRole" id="reset_admin" value="admin">
                    <label class="form-check-label" for="reset_admin">Admin</label>
                  </div>
                </div>
              </div>

              <div class="my-7">
                <label class="visually-hidden" for="email">Email</label>
                <input class="form-control <?php echo $emailValidationStatus ?? null; ?>"
                      id="email"
                      type="email"
                      name="email"
                      aria-describedby="email"
                      placeholder="Enter your email for requesting OTP.">
                <div id="email" class="form-text">
                  <?php echo $emailHelpText ?? 'Enter your registered email address.'; ?>
                </div>
              </div>

              <button class="btn btn-primary w-100"
                      name="generateOTPButton"
                      type="submit">
                Request an OTP
              </button>

              <input type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            </form>
            <p class="text-center text-body-secondary mb-0">
              Back to <a href="<?php echo escapeOutput($passwordResetBackUrl); ?>"><?php echo escapeOutput($passwordResetBackLabel); ?></a>.
            </p>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

<?php elseif(checkForEquality($_SESSION['changePasswordFormStatus'], 'OTP_VERIFICATION_PENDING', 'strict')): ?>
  <section>
    <div class="container d-flex flex-column">
      <div class="row gx-0 align-items-center justify-content-center">
        <div class="col-12 col-lg-6 col-md-8 px-8 py-8">
          <h1 class="mb-0 fw-bolder text-center">Verify OTP</h1>
          <p class="lead mb-7 text-center text-body-secondary">
            Enter the One-Time Password (OTP) sent to your registered email address to continue.
          </p>
          <form class="my-5 mt-7" method="POST" action="./?stage=verifyOTP">
            <div class="my-7">
              <label class="visually-hidden" for="OTP">OTP</label>
              <input class="form-control <?php echo $OTPValidationStatus ?? null; ?>"
                    id="OTP"
                    type="number"
                    name="OTP"
                    aria-describedby="OTP"
                    placeholder="Enter the OTP sent to your email address...">
              <div id="OTP" class="form-text">
                <?php echo $OTPHelpText ?? 'Enter the OTP sent to your registered email address.'; ?>
              </div>
            </div>

            <div class="mb-4">
              <button class="btn btn-primary w-100"
                      name="verifyOTPButton"
                      type="submit">
                Verify OTP
              </button>
            </div>

            <input type="hidden"
                  name="csrf_token"
                  value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
          </form>
          <p class="text-center text-body-secondary mb-0">
            Back to <a href="<?php echo escapeOutput($passwordResetBackUrl); ?>"><?php echo escapeOutput($passwordResetBackLabel); ?></a>.
          </p>
        </div>
      </div>
    </div>
  </section>

<?php elseif(checkForEquality($_SESSION['changePasswordFormStatus'], 'CHANGE_PASSWORD_PENDING', 'strict')): ?>
  <section>
    <div class="container d-flex flex-column">
      <div class="row gx-0 align-items-center justify-content-center">
        <div class="col-12 col-lg-6 col-md-8 px-8 py-8">
          <h1 class="mb-0 fw-bolder text-center">Change Password</h1>
          <p class="lead mb-7 text-center text-body-secondary">
            Set a fresh password for your account and confirm it to finish the reset.
          </p>
          <form class="my-5 mt-7" method="POST" action="./?stage=changePassword">
            <div class="my-4">
              <label class="visually-hidden" for="new_password">New Password</label>
              <input class="form-control <?php echo $newPasswordValidationStatus ?? null; ?>"
                    id="new_password"
                    type="password"
                    name="new_password"
                    aria-describedby="new_password"
                    placeholder="Enter your new account password..."
                    required>
              <div id="new_password" class="form-text">
                <?php echo $newPasswordHelpText ?? 'Please enter your new account password.'; ?>
              </div>
            </div>

            <div class="my-4">
              <label class="visually-hidden" for="confirm_password">Confirm Password</label>
              <input class="form-control <?php echo $confirmPasswordValidationStatus ?? null; ?>"
                    id="confirm_password"
                    type="password"
                    name="confirm_password"
                    aria-describedby="confirm_password"
                    placeholder="Re-enter your password..."
                    required>
              <div id="confirm_password" class="form-text">
                <?php echo $confirmPasswordHelpText ?? 'Please re-enter your password for confirmation.'; ?>
              </div>
            </div>

            <div class="mt-7 mb-4">
              <button class="btn btn-primary w-100"
                      name="submitPasswordChanges"
                      type="submit">
                Change Password
              </button>
            </div>

            <input type="hidden"
                  name="csrf_token"
                  value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
          </form>
          <p class="text-center text-body-secondary mb-0">
            Back to <a href="<?php echo escapeOutput($passwordResetBackUrl); ?>"><?php echo escapeOutput($passwordResetBackLabel); ?></a>.
          </p>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require_once '../components/footer.php'; ?>