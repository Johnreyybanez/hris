<?php
session_start();
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: report.php');
    exit;
}

$employee_ids = $_POST['employee_id'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];

// Function to fetch holidays within a date range
function get_holidays_in_range($conn, $start_date, $end_date) {
    $sql = "SELECT date, name FROM holidaycalendar WHERE date BETWEEN ? AND ? ORDER BY date";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $holidays = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $holidays[$row['date']] = $row['name'];
    }
    mysqli_stmt_close($stmt);
    
    return $holidays;
}

// Function to fetch work suspensions within a date range
function get_work_suspensions_in_range($conn, $start_date, $end_date) {
    $sql = "SELECT date, name, is_full_day, is_half_day FROM worksuspensions WHERE date BETWEEN ? AND ? ORDER BY date";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $suspensions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $suspensions[$row['date']] = [
            'name' => $row['name'],
            'is_full_day' => $row['is_full_day'],
            'is_half_day' => $row['is_half_day']
        ];
    }
    mysqli_stmt_close($stmt);
    
    return $suspensions;
}

// Function to check if a date is a holiday
function is_holiday($holidays, $date) {
    return isset($holidays[$date]);
}

// Function to check if a date is a work suspension
function is_work_suspension($suspensions, $date) {
    return isset($suspensions[$date]);
}

// Function to fetch employee details
function get_employee_details($conn, $employee_id) {
    $emp_sql = "
        SELECT 
            e.employee_id,
            e.biometric_id,
            CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS name,
            dept.name AS department
        FROM 
            employees e
        JOIN 
            departments dept ON e.department_id = dept.department_id
        WHERE 
            e.employee_id = ?
    ";
    $emp_stmt = mysqli_prepare($conn, $emp_sql);
    mysqli_stmt_bind_param($emp_stmt, "i", $employee_id);
    mysqli_stmt_execute($emp_stmt);
    $emp_result = mysqli_stmt_get_result($emp_stmt);
    $employee = mysqli_fetch_assoc($emp_result);
    mysqli_stmt_close($emp_stmt);
    return $employee;
}

// Function to fetch DTR data for a given month (UPDATED to include leave information)
function fetch_dtr_data($conn, $employee_id, $month_start, $month_end) {
    $sql = "
        SELECT 
            dtr.date, 
            TIME_FORMAT(dtr.time_in, '%h:%i %p') AS time_in,
            TIME_FORMAT(dtr.break_out, '%h:%i %p') AS break_out,
            TIME_FORMAT(dtr.break_in, '%h:%i %p') AS break_in,
            TIME_FORMAT(dtr.time_out, '%h:%i %p') AS time_out,
            dtr.undertime_time,
            dtr.late_time,
            dtr.time_in AS raw_time_in,
            dtr.time_out AS raw_time_out,
            dtr.has_missing_log,
            dtr.leave_type_id,
            lt.LeaveCode,
            lt.name AS leave_name,
            lt.is_paid
        FROM 
            employeedtr dtr
        LEFT JOIN 
            leavetypes lt ON dtr.leave_type_id = lt.leave_type_id
        WHERE 
            dtr.employee_id = ?
            AND dtr.date BETWEEN ? AND ?
        ORDER BY dtr.date ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die("❌ SQL Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "iss", $employee_id, $month_start, $month_end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[$row['date']] = $row;
    }
    mysqli_stmt_close($stmt);

    return $data;
}

// Function to check if a date is a work day based on shift schedule
function is_work_day($conn, $employee_id, $date) {
    // Get day of week (1=Monday, 7=Sunday)
    $day_of_week = date('N', strtotime($date));
    
    // Map day numbers to column names
    $day_columns = [
        1 => 'is_monday',
        2 => 'is_tuesday', 
        3 => 'is_wednesday',
        4 => 'is_thursday',
        5 => 'is_friday',
        6 => 'is_saturday',
        7 => 'is_sunday'
    ];
    
    $day_column = $day_columns[$day_of_week];
    
    // Get employee's shift information
    $sql = "
        SELECT sd.{$day_column}
        FROM employees e
        JOIN shiftdays sd ON e.shift_id = sd.shift_id
        WHERE e.employee_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false; // Default to not work day if query fails
    }
    
    mysqli_stmt_bind_param($stmt, "i", $employee_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Return true if the day column has value 0 (working day), false if 1 (weekend/non-working)
    return ($row && $row[$day_column] == 0);
}

// Function to calculate undertime for absent days (modified to handle leaves)
function calculate_absence_undertime($conn, $employee_id, $date, $dtr_data, $holidays, $suspensions) {
    // Check if it's a holiday first
    if (is_holiday($holidays, $date)) {
        return [0, 0]; // No undertime for holidays
    }
    
    // Check if it's a work suspension (full day)
    if (is_work_suspension($suspensions, $date)) {
        if ($suspensions[$date]['is_full_day'] == 1) {
            return [0, 0]; // No undertime for full day work suspension
        }
    }
    
    // Check if this is a scheduled work day
    if (!is_work_day($conn, $employee_id, $date)) {
        return [0, 0]; // Not a work day, no undertime
    }
    
    // If no DTR record exists for this date, consider it absent (8 hours undertime)
    if (!isset($dtr_data[$date])) {
        return [8, 0]; // 8 hours, 0 minutes
    }
    
    $record = $dtr_data[$date];
    
    // Check if employee has a PAID leave - no undertime
    if (!empty($record['leave_type_id']) && $record['is_paid'] == 1) {
        return [0, 0]; // No undertime for paid leave
    }
    
    // Check if employee has an UNPAID leave - 8 hours undertime
    if (!empty($record['leave_type_id']) && $record['is_paid'] == 0) {
        return [8, 0]; // 8 hours undertime for unpaid leave
    }
    
    // Check if has_missing_log is 1 (yes) - treat as absent
    if ($record['has_missing_log'] == 1) {
        return [8, 0]; // 8 hours, 0 minutes
    }
    
    // If DTR record exists but both time_in and time_out are empty, consider it absent
    if (empty($record['raw_time_in']) && empty($record['raw_time_out'])) {
        return [8, 0]; // 8 hours, 0 minutes
    }
    
    return [0, 0]; // Not absent
}

// Function to parse time string (HH:MM:SS or HH:MM) to hours and minutes
function parse_time_to_hours_minutes($time_str) {
    if (empty($time_str) || $time_str === '00:00:00' || $time_str === '00:00') {
        return [0, 0];
    }
    
    $parts = explode(':', $time_str);
    $hours = isset($parts[0]) ? (int)$parts[0] : 0;
    $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
    
    return [$hours, $minutes];
}

// Function to render a single DTR form (UPDATED to handle leaves)
function render_dtr_form($conn, $employee, $month_start, $dtr_data, $holidays, $suspensions) {
    $month_name = date('F', strtotime($month_start));
    $year = date('Y', strtotime($month_start));
    ?>
    <div class="form-container">
        <div class="form-number">Civil Service Form No. 48</div>
        <div class="form-header">
            <div class="form-logo">
                <img src="logo.png" alt="Company Logo">
            </div>
            <div class="header-center">
                <div class="form-title">DAILY TIME RECORD</div>
                <div class="employee-name"><?= htmlspecialchars($employee['name']); ?></div>
                <div class="name-line"></div>
                <div class="name-label">(Name)</div>
            </div>
        </div>
        <div class="employee-info">
            <div class="info-row">
                <div class="info-item">
                    <strong>For the month of</strong>
                    <span class="underline"><?= $month_name; ?></span>, <strong><?= $year; ?></strong>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <strong>Official hours of arrival and departure</strong>
                    <strong>Reg. Days</strong> <span class="underline">&nbsp;&nbsp;&nbsp;</span><br>
                    <strong>Saturdays</strong> <span class="underline">&nbsp;&nbsp;&nbsp;</span>
                </div>
            </div>
        </div>
        <table class="time-table">
            <thead>
                <tr>
                    <th rowspan="3">Day</th>
                    <th colspan="2">A.M.</th>
                    <th colspan="2">P.M.</th>
                    <th colspan="2">UNDERTIME</th>
                </tr>
                <tr>
                    <th>Arrival</th><th>Departure</th><th>Arrival</th><th>Departure</th>
                    <th>Hours</th><th>Minutes</th>
                </tr>
                <tr>
                    <th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $period = new DatePeriod(
                    new DateTime($month_start),
                    new DateInterval('P1D'),
                    (new DateTime($month_start))->modify('last day of this month')->modify('+1 day')
                );
                $day_counter = 1;
                $total_undertime_hours = 0;
                $total_undertime_minutes = 0;

                foreach ($period as $date) {
                    $date_str = $date->format('Y-m-d');
                    $time_in = $dtr_data[$date_str]['time_in'] ?? '';
                    $break_out = $dtr_data[$date_str]['break_out'] ?? '';
                    $break_in = $dtr_data[$date_str]['break_in'] ?? '';
                    $time_out = $dtr_data[$date_str]['time_out'] ?? '';

                    // Initialize undertime
                    $undertime_hours = 0;
                    $undertime_minutes = 0;
                    $row_class = '';

                    // Check if employee has a leave on this date
                    $has_leave = isset($dtr_data[$date_str]) && !empty($dtr_data[$date_str]['leave_type_id']);
                    $leave_code = $has_leave ? $dtr_data[$date_str]['LeaveCode'] : null;
                    $is_paid_leave = $has_leave ? $dtr_data[$date_str]['is_paid'] : null;

                    // Check if it's a holiday
                    if (is_holiday($holidays, $date_str)) {
                        $time_in = 'HOLIDAY';
                        $break_out = '';
                        $break_in = '';
                        $time_out = '';
                        $undertime_hours = 0;
                        $undertime_minutes = 0;
                        $row_class = 'class="holiday-row"';
                    }
                    // Check if employee has a leave
                    elseif ($has_leave) {
                        $time_in = $leave_code; // Display leave code (e.g., "VL", "SL")
                        $break_out = '';
                        $break_in = '';
                        $time_out = '';
                        
                        // If unpaid leave, add 8 hours undertime
                        if ($is_paid_leave == 0) {
                            $undertime_hours = 8;
                            $undertime_minutes = 0;
                            $row_class = 'class="leave-unpaid-row"';
                        } else {
                            $undertime_hours = 0;
                            $undertime_minutes = 0;
                            $row_class = 'class="leave-paid-row"';
                        }
                    }
                    // Check if it's a work suspension
                    elseif (is_work_suspension($suspensions, $date_str)) {
                        $suspension = $suspensions[$date_str];
                        
                        if ($suspension['is_full_day'] == 1) {
                            // Full day suspension
                            $time_in = 'SUSPENSION';
                            $break_out = '';
                            $break_in = '';
                            $time_out = '';
                            $undertime_hours = 0;
                            $undertime_minutes = 0;
                            $row_class = 'class="suspension-row"';
                        } else {
                            // Half day suspension - show actual times if available
                            if (isset($dtr_data[$date_str])) {
                                // Display actual times
                                $row_class = 'class="half-suspension-row"';
                                
                                // Calculate undertime normally for half-day suspension
                                $regular_undertime_hours = 0;
                                $regular_undertime_minutes = 0;
                                $late_hours = 0;
                                $late_minutes = 0;
                                
                                // Get regular undertime
                                if (!empty($dtr_data[$date_str]['undertime_time'])) {
                                    list($regular_undertime_hours, $regular_undertime_minutes) = parse_time_to_hours_minutes($dtr_data[$date_str]['undertime_time']);
                                }
                                
                                // Get late time
                                if (!empty($dtr_data[$date_str]['late_time'])) {
                                    list($late_hours, $late_minutes) = parse_time_to_hours_minutes($dtr_data[$date_str]['late_time']);
                                }
                                
                                // Combine undertime and late time
                                $undertime_hours = $regular_undertime_hours + $late_hours;
                                $undertime_minutes = $regular_undertime_minutes + $late_minutes;
                                
                                // Handle minute overflow
                                if ($undertime_minutes >= 60) {
                                    $extra_hours = floor($undertime_minutes / 60);
                                    $undertime_hours += $extra_hours;
                                    $undertime_minutes = $undertime_minutes % 60;
                                }
                            } else {
                                // No DTR record for half-day suspension
                                $time_in = 'HALF SUSP';
                                $break_out = '';
                                $break_in = '';
                                $time_out = '';
                                $undertime_hours = 0;
                                $undertime_minutes = 0;
                                $row_class = 'class="half-suspension-row"';
                            }
                        }
                    } else {
                        // Check if absent (including missing log cases and add 8 hours undertime if so)
                        list($absence_hours, $absence_minutes) = calculate_absence_undertime($conn, $employee['employee_id'], $date_str, $dtr_data, $holidays, $suspensions);
                        
                        if ($absence_hours > 0) {
                            // Employee is absent (either no record, missing log, or empty time in/out)
                            $undertime_hours = $absence_hours;
                            $undertime_minutes = $absence_minutes;
                            
                            // Check if it's specifically due to missing log
                            if (isset($dtr_data[$date_str]) && $dtr_data[$date_str]['has_missing_log'] == 1) {
                                // Display actual times available but mark as missing log
                                $row_class = 'class="missing-log-row"';
                                // Keep the actual time_in, break_out, break_in, time_out values
                                // They will show whatever times are available
                            } else {
                                // Completely absent - no record
                                $time_in = 'ABSENT';
                                $break_out = '';
                                $break_in = '';
                                $time_out = '';
                                $row_class = 'class="absent-row"';
                            }
                        } else {
                            // Employee is present, calculate combined undertime (undertime + late)
                            $regular_undertime_hours = 0;
                            $regular_undertime_minutes = 0;
                            $late_hours = 0;
                            $late_minutes = 0;
                            
                            // Get regular undertime
                            if (!empty($dtr_data[$date_str]['undertime_time'])) {
                                list($regular_undertime_hours, $regular_undertime_minutes) = parse_time_to_hours_minutes($dtr_data[$date_str]['undertime_time']);
                            }
                            
                            // Get late time
                            if (!empty($dtr_data[$date_str]['late_time'])) {
                                list($late_hours, $late_minutes) = parse_time_to_hours_minutes($dtr_data[$date_str]['late_time']);
                            }
                            
                            // Combine undertime and late time
                            $undertime_hours = $regular_undertime_hours + $late_hours;
                            $undertime_minutes = $regular_undertime_minutes + $late_minutes;
                            
                            // Handle minute overflow
                            if ($undertime_minutes >= 60) {
                                $extra_hours = floor($undertime_minutes / 60);
                                $undertime_hours += $extra_hours;
                                $undertime_minutes = $undertime_minutes % 60;
                            }
                        }
                    }

                    // Add to totals (don't add holiday, full-day suspension, or paid leave undertime to totals)
                    if (!is_holiday($holidays, $date_str) && 
                        !(is_work_suspension($suspensions, $date_str) && $suspensions[$date_str]['is_full_day'] == 1) &&
                        !($has_leave && $is_paid_leave == 1)) {
                        $total_undertime_hours += $undertime_hours;
                        $total_undertime_minutes += $undertime_minutes;
                    }
                    
                    echo "<tr {$row_class}>
                            <td>{$day_counter}</td>
                            <td>{$time_in}</td>
                            <td>{$break_out}</td>
                            <td>{$break_in}</td>
                            <td>{$time_out}</td>
                            <td>{$undertime_hours}</td>
                            <td>{$undertime_minutes}</td>
                          </tr>";
                    $day_counter++;
                }

                // Convert excess minutes to hours in totals
                if ($total_undertime_minutes >= 60) {
                    $extra_hours = floor($total_undertime_minutes / 60);
                    $total_undertime_hours += $extra_hours;
                    $total_undertime_minutes %= 60;
                }

                // Fill remaining days if less than 31
                while ($day_counter <= 31) {
                    echo "<tr><td>{$day_counter}</td><td colspan='6'>&nbsp;</td></tr>";
                    $day_counter++;
                }
                ?>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;"><strong>TOTAL:</strong></td>
                    <td><strong><?= $total_undertime_hours; ?></strong></td>
                    <td><strong><?= $total_undertime_minutes; ?></strong></td>
                </tr>
            </tbody>
        </table>
        <div class="certification" >
            <p><strong>I CERTIFY</strong> on my honor that the above is a true and correct report of the hours of work performed, recorded which was made daily at the time of arrival in and departure from office.</p>
            <div class="signature-section" style="margin-top: 20px;">
                <div class="signature-line-bold"></div><br>
                <strong>Employee Signature</strong>
            </div>
          
            <div class="signature-section" style="margin-top: 10px;">
                <div class="signature-line-bold"></div><br>
                <strong>In-Charge</strong>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Time Records</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Times New Roman', serif;
        font-size: 15px;
        background: #fff;
        color: #000;
    }

    .page {
        display: flex;
        justify-content: space-around;
        gap: 2mm;
        width: 240mm;
        height: 270mm;
        margin: 0;
        padding: 0;
    }

    .form-container {
        width: 45%;
        height: 100%;
        border: 3px solid #000;
        padding: 3mm 6mm; /* Reduced top padding */
        display: flex;
        flex-direction: column;
        
    }

    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2mm; /* Reduced margin */
    }

    .form-logo img {
        width: 50px; /* Slightly smaller logo */
        height: auto;
    }

    .header-center {
        text-align: center;
        flex: 1;
        margin-left: -30px; /* Reduced negative margin */
    }

    .form-number {
        text-align: right;
        margin-bottom: 1mm; /* Reduced margin */
        font-size: 12px; /* Smaller font */
    }

    .form-title {
        font-weight: bold;
        letter-spacing: 1px; /* Reduced letter spacing */
        font-size: 20px; /* Slightly smaller */
        margin-bottom: 1mm; /* Small margin */
    }

    .employee-name {
        font-weight: bold;
        margin-top: 1mm; /* Reduced margin */
        font-size: 12px; /* Slightly smaller */
        letter-spacing: 0.5px; /* Reduced letter spacing */
    }

    .name-line {
        border-bottom: 1px solid #000;
        width: 140px; /* Slightly narrower */
        margin: 2px auto; /* Reduced margin */
    }

    .name-label {
        margin-top: 1px; /* Reduced margin */
        font-size: 12px;
    }

    .employee-info {
        margin-bottom: 3mm; /* Reduced margin */
        font-size: 12px; /* Smaller font */
        line-height: 1.1; /* Tighter line height */
    }

    .info-row {
        margin-bottom: 1mm; /* Reduced margin */
    }

    .underline {
        border-bottom: 1px solid #000;
        display: inline-block;
        min-width: 60px; /* Narrower underline */
        margin-left: 3px; /* Reduced margin */
        text-align: center;
    }

    .time-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px; /* Slightly smaller font */
        margin-top: 1mm; /* Reduced margin - IMPORTANT: This was causing large gap */
    }

    .time-table th,
    .time-table td {
        border: 1px solid #000;
        padding: 1px 2px; /* Reduced padding */
        text-align: center;
        vertical-align: middle;
        height: 5mm; /* Fixed height for cells */
    }

    .time-table th {
        background-color: #f8f8f8;
        font-weight: bold;
        font-size: 11px; /* Smaller font for headers */
        padding: 1px; /* Minimal padding */
    }

    /* Adjust rowspan cells */
    .time-table th[rowspan="3"] {
        padding: 0;
        vertical-align: middle;
    }

    .total-row {
        background-color: #f0f0f0;
        font-weight: bold;
    }

    .absent-row {
        background-color: #ffffff;
        color: #000000;
    }

    .missing-log-row {
        background-color: #ffff99;
        color: #000000;
        font-weight: bold;
    }

    .holiday-row {
        background-color: #ff9999;
        color: #000000;
        font-weight: bold;
    }

    .suspension-row {
        background-color: #e6ccff;
        color: #6600cc;
        font-weight: bold;
    }

    .half-suspension-row {
        background-color: #f2e6ff;
        color: #8833cc;
    }

    /* New styles for leave rows */
    .leave-paid-row {
        background-color: #90EE90;
        color: #000000;
        font-weight: bold;
    }

    .leave-unpaid-row {
        background-color: #90EE90;
        color: #000000;
        font-weight: bold;
    }

    .certification {
        margin-top: -0%; /* Reduced margin */
        line-height: 1.2; /* Tighter line height */
        font-size: 12px; /* Smaller font */
        flex-grow: 1; /* Allow certification to fill space */
        display: flex;
        flex-direction: column;
      
    }

    .certification p {
        margin-top: 2mm; /* Reduced margin */
    }

    .signature-section {
        text-align: center;
        margin-top: 3mm; /* Reduced margin */
    }

    .signature-line-bold {
        display: inline-block;
        width: 150px; /* Narrower signature line */
        border-bottom: 1px solid #000;
        margin-bottom: 1px; /* Reduced margin */
    }

    @media print {
        body {
            margin: 0;
            padding: 0;
        }

        .page {
            padding: 0;
            margin: 0;
            height: 270mm;
        }

        .page-break {
            page-break-after: always;
        }

        .form-container {
            padding: 2mm 4mm; /* Even less padding for print */
        }

        /* Force background colors to print */
        .absent-row {
            background-color: #ffffff !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .holiday-row {
            background-color: #ff9999 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .missing-log-row {
            background-color: #ffff99 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .suspension-row {
            background-color: #e6ccff !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .half-suspension-row {
            background-color: #f2e6ff !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .leave-paid-row {
            background-color: #90EE90 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }

        .leave-unpaid-row {
            background-color: #90EE90 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
    }

    .page-break {
        page-break-after: always;
    }
</style>
</head>
<body onload="window.print()">
    <?php
    foreach ($employee_ids as $employee_id) {
        $employee = get_employee_details($conn, $employee_id);
        if (!$employee) {
            continue; // Skip if employee not found
        }

        // Generate forms for each month in the date range
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($start <= $end) {
            $month_start = $start->format('Y-m-01');
            $month_end = $start->format('Y-m-t');
            
            // Get holidays for this month
            $holidays = get_holidays_in_range($conn, $month_start, $month_end);
            
            // Get work suspensions for this month
            $suspensions = get_work_suspensions_in_range($conn, $month_start, $month_end);
            
            $dtr_data = fetch_dtr_data($conn, $employee_id, $month_start, $month_end);
            echo '<div class="page-break">';
            echo '<div class="page">';
            render_dtr_form($conn, $employee, $month_start, $dtr_data, $holidays, $suspensions);
            render_dtr_form($conn, $employee, $month_start, $dtr_data, $holidays, $suspensions);
            echo '</div>';
            echo '</div>';

            // Move to next month
            $start->modify('first day of next month');
        }
    }
    ?>
</body>
</html>