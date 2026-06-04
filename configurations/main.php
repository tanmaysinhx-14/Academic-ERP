<?php
  $appStatus = checkAppStatus($db1) ?? null;
  $currentScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
  $isMaintenancePage = str_contains($currentScriptPath, '/accounts/maintenance/');

  if(!is_null($appStatus)) {
    switch ($appStatus['value']) {
      case 'DEPLOYED_MODE':
        $activationLink = 'https://accounts.careerinstitute.co.in/activation/';
        $logo_href = "https://careerinstitute.co.in/";
      break;

      default: 
        if (!$isMaintenancePage) {
          redirectTo('../maintenance/', 0);
        }
      break;
    }
  }
  else {
    $appStatus['value'] = 'MAINTENANCE_MODE';
  }

  if(checkForEquality($appStatus['value'], 'MAINTENANCE_MODE', 'strict') && !$isMaintenancePage) {
    redirectTo('../maintenance/', 0);
  }

  $logo_text = "Career Institute";
  $urlForUniversalCSS = "../../shared/library/css/universal.css"; 
?>
