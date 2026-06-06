<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php
  $attendanceByDate = [];
  $attendanceStats = [];
  $month = (int) date('n');
  $year = (int) date('Y');

  $selectedBatch = null;
  $studentsInBatch = [];
  $studentNames = [];
  $attendanceCache = [];
  $allDates = [];
  $displayMonthName = null;
  $viewMonth = date('Y-m');
  $monthDate = DateTime::createFromFormat('Y-m', $viewMonth) ?: new DateTime('first day of this month');

  $batchListConfig = retrieveActiveBatchlist($db1);
  $activeBatchList = json_decode((string) ($batchListConfig['value'] ?? '[]'), true);
  if (!is_array($activeBatchList)) {
    $activeBatchList = [];
  }

  if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')) {
    $fetchAttendanceRecords = $db2->prepare(
      "SELECT student_attendance_issue_date
       FROM attendance_records
       WHERE student_usercode = :student_usercode"
    );
    $fetchAttendanceRecords->bindValue(':student_usercode', $_SESSION['usercode'], PDO::PARAM_STR);
    $fetchAttendanceRecords->execute();

    foreach ($fetchAttendanceRecords->fetchAll(PDO::FETCH_COLUMN) as $timestamp) {
      $attendanceByDate[date('Y-m-d', strtotime((string) $timestamp))] = true;
    }

    foreach ($attendanceByDate as $date => $present) {
      [$attendanceYear, $attendanceMonth] = explode('-', $date);
      $attendanceYear = (int) $attendanceYear;
      $attendanceMonth = (int) $attendanceMonth;

      if (!isset($attendanceStats[$attendanceYear])) {
        $attendanceStats[$attendanceYear] = [
          'yearTotal' => 0,
          'months' => array_fill(1, 12, 0),
        ];
      }

      $attendanceStats[$attendanceYear]['yearTotal']++;
      $attendanceStats[$attendanceYear]['months'][$attendanceMonth]++;
    }
  } 
  else {
    if (isset($_POST['loadAttendanceBtn'])) {
      $postedBatch = escapeOutput($_POST['batch'] ?? null) ?? null;

      if ($postedBatch !== null) {
        redirectTo('./?view=' . rawurlencode($postedBatch), 0);
      }

      $selectedBatch = escapeOutput($_GET['view'] ?? null) ?? null;
      $requestedMonth = escapeOutput($_GET['month'] ?? date('Y-m')) ?? date('Y-m');
      $monthDate = DateTime::createFromFormat('Y-m', $requestedMonth) ?: new DateTime('first day of this month');
      $viewMonth = $monthDate->format('Y-m');

      $monthStart = (clone $monthDate)->modify('first day of this month');
      $monthEnd = (clone $monthDate)->modify('last day of this month');
      $displayMonthName = $monthDate->format('F Y');

      if ($selectedBatch !== null) {
        $studentQuery = $db1->prepare(
          "SELECT student_usercode, student_name
          FROM student_details
          WHERE student_batch_details = :batch
          ORDER BY student_name ASC"
        );
        $studentQuery->bindValue(':batch', $selectedBatch, PDO::PARAM_STR);
        $studentQuery->execute();

        foreach ($studentQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
          $usercode = trim((string) $row['student_usercode']);
          $studentsInBatch[] = $usercode;
          $studentNames[$usercode] = trim((string) ($row['student_name'] ?? ''));
        }
      }

      if ($studentsInBatch !== []) {
        $placeholders = implode(',', array_fill(0, count($studentsInBatch), '?'));
        $attendanceQuery = $db2->prepare(
          "SELECT student_usercode, student_attendance_issue_date
          FROM attendance_records
          WHERE student_usercode IN ($placeholders)
            AND student_attendance_issue_date BETWEEN ? AND ?"
        );
        $attendanceQuery->execute(array_merge(
          $studentsInBatch,
          [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]
        ));

        foreach ($attendanceQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
          $usercode = trim((string) $row['student_usercode']);
          $date = trim((string) $row['student_attendance_issue_date']);
          $attendanceCache[$viewMonth][$usercode][$date] = true;
        }
      }

      $cursor = clone $monthStart;
      while ($cursor <= $monthEnd) {
        $allDates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
      }
    }
  }
?>

<?php
  $page_title = "View Attendance | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1 = '../dashboard/';
  $breadcrumb_title_1 = 'Dashboard';

  $breadcrumb_url_active = './';
  $breadcrumb_title_active = 'View Attendance';

  require_once '../components/breadcrumb.php';
?>

<link rel="stylesheet" href="./attendance-styler.css" />

<?php if(checkForEquality(checkLoginStatus($db1), true, 'strict')): ?>
  <?php if(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>
    <section class="section-border border-primary min-vh-100 d-flex align-items-center">
      <div class="container-xl">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-9 py-8 py-md-8">
            <div class="mx-auto my-10 ff-inter" style="max-width:40rem;">

              <div class="d-flex row align-items-center mb-3 text-center">
                <button type="button"
                        class="col-auto btn btn-xs btn-light btn-outline-light"
                        id="prevMonth">
                  <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.5303 5.46967C10.8232 5.76256 10.8232 6.23744 10.5303 6.53033L5.81066 11.25H20C20.4142 11.25 20.75 11.5858 20.75 12C20.75 12.4142 20.4142 12.75 20 12.75H5.81066L10.5303 17.4697C10.8232 17.7626 10.8232 18.2374 10.5303 18.5303C10.2374 18.8232 9.76256 18.8232 9.46967 18.5303L3.46967 12.5303C3.17678 12.2374 3.17678 11.7626 3.46967 11.4697L9.46967 5.46967C9.76256 5.17678 10.2374 5.17678 10.5303 5.46967Z" fill="#1C274C"/>
                  </svg>
                </button>
                <div class="col fw-bold" id="calendarTitle"></div>
                <button type="button"
                        class="col-auto btn btn-xs border border-primary"
                        id="nextMonth">
                  <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.4697 5.46967C13.7626 5.17678 14.2374 5.17678 14.5303 5.46967L20.5303 11.4697C20.8232 11.7626 20.8232 12.2374 20.5303 12.5303L14.5303 18.5303C14.2374 18.8232 13.7626 18.8232 13.4697 18.5303C13.1768 18.2374 13.1768 17.7626 13.4697 17.4697L18.1893 12.75H4C3.58579 12.75 3.25 12.4142 3.25 12C3.25 11.5858 3.58579 11.25 4 11.25H18.1893L13.4697 6.53033C13.1768 6.23744 13.1768 5.76256 13.4697 5.46967Z" fill="#1C274C"/>
                  </svg>
                </button>
              </div>

              <div class="calendar-grid mb-10" id="calendarGrid"></div>

              <div id="calendar-data">
                <p class="my-1">Days present this year (<?php echo date('Y'); ?>): <strong id="yearTotal"></strong> days</p>
                <p class="my-1">Days present this month (<?php echo date('F') . ', ' . date('Y'); ?>): <strong id="monthTotal"></strong> days</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  <?php elseif(checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
    <section class="section-border border-primary min-vh-100 ff-inter">
      <div class="container-xxl py-5">
        <div class="row justify-content-center gx-0">
          <div class="col-12">
            <div class="text-center mb-5">
              <span class="badge rounded-pill text-bg-primary-subtle mb-4">
                <?php echo ucfirst((string) getUserRoleUsingUsercode($_SESSION['usercode'])); ?> Attendance Viewer
              </span>
              <h1 class="display-5 fw-bold mb-3">Batch Attendance</h1>
              <p class="text-body-secondary mb-0">
                Select a batch to review month-wise attendance across all enrolled students.
              </p>
            </div>

            <?php if ($selectedBatch === null && $activeBatchList === []): ?>
              <div class="alert alert-light border text-center px-4 py-3">
                <strong>No active batches are configured yet.</strong>
              </div>

            <?php elseif ($selectedBatch === null): ?>
              <form method="POST" class="mb-5">
                <div class="row justify-content-center align-items-end g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Batch</label>
                    <select name="batch" class="form-select form-select-lg" required>
                      <option value="" disabled selected>Choose Batch for Attendance Viewing</option>
                      <?php foreach ($activeBatchList as $batch): ?>
                        <option value="<?php echo escapeOutput((string) $batch); ?>">
                          <?php echo prettyPrintClassCode((string) $batch); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-auto">
                    <button name="loadAttendanceBtn" class="btn btn-primary rounded-pill">
                      Load Attendance
                    </button>
                  </div>
                </div>
              </form>

            <?php elseif ($studentsInBatch !== []): ?>
              <div class="row justify-content-center mt-10 mb-8">
                <div class="col-auto d-flex align-items-center gap-4 flex-wrap justify-content-center">
                  <a class="btn btn-outline-secondary btn-sm"
                    href="?view=<?php echo urlencode((string) $selectedBatch); ?>&month=<?php echo (clone $monthDate)->modify('-1 month')->format('Y-m'); ?>">
                    Previous
                  </a>

                  <span class="fw-semibold fs-5 text-center">
                    <?php echo $displayMonthName; ?>
                    <span class="d-block fs-6 text-body-secondary mt-2">
                      <?php echo prettyPrintClassCode((string) $selectedBatch); ?>
                    </span>
                  </span>

                  <a class="btn btn-outline-secondary btn-sm"
                    href="?view=<?php echo urlencode((string) $selectedBatch); ?>&month=<?php echo (clone $monthDate)->modify('+1 month')->format('Y-m'); ?>">
                    Next
                  </a>

                  <a class="btn btn-danger btn-sm rounded-pill" href="./">
                    Clear Filter
                  </a>
                </div>
              </div>

              <div class="row justify-content-center">
                <div class="col-auto">
                  <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center align-middle mb-0 attendance-table">
                      <thead class="table-dark">
                        <tr>
                          <th rowspan="2" class="text-start px-3">Student Name</th>
                          <th rowspan="2" class="text-start px-3">Student Code</th>
                          <th colspan="<?php echo count($allDates); ?>">
                            <?php echo $displayMonthName; ?>
                          </th>
                        </tr>
                        <tr>
                          <?php foreach ($allDates as $columnIndex => $date): ?>
                            <th data-col="<?php echo $columnIndex; ?>">
                              <?php echo date('d', strtotime($date)); ?>
                            </th>
                          <?php endforeach; ?>
                        </tr>
                      </thead>

                      <tbody>
                        <?php foreach ($studentsInBatch as $rowIndex => $usercode): ?>
                          <tr data-row="<?php echo $rowIndex; ?>">
                            <td class="student-name-cell px-3">
                              <?php echo escapeOutput($studentNames[$usercode] ?? 'N/A'); ?>
                            </td>
                            <td class="student-cell px-3">
                              <?php echo escapeOutput($usercode); ?>
                            </td>

                            <?php foreach ($allDates as $colIndex => $date): ?>
                              <?php $isPresent = isset($attendanceCache[$viewMonth][$usercode][$date]); ?>
                              <td class="attendance-cell <?php echo $isPresent ? 'present' : 'absent'; ?>"
                                  data-row="<?php echo $rowIndex; ?>"
                                  data-col="<?php echo $colIndex; ?>">
                                <?php echo $isPresent ? 'P' : 'A'; ?>
                              </td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

            <?php else: ?>
              <div class="row justify-content-center">
                <div class="col-auto">
                  <div class="alert alert-light border text-center px-4 py-3">
                    <strong>No students were found in that batch.</strong>
                    <a href="./" class="ms-3 btn btn-xs btn-danger rounded-pill">Clear Filter</a>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    
  <?php else: ?>
    <!-- ACCESS DENIED: PAGE UNDER DEVELOPMENT -->
    <section class="section-border border-primary">
      <div class="container-lg">
        <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
          <svg height="100" width="100" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 490 490" xml:space="preserve" fill="#000000">
            <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <g id="XMLID_111_"> <g> <path style="fill:#FFFFFF;" d="M480,80v390c0,5.5-4.5,10-10,10H20c-5.5,0-10-4.5-10-10V80H480z"></path> <path style="fill:#FFD248;" d="M480,20v60H10V20c0-5.5,4.5-10,10-10h450C475.5,10,480,14.5,480,20z"></path> </g> <g> <path style="fill:#231F20;" d="M470,0H20C8.972,0,0,8.972,0,20v450c0,11.028,8.972,20,20,20h450c11.028,0,20-8.972,20-20V20 C490,8.972,481.028,0,470,0z M470,20v50H20V20H470z M20,470V90h450v380H20z"></path> <g> <rect x="40" y="35" style="fill:#231F20;" width="35" height="20"></rect> </g> <g> <rect x="90" y="35" style="fill:#231F20;" width="35" height="20"></rect> </g> <g> <rect x="415" y="35" style="fill:#231F20;" width="35" height="20"></rect> </g> </g> </g> </g> <g id="XMLID_112_"> <g> <path style="fill:#AFB6BB;" d="M380,240v40c0,5.5-4.5,10-10,10h-24.4c-2.1,7-4.8,13.7-8.2,19.9l17.2,17.3 c3.9,3.9,3.9,10.2,0,14.1l-28.3,28.3c-3.9,3.9-10.2,3.9-14.1,0l-17.3-17.2c-6.2,3.4-12.9,6.1-19.9,8.2V385c0,5.5-4.5,10-10,10 h-40c-5.5,0-10-4.5-10-10v-24.4c-7-2.1-13.7-4.8-19.9-8.2l-17.3,17.2c-3.9,3.9-10.2,3.9-14.1,0l-28.3-28.3 c-3.9-3.9-3.9-10.2,0-14.1l17.2-17.3c-3.4-6.2-6.1-12.9-8.2-19.9H120c-5.5,0-10-4.5-10-10v-40c0-5.5,4.5-10,10-10h24.4 c2.1-7,4.8-13.7,8.2-19.9l-17.2-17.3c-3.9-3.9-3.9-10.2,0-14.1l28.3-28.3c3.9-3.9,10.2-3.9,14.1,0l17.3,17.2 c6.2-3.4,12.9-6.1,19.9-8.2V135c0-5.5,4.5-10,10-10h40c5.5,0,10,4.5,10,10v24.4c7,2.1,13.7,4.8,19.9,8.2l17.3-17.2 c3.9-3.9,10.2-3.9,14.1,0l28.3,28.3c3.9,3.9,3.9,10.2,0,14.1l-17.2,17.3c3.4,6.2,6.1,12.9,8.2,19.9H370 C375.5,230,380,234.5,380,240z"></path> </g> <g> <g> <path style="fill:#231F20;" d="M265,405h-40c-11.028,0-20-8.972-20-20v-17.193c-2.733-1.003-5.383-2.089-7.94-3.253 l-12.209,12.138c-3.754,3.754-8.769,5.833-14.101,5.833s-10.347-2.079-14.122-5.854l-28.299-28.299 c-3.774-3.774-5.854-8.789-5.854-14.121s2.079-10.347,5.854-14.122l12.117-12.188c-1.165-2.558-2.251-5.207-3.254-7.94H120 c-11.028,0-20-8.972-20-20v-40c0-11.028,8.972-20,20-20h17.193c1.003-2.733,2.089-5.383,3.253-7.94l-12.138-12.209 c-3.754-3.754-5.833-8.769-5.833-14.101s2.079-10.347,5.854-14.122l28.299-28.299c3.774-3.774,8.789-5.854,14.121-5.854 s10.347,2.079,14.122,5.854l12.188,12.117c2.558-1.165,5.207-2.251,7.94-3.254V135c0-11.028,8.972-20,20-20h40 c11.028,0,20,8.972,20,20v17.193c2.733,1.003,5.383,2.089,7.94,3.253l12.209-12.138c3.754-3.754,8.769-5.833,14.101-5.833 s10.347,2.079,14.122,5.854l28.299,28.299c3.774,3.774,5.854,8.789,5.854,14.121s-2.079,10.347-5.854,14.122l-12.117,12.188 c1.165,2.558,2.251,5.207,3.254,7.94H370c11.028,0,20,8.972,20,20v40c0,11.028-8.972,20-20,20h-17.193 c-1.003,2.733-2.089,5.383-3.253,7.94l12.138,12.209c3.754,3.754,5.833,8.769,5.833,14.101s-2.079,10.347-5.854,14.122 l-28.299,28.299c-3.774,3.774-8.789,5.854-14.121,5.854s-10.347-2.079-14.122-5.854l-12.188-12.117 c-2.558,1.165-5.207,2.251-7.94,3.254V385C285,396.028,276.028,405,265,405z M195.103,342.4c1.641,0,3.294,0.403,4.805,1.231 c5.346,2.931,11.391,5.417,17.966,7.39c4.23,1.269,7.127,5.162,7.127,9.578V385h40v-24.4c0-4.416,2.896-8.31,7.127-9.578 c6.575-1.973,12.62-4.459,17.966-7.39c3.887-2.132,8.712-1.449,11.858,1.677l17.3,17.199l28.277-28.279l-17.22-17.277 c-3.126-3.145-3.809-7.971-1.677-11.858c2.931-5.346,5.417-11.391,7.39-17.966c1.269-4.23,5.162-7.127,9.578-7.127H370v-40 h-24.4c-4.416,0-8.31-2.896-9.578-7.127c-1.973-6.575-4.459-12.62-7.39-17.966c-2.132-3.888-1.449-8.714,1.677-11.858 l17.199-17.3l-28.279-28.277l-17.277,17.22c-3.146,3.127-7.971,3.809-11.858,1.677c-5.346-2.931-11.391-5.417-17.966-7.39 c-4.23-1.269-7.127-5.162-7.127-9.578V135h-40v24.4c0,4.416-2.896,8.31-7.127,9.578c-6.575,1.973-12.62,4.459-17.966,7.39 c-3.888,2.13-8.714,1.449-11.858-1.677l-17.3-17.199l-28.277,28.279l17.22,17.277c3.126,3.145,3.809,7.971,1.677,11.858 c-2.931,5.346-5.417,11.391-7.39,17.966c-1.269,4.23-5.162,7.127-9.578,7.127H120v40h24.4c4.416,0,8.31,2.896,9.578,7.127 c1.973,6.575,4.459,12.62,7.39,17.966c2.132,3.888,1.449,8.714-1.677,11.858l-17.199,17.3l28.279,28.277l17.277-17.22 C189.972,343.397,192.522,342.4,195.103,342.4z"></path> </g> </g> </g> <g id="XMLID_113_"> <g> <path style="fill:#FFFFFF;" d="M245,220c22.1,0,40,17.9,40,40s-17.9,40-40,40s-40-17.9-40-40S222.9,220,245,220z"></path> </g> <g> <g> <path style="fill:#231F20;" d="M245,310c-27.57,0-50-22.43-50-50s22.43-50,50-50s50,22.43,50,50S272.57,310,245,310z M245,230 c-16.542,0-30,13.458-30,30s13.458,30,30,30s30-13.458,30-30S261.542,230,245,230z"></path> </g> </g> </g> <g> <rect x="380" y="150" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="410" y="150" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="440" y="150" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="30" y="350" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="60" y="350" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="90" y="350" style="fill:#231F20;" width="20" height="20"></rect> </g> <g> <rect x="65" y="430" style="fill:#231F20;" width="360" height="20"></rect> </g> </g> </g>
          </svg>
          <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
            <h1 class="display-3 fw-bold text-center">
              Page Under Development.
            </h1>
            <p class="mb-5 text-center text-body-secondary">
              Something new is being created here. Stay tight.
            </p>
            <div class="text-center my-7">
              <a class="btn btn-primary rounded-pill" href="../dashboard/">
                Back to Dashboard
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

  <?php endif; ?>

<?php elseif(checkForEquality(checkLoginStatus($db1), false, 'strict')): ?>
  <!-- ACCESS DENIED: USER LOGGED OUT -->
  <section class="section-border border-primary">
    <div class="container-lg">
      <div class="d-flex flex-column align-items-center justify-content-center min-vh-100">
        <svg height="100px" width="100px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" fill="#000000">
          <g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <style type="text/css"> .st0{fill:#000000;} </style> <g> <path class="st0" d="M256,0C114.616,0,0,114.612,0,256s114.616,256,256,256s256-114.612,256-256S397.385,0,256,0z M207.678,378.794 c0-17.612,14.281-31.893,31.893-31.893c17.599,0,31.88,14.281,31.88,31.893c0,17.595-14.281,31.884-31.88,31.884 C221.959,410.678,207.678,396.389,207.678,378.794z M343.625,218.852c-3.596,9.793-8.802,18.289-14.695,25.356 c-11.847,14.148-25.888,22.718-37.442,29.041c-7.719,4.174-14.533,7.389-18.769,9.769c-2.905,1.604-4.479,2.95-5.256,3.826 c-0.768,0.926-1.029,1.306-1.496,2.826c-0.273,1.009-0.558,2.612-0.558,5.091c0,6.868,0,12.512,0,12.512 c0,6.472-5.248,11.728-11.723,11.728h-28.252c-6.475,0-11.732-5.256-11.732-11.728c0,0,0-5.645,0-12.512 c0-6.438,0.752-12.744,2.405-18.777c1.636-6.008,4.215-11.718,7.508-16.694c6.599-10.083,15.542-16.802,23.984-21.48 c7.401-4.074,14.723-7.455,21.516-11.281c6.789-3.793,12.843-7.91,17.302-12.372c2.988-2.975,5.31-6.05,7.087-9.52 c2.335-4.628,3.955-10.067,3.992-18.389c0.012-2.463-0.698-5.702-2.632-9.405c-1.926-3.686-5.066-7.694-9.264-11.29 c-8.45-7.248-20.843-12.545-35.054-12.521c-16.285,0.058-27.186,3.876-35.587,8.62c-8.36,4.776-11.029,9.595-11.029,9.595 c-4.268,3.718-10.603,3.85-15.025,0.314l-21.71-17.397c-2.719-2.173-4.322-5.438-4.396-8.926c-0.063-3.479,1.425-6.81,4.061-9.099 c0,0,6.765-10.43,22.451-19.38c15.62-8.992,36.322-15.488,61.236-15.429c20.215,0,38.839,5.562,54.268,14.661 c15.434,9.148,27.897,21.744,35.851,36.876c5.281,10.074,8.525,21.43,8.533,33.38C349.211,198.042,347.248,209.058,343.625,218.852 z"></path> </g> </g>
        </svg>
        <div class="col-12 col-lg-9 col-md-10 px-8 px-md-8 py-8 py-md-8">
          <h1 class="display-3 fw-bold text-center">
            Session Expired.
          </h1>
          <p class="mb-5 text-center text-body-secondary">
            You are currently logged out. Please sign in to access this page.
          </p>
          <div class="text-center my-7">
            <a class="btn btn-primary rounded-pill ff-sourcesans3" href="../login/">
              Back to Login
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<script>
  window.ATTENDANCE_CONFIG = {
    attendanceDates: <?= json_encode(array_keys($attendanceByDate)) ?>,
    attendanceStats: <?= json_encode($attendanceStats) ?>,
    currentMonth: <?= $month ?>,
    currentYear: <?= $year ?>
  };
</script>
<script type="text/javascript" src="./attendance-controller.js" defer></script>
<?php require_once '../components/footer.php'; ?>
