<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Active Batchlist 
  if (isset($_POST['submitActiveBatchlist'])) {
    $csrf_token = sanitizeInput($_POST['csrf_token'], 'alphanumeric') ?? null;
    if(validateCsrfToken($csrf_token)) {
      unsetCsrfToken();

      $activeBatchlist = [];

      if (isset($_POST['CBSEOptions'])) {
        foreach ($_POST['CBSEOptions'] as $cbseOption) {
          array_push($activeBatchlist, $cbseOption);
        }
      }

      if (isset($_POST['BSEBOptions'])) {
        foreach ($_POST['BSEBOptions'] as $bsebOption) {
          array_push($activeBatchlist, $bsebOption);
        }
      }

      if (isset($_POST['ICSEOptions'])) {
        foreach ($_POST['ICSEOptions'] as $icseOption) {
          array_push($activeBatchlist, $icseOption);
        }
      }

      $currentAttemptForUpdatingBatchlist = 0;
      $maxAttemptsForUpdatingBatchlist = 3;

      while($currentAttemptForUpdatingBatchlist < $maxAttemptsForUpdatingBatchlist) {
        try {
          $STMT_updateActiveBatchlist = "UPDATE app_configurations
                                        SET value = :value
                                        WHERE parameter = :parameter";

          $updateActiveBatchlist = $db1->prepare($STMT_updateActiveBatchlist);

          $updateActiveBatchlist->bindValue(':value', json_encode($activeBatchlist), PDO::PARAM_STR);
          $updateActiveBatchlist->bindValue(':parameter', 'active_batchlist', PDO::PARAM_STR);

          if ($updateActiveBatchlist->execute()) {
            setToast('Batchlist Updated Successfully.', 'success', 7000);
            break;
          }
        }
        catch (PDOException $ex) {
          if(!isRetryablePdoException($ex)) {
            setToast('Error occured while Updating Batchlist. Contact Admin.', 'danger', 7000);

            logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Error occured while Updating Batchlist: ' . $ex->getMessage());

            break;
          }
        }
        $currentAttemptForUpdatingBatchlist++;
        sleep(5);
      }
      if ($currentAttemptForUpdatingBatchlist >= $maxAttemptsForUpdatingBatchlist) {
        setToast('Error occured while Updating Batchlist. Contact Admin.', 'danger', 7000);
      }
    }
    else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
  }

  function checkForActiveBatches(array $currentActiveBatches, $batchToCheck) {
    foreach ($currentActiveBatches as $activeBatch) {
      if (checkForEquality($activeBatch, $batchToCheck, 'strict')) {
        return true;
      }
    }
  }

  $currentActiveBatches = retrieveActiveBatchlist($db1);
?>

<?php // Headers 
  $page_title = "Activation | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
  
  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'Active Batchlist';
  
  require_once '../components/breadcrumb.php';
?>

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): // User is logged in ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-lg-6 col-md-8 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Access Denied.
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              This page is available only to authenticated users. Please sign in with an account with higher privilege to continue.
            </p>
            <div class="text-center">
              <a class="btn btn-primary rounded-pill" href="../dashboard/">
                Back to Dashboard Page
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

  
  <?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'faculty', 'strict')): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 col-lg-6 col-md-8 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Access Denied.
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              This page is available only to authenticated users. Please sign in with an account with higher privilege to continue.
            </p>
            <div class="text-center">
              <a class="btn btn-primary rounded-pill" href="../dashboard/">
                Back to Dashboard Page
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

  <?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
    <section class="section-border border-primary ff-inter">
      <div class="container-fluid d-flex flex-column">
        <div class="row align-items-center justify-content-center gx-0 min-vh-100">
          <div class="col-12 px-md-12 px-8 px-md-8 py-8 py-md-8">
            <form method="POST" action="./">
              <div class="d-flex row mb-10">
                <!-- CBSE Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    CBSE
                  </div>

                  <!-- CBSE Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="5-CBSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '5-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">V CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="6-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '6-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VI CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="7-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VII CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="8-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">VIII CBSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="9-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">IX CBSE</label>
                    </div>
                  </div>

                  <!-- CBSE Multiple Batch Options -->
                  <hr class="m-0 mt-3">

                  <!-- CBSE Xth Options -->
                  <div class="form-group row justify-content-center">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-NULL"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">X CBSE</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-D"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-D') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (D)</label>
                    </div>

                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-E"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-E') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (E)</label>
                    </div>

                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="10-CBSE-P"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-CBSE-P') ? 'checked' : ''; ?> />
                      <label class="form-check-label">X CBSE (P)</label>
                    </div>
                  </div>

                  <hr class="m-0 mt-3">

                  <!-- CBSE XIth Options -->
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="11-CBSE-Science"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-CBSE-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XI CBSE (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="11-CBSE-Arts"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-CBSE-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XI CBSE (Arts)</label>
                    </div>
                  </div>

                  <!-- CBSE XIIth Options -->
                  <hr class="m-0 mt-3">
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="12-CBSE-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-CBSE-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XII CBSE (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="CBSEOptions[]" 
                              value="12-CBSE-Arts"
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-CBSE-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="CBSEOptions[]">XI CBSE (Arts)</label>
                    </div>
                  </div>
                </div>

                <!-- BSEB Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    BSEB
                  </div>

                  <!-- BSEB Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="7-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">VII BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="8-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">VIII BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="9-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">IX BSEB</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="10-BSEB-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-BSEB-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">X BSEB</label>
                    </div>
                  </div>

                  <!-- BSEB XIth Options -->
                  <hr class="m-0 mt-3">

                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="11-BSEB-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-BSEB-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="11-BSEB-Arts" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '11-BSEB-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Arts)</label>
                    </div>
                  </div>

                  <hr class="m-0 mt-3">

                  <!-- BSEB XIIth Options -->
                  <div class="form-group row">
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="12-BSEB-Science" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-BSEB-Science') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XII BSEB (Science)</label>
                    </div>
                    <div class="col-auto form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="BSEBOptions[]" 
                              value="12-BSEB-Arts" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '12-BSEB-Arts') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="BSEBOptions[]">XI BSEB (Arts)</label>
                    </div>
                  </div>
                </div>

                <!-- ICSE Batch Options -->
                <div class="col-12 col-lg-4">
                  <div class="display-4 text-center fw-bold mb-7">
                    ICSE
                  </div>

                  <!-- ICSE Single Batch Options -->
                  <div class="form-group row">
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="5-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '5-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">V ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="6-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '6-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VI ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="7-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '7-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VII ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="8-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '8-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">VIII ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="9-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '9-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">IX ICSE</label>
                    </div>
                    <div class="form-check form-switch mt-3">
                      <input class="form-check-input" 
                              type="checkbox" 
                              name="ICSEOptions[]" 
                              value="10-ICSE-NULL" 
                              <?php echo checkForActiveBatches(json_decode($currentActiveBatches['value']), '10-ICSE-NULL') ? 'checked' : ''; ?> />
                      <label class="form-check-label" for="ICSEOptions[]">X ICSE</label>
                    </div>
                  </div>
                </div>
              </div>

              <input type="hidden" 
                     name="csrf_token" 
                     value="<?php echo htmlspecialchars(generateCsrfToken()); ?>"
              />

              <button type="submit" 
                      name="submitActiveBatchlist" 
                      class="btn btn-primary rounded-pill mt-3">
                Update Active Batchlist
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>

  <?php endif ?>
<?php elseif(checkForEquality(checkLoginStatus($db1), false, 'strict')): // User is logged out ?>
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
