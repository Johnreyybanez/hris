<?php
session_start();
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: report.php');
    exit;
}

$employee_id = $_POST['employee_id'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];

// Fetch employee details
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

if (!$employee) {
    die("❌ No employee found for ID: $employee_id");
}

// Function to fetch DTR data for a given month
function fetch_dtr_data($conn, $employee_id, $month_start, $month_end) {
    $sql = "
        SELECT 
            date, 
            TIME_FORMAT(time_in, '%h:%i %p') AS time_in,
            TIME_FORMAT(break_out, '%h:%i %p') AS break_out,
            TIME_FORMAT(break_in, '%h:%i %p') AS break_in,
            TIME_FORMAT(time_out, '%h:%i %p') AS time_out,
            undertime_time
        FROM 
            employeedtr
        WHERE 
            employee_id = ?
            AND date BETWEEN ? AND ?
        ORDER BY date ASC
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

// Current month range
$current_month_start = date('Y-m-01', strtotime($start_date));
$current_month_end = date('Y-m-t', strtotime($start_date));
$current_dtr = fetch_dtr_data($conn, $employee_id, $current_month_start, $current_month_end);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Time Record - Two Copies</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 12px; background: #fff; color: #000; }
        .page { display: flex; justify-content: space-between; width: 290mm; height: 290mm; padding: 10mm; }
        .form-container { width: 45%; border: 2px solid #000; padding: 8mm; }
        .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .form-logo img { width: 60px; height: auto; }
        .header-center { text-align: center; flex: 1; }
        .form-number { text-align: right; font-size: 10px; font-weight: bold; margin-bottom: 5px; margin-top: -20px; }
        .form-title { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .employee-name { font-size: 12px; font-weight: bold; margin-top: 5px; }
        .name-line { border-bottom: 1px solid #000; width: 150px; margin: 5px auto; }
        .name-label { font-size: 10px; margin-top: 2px; }
        .employee-info { margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .info-item { flex: 1; }
        .underline { border-bottom: 1px solid #000; display: inline-block; min-width: 50px; margin-left: 5px; }
        .time-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .time-table th, .time-table td { border: 1px solid #000; padding: 2px; text-align: center; }
        .time-table th { background-color: #f8f8f8; font-weight: bold; }
        .certification { margin-top: 10px; font-size: 11px; line-height: 1.4; }
        .signature-section { margin-top: 30px; text-align: center; }
        .signature-line-bold { display: inline-block; width: 200px; border-bottom: 2px solid #000; margin-bottom: 5px; }
        @media print { body { margin: 0; } .page { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="page">
        <?php
        function render_form($employee, $month_start, $dtr_data) {
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
                        $period = new DatePeriod(new DateTime($month_start), new DateInterval('P1D'), (new DateTime($month_start))->modify('last day of this month')->modify('+1 day'));
                        $day_counter = 1;
                        $total_hours = 0;
                        $total_minutes = 0;

                        foreach ($period as $date) {
                            $date_str = $date->format('Y-m-d');
                            $time_in = $dtr_data[$date_str]['time_in'] ?? '';
                            $break_out = $dtr_data[$date_str]['break_out'] ?? '';
                            $break_in = $dtr_data[$date_str]['break_in'] ?? '';
                            $time_out = $dtr_data[$date_str]['time_out'] ?? '';

                            $undertime_hours = 0;
                            $undertime_minutes = 0;
                            if (!empty($dtr_data[$date_str]['undertime_time'])) {
                                list($h, $m, $s) = explode(':', $dtr_data[$date_str]['undertime_time']);
                                $undertime_hours = (int)$h;
                                $undertime_minutes = (int)$m;

                                $total_hours += $undertime_hours;
                                $total_minutes += $undertime_minutes;
                            }

                            echo "<tr>
                                    <td>{$day_counter}</td>
                                    <td>{$time_in}</td><td>{$break_out}</td><td>{$break_in}</td><td>{$time_out}</td>
                                    <td>{$undertime_hours}</td><td>{$undertime_minutes}</td>
                                  </tr>";
                            $day_counter++;
                        }

                        if ($total_minutes >= 60) {
                            $extra_hours = floor($total_minutes / 60);
                            $total_hours += $extra_hours;
                            $total_minutes %= 60;
                        }

                        while ($day_counter <= 31) {
                            echo "<tr><td>{$day_counter}</td><td colspan='6'>&nbsp;</td></tr>";
                            $day_counter++;
                        }
                        ?>
                        <tr class="total-row">
                            <td colspan="5" style="text-align: right;">TOTAL:</td>
                            <td><?= $total_hours; ?></td>
                            <td><?= $total_minutes; ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="certification">
                    <p><strong>I CERTIFY</strong> on my honor that the above is a true and correct report of the hours of work performed, recorded which was made daily at the time of arrival in and departure from office.</p>
                    <div class="signature-section" style="margin-top: 40px;">
                        <div class="signature-line-bold"></div><br>
                        <strong>Verified as to the prescribed office hours</strong>
                    </div>
                    <div class="signature-section" style="margin-top: 50px;">
                        <div class="signature-line-bold"></div><br>
                        <strong>In-Charge</strong>
                    </div>
                </div>
            </div>
            <?php
        }

        render_form($employee, $current_month_start, $current_dtr);
        render_form($employee, $current_month_start, $current_dtr);
        ?>
    </div>
</body>
</html>
