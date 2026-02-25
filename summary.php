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

// Function to calculate totals
function calculateTotals($display_rows) {
    $totals = [
        'actual_days' => 0,
        'total_days' => 0,
        'days_present' => 0.00,
        'days_absent' => 0.00,
        'sunday_count' => 0,
        'missing_logs' => 0,
        'late_instances' => 0,
        'total_late_mins' => '00.00',
        'undertime_instances' => 0,
        'total_ut_mins' => '00.00',
        'total_ot_hours' => 0.00,
        'night_diff_hours' => 0.00,
        'leave_days' => 0,
        'ob_days' => 0,
        'holiday_worked' => 0,
        'employee_count' => count($display_rows)
    ];
    
    $total_late_seconds = 0;
    $total_ut_seconds = 0;
    
    foreach ($display_rows as $row) {
        $totals['actual_days'] += (int)$row['actual_days'];
        $totals['total_days'] += (int)$row['total_days'];
        $totals['days_present'] += (float)$row['days_present'];
        $totals['days_absent'] += (float)$row['days_absent'];
        $totals['sunday_count'] += (int)$row['sunday_count'];
        $totals['missing_logs'] += (int)$row['missing_logs'];
        $totals['late_instances'] += (int)$row['late_instances'];
        $totals['undertime_instances'] += (int)$row['undertime_instances'];
        $totals['total_ot_hours'] += (float)$row['total_ot_hours'];
        $totals['night_diff_hours'] += (float)$row['night_diff_hours'];
        $totals['leave_days'] += (int)$row['leave_days'];
        $totals['ob_days'] += (int)$row['ob_days'];
        $totals['holiday_worked'] += (int)$row['holiday_worked'];
        
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

// Function to debug holiday working
function debugHolidayWorking($conn, $employee_id, $from_date, $to_date) {
    $debug_query = "
    WITH RECURSIVE date_range AS (
        SELECT DATE(?) AS d
        UNION ALL
        SELECT DATE_ADD(d, INTERVAL 1 DAY)
        FROM date_range
        WHERE d < DATE(?)
    )
    SELECT
        dr.d as date_checked,
        DAYNAME(dr.d) as day_name,
        dtr.time_in,
        dtr.time_out,
        dtr.day_type_id,
        CASE WHEN hc.date IS NOT NULL THEN 'YES' ELSE 'NO' END as is_holiday,
        hc.name as holiday_name,
        CASE
            WHEN hc.date IS NOT NULL
                 AND dtr.time_in IS NOT NULL
                 AND dtr.time_out IS NOT NULL
                 AND dtr.day_type_id IS NOT NULL
            THEN 'WORKED'
            ELSE 'NOT WORKED'
        END as holiday_status
    FROM date_range dr
    LEFT JOIN EmployeeDTR dtr ON dtr.employee_id = ? AND dtr.date = dr.d
    LEFT JOIN holidaycalendar hc ON hc.date = dr.d
    WHERE hc.date IS NOT NULL
    ORDER BY dr.d";
    
    $stmt = mysqli_prepare($conn, $debug_query);
    mysqli_stmt_bind_param($stmt, "ssi", $from_date, $to_date, $employee_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to fetch overtime details
function getEmployeeOvertimeDetails($conn, $employee_id, $from_date, $to_date) {
    $query = "
        SELECT
            date,
            COALESCE(overtime_time, 0.00) AS overtime_hours,
            day_type_id,
            remarks
        FROM EmployeeDTR
        WHERE employee_id = ?
        AND date >= ? AND date <= ?
        AND overtime_time > 0
        ORDER BY date";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iss", $employee_id, $from_date, $to_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $overtime_records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $overtime_records[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $overtime_records;
}

// Function to calculate total overtime hours
function getTotalOvertimeHours($conn, $from_date, $to_date, $department_id = null) {
    $query = "
        SELECT ROUND(SUM(COALESCE(overtime_time, 0.00)), 2) AS total_overtime_hours
        FROM EmployeeDTR dtr
        JOIN employees e ON dtr.employee_id = e.employee_id
        WHERE dtr.date >= ? AND date <= ?
        AND e.status = 'active'";
    
    $params = [$from_date, $to_date];
    $types = "ss";
    
    if (!empty($department_id)) {
        $query .= " AND e.department_id = ?";
        $params[] = $department_id;
        $types .= "i";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $row['total_overtime_hours'] ?? 0.00;
}

// Fetch departments
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments = mysqli_query($conn, $departments_query) or die("Error fetching departments: " . mysqli_error($conn));

$display_rows = [];
$search_performed = false;
$no_results = false;
$holidays_in_range = [];
$total_holidays = 0;
$total_overtime_hours = 0.00;
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
    $total_overtime_hours = getTotalOvertimeHours($conn, $from_date, $to_date, $department_id);
    
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
    
    // UPDATED CODE: Count rest days AND Sundays separately
    $rest_day_count = 0;
    $sunday_count_in_range = 0;
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    foreach ($daterange as $date) {
        $day_name = strtolower($date->format('l'));
        
        // Count Sundays separately
        if ($day_name === 'sunday') {
            $sunday_count_in_range++;
        }
        
        // Count other rest days
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
        CONCAT(e.last_name, ', ', e.first_name,
            CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
            THEN CONCAT(' ', e.middle_name) ELSE '' END) AS name,
        dept.name AS department,
        $actual_calendar_days AS actual_days,
        (DATEDIFF('$to_date', '$from_date') + 1 - $rest_day_count - $total_holidays - $sunday_count_in_range) AS total_days,
        
        ROUND(
            SUM(
                CASE
                    WHEN dtr.time_in IS NOT NULL
                         AND dtr.time_out IS NOT NULL
                         AND NOT ($rest_day_sql)
                         AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                    THEN 1
                    ELSE 0
                END
            )
            - (
                SUM(
                    CASE
                        WHEN dtr.time_in IS NOT NULL
                             AND dtr.time_out IS NOT NULL
                             AND NOT ($rest_day_sql)
                             AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                        THEN (TIME_TO_SEC(COALESCE(dtr.late_time, '00:00:00')) + TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00'))) / 60
                        ELSE 0
                    END
                ) / 480
            ), 2
        ) AS days_present,
        
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
        
        SUM(CASE
            WHEN dtr.time_in IS NOT NULL
                 AND dtr.time_out IS NOT NULL
                 AND DAYNAME(dr.d) = 'Sunday'
                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
            THEN 1
            ELSE 0
        END) AS sunday_count,
        
        SUM(CASE
            WHEN NOT ($rest_day_sql)
                 AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                 AND (dtr.time_in IS NULL OR dtr.time_out IS NULL)
                 AND NOT (dtr.time_in IS NULL AND dtr.time_out IS NULL)
            THEN 1
            ELSE 0
        END) AS missing_logs,
        
        SUM(
            CASE
                WHEN dtr.time_in IS NOT NULL
                     AND dtr.time_out IS NOT NULL
                     AND TIME_TO_SEC(COALESCE(dtr.late_time, '00:00:00')) > 0
                     AND NOT ($rest_day_sql)
                     AND NOT EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                THEN 1
                ELSE 0
            END
        ) AS late_instances,
        
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
        
        SUM(CASE WHEN TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) > 0 THEN 1 ELSE 0 END) AS undertime_instances,
        CONCAT(
            LPAD(FLOOR(SUM(TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) / 60) / 60), 2, '0'),
            '.',
            LPAD(ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.undertime_time, '00:00:00')) / 60) % 60), 2, '0')
        ) AS total_ut_mins,
        
        ROUND(SUM(COALESCE(dtr.overtime_time, 0.00)), 2) AS total_ot_hours,
        ROUND(SUM(TIME_TO_SEC(COALESCE(dtr.night_time, '00:00:00')) / 3600), 2) AS night_diff_hours,
        SUM(CASE WHEN dtr.day_type_id = 4 THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN dtr.day_type_id = 5 THEN 1 ELSE 0 END) AS ob_days,
        
        SUM(CASE
            WHEN EXISTS (SELECT 1 FROM holidaycalendar hc WHERE hc.date = dr.d)
                 AND dtr.time_in IS NOT NULL
                 AND dtr.time_out IS NOT NULL
                 AND dtr.day_type_id IS NOT NULL
            THEN 1
            ELSE 0
        END) AS holiday_worked
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
                ORDER BY e.last_name ASC, e.first_name ASC, e.middle_name ASC";
    
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

if (isset($_GET['debug']) && isset($_GET['emp_id']) && $search_performed) {
    $employee_id = mysqli_real_escape_string($conn, $_GET['emp_id']);
    
    $debug_result = debugHolidayWorking($conn, $employee_id, $from_date, $to_date);
    echo "<div class='card mt-3'><div class='card-header'><h4>Debug Holiday Working for Employee ID: " . htmlspecialchars($employee_id) . "</h4></div>";
    echo "<div class='card-body'><table class='table table-bordered'>";
    echo "<tr><th>Date</th><th>Day</th><th>Time In</th><th>Time Out</th><th>Day Type</th><th>Is Holiday</th><th>Holiday Name</th><th>Status</th></tr>";
    while ($debug_row = mysqli_fetch_assoc($debug_result)) {
        $status_class = $debug_row['holiday_status'] == 'WORKED' ? 'bg-success text-white' : 'bg-warning';
        echo "<tr class='$status_class'>";
        echo "<td>" . htmlspecialchars($debug_row['date_checked']) . "</td>";
        echo "<td>" . htmlspecialchars($debug_row['day_name']) . "</td>";
        echo "<td>" . ($debug_row['time_in'] ? htmlspecialchars($debug_row['time_in']) : 'NULL') . "</td>";
        echo "<td>" . ($debug_row['time_out'] ? htmlspecialchars($debug_row['time_out']) : 'NULL') . "</td>";
        echo "<td>" . ($debug_row['day_type_id'] ? htmlspecialchars($debug_row['day_type_id']) : 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($debug_row['is_holiday']) . "</td>";
        echo "<td>" . ($debug_row['holiday_name'] ? htmlspecialchars($debug_row['holiday_name']) : 'N/A') . "</td>";
        echo "<td><strong>" . htmlspecialchars($debug_row['holiday_status']) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table></div></div>";
    
    $overtime_details = getEmployeeOvertimeDetails($conn, $employee_id, $from_date, $to_date);
    echo "<div class='card mt-3'><div class='card-header'><h4>Overtime Details for Employee ID: " . htmlspecialchars($employee_id) . "</h4></div>";
    echo "<div class='card-body'><table class='table table-bordered' id='overtimeTable'>";
    echo "<tr><th>Date</th><th>Overtime Hours</th><th>Day Type</th><th>Remarks</th></tr>";
    if (!empty($overtime_details)) {
        foreach ($overtime_details as $ot_row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($ot_row['date']) . "</td>";
            echo "<td class='time-format'>" . number_format($ot_row['overtime_hours'], 2) . "</td>";
            echo "<td>" . ($ot_row['day_type_id'] ? htmlspecialchars($ot_row['day_type_id']) : 'N/A') . "</td>";
            echo "<td>" . ($ot_row['remarks'] ? htmlspecialchars($ot_row['remarks']) : 'N/A') . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center'>No overtime records found.</td></tr>";
    }
    echo "</table></div></div>";
}
?>

<style>
@media print {
    body { font-size: 15px !important; }
    .table { font-size: 15px !important; }
    .table th, .table td { font-weight: bold !important; padding: 10px !important; }
    .holiday-card, .overtime-summary-card, .filter-card, .page-header, .breadcrumb { display: none !important; }
    #dtrTable tfoot tr { display: table-row !important; }
    #dtrTable thead th, #overtimeTable thead th { position: relative !important; }
}
#dtrTable, #overtimeTable {
    table-layout: auto !important;
    width: 100% !important;
    border-collapse: collapse;
}
#dtrTable thead, #overtimeTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #16c216;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
#dtrTable th, #overtimeTable th {
    font-weight: bold;
    font-size: 11px;
    color: #000000;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    background-color: #16c216;
    position: sticky;
    top: 0;
}
#dtrTable td, #overtimeTable td {
    font-size: 15px;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    white-space: nowrap;
    background-color: #fff;
}
#dtrTable tbody tr:hover, #overtimeTable tbody tr:hover {
    background-color: #f8f9ff;
    transition: background-color 0.3s ease;
}
#dtrTable tfoot td {
    background-color: #d1ecf1 !important;
    color: #0c5460 !important;
    font-weight: bold;
    font-size: 13px;
    border-top: 3px solid #0c5460;
}
#dtrTable tfoot td:first-child {
    text-align: right;
    font-size: 14px;
}
#dtrTable button {
    margin: 2px;
    font-size: 11px;
    padding: 4px 8px;
}
div.dataTables_filter { text-align: left !important; }
div.dataTables_filter label { font-weight: 500; color: #495057; }
div.dt-buttons { text-align: right !important; }
.filter-card, .overtime-summary-card {
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
.decimal-present { color: #007bff; font-weight: 500; }
.decimal-absent { color: #dc3545; font-weight: 500; }
.time-format { font-family: 'Courier New', monospace; color: black; font-weight: 500; white-space: nowrap; }
.days-present-format { font-family: 'Courier New', monospace; color: #28a745; font-weight: 600; background: rgba(40, 167, 69, 0.1); padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.actual-days-format { font-family: 'Courier New', monospace; color: #17a2b8; font-weight: 600; background: rgba(23, 162, 184, 0.1); padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
.missing-logs-format { font-weight: 600; }
</style>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Attendance Summary (Decimal Format)</h5>
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
                <small class="badge bg-info ms-2">Format: Decimal (e.g., 12.14)</small>
                <small class="badge bg-success ms-2">Shift Time In: <?= htmlspecialchars($shift_time_in) ?></small>
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

    <!-- Holiday Summary Card -->
    <?php if ($search_performed && $total_holidays > 0): ?>
    <div class="card holiday-card">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0">
                <i class="fas fa-calendar-day me-2"></i>Holidays in Selected Period (<?= date('M d, Y', strtotime($from_date)) ?> - <?= date('M d, Y', strtotime($to_date)) ?>)
                <span class="badge bg-danger ms-2"><?= $total_holidays ?> Holiday(s)</span>
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($holidays_in_range)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Holiday Name</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays_in_range as $holiday): ?>
                        <tr>
                            <td>
                                <strong><?= date('M d, Y', strtotime($holiday['date'])) ?></strong><br>
                                <small class="text-muted"><?= date('l', strtotime($holiday['date'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($holiday['name']) ?></td>
                            <td><span class="badge bg-primary">Type <?= $holiday['day_type_id'] ?></span></td>
                            <td><?= htmlspecialchars($holiday['description'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
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
                <?php if ($total_holidays > 0): ?>
                <small class="text-warning ms-2">* Excluding <?= $total_holidays ?> holiday(s) and <?= $sunday_count_in_range ?> Sunday(s) from calculations</small>
                <?php endif; ?>
                <br><small class="text-info">Calendar Period: <strong><?= $actual_calendar_days ?> days</strong> | Shift Time In: <strong><?= htmlspecialchars($shift_time_in) ?></strong> | Present Days Format: <strong>Decimal (e.g., 12.14)</strong> | <span class="text-danger">Counts only days with both time-in AND time-out, total tardiness (late + undertime) deducted proportionally, Sundays excluded from absences and T. Days</span></small>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="dtrTable">
                    <thead class="table-light">
                        <tr>
                            <th>Emp No.</th>
                            <th>Name</th>
                            <th>Dept</th>
                            <th>Actual Days</th>
                            <th>T. Days</th>
                            <th>Present (Decimal)</th>
                            <th>Absent</th>
                            <th>Sunday Count</th>
                            <th>Missing Logs</th>
                            <th>Late</th>
                            <th>T. Late (HH.MM)</th>
                            <th>UT Instances</th>
                            <th>T. UT (HH.MM)</th>
                            <th>OT (hrs)</th>
                            <th>N. Prem</th>
                            <th>Leave</th>
                            <th>OB Days</th>
                            <th>Holiday Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($display_rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['biometric_id']) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                            <td class="actual-days-format"><?= htmlspecialchars($row['actual_days']) ?></td>
                            <td><?= htmlspecialchars($row['total_days']) ?></td>
                            <td class="days-present-format"><?= number_format($row['days_present'], 2) ?></td>
                            <td class="decimal-absent"><?= number_format($row['days_absent'], 2) ?></td>
                            <td>
                                <?php if ($row['sunday_count'] > 0): ?>
                                <span class="badge bg-primary text-white"><?= htmlspecialchars($row['sunday_count']) ?></span>
                                <?php else: ?>
                                <span class="text-muted"><?= htmlspecialchars($row['sunday_count']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="missing-logs-format">
                                <?php if ($row['missing_logs'] > 0): ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($row['missing_logs']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['late_instances']) ?></td>
                            <td class="time-format"><?= htmlspecialchars($row['total_late_mins']) ?></td>
                            <td><?= htmlspecialchars($row['undertime_instances']) ?></td>
                            <td class="time-format"><?= htmlspecialchars($row['total_ut_mins']) ?></td>
                            <td class="time-format"><?= number_format($row['total_ot_hours'], 2) ?></td>
                            <td><?= htmlspecialchars($row['night_diff_hours']) ?></td>
                            <td><?= htmlspecialchars($row['leave_days']) ?></td>
                            <td><?= htmlspecialchars($row['ob_days']) ?></td>
                            <td>
                                <?php if ($row['holiday_worked'] > 0): ?>
                                <span class="badge bg-success text-white"><?= htmlspecialchars($row['holiday_worked']) ?></span>
                                <?php else: ?>
                                <span class="text-muted"><?= htmlspecialchars($row['holiday_worked']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                    <td style="text-align: right;"><strong>TOTAL:</strong></td>
                       
                        <td style="text-align: center;"><strong><?= $totals['employee_count'] ?></strong></td>
                        <td style="text-align: center;"><strong><?= count(array_unique(array_column($display_rows, 'department'))) ?></strong></td>
                        <td><strong><?= $totals['actual_days'] ?></strong></td>
                        <td><strong><?= $totals['total_days'] ?></strong></td>
                        <td class="days-present-format"><strong><?= number_format($totals['days_present'], 2) ?></strong></td>
                        <td class="decimal-absent"><strong><?= number_format($totals['days_absent'], 2) ?></strong></td>
                        <td><strong><?= $totals['sunday_count'] ?></strong></td>
                        <td><strong><?= $totals['missing_logs'] ?></strong></td>
                        <td><strong><?= $totals['late_instances'] ?></strong></td>
                        <td class="time-format"><strong><?= htmlspecialchars($totals['total_late_mins']) ?></strong></td>
                        <td><strong><?= $totals['undertime_instances'] ?></strong></td>
                        <td class="time-format"><strong><?= htmlspecialchars($totals['total_ut_mins']) ?></strong></td>
                        <td class="time-format"><strong><?= number_format($totals['total_ot_hours'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['night_diff_hours'], 2) ?></strong></td>
                        <td><strong><?= $totals['leave_days'] ?></strong></td>
                        <td><strong><?= $totals['ob_days'] ?></strong></td>
                        <td><strong><?= $totals['holiday_worked'] ?></strong></td>
                    </tr>
                </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <?php if ($search_performed && !empty($display_rows) && isset($_GET['show_debug'])): ?>
    <div class="card mt-3">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fas fa-bug me-2"></i>Debug Information</h6>
        </div>
        <div class="card-body">
            <p><strong>Date Range:</strong> <?= $from_date ?> to <?= $to_date ?></p>
            <p><strong>Shift Time In:</strong> <span class="badge bg-success"><?= htmlspecialchars($shift_time_in) ?></span></p>
            <p><strong>Actual Calendar Days:</strong> <span class="actual-days-format"><?= $actual_calendar_days ?> days</span></p>
            <p><strong>Holidays Found:</strong> <?= $total_holidays ?></p>
            <p><strong>Sundays in Range:</strong> <span class="badge bg-primary"><?= $sunday_count_in_range ?></span></p>
            <p><strong>Rest Days in Range:</strong> <?= $rest_day_count ?></p>
            <p><strong>Working Days (T. Days):</strong> <?= $actual_calendar_days - $rest_day_count - $total_holidays - $sunday_count_in_range ?></p>
            <p><strong>Total Overtime Hours:</strong> <span class="time-format"><?= number_format($total_overtime_hours, 2) ?> hours</span></p>
            <?php if (!empty($holidays_in_range)): ?>
            <p><strong>Holiday Dates:</strong>
                <?php
                $holiday_dates = array_map(function($h) { return date('M d', strtotime($h['date'])); }, $holidays_in_range);
                echo implode(', ', $holiday_dates);
                ?>
            </p>
            <?php endif; ?>
            <div class="alert alert-success">
                <strong>Column Breakdown:</strong><br>
                • <strong>Actual Days:</strong> Total calendar days including weekends (<?= $actual_calendar_days ?> days)<br>
                • <strong>T. Days:</strong> Working days excluding rest days, holidays, and Sundays<br>
                • <strong>Present (Decimal):</strong> Total present days minus proportional tardiness deduction<br>
                • <strong>Absent:</strong> Days absent excluding rest days, holidays, and Sundays<br>
                • <strong>Sunday Count:</strong> Number of Sundays worked (with both time-in and time-out)<br>
                • <strong>Missing Logs:</strong> Days with incomplete attendance<br>
                • <strong>Late:</strong> Days with late_time > 0<br>
                • <strong>T. Late (HH.MM):</strong> Total late minutes (Hours.Minutes)<br>
                • <strong class="text-danger">NOTE:</strong> Counts days with both time-in AND time-out, tardiness deducted, Sundays excluded from absences and T. Days<br>
            </div>
            <small class="text-muted">
                To debug specific employee holiday working or overtime, add <code>?debug=1&emp_id=[EMPLOYEE_ID]</code> to URL.
            </small>
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
        lengthMenu: [[-1], ["All"]],
        paging: false,
        dom:
            "<'row mb-3'<'col-md-6 d-flex align-items-center'f><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-md-6'i><'col-md-6'>>",
        buttons: [
            { extend: 'copy', className: 'btn btn-sm btn-outline-secondary me-2', text: '<i class="fas fa-copy"></i> Copy' },
            { extend: 'csv', className: 'btn btn-sm btn-outline-primary me-2', text: '<i class="fas fa-file-csv"></i> CSV' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-success me-2', text: '<i class="fas fa-file-excel"></i> Excel' },
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
    text: '<i class="fas fa-print"></i> Print',
    title: '', // Remove default DataTables title
    customize: function (win) {
        // Get the date range from form inputs
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        let reportPeriod = '';

        if (fromDate && toDate) {
            const from = new Date(fromDate).toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric'
            });
            const to = new Date(toDate).toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric'
            });
            reportPeriod = `<h2 style="margin: 15px 0 20px 0; font-size: 20px; color: #0c5460; font-weight: bold;">
                              Report Period: ${from} to ${to}
                            </h2>`;
        }

        // Insert header with title and date range at the very top
        $(win.document.body).prepend(`
            <div style="text-align: center; margin-bottom: 30px; padding: 20px 0; border-bottom: 3px solid #0c5460;">
                <h1 style="margin: 0; font-size: 28px; color: black; font-weight: bold; line-height: 1.2;">
                    Attendance Summary Report (Decimal Format)
                </h1>
                ${reportPeriod}
            </div>
        `);

        const totalRecords = $('#dtrTable tbody tr').length;
        const footerRows = $('#dtrTable tfoot tr');
        let footerContent = '';
        
        $(win.document.body).find('table').addClass('compact').css('font-size', '15px');
        $(win.document.body).find('table th, table td').css({ 'font-weight': 'bold', 'padding': '10px' });
        
        footerRows.each(function() {
            const row = $(this);
            let rowHTML = '<tr style="background-color: #d1ecf1; border-top: 3px solid #0c5460;">';
            row.find('td').each(function() {
                rowHTML += '<td style="font-weight: bold; padding: 10px; border: 1px solid #000;">' + $(this).html() + '</td>';
            });
            rowHTML += '</tr>';
            footerContent += rowHTML;
        });
        
        $(win.document.body).append(`
            <div style="margin-top: 40px; padding-top: 20px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 15px;">
                    <tbody>
                        ${footerContent}
                    </tbody>
                </table>
                <div style="margin-top: -5px; padding-top: 20px; text-align: center; font-size: 18px; font-weight: bold; border-top: 3px solid #000; page-break-inside: avoid;">
                    <p style="margin: 10px 0 0 0; font-size: 20px;"><strong>Total Records: ${totalRecords}</strong></p>
                </div>
            </div>
        `);
        
        $(win.document.head).append(`
            <style>
                body { font-size: 15px !important; font-family: Arial, sans-serif; }
                table { font-size: 15px !important; }
                table th, table td { font-weight: bold !important; padding: 10px !important; }
                table tfoot { display: table-row-group; }
                table tfoot tr { page-break-inside: avoid; }
                .days-present-format { background: rgba(40, 167, 69, 0.2) !important; color: #155724 !important; }
                .actual-days-format { background: rgba(23, 162, 184, 0.2) !important; color: #0c5460 !important; }
                .time-format { font-family: 'Courier New', monospace; color: #6f42c1 !important; }
                .decimal-absent { color: #dc3545 !important; }
                .bg-primary { background-color: #007bff !important; color: #fff !important; }
                @media print { 
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    h1, h2 { color: black !important; }
                }
            </style>
        `);
    }
}
        ],
        language: {
            search: "",
            searchPlaceholder: "Search DTR records...",
            paginate: { previous: '<i class="fas fa-chevron-left"></i>', next: '<i class="fas fa-chevron-right"></i>' },
            info: "Showing _TOTAL_ records",
            infoEmpty: "Showing 0 records",
            lengthMenu: "Show _MENU_ records per page",
            zeroRecords: "No matching records found"
        }
    });
    
    if ($('#overtimeTable').length) {
        $('#overtimeTable').DataTable({
            paging: false,
            searching: false,
            ordering: true,
            info: false,
            dom: "<'row mb-3'<'col-md-12'>>" + "<'row'<'col-sm-12'tr>>",
            language: { zeroRecords: "No overtime records found" }
        });
    }
    
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
    
    $('#dtrTable tbody tr').each(function() {
        const holidayWorked = $(this).find('td:last').text().trim();
        if (parseInt(holidayWorked) > 0) {
            $(this).find('td:last').addClass('fw-bold');
        }
        const sundayCount = $(this).find('td:nth-child(8)').text().trim();
        if (parseInt(sundayCount) > 0) {
            $(this).find('td:nth-child(8)').attr('title', `Worked on ${sundayCount} Sunday(s)`).attr('data-bs-toggle', 'tooltip');
        }
    });
    
    $('.actual-days-format').each(function() {
        const actualDays = $(this).text().trim();
        $(this).attr('title', `Total calendar days including weekends: ${actualDays}`).attr('data-bs-toggle', 'tooltip');
    });
    
    $('.days-present-format').each(function() {
        const presentValue = $(this).text().trim();
        $(this).attr('title', `${presentValue} days (total present days minus tardiness deduction)`).attr('data-bs-toggle', 'tooltip');
    });
    
    $('.missing-logs-format').each(function() {
        const missingValue = $(this).text().trim();
        if (parseInt(missingValue) > 0) {
            $(this).find('span').attr('title', 'Days with incomplete attendance').attr('data-bs-toggle', 'tooltip');
        }
    });
    
    $('.time-format').each(function() {
        const timeValue = $(this).text().trim();
        if (timeValue.includes('.') || parseFloat(timeValue) > 0) {
            $(this).attr('title', `Format: ${timeValue.includes('.') ? 'HH.MM (Hours.Minutes)' : 'Hours'}`).attr('data-bs-toggle', 'tooltip');
        }
    });
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    $('#dtrTable tbody tr').each(function() {
        const lateInstances = parseInt($(this).find('td:nth-child(10)').text().trim()) || 0;
        const absentDays = parseFloat($(this).find('td:nth-child(7)').text().trim()) || 0;
        const missingLogs = parseInt($(this).find('td:nth-child(9)').text().trim()) || 0;
        if (lateInstances === 0 && absentDays === 0 && missingLogs === 0) {
            $(this).addClass('table-success').attr('title', 'Perfect Attendance').attr('data-bs-toggle', 'tooltip');
        }
    });
    
    $('#dtrTable tbody tr').each(function() {
        const absentDays = parseFloat($(this).find('td:nth-child(7)').text().trim()) || 0;
        const totalDays = parseInt($(this).find('td:nth-child(5)').text().trim()) || 1;
        if (absentDays > (totalDays * 0.2)) {
            $(this).find('td:nth-child(7)').addClass('bg-danger text-white').attr('title', 'High absence rate (>20%)').attr('data-bs-toggle', 'tooltip');
        }
    });
    
    $('#dtrTable tbody tr').each(function() {
        const missingLogs = parseInt($(this).find('td:nth-child(9)').text().trim()) || 0;
        if (missingLogs > 3) {
            $(this).find('td:nth-child(9)').addClass('bg-danger text-white').attr('title', 'High missing logs count').attr('data-bs-toggle', 'tooltip');
        }
    });
});
</script>

<?php
mysqli_close($conn);
?>