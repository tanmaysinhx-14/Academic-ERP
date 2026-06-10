<?php
  $functionsRoot = __DIR__ . '/../functions';

  require_once $functionsRoot . '/database/database.php';
  require_once $functionsRoot . '/security/envLoader.php';
  require_once $functionsRoot . '/utility/errorLogger.php';
  require_once $functionsRoot . '/utility/utilities.php';

  loadEnv($functionsRoot . '/security/credentials.env');

  date_default_timezone_set('Asia/Kolkata');
  echo "[START] Cron started at " . date('Y-m-d H:i:s') . PHP_EOL;

  $db1 = connectDatabase('DB1', PDO::ERRMODE_SILENT);
  $db2 = connectDatabase('DB2', PDO::ERRMODE_SILENT);

  $deletedCount = 0;

  if ($db1 instanceof PDO) {
    $fetchExpired = $db1->prepare(
      'SELECT sc.student_usercode
       FROM student_configurations sc
       INNER JOIN student_timestamps st
         ON sc.student_usercode = st.student_usercode
       WHERE sc.student_account_activation_status = 0
         AND st.student_account_deactivation_timestamp != :sentinel'
    );
    $fetchExpired->bindValue(':sentinel', '01/01/2000 00:00:00', PDO::PARAM_STR);
    $fetchExpired->execute();

    $candidates = $fetchExpired->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidates as $candidate) {
      $usercode = $candidate['student_usercode'];

      $fetchTimestamp = $db1->prepare(
        'SELECT student_account_deactivation_timestamp
         FROM student_timestamps
         WHERE student_usercode = :usercode
         LIMIT 1'
      );
      $fetchTimestamp->bindValue(':usercode', $usercode, PDO::PARAM_STR);
      $fetchTimestamp->execute();
      $row = $fetchTimestamp->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        continue;
      }

      if (getSecondsPassed($row['student_account_deactivation_timestamp']) <= 2592000) {
        continue;
      }

      try {
        $db1->beginTransaction();

        foreach ([
          'student_devicedetails',
          'student_timestamps',
          'student_configurations',
          'student_details',
        ] as $table) {
          $stmt = $db1->prepare(
            'DELETE FROM ' . $table . '
             WHERE student_usercode = :usercode'
          );
          $stmt->bindValue(':usercode', $usercode, PDO::PARAM_STR);
          $stmt->execute();
        }

        $db1->commit();
        $deletedCount++;

        echo "[DELETED] " . $usercode . PHP_EOL;
      }
      catch (Throwable $ex) {
        if ($db1->inTransaction()) {
          $db1->rollBack();
        }

        if ($db2 instanceof PDO) {
          logAppError($db2, $usercode, 'cron://deleteExpiredStudents', 'DATABASE', 'Student deletion failed: ' . $ex->getMessage());
        }
      }
    }
  }

  echo "[END] Cron finished at " . date('Y-m-d H:i:s') .
       " | Students deleted: $deletedCount" . PHP_EOL;
?>