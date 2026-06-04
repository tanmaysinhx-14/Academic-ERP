<?php
  require_once '../functions/database/database.php';
  require_once '../functions/database/data-retrievers.php';

  require_once '../functions/mail/Exception.php';
  require_once '../functions/mail/PHPMailer.php';
  require_once '../functions/mail/POP3.php';
  require_once '../functions/mail/SMTP.php';
  require_once '../functions/mail/mailer.php';

  require_once '../functions/security/csrf.php';
  require_once '../functions/security/encryption.php';
  require_once '../functions/security/envLoader.php';
  loadEnv('../functions/security/credentials.env');
  require_once '../functions/security/keys.php';

  require_once '../functions/utility/cookies.php';
  require_once '../functions/utility/deviceLocationRetriever.php';
  require_once '../functions/utility/errorLogger.php';
  require_once '../functions/utility/sessionHandler.php';
  require_once '../functions/utility/utilities.php';

  require_once '../functions/validation/validations.php';
  require_once '../vendor/autoload.php';

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

    if ($db1 === null || $db2 === null) {
      throw new RuntimeException('Unable to connect to the application database.');
    }

    $appStatus = null;
    $activationLink = null;
    $logo_href = null;
    $logo_text = null;
    $urlForUniversalCSS = null;

    require '../configurations/main.php';

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

    if (($options['guest_only'] ?? false) && $isLoggedIn) {
      redirectTo($options['guest_redirect'] ?? '../dashboard/', 0);
    }

    if (($options['require_login'] ?? false) && !$isLoggedIn) {
      redirectTo($options['login_redirect'] ?? '../login/', 0);
    }

    $requiredRoles = $options['required_roles'] ?? [];
    if ($requiredRoles !== [] && $isLoggedIn && !in_array($currentUserRole, $requiredRoles, true)) {
      setToast('You do not have permission to access that page.', 'danger', 7000);
      redirectTo($options['forbidden_redirect'] ?? '../dashboard/', 0);
    }

    return [
      'db1' => $db1,
      'db2' => $db2,
      'appStatus' => $appStatus,
      'activationLink' => $activationLink,
      'logo_href' => $logo_href,
      'logo_text' => $logo_text,
      'urlForUniversalCSS' => $urlForUniversalCSS,
      'isLoggedIn' => $isLoggedIn,
      'currentUserRole' => $currentUserRole,
      'userRecord' => $userRecord,
    ];
  }
?>
