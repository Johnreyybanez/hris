<?php
ob_start();
session_start();
include 'connection.php';

$employee_id = $_GET['employee_id'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

if (empty($from_date) || empty($to_date)) die("Date range required.");

$query = "
    SELECT
        d.employee_id,
        CONCAT(e.last_name, ', ', e.first_name, ' ', IFNULL(e.middle_name,'')) AS employee_name,
        dept.name AS department_name,
        d.date,
        TIME_FORMAT(d.time_in, '%H:%i')  AS time_in,
        TIME_FORMAT(d.time_out, '%H:%i') AS time_out,
        d.time_in as raw_time_in,
        d.time_out as raw_time_out,
        dt.name AS day_type,
        lt.name AS leave_type,
        e.shift_id
    FROM EmployeeDTR d
    JOIN Employees e ON d.employee_id = e.employee_id
    LEFT JOIN departments dept ON e.department_id = dept.department_id
    LEFT JOIN DayTypes dt ON d.day_type_id = dt.day_type_id
    LEFT JOIN LeaveTypes lt ON d.leave_type_id = lt.leave_type_id
    WHERE d.date BETWEEN ? AND ?
    AND e.status = 'active'
";
$params = [$from_date, $to_date];
$types  = "ss";

if (!empty($employee_id)) {
    $query .= " AND e.employee_id = ?";
    $params[] = $employee_id;
    $types .= "s";
}
$query .= " ORDER BY e.employee_id, d.date";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$employees = [];
$employee_shifts = [];

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['employee_id'];
    if (!isset($employees[$id])) {
        $employees[$id] = [
            'name' => $row['employee_name'], 
            'department' => $row['department_name'] ?? 'N/A',
            'shift_id' => $row['shift_id'],
            'data' => []
        ];
        $employee_shifts[$id] = $row['shift_id'];
    }
    $employees[$id]['data'][$row['date']] = [
        'in' => $row['time_in'], 
        'out' => $row['time_out'],
        'raw_in' => $row['raw_time_in'],
        'raw_out' => $row['raw_time_out'],
        'day_type' => $row['day_type'],
        'leave_type' => $row['leave_type']
    ];
}

mysqli_stmt_close($stmt);

// Fetch holidays from holidaycalendar
$holidays = [];
$holiday_query = "SELECT date FROM holidaycalendar WHERE date BETWEEN ? AND ?";
$holiday_stmt = mysqli_prepare($conn, $holiday_query);
mysqli_stmt_bind_param($holiday_stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($holiday_stmt);
$holiday_result = mysqli_stmt_get_result($holiday_stmt);

while ($row = mysqli_fetch_assoc($holiday_result)) {
    $holidays[] = $row['date'];
}
mysqli_stmt_close($holiday_stmt);

// Fetch shift schedules for all employees
$shift_schedules = [];
if (!empty($employee_shifts)) {
    $shift_ids = array_unique(array_values($employee_shifts));
    $shift_ids_str = implode(',', array_map('intval', $shift_ids));
    
    $shift_query = "SELECT shift_id, is_monday, is_tuesday, is_wednesday, is_thursday, is_friday, is_saturday, is_sunday 
                    FROM shiftdays 
                    WHERE shift_id IN ($shift_ids_str)";
    $shift_result = mysqli_query($conn, $shift_query);
    
    while ($row = mysqli_fetch_assoc($shift_result)) {
        $shift_schedules[$row['shift_id']] = [
            1 => $row['is_monday'],    // Monday
            2 => $row['is_tuesday'],   // Tuesday
            3 => $row['is_wednesday'], // Wednesday
            4 => $row['is_thursday'],  // Thursday
            5 => $row['is_friday'],    // Friday
            6 => $row['is_saturday'],  // Saturday
            7 => $row['is_sunday']     // Sunday
        ];
    }
}

// Function to check if date is a holiday
function isHoliday($date, $holidays) {
    return in_array($date, $holidays);
}

// Function to check if date is a rest day for specific employee
function isRestDay($date, $shift_id, $shift_schedules) {
    if (!isset($shift_schedules[$shift_id])) {
        return false;
    }
    
    $day_of_week = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
    
    // If is_[day] = 1, it's a rest day (day off)
    // If is_[day] = 0, it's a work day
    return $shift_schedules[$shift_id][$day_of_week] == 1;
}

// Function to determine status for a date
function getDateStatus($dateData, $date, $shift_id, $shift_schedules, $holidays) {
    // If there's a leave type, show LEAVE
    if (isset($dateData['leave_type']) && !empty($dateData['leave_type'])) {
        return 'LEAVE';
    }
    
    // Check if it's a company holiday (PRIORITY)
    if (isHoliday($date, $holidays)) {
        // If they have attendance on a holiday, don't show HOLIDAY (for holiday pay/overtime)
        if (!empty($dateData['raw_in']) || !empty($dateData['raw_out'])) {
            return null; // Has attendance on holiday
        }
        return 'HOLIDAY';
    }
    
    // If day type indicates holiday (backup check)
    if (isset($dateData['day_type'])) {
        $dayType = strtolower($dateData['day_type']);
        if (strpos($dayType, 'holiday') !== false) {
            if (!empty($dateData['raw_in']) || !empty($dateData['raw_out'])) {
                return null; // Has attendance on holiday
            }
            return 'HOLIDAY';
        }
    }
    
    // Check if it's the employee's scheduled rest day
    if (isRestDay($date, $shift_id, $shift_schedules)) {
        // If they have attendance on their rest day, don't show DAY OFF
        if (!empty($dateData['raw_in']) || !empty($dateData['raw_out'])) {
            return null; // Has attendance on rest day
        }
        return 'DAY OFF';
    }
    
    // If no time in and time out on a work day, it's ABSENT
    if (empty($dateData['raw_in']) && empty($dateData['raw_out'])) {
        return 'ABSENT';
    }
    
    return null; // Has attendance
}

// Function to calculate hours worked (with 1 hour lunch break deduction)
function calculateHours($time_in, $time_out) {
    if (empty($time_in) || empty($time_out)) return 0;
    
    $in = strtotime($time_in);
    $out = strtotime($time_out);
    
    if ($out < $in) {
        $out += 86400; // Add 24 hours if time_out is next day
    }
    
    $total_hours = ($out - $in) / 3600;
    
    // Deduct 1 hour lunch break if worked more than 4 hours
    if ($total_hours > 4) {
        $total_hours -= 1;
    }
    
    return round($total_hours, 2);
}

// Function to calculate late (assuming 8:00 AM is standard time)
function calculateLate($time_in) {
    if (empty($time_in)) return 0;
    
    $standard = strtotime('08:00:00');
    $actual = strtotime($time_in);
    
    if ($actual <= $standard) return 0;
    
    $minutes = ($actual - $standard) / 60;
    return round($minutes);
}

// Function to calculate undertime (assuming 8 hours is standard)
function calculateUndertime($hours_worked) {
    $standard = 8;
    if ($hours_worked >= $standard) return 0;
    
    return round($standard - $hours_worked, 2);
}

// Function to calculate overtime (hours beyond 8)
function calculateOvertime($hours_worked) {
    $standard = 8;
    if ($hours_worked <= $standard) return 0;
    
    return round($hours_worked - $standard, 2);
}

// Build full date array
$start = new DateTime($from_date);
$end   = new DateTime($to_date);
$interval = new DateInterval('P1D');
$datePeriod = new DatePeriod($start, $interval, $end->modify('+1 day'));

$all_dates = [];
foreach ($datePeriod as $dt) {
    $all_dates[] = [
        'ymd' => $dt->format('Y-m-d'),
        'display' => $dt->format('m.d')
    ];
}

$max_columns_per_table = 15; // Columns per table in portrait mode
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="Hris.png" type="image/x-icon">
    <title>DTR Print</title>
    <style>
body { 
    font-family: "Times New Roman", serif;
    padding: 0; 
    margin: 0;
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-bottom: 2px; 
    font-size: 11px; 
    page-break-inside: avoid; 
    table-layout: fixed;
    border-spacing: 0;
}
td, th { 
    border: 1px solid #000; 
    padding: 2px 2px; 
    text-align: center; 
    vertical-align: middle; 
    box-sizing: border-box;
    line-height: 1.1;
}
.header td, .header th { 
    background:#f0f0f0; 
    font-weight: bold; 
}
.in  { 
    border-bottom: 1px solid #000; 
    padding-bottom: 1px; 
}
.out { 
    padding-top: 1px; 
}
.title { 
    font-size:20px; 
    font-weight: bold; 
    margin: 20px 0 5px 0;
    text-align: center;
    text-transform: uppercase;
}
.subtitle {
    font-size: 11px;
    margin: 0px 0 10px 0;
    text-align: center;
}
.employee-group {
    margin-bottom: 10px;
    page-break-inside: avoid;
}
.empty-cell {
    background:#f0f0f0;
}
.status-text {
    font-size: 9px;
    font-weight: bold;
    color: #666;
}
.absent-text {
    color: #d9534f;
}
.leave-text {
    color: #5bc0de;
}
.dayoff-text {
    color: #999;
}
.holiday-text {
    color: #f0ad4e;
}
@media print {
    body { 
        margin: 0 !important;
        padding: 0;
    }
    .no-print { 
        display: none; 
    }
    table { 
        page-break-inside: avoid; 
        page-break-after: auto; 
        margin-bottom: 3px; 
        table-layout: fixed;
        font-size: 11px;
    }
    td, th {
        font-size: 11px; 
        padding: 2px 1px; 
        box-sizing: border-box;
        line-height: 1;
    }
    .employee-group {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    .title {
        font-size: 25px;
        margin: 0px 0 0px 0;
    }
    .subtitle {
        font-size: 12px;
        margin: 0 0 10px 0;
    }
    .status-text {
        font-size: 9px;
    }
    @page {
        margin: 55px 40px 0px 10px;
        size: 8.5in 13in;
    }
}
</style>
</head>
<body>

<button class="no-print" onclick="window.print()" style="position:fixed; top:10px; right:10px; padding:10px 20px; background:#28a745; color:#fff; border:none; cursor:pointer; font-size:16px; z-index:1000; border-radius:5px;">
    🖨️ Print
</button>

<div class="title">DAILY TIME RECORD</div>
<div class="subtitle">Period: <?= htmlspecialchars(date('F d, Y', strtotime($from_date))) ?> to <?= htmlspecialchars(date('F d, Y', strtotime($to_date))) ?></div>

<?php if (empty($employees)): ?>
    <div style="text-align:center; padding:50px; font-size:18px; color:#999;">
        No DTR records found for the selected period.
    </div>
<?php else: ?>
    <?php foreach ($employees as $id => $emp): ?>
        <?php 
        $chunks = array_chunk($all_dates, $max_columns_per_table);
        $emp_shift_id = $emp['shift_id'];
        ?>

        <div class="employee-group">
            <?php foreach ($chunks as $chunk_index => $date_chunk): ?>
                <table border="1" cellspacing="0" cellpadding="2">
                    <!-- Header Row -->
                    <tr class="header">
                        <td style="width: 50px;">Emp. ID: <?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></td>
                        <td colspan="<?= count($date_chunk) - 1 ?>">Name: <?= htmlspecialchars($emp['name']) ?></td>
                        <td>Dept: <?= htmlspecialchars($emp['department']) ?></td>
                        <td style="width: 80px;">All Totals</td>
                    </tr>

                    <!-- Date Row -->
                    <tr class="header">
                        <td>Date</td>
                        <?php foreach ($date_chunk as $d): ?>
                            <td><?= $d['display'] ?></td>
                        <?php endforeach; ?>
                        <td>No. Abs: 
                            <?php 
                            // Pre-calculate absent count for this chunk
                            $chunk_absent_count = 0;
                            foreach ($date_chunk as $d) {
                                $dateData = $emp['data'][$d['ymd']] ?? null;
                                $status = null;
                                
                                if ($dateData) {
                                    $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                                } else {
                                    // No DTR record at all - check if it's a holiday, rest day, or regular work day
                                    if (isHoliday($d['ymd'], $holidays)) {
                                        $status = 'HOLIDAY';
                                    } elseif (isRestDay($d['ymd'], $emp_shift_id, $shift_schedules)) {
                                        $status = 'DAY OFF';
                                    } else {
                                        $status = 'ABSENT';
                                    }
                                }
                                
                                // Count absences
                                if ($status === 'ABSENT') {
                                    $chunk_absent_count++;
                                }
                            }
                            echo $chunk_absent_count;
                            ?>
                        </td>
                    </tr>

                    <!-- TIME IN Row -->
                    <tr>
                        <td>Time In</td>
                        <?php 
                        foreach ($date_chunk as $d):
                            $dateData = $emp['data'][$d['ymd']] ?? null;
                            $status = null;
                            
                            if ($dateData) {
                                $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                            } else {
                                // No DTR record at all - check if it's a holiday, rest day, or regular work day
                                if (isHoliday($d['ymd'], $holidays)) {
                                    $status = 'HOLIDAY';
                                } elseif (isRestDay($d['ymd'], $emp_shift_id, $shift_schedules)) {
                                    $status = 'DAY OFF';
                                } else {
                                    $status = 'ABSENT';
                                }
                            }
                            
                            if ($status === 'HOLIDAY') {
                                echo '<td class="in status-text holiday-text">HOLIDAY</td>';
                            } elseif ($status === 'DAY OFF') {
                                echo '<td class="in status-text dayoff-text">DAY OFF</td>';
                            } elseif ($status === 'LEAVE') {
                                echo '<td class="in status-text leave-text">LEAVE</td>';
                            } elseif ($status === 'ABSENT') {
                                echo '<td class="in status-text absent-text">ABSENT</td>';
                            } else {
                                $in = $dateData['in'] ?? '';
                                echo '<td class="in">' . $in . '</td>';
                            }
                        ?>
                        <?php endforeach; ?>
                        <td rowspan="2" style="vertical-align: middle; font-weight: bold; font-size: 14px;">
                            
                        </td>
                    </tr>

                    <!-- TIME OUT Row -->
                    <tr>
                        <td>Time Out</td>
                        <?php foreach ($date_chunk as $d):
                            $dateData = $emp['data'][$d['ymd']] ?? null;
                            $status = null;
                            
                            if ($dateData) {
                                $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                            } else {
                                if (isHoliday($d['ymd'], $holidays)) {
                                    $status = 'HOLIDAY';
                                } elseif (isRestDay($d['ymd'], $emp_shift_id, $shift_schedules)) {
                                    $status = 'DAY OFF';
                                } else {
                                    $status = 'ABSENT';
                                }
                            }
                            
                            if ($status) {
                                echo '<td class="out"></td>';
                            } else {
                                $out = $dateData['out'] ?? '';
                                echo '<td class="out">' . $out . '</td>';
                            }
                        ?>
                        <?php endforeach; ?>
                    </tr>

                    <!-- DAILY TOTAL HOURS Row -->
                    <tr>
                        <td>T.Hrs</td>
                        <?php 
                        $chunk_total = 0;
                        foreach ($date_chunk as $d):
                            $hours = 0;
                            $dateData = $emp['data'][$d['ymd']] ?? null;
                            
                            if ($dateData) {
                                $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                                if (!$status) {
                                    $hours = calculateHours($dateData['raw_in'], $dateData['raw_out']);
                                    $chunk_total += $hours;
                                }
                            }
                        ?>
                            <td><?= $hours > 0 ? number_format($hours, 2) . 'h' : '' ?></td>
                        <?php endforeach; ?>
                        <td style="font-weight: bold;">
                            <?= number_format($chunk_total, 2) ?>h
                        </td>
                    </tr>

                    <!-- LATE / UNDERTIME Row -->
                    <tr>
                        <td>Late / UT</td>
                        <?php foreach ($date_chunk as $d):
                            $late = 0;
                            $undertime = 0;
                            $dateData = $emp['data'][$d['ymd']] ?? null;
                            
                            if ($dateData) {
                                $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                                if (!$status) {
                                    $late = calculateLate($dateData['raw_in']);
                                    $hours = calculateHours($dateData['raw_in'], $dateData['raw_out']);
                                    $undertime = calculateUndertime($hours);
                                }
                            }
                            
                            $display = '';
                            if ($late > 0 && $undertime > 0) {
                                $display = $late . 'm / ' . number_format($undertime, 2) . 'h';
                            } elseif ($late > 0) {
                                $display = $late . 'm';
                            } elseif ($undertime > 0) {
                                $display = number_format($undertime, 2) . 'h';
                            }
                        ?>
                            <td><?= $display ?></td>
                        <?php endforeach; ?>
                        <td></td>
                    </tr>

                    <!-- OVERTIME Row -->
                    <tr>
                        <td>OT</td>
                        <?php 
                        $chunk_total_ot = 0;
                        foreach ($date_chunk as $d):
                            $ot = 0;
                            $dateData = $emp['data'][$d['ymd']] ?? null;
                            
                            if ($dateData) {
                                $status = getDateStatus($dateData, $d['ymd'], $emp_shift_id, $shift_schedules, $holidays);
                                if (!$status) {
                                    $hours = calculateHours($dateData['raw_in'], $dateData['raw_out']);
                                    $ot = calculateOvertime($hours);
                                    $chunk_total_ot += $ot;
                                }
                            }
                        ?>
                            <td><?= $ot > 0 ? number_format($ot, 2) . 'h' : '' ?></td>
                        <?php endforeach; ?>
                        <td style="font-weight: bold;">
                            <?= number_format($chunk_total_ot, 2) ?>h
                        </td>
                    </tr>
                </table>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="no-print" style="text-align:center; margin-top:30px; padding:20px; background:#f0f0f0; border-radius:5px;">
    <p style="margin:0; color:#666;">
        <strong>Instructions:</strong> Click the Print button above or press Ctrl+P (Cmd+P on Mac) to print this document.
    </p>
</div>

</body>
</html>