<?php
  if (PHP_SAPI !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'a8f93x7pQ2LmZ') {
      http_response_code(403);
      exit;
    }
  }

  if (function_exists('set_time_limit')) {
    set_time_limit(0);
  }

  $lockHandle = fopen(sys_get_temp_dir() . '/career-institute-email-queue.lock', 'c');

  if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Queued email cron is already running.\n";
    exit(0);
  }

  register_shutdown_function(function () use ($lockHandle): void {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
  });

  $functionsRoot = __DIR__ . '/../functions';

  require_once $functionsRoot . '/database/database.php';
  require_once $functionsRoot . '/utility/errorLogger.php';
  require_once $functionsRoot . '/security/envLoader.php';

  loadEnv($functionsRoot . '/security/credentials.env');

  require_once $functionsRoot . '/mail/Exception.php';
  require_once $functionsRoot . '/mail/PHPMailer.php';
  require_once $functionsRoot . '/mail/POP3.php';
  require_once $functionsRoot . '/mail/SMTP.php';
  require_once $functionsRoot . '/mail/mailer.php';

  function logQueuedEmailCronError(PDO $db, ?string $userCode, string $description): void {
    try {
      logAppError(
        $db,
        $userCode ?? 'SYSTEM',
        'email-queue-cron',
        'MAIL_QUEUE',
        substr($description, 0, 240)
      );
    }
    catch (Throwable) {
    }
  }

  $db2 = connectDatabase('DB2');

  if ($db2 === null) {
    fwrite(STDERR, "Unable to connect to the metadata database.\n");
    exit(1);
  }

  $limit = isset($argv[1]) ? (int) $argv[1] : 25;
  $records = fetchQueuedEmailRecords($db2, $limit);
  $sentCount = 0;
  $failedCount = 0;

  foreach ($records as $record) {
    $userCode = isset($record['email_usercode']) ? (string) $record['email_usercode'] : null;

    try {
      if (!sendQueuedEmailRecord($record)) {
        $failedCount++;
        logQueuedEmailCronError($db2, $userCode, 'Queued email send returned false.');
        continue;
      }

      if (!deleteQueuedEmailRecord($db2, $record)) {
        $failedCount++;
        logQueuedEmailCronError($db2, $userCode, 'Queued email was sent but could not be removed from email_records.');
        continue;
      }

      $sentCount++;
    }
    catch (Throwable $ex) {
      $failedCount++;
      logQueuedEmailCronError($db2, $userCode, 'Queued email send failed: ' . $ex->getMessage());
    }
  }

  echo 'Queued email cron completed. Sent: ' . $sentCount . '. Failed: ' . $failedCount . ".\n";
  exit(0);
?>
