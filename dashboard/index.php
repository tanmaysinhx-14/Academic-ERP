<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php
  $page_title = "Dashboard | careerinstitute.co.in";

  require_once '../components/header.php';
?>


<?php if(checkLoginStatus($db1)): // User is logged in ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>
    <!-- Student Dashboard Code Here -->

    <?php if(checkForEquality((int)$userRecord['student_has_updated_account_profile'], 0, 'strict')): ?>
      <section class="section-border border-primary ff-inter">
        <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary min-vh-100">
          <div class="shape shape-blur-3 text-white">
            <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
              <defs>
                <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                  <stop stop-color="currentColor" stop-opacity=".075"></stop>
                  <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
                </linearGradient>
              </defs>
            </svg>      
          </div>

          <div class="container-lg text-white">
            <div class="row justify-content-center px-5">
              <div class="col-12 col-md-10">
                <p class="display-2 mb-3 fw-bold text-white">Complete your profile to get started!</p>
                <p class="my-7 lead">
                  Welcome! Before you dive into our services, we just need a few details to personalize your experience.
                </p>
                <div class="row mt-10 mb-3">
                  <a href="../profile/" class="col-auto btn btn-warning rounded-pill mx-2 px-5 py-2">Complete Your Profile</a>
                  <a href="../logout/" class="col-auto btn btn-danger rounded-pill mx-2 px-5 py-2">Logout</a>
                </div>
              </div>
            </div>
          </div>
        </main>
      </section>

    <?php elseif(checkForEquality((int)$userRecord['student_has_updated_account_profile'], 1, 'strict')): ?>
      <section class="section-border border-primary min-vh-100 ff-inter">
        <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
          <div class="shape shape-blur-3 text-white">
            <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
              <defs>
                <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                  <stop stop-color="currentColor" stop-opacity=".075"></stop>
                  <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
                </linearGradient>
              </defs>
            </svg>      
          </div>

          <div class="container">
            <div class="row justify-content-center px-5">
              <div class="col-12 col-md-10">
                <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold">
                Student Dashboard
              </span>
                <h1 class="display-2 text-white">
                  Welcome, 
                  <span class="fw-bold">
                    <?php echo escapeOutput($userRecord['student_username'] ?? 'User!'); ?>
                  </span>
                </h1>
                <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
                  Access most of the feature at one glance.
                </p>
              </div>
            </div>
          </div>
        </main>

        <div class="position-relative">
          <div class="shape shape-bottom shape-fluid-x text-light">
            <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
            </svg>      
          </div>
        </div>

        <main class="mt-n7 mt-md-n15 mb-10">
          <div class="container mt-5">
            <div class="row justify-content-center gx-4 gap-5 gap-lg-0">

            <!-- STUDENT ACADEMIC SERVICES -->
              <div class="col-12 col-md col-lg-4">
                <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                  <div class="card-body">
                    <div class="text-center mb-7">
                      <span class="badge rounded-pill text-bg-primary-subtle">
                        <span class="h6 text-uppercase">Academic Services</span>
                      </span>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../testMarksheets/">Test Marksheets</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../courseMaterials/">Course Materials</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" 
                         href="../viewAttendance/"
                         target="_blank">View Attendance</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../routines/">
                        Class Routine for <?php echo prettyPrintClassCode($userRecord['student_batch_details'] ?? '<span class="text-danger">Error</span>'); ?>
                      </a>
                    </div>
                  </div>
                </div>
              </div>

            <!-- STUDENT ACCOUNT SERVICES -->
              <div class="col-12 col-md col-lg-4">
                <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                  <div class="card-body">
                    <div class="text-center mb-7">
                      <span class="badge rounded-pill text-bg-primary-subtle">
                        <span class="h6 text-uppercase">Account Management</span>
                      </span>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../profile/">Account Profile</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../deactivation/">Deactivate Account</a>
                    </div>
                    <div class="d-flex my-5">
                      <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                        <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier"> 
                            <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                          </g>
                        </svg>
                      </div>
                      <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </main>
      </section>
      
    <?php endif ?>
  
  <?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'faculty', 'strict')): ?>
    <section class="section-border border-primary min-vh-100 ff-inter">
      <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
        <div class="shape shape-blur-3 text-white">
          <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
            <defs>
              <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                <stop stop-color="currentColor" stop-opacity=".075"></stop>
                <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
          </svg>
        </div>

        <div class="container">
          <div class="row justify-content-center px-5">
            <div class="col-12 col-md-10">
              <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold">
                Faculty Dashboard
              </span>
              <h1 class="display-2 text-white">
                Welcome,
                <span class="fw-bold">
                  <?php echo escapeOutput($userRecord['faculty_name'] ?? 'Faculty'); ?>
                </span>
              </h1>
              <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
                Keep teaching tools, attendance tracking, and account settings within quick reach.
              </p>
            </div>
          </div>
        </div>
      </main>

      <div class="position-relative">
        <div class="shape shape-bottom shape-fluid-x text-light">
          <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
          </svg>
        </div>
      </div>

      <main class="mt-n8 mt-md-n14 pb-10">
        <div class="container">
          <div class="row gx-4">

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Teaching Tools</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../viewAttendance/">Batch Attendance Viewer</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../routines/">Routine Viewer</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../dailyClassRecords/">Daily Class Records</a>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Account</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../profile/">Account Profile</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <i class="fe fe-arrow-right"></i>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0 h-100">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Current Status</span>
                    </span>
                  </div>
                  <p class="lead text-dark mb-3">
                    Username:
                    <span class="fw-bold">
                      <?php echo escapeOutput($userRecord['faculty_username'] ?? 'Not set'); ?>
                    </span>
                  </p>
                  <p class="text-gray-700 mb-3">
                    Email: <?php echo escapeOutput($userRecord['faculty_email'] ?? 'Not available'); ?>
                  </p>
                  <p class="text-gray-700 mb-3">
                    Profile setup:
                    <span class="fw-semibold">
                      <?php echo checkForEquality((int) ($userRecord['faculty_has_updated_account_profile'] ?? 0), 1, 'strict') ? 'Completed' : 'Needs review'; ?>
                    </span>
                  </p>
                  <p class="text-gray-700 mb-0">
                    Email updates:
                    <span class="fw-semibold">
                      <?php echo checkForEquality((int) ($userRecord['faculty_has_opted_email_communication'] ?? 0), 1, 'strict') ? 'Enabled' : 'Disabled'; ?>
                    </span>
                  </p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
    </section>

  <?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
     <section class="section-border border-primary min-vh-100 ff-inter">
      <main class="pt-8 pt-md-11 pb-10 pb-md-15 bg-primary">
        <div class="shape shape-blur-3 text-white">
          <svg viewBox="0 0 1738 487" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h1420.92s713.43 457.505 0 485.868C707.502 514.231 0 0 0 0z" fill="url(#paint0_linear)"></path>
            <defs>
              <linearGradient id="paint0_linear" x1="0" y1="0" x2="1049.98" y2="912.68" gradientUnits="userSpaceOnUse">
                <stop stop-color="currentColor" stop-opacity=".075"></stop>
                <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
          </svg>
        </div>

        <div class="container">
          <div class="row justify-content-center px-5">
            <div class="col-12 col-md-10">
              <span class="badge py-2 px-4 mb-5 bg-white rounded-pill text-primary fs-sm fw-bold">
              Admin Dashboard
            </span>
              <h1 class="display-2 text-white">
                Welcome, 
                <span class="fw-bold">
                  <?php echo escapeOutput($userRecord['admin_name'] ?? 'User!'); ?>
                </span>
              </h1>
              <p class="lead text-white text-opacity-80 mb-6 mb-md-8">
                Access most of the feature at one glance.
              </p>
            </div>
          </div>
        </div>
      </main>

      <div class="position-relative">
        <div class="shape shape-bottom shape-fluid-x text-light">
          <svg viewBox="0 0 2880 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 48h2880V0h-720C1442.5 52 720 0 720 0H0v48z" fill="currentColor"></path>
          </svg>
        </div>
      </div>

      <main class="mt-n8 mt-md-n14 pb-10">
        <div class="container">
          <div class="row gx-4">

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Student Services</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../userManager/">Student List</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../testMarksheets/">Batch-wise Marksheets</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../viewAttendance/">Attendance Viewer</a>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Faculty Services</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../dailyClassRecords/">Daily Class Routines</a>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card shadow-lg mb-6 mb-lg-0">
                <div class="card-body">
                  <div class="text-center mb-7">
                    <span class="badge rounded-pill text-bg-primary-subtle">
                      <span class="h6 text-uppercase">Admin & Account</span>
                    </span>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../approvalManager/">Approvals</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../batchlistManager/">Batchlist Manager</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../enquiryManager/">Enquiry Manager</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../facultyManager/">Faculty Manager</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../profile/">Account Profile</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../changePassword/">Change Password</a>
                  </div>
                  <div class="d-flex my-5">
                    <div class="badge badge-rounded-circle text-bg-success-subtle mt-1 me-4">
                      <svg style="position: relative; top: -2px;" width="16px" height="16px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier"> 
                          <path d="M9 6L15 12L9 18" stroke="#1d8b30" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path> 
                        </g>
                      </svg>
                    </div>
                    <a class="text-decoration-none text-dark lead" href="../logout/">Logout</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </section>

  <?php endif ?>

<?php else: // User is logged out ?>
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
