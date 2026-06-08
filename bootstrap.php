<?php
  require_once __DIR__ . '/functions/database/database.php';
  require_once __DIR__ . '/functions/database/data-retrievers.php';

  require_once __DIR__ . '/functions/mail/Exception.php';
  require_once __DIR__ . '/functions/mail/PHPMailer.php';
  require_once __DIR__ . '/functions/mail/POP3.php';
  require_once __DIR__ . '/functions/mail/SMTP.php';
  require_once __DIR__ . '/functions/mail/mailer.php';

  require_once __DIR__ . '/functions/security/csrf.php';
  require_once __DIR__ . '/functions/security/encryption.php';
  require_once __DIR__ . '/functions/security/envLoader.php';
  loadEnv(__DIR__ . '/functions/security/credentials.env');
  require_once __DIR__ . '/functions/security/keys.php';

  require_once __DIR__ . '/functions/utility/cookies.php';
  require_once __DIR__ . '/functions/utility/deviceLocationRetriever.php';
  require_once __DIR__ . '/functions/utility/errorLogger.php';
  require_once __DIR__ . '/functions/utility/sessionHandler.php';
  require_once __DIR__ . '/functions/utility/utilities.php';

  require_once __DIR__ . '/functions/validation/validations.php';
  require_once __DIR__ . '/vendor/autoload.php';

  function bootstrapAccounts(array $options = []): array {
    static $sharedLoaded = false;

    issueSecurityHeaders();

    if (session_status() !== PHP_SESSION_ACTIVE) {
      initializeSecureSession();
      session_start();
    }

    $dbErrorMode = $options['db_error_mode'] ?? PDO::ERRMODE_EXCEPTION;
    $db1 = connectDatabase('DB1', $dbErrorMode);
    $db2 = connectDatabase('DB2', $dbErrorMode);

    if (checkForEquality($db1, null, 'strict') || checkForEquality($db2, null, 'strict')) {
      throw new RuntimeException('Unable to connect to the application database.');
    }

    $appStatus = null;
    $logo_href = null;
    $logo_text = null;

    require __DIR__ . '/configurations/main.php';

    $hasAuthenticationState = !empty($_SESSION['login_status']) || !empty($_SESSION['usercode']) || !empty($_SESSION['sessionID']);
    $isLoggedIn = checkLoginStatus($db1);

    if (!$isLoggedIn && $hasAuthenticationState) {
      clearAuthenticationSession();
    }

    $currentUserRole = $isLoggedIn && !empty($_SESSION['usercode'])
      ? getUserRoleUsingUsercode($_SESSION['usercode'])
      : null;
    $userRecord = null;

    if ($isLoggedIn && $currentUserRole !== null && ($options['hydrate_user'] ?? true)) {
      $userRecord = getAuthenticatedUserRecord($db1);

      if ($userRecord === null) {
        clearAuthenticationSession();
        $isLoggedIn = false;
        $currentUserRole = null;
      }
    }

    $requiredRoles = $options['required_roles'] ?? [];
    if (is_string($requiredRoles)) {
      $requiredRoles = [$requiredRoles];
    }

    $requiresLogin = ($options['require_login'] ?? false) || $requiredRoles !== [];

    if (!empty($options['guest_only']) && $isLoggedIn) {
      setToast('You are already logged in. Please log out to access this page.', 'info', 7000);
      redirectTo($options['redirect_if_logged_in'] ?? '../dashboard/', 0);
    }

    if ($requiresLogin && !$isLoggedIn) {
      setToast('You are not logged in. Please log in to access this page.', 'danger', 7000);
      redirectTo($options['login_redirect'] ?? '../login/', 0);
    }

    if ($requiredRoles !== [] && !in_array($currentUserRole, $requiredRoles, true)) {
      setToast('You do not have permission to access that page. Unauthorized access are being monitored.', 'danger', 7000);
      redirectTo($options['forbidden_redirect'] ?? '../dashboard/', 0);
    }

    return [
      'db1' => $db1,
      'db2' => $db2,
      'appStatus' => $appStatus,
      'logo_href' => $logo_href,
      'logo_text' => $logo_text,
      'isLoggedIn' => $isLoggedIn,
      'currentUserRole' => $currentUserRole,
      'userRecord' => $userRecord,
    ];
  }
?>
