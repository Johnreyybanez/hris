<?php
// Start session and enable output buffering to avoid "headers already sent"
ob_start();
session_start();

include 'connection.php';

// Handle UPDATE DTR before any HTML or includes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dtr'])) {
    $dtr_id = $_POST['dtr_id'];
    $actual_in = $_POST['actual_in'];
    $actual_out = $_POST['actual_out'];
    $break_out = $_POST['break_out'];
    $break_in = $_POST['break_in'];
    $late_time = $_POST['late_time'];
    $undertime_time = $_POST['undertime_time'];
    $overtime_time = $_POST['overtime_time'];
    $night_time = $_POST['night_time'];
    $total_work_time = $_POST['total_work_time'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $leave_type_id = $_POST['leave_type_id'] ?? 'NULL';
    $leave_type_value = ($leave_type_id === '' || $leave_type_id === 'NULL') ? 'NULL' : intval($leave_type_id);

    // First, get the current date from the DTR record to construct proper datetime values
    $date_query = "SELECT date FROM EmployeeDTR WHERE dtr_id = '$dtr_id'";
    $date_result = mysqli_query($conn, $date_query);
    $date_row = mysqli_fetch_assoc($date_result);
    $record_date = $date_row['date'];

    // Convert time inputs to datetime format for database storage
    $time_in_datetime = !empty($actual_in) ? $record_date . ' ' . $actual_in . ':00' : 'NULL';
    $time_out_datetime = !empty($actual_out) ? $record_date . ' ' . $actual_out . ':00' : 'NULL';
    $break_out_datetime = !empty($break_out) ? $record_date . ' ' . $break_out . ':00' : 'NULL';
    $break_in_datetime = !empty($break_in) ? $record_date . ' ' . $break_in . ':00' : 'NULL';

    // Handle time fields (convert HH:MM to TIME format)
    $late_time_formatted = !empty($late_time) ? "'" . $late_time . ":00'" : "'00:00:00'";
    $undertime_time_formatted = !empty($undertime_time) ? "'" . $undertime_time . ":00'" : "'00:00:00'";
    $total_work_time_formatted = !empty($total_work_time) ? "'" . $total_work_time . ":00'" : "'00:00:00'";
    
    // Handle overtime as decimal (convert H.MM to decimal format)
    $overtime_decimal = 0.00;
    if (!empty($overtime_time)) {
        if (strpos($overtime_time, '.') !== false) {
            $overtime_decimal = floatval($overtime_time);
        } else if (strpos($overtime_time, ':') !== false) {
            // Convert HH:MM to decimal
            $parts = explode(':', $overtime_time);
            $overtime_decimal = floatval($parts[0]) + (floatval($parts[1]) / 60);
        } else {
            $overtime_decimal = floatval($overtime_time);
        }
    }

    // Handle night differential as time (convert H.MM to HH:MM:SS)
    $night_time_formatted = "'00:00:00'";
    if (!empty($night_time)) {
        if (strpos($night_time, '.') !== false) {
            // Convert decimal hours to HH:MM:SS
            $hours = floor(floatval($night_time));
            $minutes = round((floatval($night_time) - $hours) * 60);
            $night_time_formatted = "'" . sprintf("%02d:%02d:00", $hours, $minutes) . "'";
        } else if (strpos($night_time, ':') !== false) {
            $night_time_formatted = "'" . $night_time . ":00'";
        }
    }

    $update_sql = "
        UPDATE EmployeeDTR SET
            time_in = " . ($time_in_datetime === 'NULL' ? 'NULL' : "'" . $time_in_datetime . "'") . ",
            time_out = " . ($time_out_datetime === 'NULL' ? 'NULL' : "'" . $time_out_datetime . "'") . ",
            break_out = " . ($break_out_datetime === 'NULL' ? 'NULL' : "'" . $break_out_datetime . "'") . ",
            break_in = " . ($break_in_datetime === 'NULL' ? 'NULL' : "'" . $break_in_datetime . "'") . ",
            late_time = $late_time_formatted,
            undertime_time = $undertime_time_formatted,
            overtime_time = $overtime_decimal,
            night_time = $night_time_formatted,
            total_work_time = $total_work_time_formatted,
            leave_type_id = $leave_type_value,
            remarks = '$remarks',
            updated_at = NOW()
        WHERE dtr_id = '$dtr_id'
    ";

    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['success'] = "DTR record updated successfully!";
        
        // ✅ PRESERVE SEARCH PARAMETERS FOR AUTO-REFRESH
        $redirect_params = [];
        if (isset($_POST['preserve_employee_id'])) {
            $redirect_params['employee_id'] = $_POST['preserve_employee_id'];
        }
        if (isset($_POST['preserve_from_date'])) {
            $redirect_params['from_date'] = $_POST['preserve_from_date'];
        }
        if (isset($_POST['preserve_to_date'])) {
            $redirect_params['to_date'] = $_POST['preserve_to_date'];
        }
        $redirect_params['filter_dtr'] = '1'; // Auto-trigger filter
        
        $redirect_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($redirect_params);
        header("Location: " . $redirect_url);
        exit;
    } else {
        $_SESSION['error'] = "Failed to update DTR: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch all employees
$employees_query = "SELECT employee_id, last_name, middle_name, first_name FROM employees ORDER BY last_name";
$employees = mysqli_query($conn, $employees_query);
if (!$employees) {
    die("Error fetching employees: " . mysqli_error($conn));
}

// Fetch all leave types
$leave_types_query = "SELECT leave_type_id, name FROM LeaveTypes ORDER BY name";
$leave_types = mysqli_query($conn, $leave_types_query);
if (!$leave_types) {
    die("Error fetching leave types: " . mysqli_error($conn));
}

$display_rows = [];
$search_performed = false;
$no_results = false;

// ✅ HANDLE BOTH POST AND GET REQUESTS FOR FILTERING
$should_filter = false;
$employee_id = '';
$from_date = '';
$to_date = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_dtr'])) {
    // Manual filter submission
    $should_filter = true;
    $employee_id = $_POST['employee_id'] ?? '';
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['filter_dtr'])) {
    // Auto-filter after update redirect
    $should_filter = true;
    $employee_id = $_GET['employee_id'] ?? '';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
}

if ($should_filter) {
    $search_performed = true;
    
    if (empty($from_date) || empty($to_date)) {
        die("Error: Both date fields are required.");
    }

    $query = "
    SELECT 
        d.dtr_id,
        CONCAT(e.last_name, ', ', e.first_name, ' ', IFNULL(e.middle_name, '')) AS employee_name,
        d.date,
        dt.name AS day_type,
        TIME_FORMAT(s.time_in, '%h:%i %p') AS schedule_in,
        TIME_FORMAT(d.time_in, '%h:%i %p') AS actual_in,
        TIME_FORMAT(s.time_out, '%h:%i %p') AS schedule_out,
        TIME_FORMAT(d.time_out, '%h:%i %p') AS actual_out,
        TIME_FORMAT(d.break_out, '%h:%i %p') AS break_out,
        TIME_FORMAT(d.break_in, '%h:%i %p') AS break_in,
        TIME_FORMAT(d.late_time, '%H:%i') AS late_time,
        TIME_FORMAT(d.undertime_time, '%H:%i') AS undertime_time,
        d.overtime_time AS overtime_hours,
        TIME_FORMAT(d.night_time, '%H:%i') AS night_differential,
        TIME_FORMAT(d.total_work_time, '%H:%i') AS total_hours_worked,
        d.leave_type_id,
        lt.name AS leave_type_name,
        d.remarks AS logs,
        -- Raw values for editing (without formatting)
        TIME_FORMAT(d.time_in, '%H:%i') AS raw_actual_in,
        TIME_FORMAT(d.time_out, '%H:%i') AS raw_actual_out,
        TIME_FORMAT(d.break_out, '%H:%i') AS raw_break_out,
        TIME_FORMAT(d.break_in, '%H:%i') AS raw_break_in,
        TIME_FORMAT(d.late_time, '%H:%i') AS raw_late_time,
        TIME_FORMAT(d.undertime_time, '%H:%i') AS raw_undertime_time,
        d.overtime_time AS raw_overtime_time,
        TIME_FORMAT(d.night_time, '%H:%i') AS raw_night_time,
        TIME_FORMAT(d.total_work_time, '%H:%i') AS raw_total_work_time
    FROM EmployeeDTR d
    JOIN Employees e ON d.employee_id = e.employee_id
    LEFT JOIN Shifts s ON d.shift_id = s.shift_id
    LEFT JOIN DayTypes dt ON d.day_type_id = dt.day_type_id
    LEFT JOIN LeaveTypes lt ON d.leave_type_id = lt.leave_type_id
    WHERE d.date BETWEEN ? AND ?
    ";

    $params = [$from_date, $to_date];
    $types = "ss";

    if (!empty($employee_id)) {
        $query .= " AND e.employee_id = ?";
        $params[] = $employee_id;
        $types .= "s";
    }

    $query .= " ORDER BY employee_name ASC, d.date DESC";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        die("Execute failed: " . mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }

    $no_results = empty($display_rows);
    mysqli_stmt_close($stmt);
}

// Now include UI
include 'head.php';
include 'sidebar.php';
include 'header.php';
?>
<style>
@media print {
    th:last-child, td:last-child {
        display: none !important;
    }
}
#dtrTable th {
  font-weight: bold;
  font-size: 11px;
  color: #212529;
  text-align: center;
  vertical-align: middle;
}

#dtrTable td {
  white-space: normal !important;
  word-break: break-word;
  vertical-align: middle;
  font-size: 11px;
}
#dtrTable th,
#dtrTable td {
  white-space: normal !important;
  word-break: break-word;
  vertical-align: middle;
}

#dtrTable {
  table-layout: auto !important;
  width: 100% !important;
}

#dtrTable button {
  margin: 2px;
  font-size: 11px;
  padding: 4px 8px;
}

#dtrTable .btn {
  display: inline-block;
  white-space: nowrap;
}
</style>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Employee DTR Filter</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">DTR</li>
              <li class="breadcrumb-item">Employee DTR</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h5>Employee DTR Filter</h5>
      </div>
      <div class="card-body">
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label>Select Employee</label>
            <select name="employee_id" class="form-select">
                <option value="">-- All Employees --</option>
                <?php
                mysqli_data_seek($employees, 0);
                while ($emp = mysqli_fetch_assoc($employees)):
                ?>
                <option value="<?= $emp['employee_id'] ?>" <?= ($employee_id == $emp['employee_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Start Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>End Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control" required>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
            <button type="submit" name="filter_dtr" class="btn btn-primary">
                <i class="fas fa-filter me-1"></i> Filter
            </button>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">
                <i class="fas fa-times-circle me-1"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php if ($search_performed && $no_results): ?>
<div class="alert alert-warning text-center">
  <strong>No Records Found.</strong> Try adjusting your filters.
</div>
<?php endif; ?>

<?php if (!empty($display_rows)): ?>
<div class="card">
  <div class="card-header">
    <h5>DTR Records (<?= count($display_rows) ?> found)</h5>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover" id="dtrTable">
        <thead class="table-light">
          <tr>
            <th>Actions</th>
            <th>Employee</th>
            <th>Date</th>
            <th>Day Type</th>
            <th>Sched In</th>
             <th>Sched Out</th>
            <th>Actual In</th>
            <th>Actual Out</th>
            <th>Break Out</th>
            <th>Break In</th>
            <th>Late</th>
            <th>Undertime</th>
            <th>Overtime</th>
            <th>Night Diff</th>
            <th>Total Work</th>
            <th>Leave Type</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($display_rows as $r): ?>
          <tr
            data-dtr-id="<?= $r['dtr_id'] ?>"
            data-actual-in="<?= $r['raw_actual_in'] ?>"
            data-actual-out="<?= $r['raw_actual_out'] ?>"
            data-break-out="<?= $r['raw_break_out'] ?>"
            data-break-in="<?= $r['raw_break_in'] ?>"
            data-late="<?= $r['raw_late_time'] ?>"
            data-undertime="<?= $r['raw_undertime_time'] ?>"
            data-overtime="<?= $r['raw_overtime_time'] ?>"
            data-night="<?= $r['raw_night_time'] ?>"
            data-total-work="<?= $r['raw_total_work_time'] ?>"
            data-leave-type="<?= $r['leave_type_id'] ?>"
            data-remarks="<?= htmlspecialchars($r['logs']) ?>"
          >
          <td>
              <button type="button" class="btn btn-sm btn-outline-warning editDTRBtn">
              <i class="fas fa-edit"></i>
            </button>
            </td>
            <td><?= htmlspecialchars($r['employee_name']) ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= htmlspecialchars($r['day_type']) ?></td>
            <td><?= $r['schedule_in'] ?? 'N/A' ?></td>
             <td><?= $r['schedule_out'] ?? 'N/A' ?></td>
            <td><?= $r['actual_in'] ?? 'N/A' ?></td>
            <td><?= $r['actual_out'] ?? 'N/A' ?></td>
            <td><?= $r['break_out'] ?? 'N/A' ?></td>
            <td><?= $r['break_in'] ?? 'N/A' ?></td>
            <td><?= $r['late_time'] ?? '00:00' ?></td>
            <td><?= $r['undertime_time'] ?? '00:00' ?></td>
            <td><?= number_format($r['overtime_hours'], 2) ?></td>
            <td><?= $r['night_differential'] ?? '00:00' ?></td>
            <td><?= $r['total_hours_worked'] ?? '00:00' ?></td>
            <td><?= htmlspecialchars($r['leave_type_name'] ?? 'None') ?></td>
            <td><span class="badge bg-info"><?= htmlspecialchars($r['logs']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editDTRModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5>Edit DTR Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="dtr_id" id="edit_dtr_id">
        
        <!-- ✅ PRESERVE SEARCH PARAMETERS -->
        <input type="hidden" name="preserve_employee_id" value="<?= htmlspecialchars($employee_id) ?>">
        <input type="hidden" name="preserve_from_date" value="<?= htmlspecialchars($from_date) ?>">
        <input type="hidden" name="preserve_to_date" value="<?= htmlspecialchars($to_date) ?>">
        
        <div class="row g-3">
          <div class="col-md-6">
            <label>Actual In</label>
            <input type="time" name="actual_in" id="edit_actual_in" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Actual Out</label>
            <input type="time" name="actual_out" id="edit_actual_out" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Break Out</label>
            <input type="time" name="break_out" id="edit_break_out" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Break In</label>
            <input type="time" name="break_in" id="edit_break_in" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Late (HH:MM)</label>
            <input type="text" name="late_time" id="edit_late_time" class="form-control" placeholder="00:00">
          </div>
          <div class="col-md-4">
            <label>Undertime (HH:MM)</label>
            <input type="text" name="undertime_time" id="edit_undertime_time" class="form-control" placeholder="00:00">
          </div>
          <div class="col-md-4">
            <label>Overtime (Hours)</label>
            <input type="number" step="0.01" name="overtime_time" id="edit_overtime_time" class="form-control" placeholder="0.00">
          </div>
          <div class="col-md-4">
            <label>Night Diff (HH:MM)</label>
            <input type="text" name="night_time" id="edit_night_time" class="form-control" placeholder="00:00">
          </div>
          <div class="col-md-4">
            <label>Total Work (HH:MM)</label>
            <input type="text" name="total_work_time" id="edit_total_work_time" class="form-control" placeholder="00:00">
          </div>
          <div class="col-md-4">
            <label>Leave Type</label>
            <select name="leave_type_id" id="edit_leave_type_id" class="form-select">
              <option value="">-- None --</option>
              <?php
              mysqli_data_seek($leave_types, 0);
              while ($lt = mysqli_fetch_assoc($leave_types)):
              ?>
              <option value="<?= $lt['leave_type_id'] ?>">
                <?= htmlspecialchars($lt['name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-12">
            <label>Remarks</label>
            <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_dtr" class="btn btn-warning">
          <i class="fas fa-save"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if (isset($_SESSION['success'])): ?>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: '<?= $_SESSION['success'] ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: '<?= $_SESSION['error'] ?>',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});
<?php unset($_SESSION['error']); endif; ?>
</script>

<!-- DataTables Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    // Initialize DataTable
    const table = $('#dtrTable').DataTable({
        pageLength: -1,
        lengthMenu: [[-1], ["All"]],
        dom:
            "<'row mb-3'<'col-md-6 d-flex align-items-center'f><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-md-6'i><'col-md-6'p>>",
       buttons: [
    {
        extend: 'copy',
        className: 'btn btn-sm btn-outline-secondary me-2',
        text: '<i class="fas fa-copy"></i> Copy',
        exportOptions: {
            columns: ':not(:first-child)'
        }
    },
    {
        extend: 'csv',
        className: 'btn btn-sm btn-outline-primary me-2',
        text: '<i class="fas fa-file-csv"></i> CSV',
        exportOptions: {
            columns: ':not(:first-child)'
        }
    },
    {
        extend: 'excel',
        className: 'btn btn-sm btn-outline-success me-2',
        text: '<i class="fas fa-file-excel"></i> Excel',
        exportOptions: {
            columns: ':not(:first-child)'
        }
    },
    {
        extend: 'pdf',
        className: 'btn btn-sm btn-outline-danger me-2',
        text: '<i class="fas fa-file-pdf"></i> PDF',
        orientation: 'landscape',
        pageSize: 'A4',
        exportOptions: {
            columns: ':not(:first-child)'
        },
        customize: function (doc) {
            doc.defaultStyle.fontSize = 8;
            doc.styles.tableHeader.fontSize = 9;
            doc.styles.tableHeader.fillColor = '#667eea';
            doc.content[1].margin = [0, 0, 0, 0];
        }
    },
    {
        extend: 'print',
        className: 'btn btn-sm btn-outline-info',
        text: '<i class="fas fa-print"></i> Print',
        exportOptions: {
            columns: ':not(:first-child)'
        }
    }
],
        language: {
            search: "",
            searchPlaceholder: "Search DTR records...",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            },
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty: "Showing 0 to 0 of 0 records",
            infoFiltered: "(filtered from _MAX_ total records)",
            lengthMenu: "Show _MENU_ records per page",
            zeroRecords: "No matching records found"
        }
    });

    $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
    $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');

    $(window).on('resize', function () {
        table.columns.adjust();
    });

   // Fixed edit button click handler
    $(document).on('click', '.editDTRBtn', function() {
        const row = $(this).closest('tr');
        
        // Get data attributes from the row
        const dtrId = row.data('dtr-id');
        const actualIn = row.data('actual-in');
        const actualOut = row.data('actual-out');
        const breakOut = row.data('break-out');
        const breakIn = row.data('break-in');
        const late = row.data('late');
        const undertime = row.data('undertime');
        const overtime = row.data('overtime');
        const night = row.data('night');
        const totalWork = row.data('total-work');
        const leaveType = row.data('leave-type');
        const remarks = row.data('remarks');
        
        // Populate modal fields
        $('#edit_dtr_id').val(dtrId);
        $('#edit_actual_in').val(actualIn || '');
        $('#edit_actual_out').val(actualOut || '');
        $('#edit_break_out').val(breakOut || '');
        $('#edit_break_in').val(breakIn || '');
        $('#edit_late_time').val(late || '00:00');
        $('#edit_undertime_time').val(undertime || '00:00');
        $('#edit_overtime_time').val(overtime || '0.00');
        $('#edit_night_time').val(night || '00:00');
        $('#edit_total_work_time').val(totalWork || '00:00');
        $('#edit_leave_type_id').val(leaveType || '');
        $('#edit_remarks').val(remarks || '');
        
        // Show the modal
        $('#editDTRModal').modal('show');
    });

    // Form validation for time inputs
    $('input[type="text"]').on('input', function() {
        const value = $(this).val();
        const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
        
        if (value && !timePattern.test(value) && $(this).attr('name').includes('time')) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    // Auto-format time inputs
    $('input[name="late_time"], input[name="undertime_time"], input[name="night_time"], input[name="total_work_time"]').on('blur', function() {
        let value = $(this).val().replace(/[^\d:]/g, '');
        
        if (value.length === 3 && !value.includes(':')) {
            // Convert "800" to "8:00"
            value = value.charAt(0) + ':' + value.slice(1);
        } else if (value.length === 4 && !value.includes(':')) {
            // Convert "0800" to "08:00"
            value = value.slice(0, 2) + ':' + value.slice(2);
        }
        
        // Ensure proper format
        if (value && !/^\d{1,2}:\d{2}$/.test(value)) {
            value = '';
        }
        
        $(this).val(value);
    });

    // Calculate total work time automatically
    function calculateTotalWorkTime() {
        const timeIn = $('#edit_actual_in').val();
        const timeOut = $('#edit_actual_out').val();
        const breakOut = $('#edit_break_out').val();
        const breakIn = $('#edit_break_in').val();
        
        if (timeIn && timeOut) {
            const inTime = new Date('2000-01-01 ' + timeIn);
            const outTime = new Date('2000-01-01 ' + timeOut);
            
            // Handle overnight shifts
            if (outTime < inTime) {
                outTime.setDate(outTime.getDate() + 1);
            }
            
            let totalMinutes = (outTime - inTime) / (1000 * 60);
            
            // Subtract break time if provided
            if (breakOut && breakIn) {
                const breakOutTime = new Date('2000-01-01 ' + breakOut);
                const breakInTime = new Date('2000-01-01 ' + breakIn);
                const breakMinutes = (breakInTime - breakOutTime) / (1000 * 60);
                totalMinutes -= breakMinutes;
            }
            
            if (totalMinutes > 0) {
                const hours = Math.floor(totalMinutes / 60);
                const minutes = Math.round(totalMinutes % 60);
                $('#edit_total_work_time').val(
                    String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0')
                );
            }
        }
    }
    
    // Auto-calculate when time fields change
    $('#edit_actual_in, #edit_actual_out, #edit_break_out, #edit_break_in').on('change', calculateTotalWorkTime);
    
    // Prevent form submission if validation fails
    $('form').on('submit', function(e) {
        const invalidFields = $(this).find('.is-invalid');
        if (invalidFields.length > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please correct the highlighted fields before submitting.',
                confirmButtonText: 'OK'
            });
        }
    });
});
</script>