<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts();

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Headers 
  $page_title = "Maintenance | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
?>

<section class="section-border border-primary ff-inter">
  <div class="container d-flex flex-column">
    <div class="row align-items-center justify-content-center gx-0 min-vh-100">
      <div class="col-12 col-lg-6 col-md-8 px-8 px-md-8 py-8 py-md-8">
        <h1 class="mb-0 fw-bold text-center">
          Maintenance Mode
        </h1>
        <p class="lead mb-0 mt-6 text-center text-body-secondary">
          Developers are working to make the services better. Please wait for a moment before trying again.
        </p>
      </div>
    </div>
  </div>
</section>

<?php // Footers 
  require_once '../components/footer.php'; 
?>
