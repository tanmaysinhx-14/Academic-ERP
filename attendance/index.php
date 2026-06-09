<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => true,
    'required_roles' => ['admin', 'student'],
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Backend for Attendances
  if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')) {
    $csrfToken       = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    $batchListConfig = retrieveActiveBatchlist($db2);
    $activeBatchList = json_decode((string)($batchListConfig['value'] ?? '[]'), true);
    if (!is_array($activeBatchList)) $activeBatchList = [];

    $selectedBatch   = isset($_GET['batch']) ? escapeOutput($_GET['batch']) : null;
    $studentsInBatch = [];
    $batchAttendance = [];

    if (!checkForEquality($selectedBatch, null, 'strict')) {
      try { // Get List of Enrolled Students in a Batch 
        $STMT_fetchStudentListUsingBatchCode = $db1->prepare('SELECT student_usercode, student_name FROM student_details WHERE student_batch_details = :b ORDER BY student_name ASC');
        $STMT_fetchStudentListUsingBatchCode->bindValue(':b', $selectedBatch, PDO::PARAM_STR);
        $STMT_fetchStudentListUsingBatchCode->execute();

        $studentsInBatch = $STMT_fetchStudentListUsingBatchCode->fetchAll(PDO::FETCH_ASSOC);
      } 
      catch (PDOException) {}

      try { // Fetch Attendance Records of a Batch
        $STMT_fetchBatchAttendance = $db2->prepare('SELECT attendance_id, attendance_timestamp, attendance_value FROM attendance_records WHERE attendance_batch_code = :b');
        $STMT_fetchBatchAttendance->bindValue(':b', $selectedBatch, PDO::PARAM_STR);
        $STMT_fetchBatchAttendance->execute();

        foreach ($STMT_fetchBatchAttendance->fetchAll(PDO::FETCH_ASSOC) as $row) {
          $dt  = parseStoredTimestamp($row['attendance_timestamp']);
          $key = $dt ? $dt->format('Y-m-d') : date('Y-m-d', strtotime($row['attendance_timestamp']));
          $batchAttendance[$key] = [
            'id'        => (int)$row['attendance_id'],
            'usercodes' => json_decode((string)$row['attendance_value'], true) ?? [],
          ];
        }
      } 
      catch (PDOException) {}
    }

    if (isset($_POST['createAttendance'])) {
      $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

      if (validateCsrfToken($csrfToken)) {
        unsetCsrfToken();

        $postBatch    = escapeOutput($_POST['attendance_batch'] ?? '');
        $postDate     = escapeOutput($_POST['attendance_date']  ?? '');
        $presentCodes = array_values(array_map('strval', (array)($_POST['present_students'] ?? [])));
        $timestamp    = strtotime($postDate . ' 00:00:00');

        if (!$timestamp || !$postBatch) {
          setToast('Invalid batch or date.', 'danger', 5000);
        } 
        else {
          $isAttendanceRecordPresent = false;
          try {
            $checkAttendanceRecord = $db2->prepare("SELECT attendance_id FROM attendance_records WHERE attendance_batch_code = :b AND DATE(attendance_timestamp) = :d LIMIT 1");
            $checkAttendanceRecord->execute([':b' => $postBatch, ':d' => date('Y-m-d', $timestamp)]);
            $isAttendanceRecordPresent = $checkAttendanceRecord->fetchColumn();
          } 
          catch (PDOException) {}

          if ($isAttendanceRecordPresent !== false) {
            setToast('Attendance for this date already exists. Use Edit instead.', 'warning', 6000);
          } 
          else {
            $formattedTimestamp = formatTimestampForStorage($timestamp);
            $attendanceValue    = json_encode($presentCodes);

            $currentAttemptForInsertingAttendanceRecords = 0;
            while ($currentAttemptForInsertingAttendanceRecords < 3) {
              try {
                $STMT_insertAttendanceRecord = $db2->prepare('INSERT INTO attendance_records (attendance_batch_code, attendance_timestamp, attendance_value) VALUES (:b, :t, :v)');
                $STMT_insertAttendanceRecord->execute([':b' => $postBatch, ':t' => $formattedTimestamp, ':v' => $attendanceValue]);

                setToast('Attendance created successfully.', 'success', 5000);
                redirectTo('./?batch=' . rawurlencode($postBatch), 0);
                break;
              } 
              catch (PDOException $ex) {
                if (!isRetryablePdoException($ex)) {
                  setToast('Error creating attendance. Contact administrator.', 'danger', 7000);
                  logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Create attendance: ' . $ex->getMessage());
                  break;
                }
                $currentAttemptForInsertingAttendanceRecords++; sleep(3);
              }
            }
            if ($currentAttemptForInsertingAttendanceRecords >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
          }
        }
      } 
      else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }

    if (isset($_POST['updateAttendance'])) {
      $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

      if (validateCsrfToken($csrfToken)) {
        unsetCsrfToken();

        $postBatch    = escapeOutput($_POST['attendance_batch'] ?? '');
        $postId       = (int)($_POST['attendance_id'] ?? 0);
        $presentCodes = array_values(array_map('strval', (array)($_POST['present_students'] ?? [])));
        $attendanceValue = json_encode($presentCodes);
        
        $currentAttemptForUpdatingAttendanceRecords = 0;
        while ($currentAttemptForUpdatingAttendanceRecords < 3) {
          try {
            $STMT_updateAttendanceRecord = $db2->prepare('UPDATE attendance_records SET attendance_value = :v WHERE attendance_id = :id LIMIT 1');
            $STMT_updateAttendanceRecord->execute([':v' => $attendanceValue, ':id' => $postId]);

            setToast('Attendance updated successfully.', 'success', 5000);
            redirectTo('./?batch=' . rawurlencode($postBatch), 0);
            break;
          } 
          catch (PDOException $ex) {
            if (!isRetryablePdoException($ex)) {
              setToast('Error updating attendance. Contact administrator.', 'danger', 7000);
              logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Update attendance: ' . $ex->getMessage());
              break;
            }
            $currentAttemptForUpdatingAttendanceRecords++; 
            sleep(3);
          }
        }
        if ($currentAttemptForUpdatingAttendanceRecords >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
      } 
      else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }

    if (isset($_POST['deleteAttendance'])) {
      $csrfToken = escapeOutput($_POST['csrf_token'] ?? null);

      if (validateCsrfToken($csrfToken)) {
        unsetCsrfToken();

        $postBatch = escapeOutput($_POST['attendance_batch'] ?? '');
        $postId    = (int)($_POST['attendance_id'] ?? 0);

        $currentAttemptForDeletingAttendanceRecords = 0;
        while ($currentAttemptForDeletingAttendanceRecords < 3) {
          try {
            $STMT_deleteAttendanceRecord = $db2->prepare('DELETE FROM attendance_records WHERE attendance_id = :id LIMIT 1');
            $STMT_deleteAttendanceRecord->execute([':id' => $postId]);

            setToast('Attendance record deleted successfully.', 'success', 5000);
            redirectTo('./?batch=' . rawurlencode($postBatch), 0);

            break;
          } 
          catch (PDOException $ex) {
            if (!isRetryablePdoException($ex)) {
              setToast('Error deleting attendance. Contact administrator.', 'danger', 7000);
              logAppError($db2, $_SESSION['usercode'], getCurrentURL(), 'DATABASE', 'Delete attendance: ' . $ex->getMessage());
              break;
            }
            $currentAttemptForDeletingAttendanceRecords++; 
            sleep(3);
          }
        }
        if ($currentAttemptForDeletingAttendanceRecords >= 3) setToast('Failed after multiple attempts. Try again later.', 'danger', 7000);
      } 
      else setToast('Page Reload Activity detected. Please avoid reloading the page.', 'danger', 7000);
    }
  }

  elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')) {
    $attendanceByDate = [];
    $attendanceStats  = [];
    $month = (int)date('n');
    $year  = (int)date('Y');
    $academicYearStart = ($month >= 4) ? $year : $year - 1;

    try {
      $s = $db1->prepare('SELECT student_batch_details FROM student_details WHERE student_usercode = :uc LIMIT 1');
      $s->bindValue(':uc', $_SESSION['usercode'], PDO::PARAM_STR);
      $s->execute();
      $studentBatch = $s->fetchColumn();

      if ($studentBatch) {
        $s = $db2->prepare('SELECT attendance_timestamp, attendance_value FROM attendance_records WHERE attendance_batch_code = :b');
        $s->bindValue(':b', $studentBatch, PDO::PARAM_STR);
        $s->execute();

        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
          $dt  = parseStoredTimestamp($row['attendance_timestamp']);
          $key = $dt ? $dt->format('Y-m-d') : date('Y-m-d', strtotime($row['attendance_timestamp']));
          $ucs = json_decode((string)$row['attendance_value'], true) ?? [];

          if (in_array($_SESSION['usercode'], $ucs, true)) {
            if (isset($attendanceByDate[$key])) continue;
            $attendanceByDate[$key] = true;
            $recYear  = (int)substr($key, 0, 4);
            $recMonth = (int)substr($key, 5, 2);

            if (!isset($attendanceStats[$recYear])) {
              $attendanceStats[$recYear] = ['yearTotal' => 0, 'months' => array_fill(1, 12, 0)];
            }
            $attendanceStats[$recYear]['yearTotal']++;
            $attendanceStats[$recYear]['months'][$recMonth]++;
          }
        }
      }
    } 
    catch (PDOException) {}
  }
?>

<?php // Headers
  $page_title = "Attendance | careerinstitute.co.in";

  require_once '../components/header.php';

  $breadcrumb_url_1        = '../dashboard/';
  $breadcrumb_title_1      = 'Dashboard';
  
  $breadcrumb_url_active   = './';
  $breadcrumb_title_active = 'Attendance';

  require_once '../components/breadcrumb.php';
?>

<link rel="stylesheet" href="./attendance-styler.css" />

<?php if (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'admin', 'strict')): ?>
  <section class="section-border border-primary">
    <div class="container-xxl d-flex flex-column">
      <div class="row gx-0 align-items-start justify-content-center min-vh-100">
        <div class="col-12 px-8 py-8">

          <?php if (checkForEquality($selectedBatch, null, 'strict')): ?>

            <h2 class="fw-bold mb-1">Attendance</h2>
            <p class="text-body-secondary mb-6">Select a batch to manage or view attendance records.</p>

            <?php if (empty($activeBatchList)): ?>
              <div class="alert alert-light border">No active batches configured.</div>
            <?php else: ?>
              <form method="GET" action="./" class="row g-3 align-items-end" style="max-width: 520px;">
                <div class="col">
                  <label class="form-label fw-semibold">Select Batch</label>
                  <select name="batch" class="form-select form-select-lg" required>
                    <option value="" disabled selected>Choose a batch…</option>
                    <?php foreach ($activeBatchList as $batch): ?>
                      <option value="<?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars(prettyPrintClassCode($batch), ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-primary rounded-pill px-4">Continue</button>
                </div>
              </form>
            <?php endif; ?>

          <?php else: ?>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
              <div>
                <h2 class="fw-bold mb-1">
                  Attendance &mdash;
                  <span class="text-primary"><?= htmlspecialchars(prettyPrintClassCode($selectedBatch), ENT_QUOTES, 'UTF-8'); ?></span>
                </h2>
                <p class="text-body-secondary mb-0">
                  <?= count($studentsInBatch); ?> student(s) &middot;
                  <?= count($batchAttendance); ?> recorded session(s)
                </p>
              </div>
              <a href="./" class="btn btn-secondary rounded-pill px-4">Change Batch</a>
            </div>

            <?php if (empty($studentsInBatch)): ?>
              <div class="alert alert-light border">No students found in this batch.</div>
            <?php else: ?>

              <!-- Mode Toggle -->
              <div class="d-flex gap-2 mb-6">
                <button type="button"
                        id="btnManageMode"
                        class="btn btn-primary rounded-pill px-4"
                        onclick="setMode('manage')">
                  <span class="material-symbols-outlined align-middle me-1" style="font-size:1rem;">edit_calendar</span>
                  Manage Records
                </button>
                <button type="button"
                        id="btnViewMode"
                        class="btn btn-outline-secondary rounded-pill px-4"
                        onclick="setMode('view')">
                  <span class="material-symbols-outlined align-middle me-1" style="font-size:1rem;">table_view</span>
                  View Table
                </button>
              </div>

              <!-- ATTENDANCE MANAGE MODE -->
              <div id="managePane">
                <div class="mx-auto" style="max-width: 400px;">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <button type="button" class="btn btn-sm btn-secondary px-3" id="prevMonth">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M6 12L11 7M6 12L11 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <p id="calTitle" class="fs-lg fw-bolder text-center mb-0"></p>
                    <button type="button" class="btn btn-sm btn-secondary px-3" id="nextMonth">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M18 12L13 7M18 12L13 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  </div>
                  <div class="att-grid" id="calGrid"></div>
                  <div class="mt-3 d-flex gap-4 fs-sm text-body-secondary">
                    <span>
                      <span class="d-inline-block rounded-1 me-1" style="width:13px;height:13px;background:#d1fae5;vertical-align:middle;"></span>
                      Has record
                    </span>
                    <span>
                      <span class="d-inline-block rounded-1 me-1" style="width:13px;height:13px;background:#e9ecef;vertical-align:middle;"></span>
                      No record
                    </span>
                  </div>
                </div>

                <!-- Attendance Dialog (Create/Edit) -->
                <dialog class="ci-dialog" id="attendanceDialog" aria-labelledby="attendanceDialogTitle">
                  <form method="POST" action="./?batch=<?= rawurlencode($selectedBatch); ?>" id="attendanceForm">
                    <div class="ci-dialog__header">
                      <div>
                        <h5 class="fw-semibold mb-0" id="attendanceDialogTitle">Attendance</h5>
                        <small class="text-body-secondary" id="attendanceDialogSub"></small>
                      </div>
                      <button type="button" class="ci-dialog__close" onclick="closeDialog('attendanceDialog')" aria-label="Close"></button>
                    </div>
                    <div class="ci-dialog__body">
                      <input type="hidden" name="attendance_batch" value="<?= htmlspecialchars($selectedBatch, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="attendance_date"  id="dialogDate">
                      <input type="hidden" name="attendance_id"    id="dialogRecordId">
                      <input type="hidden" name="csrf_token"       value="<?= $csrfToken; ?>">

                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="fw-semibold fs-sm">Students Present</label>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-xs btn-success rounded-pill px-2 py-0" onclick="toggleAll(true)">All Present</button>
                          <button type="button" class="btn btn-xs btn-danger  rounded-pill px-2 py-0" onclick="toggleAll(false)">All Absent</button>
                        </div>
                      </div>
                      <div id="studentChecklist" class="border rounded-3" style="max-height:340px;overflow-y:auto;"></div>
                    </div>
                    <div class="ci-dialog__footer" id="attendanceDialogFooter"></div>
                  </form>
                </dialog>

                <!-- Attendance Dialog (Delete Confirmation) -->
                <dialog class="ci-dialog ci-dialog--sm" id="deleteConfirmDialog" aria-labelledby="deleteConfirmTitle">
                  <form method="POST" action="./?batch=<?= rawurlencode($selectedBatch); ?>">
                    <div class="ci-dialog__header">
                      <h5 class="fw-semibold mb-0" id="deleteConfirmTitle">Delete Attendance Record</h5>
                      <button type="button" class="ci-dialog__close" onclick="closeDialog('deleteConfirmDialog')" aria-label="Close"></button>
                    </div>
                    <div class="ci-dialog__body">
                      <p class="mb-1">Are you sure you want to delete the attendance record for</p>
                      <p class="fw-semibold mb-3" id="deleteConfirmDateDisplay">—</p>
                      <p class="text-danger fs-sm mb-0 d-flex align-items-start gap-1">
                        <span class="material-symbols-outlined mt-1" style="font-size:1rem;">warning</span>
                        This permanently removes attendance data for all students on this date.
                      </p>
                      <input type="hidden" name="attendance_batch" value="<?= htmlspecialchars($selectedBatch, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="attendance_id"    id="deleteConfirmId">
                      <input type="hidden" name="csrf_token"       value="<?= $csrfToken; ?>">
                    </div>
                    <div class="ci-dialog__footer">
                      <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-1" onclick="closeDialog('deleteConfirmDialog')">Cancel</button>
                      <button type="submit" name="deleteAttendance" class="btn btn-danger rounded-pill px-4 py-1">Delete</button>
                    </div>
                  </form>
                </dialog>
              </div>

              <!-- ATTENDANCE VIEW MODE -->
              <div id="viewPane" class="d-none">
                <div class="d-flex align-items-center justify-content-between mb-4" style="max-width:420px;">
                  <button type="button" class="btn btn-sm btn-secondary px-3" id="prevViewMonth">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M6 12L11 7M6 12L11 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <p id="tableTitle" class="fw-bolder text-center mb-0"></p>
                  <button type="button" class="btn btn-sm btn-secondary px-3" id="nextViewMonth">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M18 12L13 7M18 12L13 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>

                <div id="tableNoData" class="alert alert-light border d-none">
                  No attendance records for this month.
                </div>

                <div class="table-responsive" id="tableWrapper">
                  <table class="table table-bordered table-sm text-center align-middle mb-0 attendance-table" id="attendanceTable">
                    <thead class="table-dark"><tr></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>

                <div class="mt-3 d-flex gap-4 fs-sm text-body-secondary flex-wrap">
                  <span><span class="badge me-1" style="background:#d1e7dd;color:#0f5132;">P</span> Present</span>
                  <span><span class="badge me-1" style="background:#f8d7da;color:#842029;">A</span> Absent</span>
                  <span><span class="badge me-1 bg-secondary-subtle text-secondary">—</span> No session</span>
                </div>
              </div>

            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <script type="text/javascript">
    const BATCH_STUDENTS   = <?= json_encode(array_values($studentsInBatch), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const BATCH_ATTENDANCE = <?= json_encode($batchAttendance,               JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const MONTH_NAMES      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const MONTH_SHORT      = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function setMode(mode) {
      const isManage = mode === 'manage';
      document.getElementById('managePane').classList.toggle('d-none', !isManage);
      document.getElementById('viewPane').classList.toggle('d-none',  isManage);
      document.getElementById('btnManageMode').className = isManage
        ? 'btn btn-primary rounded-pill px-4'
        : 'btn btn-outline-secondary rounded-pill px-4';
      document.getElementById('btnViewMode').className = isManage
        ? 'btn btn-outline-secondary rounded-pill px-4'
        : 'btn btn-primary rounded-pill px-4';
      if (!isManage) renderTable(viewMonth, viewYear);
    }

    function closeDialog(id) { document.getElementById(id).close(); }
    function toggleAll(checked) {
      document.querySelectorAll('#studentChecklist input[type="checkbox"]').forEach(cb => cb.checked = checked);
    }
    function esc(str) {
      return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    document.querySelectorAll('dialog.ci-dialog').forEach(dlg => {
      dlg.addEventListener('click', e => {
        const r = dlg.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dlg.close();
      });
    });

    let curMonth = new Date().getMonth() + 1;
    let curYear  = new Date().getFullYear();

    function renderCalendar(month, year) {
      const grid  = document.getElementById('calGrid');
      const title = document.getElementById('calTitle');
      if (!grid || !title) return;

      grid.innerHTML  = '';
      title.textContent = `${MONTH_NAMES[month - 1]} ${year}`;

      ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
        const el = document.createElement('div');
        el.className = 'att-weekday';
        el.textContent = d;
        grid.appendChild(el);
      });

      const startDay  = new Date(year, month - 1, 1).getDay();
      const totalDays = new Date(year, month, 0).getDate();
      const today     = new Date();

      for (let i = 0; i < startDay; i++) {
        const e = document.createElement('div');
        e.className = 'att-day att-day--empty';
        grid.appendChild(e);
      }

      for (let day = 1; day <= totalDays; day++) {
        const dateKey = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
        const hasRec  = !!BATCH_ATTENDANCE[dateKey];
        const cell    = document.createElement('div');

        cell.className = 'att-day' + (hasRec ? ' att-day--has' : '');
        if (day === today.getDate() && month === today.getMonth() + 1 && year === today.getFullYear()) {
          cell.classList.add('att-day--today');
        }
        cell.textContent = day;
        cell.title = hasRec
          ? `${dateKey} — ${BATCH_ATTENDANCE[dateKey].usercodes.length} present`
          : `${dateKey} — no record`;
        cell.addEventListener('click', () => openAttendanceDialog(dateKey));
        grid.appendChild(cell);
      }
    }

    function openAttendanceDialog(dateKey) {
      const rec    = BATCH_ATTENDANCE[dateKey] || null;
      const isEdit = rec !== null;

      document.getElementById('attendanceDialogTitle').textContent = dateKey;
      document.getElementById('attendanceDialogSub').textContent   = isEdit
        ? `Editing record — ${rec.usercodes.length} student(s) marked present.`
        : 'No record yet. Mark present students and create.';

      document.getElementById('dialogDate').value     = dateKey;
      document.getElementById('dialogRecordId').value = isEdit ? rec.id : '';

      const list = document.getElementById('studentChecklist');
      list.innerHTML = '';
      BATCH_STUDENTS.forEach(s => {
        const checked  = isEdit && rec.usercodes.includes(s.student_usercode);
        const ucSafe   = esc(s.student_usercode);
        const nameSafe = esc(s.student_name);
        const wrapper  = document.createElement('div');
        wrapper.className = 'form-check d-flex align-items-center gap-2 px-3 py-2 border-bottom';
        wrapper.innerHTML =
          `<input class="form-check-input flex-shrink-0 mt-0" type="checkbox"
                  name="present_students[]" value="${ucSafe}" id="sc_${ucSafe}"
                  ${checked ? 'checked' : ''}>
           <label class="form-check-label d-flex flex-column" for="sc_${ucSafe}" style="cursor:pointer;">
             <span class="fw-semibold">${nameSafe}</span>
             <small class="text-body-secondary">${ucSafe}</small>
           </label>`;
        list.appendChild(wrapper);
      });

      const footer = document.getElementById('attendanceDialogFooter');
      if (isEdit) {
        footer.innerHTML =
          `<button type="button" class="btn btn-outline-danger rounded-pill px-4 py-1 me-auto"
                   onclick="openDeleteConfirm('${esc(dateKey)}', ${rec.id})">Delete Record</button>
           <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-1"
                   onclick="closeDialog('attendanceDialog')">Cancel</button>
           <button type="submit" name="updateAttendance" class="btn btn-primary rounded-pill px-4 py-1">Update</button>`;
      } else {
        footer.innerHTML =
          `<button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-1"
                   onclick="closeDialog('attendanceDialog')">Cancel</button>
           <button type="submit" name="createAttendance" class="btn btn-primary rounded-pill px-4 py-1">Create Record</button>`;
      }

      document.getElementById('attendanceDialog').showModal();
    }

    function openDeleteConfirm(dateKey, recordId) {
      document.getElementById('deleteConfirmDateDisplay').textContent = dateKey + '?';
      document.getElementById('deleteConfirmId').value = recordId;
      closeDialog('attendanceDialog');
      document.getElementById('deleteConfirmDialog').showModal();
    }

    document.getElementById('prevMonth')?.addEventListener('click', () => {
      if (--curMonth < 1) { curMonth = 12; curYear--; }
      renderCalendar(curMonth, curYear);
    });
    document.getElementById('nextMonth')?.addEventListener('click', () => {
      if (++curMonth > 12) { curMonth = 1; curYear++; }
      renderCalendar(curMonth, curYear);
    });

    renderCalendar(curMonth, curYear);

    let viewMonth = new Date().getMonth() + 1;
    let viewYear  = new Date().getFullYear();

    function renderTable(month, year) {
      document.getElementById('tableTitle').textContent = `${MONTH_NAMES[month - 1]} ${year}`;
      const totalDays  = new Date(year, month, 0).getDate();
      const today      = new Date();
      const todayStr   = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

      // Collect dates that have records in this month
      const monthDates = [];
      for (let d = 1; d <= totalDays; d++) {
        monthDates.push(`${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`);
      }

      const hasAnyRecord = monthDates.some(k => !!BATCH_ATTENDANCE[k]);
      document.getElementById('tableNoData').classList.toggle('d-none', hasAnyRecord);
      document.getElementById('tableWrapper').classList.toggle('d-none', !hasAnyRecord);
      if (!hasAnyRecord) return;

      // Build thead
      let theadRow = '<tr><th class="student-name-cell text-start px-3" rowspan="1">Student</th>';
      theadRow += '<th class="student-cell text-start px-3">Code</th>';
      for (let d = 1; d <= totalDays; d++) {
        const dk = monthDates[d - 1];
        const hasRec = !!BATCH_ATTENDANCE[dk];
        const isToday = dk === todayStr;
        theadRow += `<th data-col="${d}" style="${hasRec ? 'background:#198754;color:#fff;' : ''}${isToday ? 'border-bottom:2px solid #0d6efd;' : ''}">${d}</th>`;
      }
      theadRow += '<th>Present</th><th>Absent</th></tr>';

      // Build tbody
      let tbodyHtml = '';
      BATCH_STUDENTS.forEach((s, rowIdx) => {
        let presentCount = 0;
        let recordedDays = 0;
        let cells = '';

        monthDates.forEach((dk, colIdx) => {
          const rec = BATCH_ATTENDANCE[dk];
          if (rec) {
            recordedDays++;
            const isPresent = rec.usercodes.includes(s.student_usercode);
            if (isPresent) presentCount++;
            cells += `<td class="attendance-cell ${isPresent ? 'present' : 'absent'}"
                          data-row="${rowIdx}" data-col="${colIdx}">${isPresent ? 'P' : 'A'}</td>`;
          } else {
            cells += `<td class="text-body-tertiary" data-row="${rowIdx}" data-col="${colIdx}">—</td>`;
          }
        });

        tbodyHtml +=
          `<tr data-row="${rowIdx}">
             <td class="student-name-cell text-start px-3">${esc(s.student_name)}</td>
             <td class="student-cell text-start px-3"><code class="fs-sm">${esc(s.student_usercode)}</code></td>
             ${cells}
             <td><strong>${presentCount}</strong></td>
             <td class="text-body-secondary">${recordedDays - presentCount}</td>
           </tr>`;
      });

      const table = document.getElementById('attendanceTable');
      table.querySelector('thead').innerHTML = theadRow;
      table.querySelector('tbody').innerHTML = tbodyHtml;
    }

    document.getElementById('prevViewMonth')?.addEventListener('click', () => {
      if (--viewMonth < 1) { viewMonth = 12; viewYear--; }
      renderTable(viewMonth, viewYear);
    });
    document.getElementById('nextViewMonth')?.addEventListener('click', () => {
      if (++viewMonth > 12) { viewMonth = 1; viewYear++; }
      renderTable(viewMonth, viewYear);
    });
  </script>

<?php elseif (checkForEquality(getUserRoleUsingUsercode($_SESSION['usercode']), 'student', 'strict')): ?>

  <section class="section-border border-primary">
    <div class="container-xxl d-flex flex-column">
      <div class="row gx-0 justify-content-center min-vh-100">

        <!-- CALENDAR -->
        <div class="col-12 col-xl-5 px-8 py-8">
          <h5 class="fw-bold mb-4">Monthly View</h5>
          <div class="d-flex row align-items-center mb-3 text-center">
            <button type="button" class="col-auto btn btn-xs btn-secondary" id="prevMonth">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.5303 5.46967C10.8232 5.76256 10.8232 6.23744 10.5303 6.53033L5.81066 11.25H20C20.4142 11.25 20.75 11.5858 20.75 12C20.75 12.4142 20.4142 12.75 20 12.75H5.81066L10.5303 17.4697C10.8232 17.7626 10.8232 18.2374 10.5303 18.5303C10.2374 18.8232 9.76256 18.8232 9.46967 18.5303L3.46967 12.5303C3.17678 12.2374 3.17678 11.7626 3.46967 11.4697L9.46967 5.46967C9.76256 5.17678 10.2374 5.17678 10.5303 5.46967Z" fill="#fff"/></svg>
            </button>
            <strong class="col" id="calendarTitle"></strong>
            <button type="button" class="col-auto btn btn-xs btn-secondary" id="nextMonth">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M13.4697 5.46967C13.7626 5.17678 14.2374 5.17678 14.5303 5.46967L20.5303 11.4697C20.8232 11.7626 20.8232 12.2374 20.5303 12.5303L14.5303 18.5303C14.2374 18.8232 13.7626 18.8232 13.4697 18.5303C13.1768 18.2374 13.1768 17.7626 13.4697 17.4697L18.1893 12.75H4C3.58579 12.75 3.25 12.4142 3.25 12C3.25 11.5858 3.58579 11.25 4 11.25H18.1893L13.4697 6.53033C13.1768 6.23744 13.1768 5.76256 13.4697 5.46967Z" fill="#fff"/></svg>
            </button>
          </div>
          <div class="calendar-grid" id="calendarGrid"></div>
          <div class="mt-4" id="calendar-data">
            <p class="my-1 fs-sm text-body-secondary">
              Days present this year (<?= date('Y'); ?>):
              <strong class="text-body" id="yearTotal"></strong>
            </p>
            <p class="my-1 fs-sm text-body-secondary">
              Days present this month:
              <strong class="text-body" id="monthTotal"></strong>
            </p>
          </div>
        </div>

        <!-- TABULAR SUMMARY -->
        <div class="col-12 col-xl-7 px-8 py-8 border-start">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <h5 class="fw-bold mb-0">Academic Year Summary</h5>
            <div class="d-flex align-items-center gap-2">
              <button type="button" class="btn btn-sm btn-secondary px-3" id="prevAcadYear">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M6 12L11 7M6 12L11 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <span id="acadYearLabel" class="fw-semibold fs-sm"></span>
              <button type="button" class="btn btn-sm btn-secondary px-3" id="nextAcadYear">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M6 12H18M18 12L13 7M18 12L13 17" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle" id="summaryTable">
              <thead class="table-dark">
                <tr>
                  <th>Month</th>
                  <th class="text-center">Days Present</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot></tfoot>
            </table>
          </div>
        </div>

      </div>
    </div>
  </section>

  <script type="text/javascript">
    window.ATTENDANCE_CONFIG = {
      attendanceDates: <?= json_encode(array_keys($attendanceByDate)); ?>,
      attendanceStats: <?= json_encode($attendanceStats); ?>,
      currentMonth:    <?= $month; ?>,
      currentYear:     <?= $year; ?>
    };

    /* ── Academic year summary table ───────────────────────────────── */
    const ACADEMIC_MONTH_ORDER = [4,5,6,7,8,9,10,11,12,1,2,3];
    const MONTH_FULL = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    let acadYearStart = <?= $academicYearStart; ?>;

    function renderSummaryTable(startYear) {
      const stats  = window.ATTENDANCE_CONFIG.attendanceStats;
      const tbody  = document.querySelector('#summaryTable tbody');
      const tfoot  = document.querySelector('#summaryTable tfoot');
      const label  = document.getElementById('acadYearLabel');

      label.textContent = `${startYear}–${String(startYear + 1).slice(-2)}`;

      let rows  = '';
      let total = 0;

      ACADEMIC_MONTH_ORDER.forEach(m => {
        const y     = (m >= 4) ? startYear : startYear + 1;
        const count = stats[y]?.months?.[m] ?? 0;
        total += count;

        const isCurrentMonth = (m === window.ATTENDANCE_CONFIG.currentMonth &&
                                y === window.ATTENDANCE_CONFIG.currentYear);
        rows +=
          `<tr${isCurrentMonth ? ' class="table-primary"' : ''}>
             <td>${MONTH_FULL[m - 1]} ${y}${isCurrentMonth ? ' <span class="badge bg-primary-subtle text-primary rounded-pill fs-xs">Current</span>' : ''}</td>
             <td class="text-center fw-semibold">${count > 0 ? count : '<span class="text-body-tertiary">—</span>'}</td>
           </tr>`;
      });

      tbody.innerHTML = rows;
      tfoot.innerHTML =
        `<tr class="table-success">
           <td class="fw-bold">Total (${startYear}–${String(startYear + 1).slice(-2)})</td>
           <td class="text-center fw-bold">${total}</td>
         </tr>`;
    }

    document.getElementById('prevAcadYear').addEventListener('click', () => {
      acadYearStart--;
      renderSummaryTable(acadYearStart);
    });
    document.getElementById('nextAcadYear').addEventListener('click', () => {
      acadYearStart++;
      renderSummaryTable(acadYearStart);
    });

    renderSummaryTable(acadYearStart);
  </script>

  <script src="./attendance-controller.js" defer></script>

<?php endif; ?>

<?php require_once '../components/footer.php'; ?>