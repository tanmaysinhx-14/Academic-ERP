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

  if ($currentUserRole === 'student') {
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
  } elseif (in_array($currentUserRole, ['faculty', 'admin'], true)) {
    if (isset($_POST['loadAttendanceBtn'])) {
      $postedBatch = sanitizeInput($_POST['batch'] ?? null, 'text') ?? null;

      if ($postedBatch !== null) {
        redirectTo('./?view=' . rawurlencode($postedBatch), 0);
      }
    }

    $selectedBatch = sanitizeInput($_GET['view'] ?? null, 'text') ?? null;
    $requestedMonth = sanitizeInput($_GET['month'] ?? date('Y-m'), 'text') ?? date('Y-m');
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

<?php if ($currentUserRole === 'student'): ?>
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

  <script>
  window.ATTENDANCE_CONFIG = {
    attendanceDates: <?= json_encode(array_keys($attendanceByDate)) ?>,
    attendanceStats: <?= json_encode($attendanceStats) ?>,
    currentMonth: <?= $month ?>,
    currentYear: <?= $year ?>
  };
  </script>
  <script type="text/javascript" src="./attendance-controller.js" defer></script>

<?php elseif (in_array($currentUserRole, ['faculty', 'admin'], true)): ?>
  <link rel="stylesheet" href="./attendance-styler.css" />
  <section class="section-border border-primary min-vh-100 ff-inter">
    <div class="container-xxl py-5">
      <div class="row justify-content-center gx-0">
        <div class="col-12">
          <div class="text-center mb-5">
            <span class="badge rounded-pill text-bg-primary-subtle mb-4">
              <?php echo ucfirst((string) $currentUserRole); ?> Attendance Viewer
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
<?php endif; ?>

<?php
  require_once '../components/footer.php';
?>
