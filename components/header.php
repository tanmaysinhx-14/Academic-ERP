<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=0.75" />
        
    <!-- Favicon -->
    <link rel="apple-touch-icon"          sizes="180x180"   href="../../library/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png"     sizes="32x32"     href="../../library/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png"     sizes="16x16"     href="../../library/favicon/favicon-16x16.png">
    <link rel="mask-icon"                 color="#5bbad5"   href="../../library/favicon/safari-pinned-tab.svg" >
    <meta name="msapplication-TileImage"                    content="../../library/favicon/mstile-144x144.png">
    <meta name="msapplication-TileColor"  content="#2d89ef">
    <meta name="theme-color"              content="#ffffff">
    
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"  />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,1,0" />
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="../library/css/libs.bundle.css">
    <link rel="stylesheet" href="../library/css/theme.bundle.css">
    <link rel="stylesheet" href="../library/css/universal.css" />
    <title>
      <?php echo htmlspecialchars($page_title ?? 'Unknown Page Title', ENT_QUOTES, 'UTF-8'); ?>
    </title>
  </head>
  <body>
    <div>
      <nav class="navbar navbar-expand-lg bg-dark border-bottom d-none d-lg-flex">
        <div class="container">
          <a class="navbar-brand text-white" href="<?php echo escapeOutput($logo_href); ?>">
            <span class="ff-poppins logo-md"><?php echo escapeOutput($logo_text); ?></span>
          </a>
          <span class="bg-primary p-2 px-5 rounded-pill text-white ff-inter ms-auto fw-bold">Accounts Portal</span>
        </div>
      </nav>

      <nav class="navbar navbar-expand-lg bg-dark border-bottom d-flex d-lg-none">
        <div class="container">
          <a class="navbar-brand text-white" href="<?php echo escapeOutput($logo_href); ?>">
            <span class="ff-poppins logo-md"><?php echo escapeOutput($logo_text); ?></span>
          </a>
        </div>
      </nav>

      <?php if (!empty($_SESSION['toast'])): ?>
        <div class="toast-float" aria-live="polite" aria-atomic="true">
          <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type'], ENT_QUOTES, 'UTF-8') ?>"
              role="alert"
              data-duration="<?= (int) $_SESSION['toast']['duration'] ?>">
            <div class="toast-body ff-inter">
              <?= htmlspecialchars($_SESSION['toast']['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>

        <?php unset($_SESSION['toast']); ?>
      <?php endif; ?>

      <div>

        <script type="text/javascript">
          window.addEventListener('load', () => {
            document.querySelectorAll('.toast').forEach(toast => {
              const duration = parseInt(toast.dataset.duration, 10) || 7000;

              setTimeout(() => {
                toast.classList.add('toast-hide');
                toast.addEventListener(
                  'animationend',
                  () => toast.remove(),
                  { once: true }
                );
              }, duration);
            });
          });
        </script>
