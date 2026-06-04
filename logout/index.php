<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php
  $roleMap = $currentUserRole !== null ? getRoleDatabaseMap($currentUserRole) : null;
  $activeSessionID = $roleMap !== null ? ($userRecord[$roleMap['current_session_column']] ?? ($_SESSION['session_ID'] ?? null)) : ($_SESSION['session_ID'] ?? null);

  
  if (!is_null($roleMap) && !is_null($activeSessionID)) {
    $deviceRecord = fetchUserRecord($db1, $roleMap['device_table'], $roleMap['device_session_column'], $activeSessionID);

    $rememberMeEnabled = checkForEquality((int) ($deviceRecord[$roleMap['remember_me_column']] ?? 0), 1, 'strict');
  }
  else $rememberMeEnabled = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = escapeOutput($_POST['csrf_token']) ?? null;

    if(validateCsrfToken($csrfToken)) {
      unsetCsrfToken();
      
      if (isset($_POST['logoutUser'])) { // Logout the User (Save the REMEMBER ME data)
        $logoutSucceeded = true;

        if (!is_null($currentUserRole) && !is_null($activeSessionID)) {
          clearAuthenticationSession(true);
          redirectTo('../login/', 0);
        }
      }

      elseif (isset($_POST['clearRememberMe'])) { // Clear REMEMBER ME data
        if (!is_null($currentUserRole) && !is_null($activeSessionID)) {
          removeRememberMeDevice($db1, $db2, $currentUserRole, $_SESSION['usercode'], $activeSessionID);

          setToast('Saved login details cleared successfully.', 'success', 7000);
          redirectTo('./', 0);
        } 
        else {
          destroyCookie('rememberMe');
        }
      }
    } 
    else setToast('Page reload activity detected. Please try again.', 'danger', 7000);
  }
?>

<?php
  $page_title = "Logout | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Logout';

  require_once '../components/breadcrumb.php';
?>

<section class="section-border border-primary min-vh-100 ff-inter">
  <div class="container">
    <div class="col-12 col-lg-8 col-md-10 px-8 px-md-8 py-8 py-md-8">
      <span class="badge rounded-pill text-bg-primary-subtle mb-4">
        <?php echo ucfirst((string) $currentUserRole); ?> Account
      </span>
      <h1 class="display-4 fw-bold">
        Sign out from this session?
      </h1>
      <p class="mb-4 text-body-secondary">
        You are signed in as
        <span class="fw-semibold"><?php echo escapeOutput($_SESSION['email'] ?? ''); ?></span>.
        <?php if ($rememberMeEnabled): ?>
          Saved login is active for this device, and signing out will remove it from this browser for safety.
        <?php else: ?>
          Logging out will remove the current saved session from this device.
        <?php endif; ?>
      </p>

      <form method="POST" action="./">
        <input type="hidden"
               name="csrf_token"
               value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

        <div class="d-flex flex-wrap align-items-start gap-3 mb-4">
          <button class="btn btn-danger lead col-auto"
                  name="logoutUser"
                  type="submit">
            Logout only
          </button>

          <?php if (isset($_COOKIE['rememberMe'])): ?>
            <button class="btn btn-warning lead col-auto"
                    name="clearRememberMe"
                    type="submit">
              Logout and Clear Saved Login Details
            </button>
          <?php endif; ?>

          <a class="btn btn-outline-primary lead col-auto" href="../dashboard/">
            Back to Dashboard
          </a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php
  require_once '../components/footer.php';
?>
