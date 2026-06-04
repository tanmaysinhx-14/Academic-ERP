<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Headers 
  $page_title = "Test Marksheets | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Test Marksheets';
  
  require_once '../components/breadcrumb.php';
?>

<?php if (checkForEquality(checkLoginStatus($db1), true, 'strict')): // User is logged in ?>
  <!-- PAGE UNDER DEVELOPMENT -->
  <section class="section-border border-primary ff-inter">
    <div class="container d-flex flex-column">
      <div class="row align-items-center justify-content-center gx-0 min-vh-100">
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Coming soon ...
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            Development for the requested page is going on. Comeback some other day :)
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill" href="../dashboard/">
              Back to Dashboard Page
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
  
<?php elseif (checkForEquality(checkLoginStatus($db1), false, 'strict')): // User is logged out ?>
  <!-- ACCESS DENIED: USER NOT AUTHENTICATED -->
   <section class="section-border border-primary ff-inter">
    <div class="container d-flex flex-column">
      <div class="row align-items-center justify-content-center gx-0 min-vh-100">
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Access Denied
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            This page is available only to authenticated users. Please sign in with your registered account to continue.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">
              Back to Login Page
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php // Footers 
  require_once '../components/footer.php'; 
?>
