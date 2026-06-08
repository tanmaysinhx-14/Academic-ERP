<?php
  require __DIR__ . '/bootstrap.php';

  bootstrapAccounts([
    'require_login' => false,
  ]);

  redirectTo('./login/', 0);
?>
