<?php
session_start();
include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch all departments
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments = mysqli_query($conn, $departments_query);

if (!$departments) {
    die("Error fetching departments: " . mysqli_error($conn));
}

$display_rows = [];
$search_performed = false;
$no_results = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_performed = true;
    $from_date = mysqli_real_escape_string($conn, $_POST['from_date'] ?? '');
    $to_date = mysqli_real_escape_string($conn, $_POST['to_date'] ?? '');
    $department_id = mysqli_real_escape_string($conn, $_POST['department_id'] ?? '');

    if (empty($from_date) || empty($to_date)) {
        die("❌ Error: Both date fields are required.");
    }

    // Main summary query with NULL-safe handling
    $query = "
        SELECT 
            e.employee_id,
            e.biometric_id,
            CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name) AS name,
            dept.name AS department,
            COUNT(DISTINCT dtr.date) AS total_days,
            SUM(CASE WHEN dtr.time_in IS NOT NULL THEN 1 ELSE 0 END) AS days_present,
            SUM(CASE WHEN dtr.time_in IS NULL THEN 1 ELSE 0 END) AS days_absent,
            SUM(CASE WHEN COALESCE(TIME(dtr.time_in), '00:00:00') > '08:00:00' THEN 1 ELSE 0 END) AS late_instances,
            ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.late_time, '00:00:00')) / 60), 2) AS total_late_mins,
            SUM(CASE WHEN TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) > 0 THEN 1 ELSE 0 END) AS undertime_instances,
            ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) / 60), 2) AS total_ut_mins,
            ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.overtime_time, '00:00:00')) / 3600), 2) AS total_ot_hours,
            ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.night_time, '00:00:00')) / 3600), 2) AS night_diff_hours,
            SUM(CASE WHEN dtr.day_type_id = 4 THEN 1 ELSE 0 END) AS leave_days,
            SUM(CASE WHEN dtr.day_type_id = 5 THEN 1 ELSE 0 END) AS ob_days,
            SUM(CASE WHEN dtr.day_type_id = 2 THEN 1 ELSE 0 END) AS holiday_worked,
            CASE WHEN SUM(COALESCE(dtr.has_missing_log, 0)) > 0 THEN 'Has Missing Logs' ELSE 'OK' END AS remarks
        FROM employees e
        LEFT JOIN departments dept ON e.department_id = dept.department_id
        LEFT JOIN EmployeeDTR dtr ON e.employee_id = dtr.employee_id
            AND dtr.date BETWEEN '$from_date' AND '$to_date'
    ";

    if (!empty($department_id)) {
        $query .= " WHERE e.department_id = '$department_id'";
    }

    $query .= " GROUP BY e.employee_id ORDER BY name";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query Error: " . mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }

    if (empty($display_rows)) {
        $no_results = true;
    }
}
?>

<style>
div.dataTables_filter {
    text-align: left !important;
}
div.dataTables_filter label {
    font-weight: 500;
    color: #495057;
}
div.dt-buttons {
    text-align: right !important;
}
.filter-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    color: white;
    margin-top: 2rem;
    margin-bottom: 2rem;
}
</style>


<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Attendance Summary </h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Summary</li>
              <li class="breadcrumb-item">Attendance Summary Filter</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

                <!-- Filter Card -->
                <div class="card filter-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i> Attendance Summary Filter
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label for="department_id" class="form-label">
                                    <i class="fas fa-building me-1"></i> Department
                                </label>
                                <select name="department_id" id="department_id" class="form-select">
                                    <option value="">-- All Departments --</option>
                                    <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                                        <option value="<?= $dept['department_id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="from_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> Start Date
                                </label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="<?= $_POST['from_date'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="to_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> End Date
                                </label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="<?= $_POST['to_date'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i> Generate Summary
                                </button>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i> Clear Filter
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- No Results -->
                <?php if ($search_performed && $no_results): ?>
                <center>
                    <div class="no-results-card">
                        <div class="no-results-icon"><i class="fas fa-search"></i></div>
                        <h4 class="text-muted">No Records Found</h4>
                        <p class="text-muted">Try adjusting your filters and search again.</p>
                    </div>
                </center>
                <?php endif; ?>

                <!-- Results Table -->
                <?php if (!empty($display_rows)): ?>
                <div class="card results-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i> Attendance Summary (<?= count($display_rows) ?> Records)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="summaryTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Biometric ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Total Days</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Total Late (min)</th>
                                        <th>UT Instances</th>
                                        <th>Total UT (min)</th>
                                        <th>OT (hrs)</th>
                                        <th>Night Diff (hrs)</th>
                                        <th>Leave Days</th>
                                        <th>OB Days</th>
                                        <th>Holiday Worked</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($display_rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['biometric_id']) ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['department']) ?></td>
                                        <td><?= htmlspecialchars($row['total_days']) ?></td>
                                        <td><?= htmlspecialchars($row['days_present']) ?></td>
                                        <td><?= htmlspecialchars($row['days_absent']) ?></td>
                                        <td><?= htmlspecialchars($row['late_instances']) ?></td>
                                        <td><?= htmlspecialchars($row['total_late_mins']) ?></td>
                                        <td><?= htmlspecialchars($row['undertime_instances']) ?></td>
                                        <td><?= htmlspecialchars($row['total_ut_mins']) ?></td>
                                        <td><?= htmlspecialchars($row['total_ot_hours']) ?></td>
                                        <td><?= htmlspecialchars($row['night_diff_hours']) ?></td>
                                        <td><?= htmlspecialchars($row['leave_days']) ?></td>
                                        <td><?= htmlspecialchars($row['ob_days']) ?></td>
                                        <td><?= htmlspecialchars($row['holiday_worked']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['remarks'] == 'OK' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($row['remarks']) ?>
                                            </span>
                                        </td>
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
    </div>
</div>


<!-- DataTables Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
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
    const table = $('#summaryTable').DataTable({
        scrollX: true,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        pageLength: 10,
        dom:
            "<'row mb-3'<'col-md-6 d-flex align-items-center'f><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-md-6'i><'col-md-6'p>>",
        buttons: [
            {
                extend: 'copy',
                className: 'btn btn-sm btn-outline-secondary me-2',
                text: '<i class="fas fa-copy"></i> Copy'
            },
            {
                extend: 'csv',
                className: 'btn btn-sm btn-outline-primary me-2',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            {
                extend: 'excel',
                className: 'btn btn-sm btn-outline-success me-2',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                className: 'btn btn-sm btn-outline-danger me-2',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                orientation: 'landscape',
                pageSize: 'A4',
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
                text: '<i class="fas fa-print"></i> Print'
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

    // Add icon inside search box
    $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
    $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');

    $(window).on('resize', function() {
        table.columns.adjust();
    });
});

</script>
