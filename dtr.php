<?php
ob_start();
session_start();
include 'connection.php';

// Fetch only ACTIVE employees WITH DEPARTMENT
$employees_query = "SELECT e.employee_id, e.last_name, e.middle_name, e.first_name, d.name AS department_name
                   FROM employees e
                   LEFT JOIN departments d ON e.department_id = d.department_id
                   WHERE e.status = 'active'
                   ORDER BY e.last_name";
$employees = mysqli_query($conn, $employees_query);
if (!$employees) {
    die("Error fetching employees: " . mysqli_error($conn));
}

$display_rows = [];
$search_performed = false;
$no_results = false;

// HANDLE BOTH POST AND GET REQUESTS FOR FILTERING
$should_filter = false;
$employee_id = '';
$from_date = '';
$to_date = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_dtr'])) {
    $should_filter = true;
    $employee_id = $_POST['employee_id'] ?? '';
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['filter_dtr'])) {
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
            d.employee_id,
            d.is_manual,
            CONCAT(e.last_name, ', ', e.first_name, ' ', IFNULL(e.middle_name, '')) AS employee_name,
            dept.name AS department_name,
            d.date,
            dt.name AS day_type,
            TIME_FORMAT(s.time_in, '%H:%i') AS schedule_in,
            TIME_FORMAT(d.time_in, '%H:%i') AS actual_in,
            TIME_FORMAT(s.time_out, '%H:%i') AS schedule_out,
            TIME_FORMAT(d.time_out, '%H:%i') AS actual_out,
            TIME_FORMAT(d.break_out, '%H:%i') AS break_out,
            TIME_FORMAT(d.break_in, '%H:%i') AS break_in,
            TIME_FORMAT(d.late_time, '%H:%i') AS late_time,
            TIME_FORMAT(d.undertime_time, '%H:%i') AS undertime_time,
            d.overtime_time AS overtime_hours,
            TIME_FORMAT(d.night_time, '%H:%i') AS night_differential,
            TIME_FORMAT(d.total_work_time, '%H:%i') AS total_hours_worked,
            lt.name AS leave_type_name,
            d.remarks AS logs,
            d.time_in AS time_in_raw,
            d.time_out AS time_out_raw,
            d.break_out AS break_out_raw,
            d.break_in AS break_in_raw
        FROM EmployeeDTR d
        JOIN Employees e ON d.employee_id = e.employee_id
        LEFT JOIN departments dept ON e.department_id = dept.department_id
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        LEFT JOIN DayTypes dt ON d.day_type_id = dt.day_type_id
        LEFT JOIN LeaveTypes lt ON d.leave_type_id = lt.leave_type_id
        WHERE d.date BETWEEN ? AND ?
        AND e.status = 'active'
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

    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }

    $no_results = empty($display_rows);
    mysqli_stmt_close($stmt);
}

// Include UI
include 'head.php';
include 'sidebar.php';
include 'header.php';
?>

<style>
@media print {
    th:last-child, td:last-child { display: none !important; }
    #dtrTable thead th { position: relative !important; }
}
#dtrTable {
    table-layout: auto !important;
    width: 100% !important;
    border-collapse: collapse;
}
#dtrTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #16c216ff;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
#dtrTable th {
    font-weight: bold;
    font-size: 11px;
    color: #0a0a0aff;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    background: transparent;
    position: sticky;
    top: 0;
}
#dtrTable td {
    font-size: 15px;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    white-space: nowrap;
    word-break: normal;
    background-color: #fff;
}
#dtrTable tbody tr:hover {
    background-color: #f8f9ff;
    transition: background-color 0.3s ease;
}
#dtrTable th:nth-child(2), #dtrTable td:nth-child(2) {
    text-align: left !important;
    padding-left: 12px !important;
}
#dtrTable th:nth-child(3), #dtrTable td:nth-child(3) {
    text-align: left !important;
    padding-left: 12px !important;
}
.table-responsive {
    max-height: calc(100vh - 350px);
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 8px;
}
.alert-info, .alert-warning {
    background-color: #e7f3ff;
    border-color: #b8daff;
    color: #004085;
}
.manual-edit-badge {
    display: inline-block;
    background-color: #fff3cd;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 5px;
    border: 1px solid #ffc107;
}
.manual-edit-badge i { font-size: 9px; }
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

    <!-- Filter Card -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Employee DTR Filter</h5>
      </div>
      <div class="card-body">
        <form method="POST" class="row g-3">
          <div class="col-md-4">
            <label>Select Employee</label>
            <select name="employee_id" class="form-select">
              <option value="">-- All Active Employees --</option>
              <?php
              mysqli_data_seek($employees, 0);
              while ($emp = mysqli_fetch_assoc($employees)):
                $dept_display = $emp['department_name'] ? ' - ' . $emp['department_name'] : '';
              ?>
              <option value="<?= $emp['employee_id'] ?>" <?= ($employee_id == $emp['employee_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . ($emp['middle_name'] ?? '') . $dept_display) ?>
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
    </div>

    <?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?= $_SESSION['warning'] ?>
    </div>
    <?php unset($_SESSION['warning']); endif; ?>

    <?php if ($search_performed && $no_results): ?>
    <div class="alert alert-warning text-center">
      <strong>No Records Found.</strong> Try adjusting your filters.
    </div>
    <?php endif; ?>

    <?php if (!empty($display_rows)): ?>
    <div class="alert alert-info mb-3">
      <i class="fas fa-info-circle me-2"></i>
      <strong>View Mode:</strong> Displaying DTR records. 
      <span class="manual-edit-badge"><i class="fas fa-user-edit"></i> MANUAL</span> = Manually edited and protected from imports.
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">DTR Records (<?= count($display_rows) ?> found)</h5>
        <button onclick="window.open('print_dtr.php?<?= http_build_query(['employee_id' => $employee_id, 'from_date' => $from_date, 'to_date' => $to_date]) ?>', '_blank')" 
                class="btn btn-success btn-sm">
          <i class="fas fa-print me-2"></i> Print Daily Format
        </button>
      </div>
      
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="dtrTable">
            <thead class="table-light">
              <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Date</th>
                <th>Day Type</th>
                <th>Sched In</th>
                <th>Sched Out</th>
                <th>Actual In</th>
                <th>Actual Out</th>
                <th>Break Out</th>
                <th>Break In</th>
                <th>Late</th>
                <th>UT</th>
                <th>OT</th>
                <th>N Prem</th>
                <th>T. Hrs</th>
                <th>Leave</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($display_rows as $r): ?>
              <tr>
                <td>
                  <?= htmlspecialchars($r['employee_name']) ?>
                  <?php if ($r['is_manual'] == 1): ?>
                  <span class="manual-edit-badge" title="Manually edited - Protected from imports">
                    <i class="fas fa-user-edit"></i> MANUAL
                  </span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['department_name'] ?? 'N/A') ?></td>
                <td><?= date('m-d-y', strtotime($r['date'])) ?></td>
                <td><?= htmlspecialchars($r['day_type'] ?? '') ?></td>
                <td><?= $r['schedule_in'] ?? 'N/A' ?></td>
                <td><?= $r['schedule_out'] ?? 'N/A' ?></td>
                <td><?= $r['time_in_raw'] ? date('H:i', strtotime($r['time_in_raw'])) : '' ?></td>
                <td><?= $r['time_out_raw'] ? date('H:i', strtotime($r['time_out_raw'])) : '' ?></td>
                <td><?= $r['break_out_raw'] ? date('H:i', strtotime($r['break_out_raw'])) : '' ?></td>
                <td><?= $r['break_in_raw'] ? date('H:i', strtotime($r['break_in_raw'])) : '' ?></td>
                <td><?= $r['late_time'] ?? '00:00' ?></td>
                <td><?= $r['undertime_time'] ?? '00:00' ?></td>
                <td><?= number_format($r['overtime_hours'], 2) ?></td>
                <td><?= $r['night_differential'] ?? '00:00' ?></td>
                <td><?= $r['total_hours_worked'] ?? '00:00' ?></td>
                <td><?= htmlspecialchars($r['leave_type_name'] ?? 'None') ?></td>
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

<!-- DataTables (Search, Sort, Pagination only - No Export) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    const table = $('#dtrTable').DataTable({
        pageLength: -1,
        lengthMenu: [[-1], ["All"]],
        dom: 'frtip',
        language: {
            search: "",
            searchPlaceholder: "Search DTR records...",
            paginate: { previous: '<i class="fas fa-chevron-left"></i>', next: '<i class="fas fa-chevron-right"></i>' },
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty: "Showing 0 to 0 of 0 records",
            infoFiltered: "(filtered from _MAX_ total records)",
            zeroRecords: "No matching records found"
        }
    });

    $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
    $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');
    $(window).on('resize', function () { table.columns.adjust(); });
});
</script>