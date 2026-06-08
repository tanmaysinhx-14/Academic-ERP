<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['student']
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Logic for Deactivation
  function queueDeactivationAttemptNotification(PDO $db2, string $role, array $userRecord): void {
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
      if (!queueNewDeactivationAttemptEmail($db2, $userCode, $email)) {
        logAppError($db2, $userCode, getCurrentURL(), 'MAIL_QUEUE', 'Unable to queue new deactivation attempt email.');
      }
    }
    catch (Throwable $ex) {
      logAppError($db2, $userCode, getCurrentURL(), 'MAIL_QUEUE', 'Error occured while queueing new deactivation attempt email: ' . substr($ex->getMessage(), 0, 170));
    }
  }

  if (isset($_POST['deactivateUserBtn'])) {
    $csrfToken = escapeOutput($_POST['csrf_token'] ?? null) ?? null;

    $roleMap = $currentUserRole !== null ? getRoleDatabaseMap($currentUserRole) : null;
    $activeSessionID = $roleMap !== null ? ($userRecord[$roleMap['current_session_column']] ?? ($_SESSION['session_ID'] ?? null)) : ($_SESSION['session_ID'] ?? null);

    if(validateCsrfToken($csrfToken)) {
      unsetCsrfToken();

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

          
          $STMT_updateDeactivationTimestamp = "UPDATE student_timestamps
                                              SET student_account_deactivation_timestamp = :student_account_deactivation_timestamp
                                              WHERE student_usercode = :student_usercode
                                              LIMIT 1";
          $updateDeactivationTimestamp = $db1->prepare($STMT_updateDeactivationTimestamp);
          $updateDeactivationTimestamp->bindValue(':student_account_deactivation_timestamp', getCurrentTimestamp(), PDO::PARAM_STR);
          $updateDeactivationTimestamp->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);
          $updateDeactivationTimestamp->execute();



          if($db1->commit()) {
            queueDeactivationAttemptNotification($db2, getUserRoleUsingUsercode($_SESSION['usercode']), $userRecord);
            deleteRememberMeData($db1, $db2, getUserRoleUsingUsercode($_SESSION['usercode']), $_SESSION['usercode'], $activeSessionID);
            clearAuthenticationSession(true);
            redirectTo('../login/', 3);
          }
        }
        catch (PDOException $ex) {
          if($db1->inTransaction()) $db1->rollBack();

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
    }
    else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
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

<section class="section-border border-primary min-vh-100">
  <div class="container">
    <div class="col-12 px-8 py-8">
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

<?php require_once '../components/footer.php'; ?>