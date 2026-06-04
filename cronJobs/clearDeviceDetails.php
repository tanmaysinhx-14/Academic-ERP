<?php
  $functionsRoot = __DIR__ . '/../functions';

  require_once $functionsRoot . '/database/database.php';

  require_once $functionsRoot . '/security/encryption.php';
  require_once $functionsRoot . '/security/envLoader.php';
  require_once $functionsRoot . '/security/keys.php';
  loadEnv($functionsRoot . '/security/credentials.env');

  require_once $functionsRoot . '/utility/errorLogger.php';
  require_once $functionsRoot . '/utility/sessionHandler.php';
  require_once $functionsRoot . '/utility/utilities.php';

  date_default_timezone_set('Asia/Kolkata');
  echo "[START] Cron started at " . date('Y-m-d H:i:s') . PHP_EOL;

  $db1 = connectDatabase('DB1', PDO::ERRMODE_SILENT);
  $db2 = connectDatabase('DB2', PDO::ERRMODE_SILENT);

  $deletedCount = 0;

  if ($db1 instanceof PDO) {
    foreach (['student', 'faculty', 'admin'] as $role) {
      $roleMap = getRoleDatabaseMap($role);
      $sessionStorage = getcurrent_active_sessionStorage($role);

      if ($roleMap === null || $sessionStorage === null) {
        continue;
      }

      $fetchSessions = $db1->prepare(
        'SELECT ' . $roleMap['usercode_column'] . ' AS usercode,
                ' . $roleMap['device_session_column'] . ' AS session_id,
                ' . $role . '_session_expire_timestamp AS session_expire_timestamp
         FROM ' . $roleMap['device_table'] . '
         WHERE ' . $roleMap['device_session_column'] . ' IS NOT NULL'
      );
      $fetchSessions->execute();

      while ($session = $fetchSessions->fetch(PDO::FETCH_ASSOC)) {
        if (!isStoredTimestampExpired($session['session_expire_timestamp'] ?? null)) {
          continue;
        }

        try {
          $db1->beginTransaction();

          $clearSession = $db1->prepare(
            'UPDATE ' . $sessionStorage['table'] . '
             SET ' . $sessionStorage['column'] . ' = :currentSession
             WHERE ' . $roleMap['usercode_column'] . ' = :usercode
               AND ' . $sessionStorage['column'] . ' = :sessionID'
          );
          $clearSession->bindValue(':currentSession', null, PDO::PARAM_NULL);
          $clearSession->bindValue(':usercode', $session['usercode'], PDO::PARAM_STR);
          $clearSession->bindValue(':sessionID', $session['session_id'], PDO::PARAM_STR);
          $clearSession->execute();

          $deleteSession = $db1->prepare(
            'DELETE FROM ' . $roleMap['device_table'] . '
             WHERE ' . $roleMap['usercode_column'] . ' = :usercode
               AND ' . $roleMap['device_session_column'] . ' = :sessionID'
          );
          $deleteSession->bindValue(':usercode', $session['usercode'], PDO::PARAM_STR);
          $deleteSession->bindValue(':sessionID', $session['session_id'], PDO::PARAM_STR);
          $deleteSession->execute();

          $db1->commit();
          $deletedCount++;
        }
        catch (Throwable $ex) {
          if ($db1->inTransaction()) {
            $db1->rollBack();
          }

          if ($db2 instanceof PDO) {
            logAppError($db2, $session['usercode'] ?? null, 'cron://cleardevice_details', 'DATABASE', ucfirst($role) . ' session cleanup failed: ' . $ex->getMessage());
          }
        }
      }
    }
  }

  echo "[END] Cron finished at " . date('Y-m-d H:i:s') .
     " | Sessions deleted: $deletedCount" . PHP_EOL;
?>