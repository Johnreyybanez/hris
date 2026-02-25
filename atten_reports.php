<?php
session_start();
include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Function to count holidays within a date range
function countHolidaysInRange($conn, $from_date, $to_date, $day_type_id = null) {
    $query = "SELECT COUNT(*) as holiday_count FROM holidaycalendar WHERE date >= ? AND date <= ?";
    $params = [$from_date, $to_date];
    $types = "ss";
    
    if ($day_type_id !== null) {
        $query .= " AND day_type_id = ?";
        $params[] = $day_type_id;
        $types .= "i";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['holiday_count'];
}

// Function to get all holidays within a date range
function getHolidaysInRange($conn, $from_date, $to_date) {
    $query = "SELECT date, name, day_type_id, description FROM holidaycalendar WHERE date >= ? AND date <= ? ORDER BY date";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $holidays = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $holidays[] = $row;
    }
    
    return $holidays;
}

// Function to calculate actual calendar days
function getActualDays($from_date, $to_date) {
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $diff = $start->diff($end);
    return $diff->days + 1;
}

// Function to get shift time_in
function getShiftTimeIn($conn) {
    $shift_query = "SELECT time_in FROM Shifts LIMIT 1";
    $shift_result = mysqli_query($conn, $shift_query);
    return ($shift_row = mysqli_fetch_assoc($shift_result)) ? $shift_row['time_in'] : '08:00:00';
}

// Function to calculate totals (UPDATED with undertime)
function calculateTotals($display_rows) {
    $totals = [
        'days_absent' => 0.00,
        'total_late_mins' => '00.00',
        'total_ut_mins' => '00.00',
        'employee_count' => count($display_rows)
    ];
    
    $total_late_seconds = 0;
    $total_ut_seconds = 0;
    
    foreach ($display_rows as $row) {
        $totals['days_absent'] += (float)$row['days_absent'];
        
        if (strpos($row['total_late_mins'], '.') !== false) {
            list($hours, $mins) = explode('.', $row['total_late_mins']);
            $total_late_seconds += ((int)$hours * 3600) + ((int)$mins * 60);
        }
        
        if (strpos($row['total_ut_mins'], '.') !== false) {
            list($hours, $mins) = explode('.', $row['total_ut_mins']);
            $total_ut_seconds += ((int)$hours * 3600) + ((int)$mins * 60);
        }
    }
    
    $late_hours = floor($total_late_seconds / 3600);
    $late_mins = round(($total_late_seconds % 3600) / 60);
    $totals['total_late_mins'] = str_pad($late_hours, 2, '0', STR_PAD_LEFT) . '.' . str_pad($late_mins, 2, '0', STR_PAD_LEFT);
    
    $ut_hours = floor($total_ut_seconds / 3600);
    $ut_mins = round(($total_ut_seconds % 3600) / 60);
    $totals['total_ut_mins'] = str_pad($ut_hours, 2, '0', STR_PAD_LEFT) . '.' . str_pad($ut_mins, 2, '0', STR_PAD_LEFT);
    
    return $totals;
}

// Fetch departments
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments = mysqli_query($conn, $departments_query) or die("Error fetching departments: " . mysqli_error($conn));

$display_rows = [];
$search_performed = false;
$no_results = false;
$holidays_in_range = [];
$total_holidays = 0;
$actual_calendar_days = 0;
$shift_time_in = '08:00:00';
$totals = [];
$sunday_count_in_range = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_performed = true;
    $from_date = mysqli_real_escape_string($conn, $_POST['from_date'] ?? '');
    $to_date = mysqli_real_escape_string($conn, $_POST['to_date'] ?? '');
    $department_id = mysqli_real_escape_string($conn, $_POST['department_id'] ?? '');
    
    if (empty($from_date) || empty($to_date)) {
        die("❌ Error: Both date fields are required.");
    }
    
    $shift_time_in = getShiftTimeIn($conn);
    $actual_calendar_days = getActualDays($from_date, $to_date);
    $total_holidays = countHolidaysInRange($conn, $from_date, $to_date);
    $holidays_in_range = getHolidaysInRange($conn, $from_date, $to_date);
    
    $shift_query = "SELECT * FROM ShiftDays LIMIT 1";
    $shift_result = mysqli_query($conn, $shift_query);
    $active_rest_days = [
        'sunday' => 0,
        'monday' => 0,
        'tuesday' => 0,
        'wednesday' => 0,
        'thursday' => 0,
        'friday' => 0,
        'saturday' => 0,
    ];
    
    if ($shift_row = mysqli_fetch_assoc($shift_result)) {
        foreach ($active_rest_days as $day => $_) {
            $active_rest_days[$day] = (int)$shift_row["is_" . $day];
        }
    }
    
    // Count rest days AND Sundays separately
    $rest_day_count = 0;
    $sunday_count_in_range = 0;
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    foreach ($daterange as $date) {
        $day_name = strtolower($date->format('l'));
        
        if ($day_name === 'sunday') {
            $sunday_count_in_range++;
        }
        
        if (!empty($active_rest_days[$day_name]) && $active_rest_days[$day_name] == 1) {
            $rest_day_count++;
        }
    }
    
    $rest_day_conditions = [];
    foreach ($active_rest_days as $day => $is_active) {
        if ($is_active == 1) {
            $rest_day_conditions[] = "DAYNAME(dr.d) = '" . ucfirst($day) . "'";
        }
    }
    $rest_day_sql = !empty($rest_day_conditions) ? "(" . implode(" OR ", $rest_day_conditions) . ")" : "FALSE";
    
    $query = "
    WITH RECURSIVE date_range AS (
        SELECT DATE('$from_date') AS d
        UNION ALL
        SELECT DATE_ADD(d, INTERVAL 1 DAY)
        FROM date_range
        WHERE d < DATE('$to_date')
    )
    SELECT
        e.employee_id,
        e.biometric_id,
        dept.name AS department,
        CONCAT(e.last_name, ', ', e.first_name,
            CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
            THEN CONCAT(' ', e.middle_name) ELSE '' END) AS name,
        
        ROUND(
            SUM(CASE
                WHEN dtr.time_in IS NULL AND dtr.time_out IS NULL
                     AND NOT ($rest_day_sql)
                     AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                     AND DAYNAME(dr.d) != 'Sunday'
                THEN 1
                ELSE 0
            END)
        , 2) AS days_absent,
        
        CONCAT(
            LPAD(
                FLOOR(
                    SUM(
                        CASE
                            WHEN dtr.time_in IS NOT NULL
                                 AND dtr.time_out IS NOT NULL
                                 AND NOT ($rest_day_sql)
                                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                            THEN TIME_TO_SEC(COALESCE(dtr.late_time, '00:00:00')) / 60
                            ELSE 0
                        END
                    ) / 60
                ), 2, '0'
            ),
            '.',
            LPAD(
                ROUND(
                    SUM(
                        CASE
                            WHEN dtr.time_in IS NOT NULL
                                 AND dtr.time_out IS NOT NULL
                                 AND NOT ($rest_day_sql)
                                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                            THEN TIME_TO_SEC(COALESCE(dtr.late_time, '00:00:00')) / 60
                            ELSE 0
                        END
                    ) % 60
                ), 2, '0'
            )
        ) AS total_late_mins,
        
        CONCAT(
            LPAD(
                FLOOR(
                    SUM(
                        CASE
                            WHEN dtr.time_in IS NOT NULL
                                 AND dtr.time_out IS NOT NULL
                                 AND NOT ($rest_day_sql)
                                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                            THEN TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) / 60
                            ELSE 0
                        END
                    ) / 60
                ), 2, '0'
            ),
            '.',
            LPAD(
                ROUND(
                    SUM(
                        CASE
                            WHEN dtr.time_in IS NOT NULL
                                 AND dtr.time_out IS NOT NULL
                                 AND NOT ($rest_day_sql)
                                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                            THEN TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) / 60
                            ELSE 0
                        END
                    ) % 60
                ), 2, '0'
            )
        ) AS total_ut_mins
    FROM employees e
    LEFT JOIN departments dept ON e.department_id = dept.department_id
    JOIN date_range dr
    LEFT JOIN EmployeeDTR dtr ON e.employee_id = dtr.employee_id AND dtr.date = dr.d
    WHERE e.status = 'active'
    ";
    
    if (!empty($department_id)) {
        $query .= " AND e.department_id = '$department_id'";
    }
    
    $query .= " GROUP BY e.employee_id, e.last_name, e.first_name, e.middle_name, e.biometric_id, dept.name
                HAVING days_absent > 0 OR total_late_mins != '00.00' OR total_ut_mins != '00.00'
                ORDER BY dept.name ASC, e.last_name ASC, e.first_name ASC, e.middle_name ASC";
    
    $result = mysqli_query($conn, $query) or die("Query Error: " . mysqli_error($conn));
    
    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }
    
    if (empty($display_rows)) {
        $no_results = true;
    } else {
        $totals = calculateTotals($display_rows);
    }
}
?>

<style>
@media print {
    body { font-size: 15px !important; }
    .table { font-size: 15px !important; }
    .table th, .table td { font-weight: bold !important; padding: 10px !important; }
    .holiday-card, .filter-card, .page-header, .breadcrumb { display: none !important; }
    #dtrTable tfoot tr { display: table-row !important; }
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
    background-color: #16c216;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
#dtrTable th {
    font-weight: bold;
    font-size: 13px;
    color: #000000;
    text-align: left;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    background-color: #16c216;
    position: sticky;
    top: 0;
}
#dtrTable td {
    font-size: 15px;
    text-align: left;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    white-space: nowrap;
    background-color: #fff;
}
#dtrTable tbody tr:hover {
    background-color: #f8f9ff;
    transition: background-color 0.3s ease;
}
#dtrTable tfoot td {
    background-color: #d1ecf1 !important;
    color: #0c5460 !important;
    font-weight: bold;
    font-size: 14px;
    border-top: 3px solid #0c5460;
}
#dtrTable tfoot td:first-child {
    text-align: left;
    font-size: 15px;
}
div.dataTables_filter { text-align: left !important; }
div.dataTables_filter label { font-weight: 500; color: #495057; }
div.dt-buttons { text-align: right !important; }
.filter-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    color: white;
    margin: 2rem 0;
}
.table-responsive {
    max-height: calc(100vh - 350px);
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 8px;
}
.holiday-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}
.no-results-card { padding: 2rem; }
.no-results-icon { font-size: 4rem; color: #6c757d; margin-bottom: 1rem; }
.results-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.decimal-absent { color: #dc3545; font-weight: 600; }
.late-format { color: #fd7e14; font-weight: 600; }
.undertime-format { color: #e83e8c; font-weight: 600; }
</style>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Attendance Summary - Simplified View</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Summary</li>
              <li class="breadcrumb-item">Simplified Attendance Summary</li>
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
                <small class="badge bg-info ms-2">Showing: Emp No, Name, Absent, Late, Undertime</small>
                <small class="badge bg-warning ms-2">Excluding Perfect Attendance (0 Absent, 0 Late & 0 Undertime)</small>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label for="department_id" class="form-label"><i class="fas fa-building me-1"></i> Department</label>
                    <select name="department_id" id="department_id" class="form-select">
                        <option value="">-- All Departments --</option>
                        <?php
                        mysqli_data_seek($departments, 0);
                        while ($dept = mysqli_fetch_assoc($departments)):
                        ?>
                            <option value="<?= $dept['department_id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="from_date" class="form-label"><i class="fas fa-calendar-alt me-1"></i> Start Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control" value="<?= $_POST['from_date'] ?? '' ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="to_date" class="form-label"><i class="fas fa-calendar-alt me-1"></i> End Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control" value="<?= $_POST['to_date'] ?? '' ?>" required>
                </div>
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> Generate Summary</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2"><i class="fas fa-times me-2"></i> Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
    
    
    <!-- No Results -->
    <?php if ($search_performed && $no_results): ?>
    <center>
        <div class="no-results-card">
            <div class="no-results-icon"><i class="fas fa-check-circle text-success"></i></div>
            <h4 class="text-success">Excellent! No Attendance Issues Found</h4>
            <p class="text-muted">All employees have perfect attendance (0 absent days, 0 late minutes, and 0 undertime) for the selected period.</p>
        </div>
    </center>
    <?php endif; ?>
    
    <!-- Results Table -->
    <?php if (!empty($display_rows)): ?>
    <div class="card results-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i> Simplified Attendance Summary (<?= count($display_rows) ?> Records)
                <br><small class="text-info">Showing: Department, Employee No., Name, Absent Days, Late (HH.MM), and Undertime (HH.MM)</small>
                <br><small class="text-warning">⚠️ Employees with perfect attendance (0 absent, 0 late & 0 undertime) are hidden</small>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover" id="dtrTable">
                    <thead class="table-light">
                        <tr>
                            <th>Department</th>
                            <th>Employee No.</th>
                            <th>Employee Name</th>
                            <th>Absent</th>
                            <th>Late (HH.MM)</th>
                            <th>Undertime (HH.MM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($display_rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                            <td><?= htmlspecialchars($row['biometric_id']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td class="decimal-absent"><?= number_format($row['days_absent'], 2) ?></td>
                            <td class="late-format"><?= htmlspecialchars($row['total_late_mins']) ?></td>
                            <td class="undertime-format"><?= htmlspecialchars($row['total_ut_mins']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td style="text-align: left;"><strong>Total Dept: <?= count(array_unique(array_column($display_rows, 'department'))) ?></strong></td>
                        <td style="text-align: left;"><strong>Total Emp: <?= $totals['employee_count'] ?></strong></td>
                        <td style="text-align: left;"><strong>GRAND TOTAL:</strong></td>
                        <td class="decimal-absent"><strong><?= number_format($totals['days_absent'], 2) ?></strong></td>
                        <td class="late-format"><strong><?= htmlspecialchars($totals['total_late_mins']) ?></strong></td>
                        <td class="undertime-format"><strong><?= htmlspecialchars($totals['total_ut_mins']) ?></strong></td>
                    </tr>
                </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
    const table = $('#dtrTable').DataTable({
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        pageLength: 25,
        dom:
            "<'row mb-3'<'col-md-6 d-flex align-items-center'l><'col-md-6 d-flex justify-content-end align-items-center'f>>" +
            "<'row mb-3'<'col-md-12 d-flex justify-content-end'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-md-6'i><'col-md-6'p>>",
        buttons: [
            { 
                extend: 'copy', 
                className: 'btn btn-sm btn-outline-secondary me-2', 
                text: '<i class="fas fa-copy"></i> Copy',
                title: ''
            },
            { 
                extend: 'csv', 
                className: 'btn btn-sm btn-outline-primary me-2', 
                text: '<i class="fas fa-file-csv"></i> CSV',
                title: 'Attendance_Summary'
            },
            { 
                extend: 'excel', 
                className: 'btn btn-sm btn-outline-success me-2', 
                text: '<i class="fas fa-file-excel"></i> Excel',
                title: 'Attendance_Summary'
            },
            {
                extend: 'pdf',
                className: 'btn btn-sm btn-outline-danger me-2',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                orientation: 'portrait',
                pageSize: 'A4',
                title: '',
                customize: function (doc) {
                    doc.defaultStyle.fontSize = 10;
                    doc.styles.tableHeader.fontSize = 11;
                    doc.styles.tableHeader.fillColor = '#16c216';
                    
                    // Add date period header
                    const fromDate = $('#from_date').val();
                    const toDate = $('#to_date').val();
                    if (fromDate && toDate) {
                        const formattedFrom = new Date(fromDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        const formattedTo = new Date(toDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        doc.content.splice(0, 0, {
                            text: 'ATTENDANCE REPORTS',
                            style: 'header',
                            alignment: 'center',
                            fontSize: 16,
                            bold: true,
                            margin: [0, 0, 0, 10]
                        });
                        doc.content.splice(1, 0, {
                            text: 'Report Period: ' + formattedFrom + ' to ' + formattedTo,
                            style: 'subheader',
                            alignment: 'center',
                            fontSize: 12,
                            margin: [0, 0, 0, 15]
                        });
                    }
                    doc.content[doc.content.length - 1].margin = [0, 0, 0, 0];
                }
            },
            {
                extend: 'print',
                className: 'btn btn-sm btn-outline-info',
                text: '<i class="fas fa-print"></i> Print',
                title: '',
                customize: function (win) {
                    const fromDate = $('#from_date').val();
                    const toDate = $('#to_date').val();
                    let dateRangeHTML = '';

                    if (fromDate && toDate) {
                        const formattedFrom = new Date(fromDate).toLocaleDateString('en-US', {
                            month: 'long', day: 'numeric', year: 'numeric'
                        });
                        const formattedTo = new Date(toDate).toLocaleDateString('en-US', {
                            month: 'long', day: 'numeric', year: 'numeric'
                        });
                        dateRangeHTML = `<h3 style="margin: 10px 0 30px 0; font-size: 20px; color: black; font-weight: bold;">
                                            Report Period: ${formattedFrom} – ${formattedTo}
                                         </h3>`;
                    }

                    // Add HRIS Dashboard title (BLACK) + Date Range below it
                    $(win.document.body).prepend(`
                        <div style="text-align: center; margin-bottom: 30px; page-break-inside: avoid;">
                            <h1 style="margin: 0; font-size: 32px; color: black; font-weight: bold; line-height: 1.2;">
                                ATTENDANCE REPORTS
                            </h1>
                            ${dateRangeHTML}
                            <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                                ⚠️ Excludes employees with perfect attendance (0 absent, 0 late & 0 undertime)
                            </p>
                        </div>
                    `);

                    // Hide default tfoot from printing
                    $(win.document.body).find('table tfoot').hide();
                    
                    // Table styling
                    $(win.document.body).find('table').addClass('compact').css({
                        'font-size': '15px',
                        'width': '100%',
                        'border-collapse': 'collapse'
                    });
                    $(win.document.body).find('table th, table td').css({
                        'font-weight': 'bold',
                        'padding': '10px',
                        'border': '1px solid #333',
                        'text-align': 'left'
                    });
                    $(win.document.body).find('table th').css('background-color', '#16c216').css('color', 'white');

                    // Get column widths from the actual table
                    const colWidths = [];
                    $(win.document.body).find('table thead th').each(function() {
                        colWidths.push($(this).outerWidth());
                    });
                    
                    // Calculate percentages based on actual widths
                    const totalWidth = colWidths.reduce((a, b) => a + b, 0);
                    const colPercentages = colWidths.map(w => ((w / totalWidth) * 100).toFixed(2));

                    // Extract footer values
                    const footerRow = $('#dtrTable tfoot tr');
                    const totalDept = footerRow.find('td:eq(0)').text().trim();
                    const totalEmp = footerRow.find('td:eq(1)').text().trim();
                    const grandTotalLabel = footerRow.find('td:eq(2)').text().trim();
                    const totalAbsent = footerRow.find('td:eq(3)').text().trim();
                    const totalLate = footerRow.find('td:eq(4)').text().trim();
                    const totalUT = footerRow.find('td:eq(5)').text().trim();

                    // Append totals row after the main table
                    $(win.document.body).find('table').after(`
                        <table style="width: 100%; border-collapse: collapse; font-size: 15px; margin-top: -1px;">
                            <tbody>
                                <tr style="background-color: #d1ecf1; border-top: 3px solid #0c5460;">
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #0c5460; border: 1px solid #333; width: ${colPercentages[0]}%; text-align: left;">
                                        ${totalDept}
                                    </td>
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #0c5460; border: 1px solid #333; width: ${colPercentages[1]}%; text-align: left;">
                                        ${totalEmp}
                                    </td>
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #0c5460; border: 1px solid #333; width: ${colPercentages[2]}%; text-align: left;">
                                        ${grandTotalLabel}
                                    </td>
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #dc3545; border: 1px solid #333; width: ${colPercentages[3]}%; text-align: left;">
                                        ${totalAbsent}
                                    </td>
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #fd7e14; border: 1px solid #333; width: ${colPercentages[4]}%; text-align: left;">
                                        ${totalLate}
                                    </td>
                                    <td style="font-weight: bold; padding: 10px; font-size: 16px; color: #e83e8c; border: 1px solid #333; width: ${colPercentages[5]}%; text-align: left;">
                                        ${totalUT}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    `);

                    // Final print styles
                    $(win.document.head).append(`
                        <style>
                            body { font-family: Arial, sans-serif; color: black; }
                            @media print {
                                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                                h1, h3 { color: black !important; }
                            }
                            .decimal-absent { color: #dc3545 !important; }
                            .late-format { color: #fd7e14 !important; }
                            .undertime-format { color: #e83e8c !important; }
                        </style>
                    `);
                }
            }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search records...",
            paginate: { previous: '<i class="fas fa-chevron-left"></i>', next: '<i class="fas fa-chevron-right"></i>' },
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty: "Showing 0 records",
            lengthMenu: "Show _MENU_ records per page",
            zeroRecords: "No matching records found"
        }
    });
    
    $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
    $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');
    
    $(window).on('resize', function() { table.columns.adjust(); });
    
    $('#from_date, #to_date').on('change', function() {
        const fromDate = new Date($('#from_date').val());
        const toDate = new Date($('#to_date').val());
        if (fromDate && toDate && fromDate > toDate) {
            alert('Start date cannot be later than end date.');
            $(this).val('');
        }
    });
    
    // Highlight high absence
    $('#dtrTable tbody tr').each(function() {
        const absentDays = parseFloat($(this).find('td:nth-child(4)').text().trim()) || 0;
        if (absentDays >= 3) {
            $(this).find('td:nth-child(4)').addClass('bg-danger text-white').attr('title', 'High absence rate').attr('data-bs-toggle', 'tooltip');
        }
    });
    
    // Highlight high late
    $('#dtrTable tbody tr').each(function() {
        const lateTime = $(this).find('td:nth-child(5)').text().trim();
        if (lateTime && lateTime !== '00.00') {
            const [hours, mins] = lateTime.split('.');
            const totalMins = (parseInt(hours) * 60) + parseInt(mins);
            if (totalMins >= 120) { // 2+ hours
                $(this).find('td:nth-child(5)').addClass('bg-warning text-dark').attr('title', 'High tardiness').attr('data-bs-toggle', 'tooltip');
            }
        }
    });
    
    // Highlight high undertime
    $('#dtrTable tbody tr').each(function() {
        const utTime = $(this).find('td:nth-child(6)').text().trim();
        if (utTime && utTime !== '00.00') {
            const [hours, mins] = utTime.split('.');
            const totalMins = (parseInt(hours) * 60) + parseInt(mins);
            if (totalMins >= 120) { // 2+ hours
                $(this).find('td:nth-child(6)').addClass('bg-danger text-white').attr('title', 'High undertime').attr('data-bs-toggle', 'tooltip');
            }
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
mysqli_close($conn);
?>