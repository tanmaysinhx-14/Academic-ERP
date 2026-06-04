<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Logic for Account Deactivation
  if (isset($_POST['deactivateUserBtn'])) {
    $csrfToken = sanitizeInput($_POST['csrf_token'] ?? null, 'alphanumeric') ?? null;

    if (!validateCsrfToken($csrfToken)) {
      setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
      redirectTo('./', 0);
    }

    unsetCsrfToken();

    switch (getUserRoleUsingUsercode($_SESSION['usercode'])) {
      case 'student':
        $currentAttemptForDeactivatingUser = 0;
        $maxAttemptsForDeactivatingUser = 3;

        while ($currentAttemptForDeactivatingUser < $maxAttemptsForDeactivatingUser) {
          try {
            $db1->beginTransaction();

            $STMT_setActivationStatus = "UPDATE student_configurations
                                         SET student_account_activation_status = :student_account_activation_status
                                         WHERE student_usercode = :student_usercode
                                         LIMIT 1";
            $setActivationStatus = $db1->prepare($STMT_setActivationStatus);
            $setActivationStatus->bindValue(':student_account_activation_status', 0, PDO::PARAM_INT);
            $setActivationStatus->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);
            $setActivationStatus->execute();


            
            $STMT_removeDeviceDetails = "DELETE from student_devicedetails
                                         WHERE student_session_ID = :student_session_ID
                                         LIMIT 1";
            $removeDeviceDetails = $db1->prepare($STMT_removeDeviceDetails);
            $removeDeviceDetails->bindValue(':student_session_ID', $userRecord['student_current_active_session'], PDO::PARAM_STR);
            $removeDeviceDetails->execute();



            $STMT_updateCurrentSession = "UPDATE student_configurations 
                                          SET student_current_active_session = :student_current_active_session
                                          WHERE student_usercode = :student_usercode
                                          LIMIT 1";
            $updateCurrentSession = $db1->prepare($STMT_updateCurrentSession);
            $updateCurrentSession->bindValue(':student_current_active_session', null, PDO::PARAM_NULL);
            $updateCurrentSession->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);
            $updateCurrentSession->execute();


            
            $STMT_updateDeactivationTimestamp = "UPDATE student_timestamps
                                                 SET student_account_deactivation_timestamp = :student_account_deactivation_timestamp
                                                 WHERE student_usercode = :student_usercode
                                                 LIMIT 1";
            $updateDeactivationTimestamp = $db1->prepare($STMT_updateDeactivationTimestamp);
            $updateDeactivationTimestamp->bindValue(':student_account_deactivation_timestamp', getCurrentTimestamp(), PDO::PARAM_STR);
            $updateDeactivationTimestamp->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);
            $updateDeactivationTimestamp->execute();



            if($db1->commit()) {
              session_destroy();
              redirectTo('../login/', 0);
            }
          }
          catch (PDOException $ex) {
            if($db1->inTransaction()) {
              $db1->rollBack();
            }

            if(!isRetryablePdoException($ex)) {
              setToast('Error occured while Deactivating User. Contact Admin.', 'danger', 7000);

              logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Deactivating User: ' . $ex->getMessage());

              break;
            }

            $currentAttemptForDeactivatingUser++;
            sleep(5);
          }
        }
        if($currentAttemptForDeactivatingUser >= $maxAttemptsForDeactivatingUser) {
          setToast('Error occured while Deactivating User. Contact Admin.', 'danger', 7000);
        }
      break;

      // Faculty cannot deactivate their accounts.
      case 'faculty':
      break;

      // Admins cannot deactivate their accounts.
      case 'admin':
      break;
    }
  }
?>

<?php // Headers
  $page_title = "Deactivation | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Account Deactivation';
  
  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User is logged in ?>
  <?php if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>
    <section class="section-border border-primary min-vh-100 ff-inter">
      <div class="container">
        <div class="col-12 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-4 fw-bold">
            Are you sure you want to deactivate your account?
          </h1>
          <p class="mb-10">
            Deactivating your account will temporarily suspend access to all associated services for a designated grace period. If the account remains inactive for 30 days, all related data will be permanently deleted from our servers and cannot be restored.
              <br><br>
            Signing in before the grace period expires will automatically reactivate your account.
              <br><br>
            If you have changed your mind and want to keep using our services, go back to <a href="../dashboard/">Dashboard</a>.
          </p>
          
          <form method="POST" action="./">
            <input type="hidden"
                   name="csrf_token"
                   value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="d-flex align-items-start gap-3 mb-4">
              <button class="btn btn-danger lead col-auto"
                      name="deactivateUserBtn"
                      type="submit">
                Deactivate your account
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
  
  <?php elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'faculty', 'strict')): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Contact Admin
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              Faculty accounts are managed centrally. If you need to deactivate this account, please contact an administrator so attendance and classroom access can be handled safely.
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

  <?php elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
    <!-- ACCESS DENIED: USER CANNOT DEACTIVATE -->
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Access Denied
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              Administrators can't deactivate their accounts for security purposes.
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
  <?php endif; ?>
  
<?php elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')): // User is logged out ?>
  <!-- ACCESS DENIED: USER NOT AUTHENTICATED -->
  <section class="section-border border-primary ff-inter">
    <div class="container d-flex flex-column">
      <div class="row align-items-center justify-content-center gx-0 min-vh-100">
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Access Denied
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            This page is available only to authenticated users. Please sign in with your registered account to continue.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">
              Back to Login Page
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php // Footers 
  require_once '../components/footer.php'; 
?>
