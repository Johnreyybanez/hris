<?php
ob_start();
session_start();
include 'connection.php';

/**
 * Calculate night differential hours (6:00 PM to 3:00 AM) - OVERNIGHT SHIFTS ONLY
 * @param string $timeIn Actual time in (HH:MM format)
 * @param string $timeOut Actual time out (HH:MM format)
 * @param string $scheduleIn Schedule time in (HH:MM format)
 * @param string $scheduleOut Schedule time out (HH:MM format)
 * @return string Night hours in HH:MM format
 */
function calculateNightDifferential($timeIn, $timeOut, $scheduleIn, $scheduleOut)
{
    if (empty($timeIn) || empty($timeOut)) {
        return '00:00';
    }

    // Parse actual times
    list($inHour, $inMin) = explode(':', $timeIn);
    list($outHour, $outMin) = explode(':', $timeOut);

    $inHour = intval($inHour);
    $inMin = intval($inMin);
    $outHour = intval($outHour);
    $outMin = intval($outMin);

    // CRITICAL FIX: Only calculate for overnight shifts (PM to AM)
    // If time in is before noon (AM) and time out is also AM/afternoon, it's a day shift
    $isOvernightShift = ($inHour >= 12 && $outHour < 12);

    if (!$isOvernightShift) {
        return '00:00'; // No night differential for day shifts
    }

    // Night differential period: 6:00 PM (18:00) to 3:00 AM (03:00)
    $nightStart = 18; // 6 PM
    $nightEnd = 3;    // 3 AM

    // Determine if this is an overnight shift based on schedule
    $isOvernight = false;
    if (!empty($scheduleIn) && !empty($scheduleOut)) {
        list($schedInHour) = explode(':', $scheduleIn);
        list($schedOutHour) = explode(':', $scheduleOut);
        $isOvernight = intval($schedOutHour) < intval($schedInHour);
    }

    // If overnight shift and out time is less than in time, add 24 hours to out time
    if ($isOvernight && $outHour < $inHour) {
        $outHour += 24;
    }

    $nightMinutes = 0;
    $currentHour = $inHour;
    $currentMin = $inMin;

    // Iterate through each hour
    while ($currentHour < $outHour || ($currentHour == $outHour && $currentMin < $outMin)) {
        $checkHour = $currentHour % 24; // Handle 24+ hours

        // Check if current hour is within night differential period
        if ($checkHour >= $nightStart || $checkHour < $nightEnd) {
            // Calculate minutes in this night hour
            $startMin = $currentMin;
            $endMin = 60;

            // If this is the last hour, use actual out minutes
            if ($currentHour == $outHour) {
                $endMin = $outMin;
            }

            $nightMinutes += ($endMin - $startMin);
        }

        // Move to next hour
        $currentMin = 0;
        $currentHour++;
    }

    // Convert to HH:MM format
    $hours = floor($nightMinutes / 60);
    $minutes = $nightMinutes % 60;
    return sprintf("%02d:%02d", $hours, $minutes);
}
/* =============================================================================
   MULTI-EMPLOYEE SHIFT ASSIGNMENT HANDLER (MANUAL PROTECTED)
============================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shifts'])) {

    if (empty($_POST['employee_ids'])) {
        $_SESSION['error'] = "No employees selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $employee_ids = $_POST['employee_ids'];
    $shift_id     = intval($_POST['shift_id']);
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];

    /* =====================
       FETCH SHIFT DETAILS
    ===================== */
    $shift_sql = "SELECT * FROM Shifts WHERE shift_id = ?";
    $stmt = mysqli_prepare($conn, $shift_sql);
    mysqli_stmt_bind_param($stmt, "i", $shift_id);
    mysqli_stmt_execute($stmt);
    $shift = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$shift) {
        $_SESSION['error'] = "Invalid shift selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $sched_in  = $shift['time_in'];
    $sched_out = $shift['time_out'];
    $is_flex   = (int)$shift['is_flexible'];

    /* =====================
       LOOP EMPLOYEES
    ===================== */
    foreach ($employee_ids as $emp_id) {

        $current = strtotime($start_date);
        $end     = strtotime($end_date);

        while ($current <= $end) {

            $date = date('Y-m-d', $current);

            /* =====================
               FETCH EXISTING DTR
            ===================== */
            $dtr_sql = "
                SELECT 
                    dtr_id,
                    TIME_FORMAT(time_in, '%H:%i') AS actual_in,
                    TIME_FORMAT(time_out, '%H:%i') AS actual_out,
                    TIME_FORMAT(break_out, '%H:%i') AS break_out,
                    TIME_FORMAT(break_in, '%H:%i') AS break_in
                FROM EmployeeDTR
                WHERE employee_id = ? AND date = ?
            ";

            $stmt = mysqli_prepare($conn, $dtr_sql);
            mysqli_stmt_bind_param($stmt, "is", $emp_id, $date);
            mysqli_stmt_execute($stmt);
            $dtr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            // Skip if no DTR exists
            if (!$dtr) {
                $current = strtotime("+1 day", $current);
                continue;
            }

            /* =====================
               TIME CALCULATIONS
            ===================== */

            // ---- LATE ----
            $late = "00:00:00";
            if ($dtr['actual_in'] && !$is_flex) {
                if (strtotime($dtr['actual_in']) > strtotime($sched_in)) {
                    $late = gmdate(
                        "H:i:s",
                        strtotime($dtr['actual_in']) - strtotime($sched_in)
                    );
                }
            }

            // ---- UNDERTIME ----
            $ut = "00:00:00";
            if ($dtr['actual_out'] && !$is_flex) {
                $actual_out = strtotime($dtr['actual_out']);
                $sched_out_ts = strtotime($sched_out);

                // Overnight shift handling
                if ($sched_out_ts < strtotime($sched_in)) {
                    $sched_out_ts += 86400;
                    if ($actual_out < strtotime($dtr['actual_in'])) {
                        $actual_out += 86400;
                    }
                }

                if ($actual_out < $sched_out_ts) {
                    $ut = gmdate("H:i:s", $sched_out_ts - $actual_out);
                }
            }

            // ---- TOTAL WORK ----
            $total_work = "00:00:00";
            if ($dtr['actual_in'] && $dtr['actual_out']) {
                $in  = strtotime($dtr['actual_in']);
                $out = strtotime($dtr['actual_out']);

                if ($out < $in) {
                    $out += 86400; // overnight
                }

                $seconds = $out - $in;

                if ($dtr['break_out'] && $dtr['break_in']) {
                    $bo = strtotime($dtr['break_out']);
                    $bi = strtotime($dtr['break_in']);
                    if ($bi > $bo) {
                        $seconds -= ($bi - $bo);
                    }
                }

                $total_work = gmdate("H:i:s", max(0, $seconds));
            }

            // ---- NIGHT DIFFERENTIAL ----
            $night = calculateNightDifferential(
                $dtr['actual_in'],
                $dtr['actual_out'],
                $sched_in,
                $sched_out
            );

            /* =====================
               UPDATE DTR (MANUAL LOCK)
            ===================== */
            $update_sql = "
                UPDATE EmployeeDTR
                SET
                    shift_id = ?,
                    late_time = ?,
                    undertime_time = ?,
                    total_work_time = ?,
                    night_time = ?,
                    is_manual = 1,
                    approval_status = 'Pending',
                    remarks = CONCAT(
                        IFNULL(remarks, ''),
                        ' | Shift manually reassigned'
                    ),
                    updated_at = NOW()
                WHERE dtr_id = ?
            ";

            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $stmt,
                "issssi",
                $shift_id,
                $late,
                $ut,
                $total_work,
                $night,
                $dtr['dtr_id']
            );
            mysqli_stmt_execute($stmt);

            $current = strtotime("+1 day", $current);
        }
    }

    $_SESSION['success'] =
        "Shift schedule updated successfully. All affected DTRs are MANUAL & protected.";

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


/* =============================================================================
   DTR FILTER AND DATA RETRIEVAL
============================================================================= */

$employee_id = $_POST['employee_id'] ?? $_GET['employee_id'] ?? '';
$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? '';
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? '';
$display_rows = [];

if (!empty($from_date) && !empty($to_date)) {
    $query = "
        SELECT 
            d.*,
            CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
            TIME_FORMAT(s.time_in, '%H:%i') AS schedule_in,
            TIME_FORMAT(s.time_out, '%H:%i') AS schedule_out,
            TIME_FORMAT(d.time_in, '%H:%i') AS actual_in,
            TIME_FORMAT(d.time_out, '%H:%i') AS actual_out,
            TIME_FORMAT(d.break_out, '%H:%i') AS break_out,
            TIME_FORMAT(d.break_in, '%H:%i') AS break_in
        FROM EmployeeDTR d
        JOIN Employees e ON d.employee_id = e.employee_id
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        WHERE d.date BETWEEN '$from_date' AND '$to_date' 
        AND e.status = 'active'
    ";
    
    if (!empty($employee_id)) {
        $query .= " AND e.employee_id = '$employee_id'";
    }
    
    $query .= " ORDER BY employee_name, d.date DESC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }
}

/* =============================================================================
   FETCH MASTER DATA (EMPLOYEES & SHIFTS)
============================================================================= */

$employees_query = "
    SELECT employee_id, last_name, first_name, middle_name 
    FROM Employees 
    WHERE status = 'active' 
    ORDER BY last_name
";
$employees = mysqli_query($conn, $employees_query);

$shifts_query = "
    SELECT shift_id, shift_name, time_in, time_out 
    FROM Shifts 
    ORDER BY shift_name
";
$shifts = mysqli_query($conn, $shifts_query);

/* =============================================================================
   END OF THE MULTIPLE SHIFTS
============================================================================= */
// Handle UPDATE DTR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dtr'])) {
    $dtr_id = intval($_POST['dtr_id']);
    $shift_id = $_POST['shift_id'] ?? 'NULL';
    $shift_id_value = ($shift_id === '' || $shift_id === 'NULL') ? 'NULL' : intval($shift_id);
    $actual_in = $_POST['actual_in'] ?? null;
    $actual_out = $_POST['actual_out'] ?? null;
    $break_out = $_POST['break_out'] ?? null;
    $break_in = $_POST['break_in'] ?? null;
    $overtime_time = $_POST['overtime_time'] ?? '0.00';
    $night_time = $_POST['night_time'] ?? null;
    $late_time = $_POST['late_time'] ?? '00:00';
    $undertime_time = $_POST['undertime_time'] ?? '00:00';
    $total_work_time = $_POST['total_work_time'] ?? '00:00';
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $leave_type_id = $_POST['leave_type_id'] ?? 'NULL';
    $leave_type_value = ($leave_type_id === '' || $leave_type_id === 'NULL') ? 'NULL' : intval($leave_type_id);

    // Validate time inputs (24-hour format: HH:MM)
    $time_pattern = '/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/';
    if (
        ($actual_in && !preg_match($time_pattern, $actual_in)) ||
        ($actual_out && !preg_match($time_pattern, $actual_out)) ||
        ($break_out && !preg_match($time_pattern, $break_out)) ||
        ($break_in && !preg_match($time_pattern, $break_in)) ||
        ($night_time && !preg_match($time_pattern, $night_time)) ||
        ($late_time && !preg_match($time_pattern, $late_time)) ||
        ($undertime_time && !preg_match($time_pattern, $undertime_time))
    ) {
        $_SESSION['error'] = "Invalid time format. Use HH:MM (24-hour).";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Get schedule information
    $schedule_query = "
        SELECT d.date, d.employee_id, TIME_FORMAT(s.time_in, '%H:%i') as schedule_in, TIME_FORMAT(s.time_out, '%H:%i') as schedule_out, s.is_flexible
        FROM EmployeeDTR d
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        WHERE d.dtr_id = '$dtr_id'
    ";
    $schedule_result = mysqli_query($conn, $schedule_query);
    if (!$schedule_result) {
        die("Error fetching schedule: " . mysqli_error($conn));
    }
    $schedule_data = mysqli_fetch_assoc($schedule_result);
    $record_date = $schedule_data['date'];
    $schedule_in = $schedule_data['schedule_in'];
    $schedule_out = $schedule_data['schedule_out'];
    $is_flexible = $schedule_data['is_flexible'] ?? 0;

    // Convert time inputs to datetime format
    $time_in_datetime = !empty($actual_in) ? "'$record_date $actual_in:00'" : 'NULL';
    $time_out_datetime = !empty($actual_out) ? "'$record_date $actual_out:00'" : 'NULL';
    $break_out_datetime = !empty($break_out) ? "'$record_date $break_out:00'" : 'NULL';
    $break_in_datetime = !empty($break_in) ? "'$record_date $break_in:00'" : 'NULL';

    // AUTO-CALCULATE LATE TIME (use form value unless invalid or flexible shift)
    $late_time_formatted = "'00:00:00'";
    if (!$is_flexible && !empty($actual_in) && !empty($schedule_in)) {
        $actual_time = new DateTime($actual_in);
        $scheduled_time = new DateTime($schedule_in);
        if ($actual_time > $scheduled_time) {
            $interval = $actual_time->diff($scheduled_time);
            $late_time_formatted = "'" . sprintf("%02d:%02d:00", $interval->h, $interval->i) . "'";
        }
    } elseif (preg_match($time_pattern, $late_time)) {
        $late_time_formatted = "'" . $late_time . ":00'";
    }

    // AUTO-CALCULATE UNDERTIME (use form value unless invalid or flexible shift)
    $undertime_time_formatted = "'00:00:00'";
    if (!$is_flexible && !empty($actual_out) && !empty($schedule_out)) {
        $actual_out_time = new DateTime($actual_out);
        $scheduled_out_time = new DateTime($schedule_out);
        $scheduled_in_time = !empty($schedule_in) ? new DateTime($schedule_in) : null;

        // Handle overnight shifts
        $is_overnight = $scheduled_in_time && $scheduled_out_time < $scheduled_in_time;
        if ($is_overnight) {
            $scheduled_out_time->modify('+1 day');
            if ($actual_in && $actual_out_time < new DateTime($actual_in)) {
                $actual_out_time->modify('+1 day');
            }
        } else {
            // Warn about potential early departure
            $time_diff = $scheduled_out_time->diff($actual_out_time);
            if ($time_diff->h > 6 && $actual_out_time < $scheduled_out_time) {
                $_SESSION['warning'] = "Warning: Actual Out ($actual_out) is significantly earlier than Schedule Out ($schedule_out). Please verify.";
            }
        }

        if ($actual_out_time < $scheduled_out_time) {
            $interval = $scheduled_out_time->diff($actual_out_time);
            $undertime_time_formatted = "'" . sprintf("%02d:%02d:00", $interval->h, $interval->i) . "'";
        }
    } elseif (preg_match($time_pattern, $undertime_time)) {
        $undertime_time_formatted = "'" . $undertime_time . ":00'";
    }

    // AUTO-CALCULATE TOTAL WORK TIME
    $total_work_time_formatted = "'00:00:00'";
    if (!empty($actual_in) && !empty($actual_out)) {
        $time_in = new DateTime($actual_in);
        $time_out = new DateTime($actual_out);

        // Handle overnight shifts
        $is_overnight = $scheduled_in_time && $scheduled_out_time && $scheduled_out_time < $scheduled_in_time;
        if ($is_overnight && $time_out < $time_in) {
            $time_out->modify('+1 day');
        }

        $total_work_interval = $time_out->diff($time_in);
        $total_work_seconds = ($total_work_interval->h * 3600) + ($total_work_interval->i * 60);

        // Subtract break time if provided
        if (!empty($break_out) && !empty($break_in) && $break_out !== '00:00' && $break_in !== '00:00') {
            $break_out_time = new DateTime($break_out);
            $break_in_time = new DateTime($break_in);

            if ($break_in_time > $break_out_time) {
                $break_interval = $break_in_time->diff($break_out_time);
                $break_seconds = ($break_interval->h * 3600) + ($break_interval->i * 60);
                $total_work_seconds -= $break_seconds;
            }
        }

        if ($total_work_seconds > 0) {
            $work_hours = floor($total_work_seconds / 3600);
            $work_minutes = floor(($total_work_seconds % 3600) / 60);
            $total_work_time_formatted = "'" . sprintf("%02d:%02d:00", $work_hours, $work_minutes) . "'";
        }
    }

    // Handle overtime as decimal
    $overtime_decimal = 0.00;
    if (!empty($overtime_time)) {
        if (strpos($overtime_time, '.') !== false) {
            $overtime_decimal = floatval($overtime_time);
        } else if (strpos($overtime_time, ':') !== false) {
            $parts = explode(':', $overtime_time);
            $overtime_decimal = floatval($parts[0]) + (floatval($parts[1]) / 60);
        } else {
            $overtime_decimal = floatval($overtime_time);
        }
    }

    // AUTO-CALCULATE NIGHT DIFFERENTIAL (6 PM - 3 AM, OVERNIGHT SHIFTS ONLY)
    $night_time_calculated = calculateNightDifferential(
        $actual_in,
        $actual_out,
        $schedule_in,
        $schedule_out
    );

    // Use calculated value, but allow manual override if provided
    if (!empty($night_time) && $night_time !== '00:00') {
        // Manual override provided
        if (strpos($night_time, '.') !== false) {
            $hours = floor(floatval($night_time));
            $minutes = round((floatval($night_time) - $hours) * 60);
            $night_time_formatted = "'" . sprintf("%02d:%02d:00", $hours, $minutes) . "'";
        } else if (strpos($night_time, ':') !== false) {
            $night_time_formatted = "'" . $night_time . ":00'";
        } else {
            $night_time_formatted = "'00:00:00'";
        }
    } else {
        // Use auto-calculated value
        $night_time_formatted = "'" . $night_time_calculated . ":00'";
    }

    // UPDATE QUERY WITH is_manual = 1
    $update_sql = "
        UPDATE EmployeeDTR SET
            shift_id = $shift_id_value,
            time_in = $time_in_datetime,
            time_out = $time_out_datetime,
            break_out = $break_out_datetime,
            break_in = $break_in_datetime,
            late_time = $late_time_formatted,
            undertime_time = $undertime_time_formatted,
            overtime_time = $overtime_decimal,
            night_time = $night_time_formatted,
            total_work_time = $total_work_time_formatted,
            leave_type_id = $leave_type_value,
            remarks = '$remarks',
            is_manual = 1,
            updated_at = NOW()
        WHERE dtr_id = '$dtr_id'
    ";

    if (mysqli_query($conn, $update_sql)) {
        // Verify only one record was updated
        $affected_rows = mysqli_affected_rows($conn);
        if ($affected_rows === 1) {
            $_SESSION['success'] = "DTR record updated successfully for the selected date only! Late time, undertime, night differential (overnight shifts only), and total work time auto-calculated. Record marked as manually edited and protected from future imports.";
        } elseif ($affected_rows === 0) {
            $_SESSION['warning'] = "No changes were made to the DTR record.";
        } else {
            $_SESSION['warning'] = "Update completed, but $affected_rows record(s) were affected. Please verify.";
        }

        $redirect_params = [
            'filter_dtr' => '1',
            'employee_id' => $_POST['preserve_employee_id'] ?? '',
            'from_date' => $_POST['preserve_from_date'] ?? '',
            'to_date' => $_POST['preserve_to_date'] ?? ''
        ];
        $redirect_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($redirect_params);
        header("Location: $redirect_url");
        exit;
    } else {
        $_SESSION['error'] = "Failed to update DTR: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch only ACTIVE employees
$employees_query = "SELECT employee_id, last_name, middle_name, first_name
                   FROM employees
                   WHERE status = 'active'
                   ORDER BY last_name";
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

// Fetch all shifts
$shifts_query = "SELECT shift_id, shift_name, TIME_FORMAT(time_in, '%H:%i') AS time_in, TIME_FORMAT(break_out, '%H:%i') AS break_out, TIME_FORMAT(break_in, '%H:%i') AS break_in, TIME_FORMAT(time_out, '%H:%i') AS time_out, description, is_flexible, has_break FROM Shifts ORDER BY shift_name";
$shifts = mysqli_query($conn, $shifts_query);
if (!$shifts) {
    die("Error fetching shifts: " . mysqli_error($conn));
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
            d.shift_id,
            d.is_manual,
            CONCAT(e.last_name, ', ', e.first_name, ' ', IFNULL(e.middle_name, '')) AS employee_name,
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
            d.leave_type_id,
            lt.name AS leave_type_name,
            d.remarks AS logs,
            TIME_FORMAT(d.time_in, '%H:%i') AS raw_actual_in,
            TIME_FORMAT(d.time_out, '%H:%i') AS raw_actual_out,
            TIME_FORMAT(d.break_out, '%H:%i') AS raw_break_out,
            TIME_FORMAT(d.break_in, '%H:%i') AS raw_break_in,
            TIME_FORMAT(d.late_time, '%H:%i') AS raw_late_time,
            TIME_FORMAT(d.undertime_time, '%H:%i') AS raw_undertime_time,
            d.overtime_time AS raw_overtime_time,
            TIME_FORMAT(d.night_time, '%H:%i') AS raw_night_time,
            TIME_FORMAT(d.total_work_time, '%H:%i') AS raw_total_work_time,
            d.time_in AS time_in_raw,
            d.time_out AS time_out_raw,
            d.break_out AS break_out_raw,
            d.break_in AS break_in_raw
        FROM EmployeeDTR d
        JOIN Employees e ON d.employee_id = e.employee_id
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
    if (!$result) {
        die("Execute failed: " . mysqli_error($conn));
    }
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

        th:last-child,
        td:last-child {
            display: none !important;
        }

        #dtrTable thead th {
            position: relative !important;
        }
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
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

    #dtrTable th:nth-child(2),
    #dtrTable td:nth-child(2) {
        text-align: left !important;
        padding-left: 12px !important;
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

    .table-responsive {
        max-height: calc(100vh - 350px);
        overflow-y: auto;
        overflow-x: auto;
        border-radius: 8px;
    }

    .alert-info,
    .alert-warning {
        background-color: #e7f3ff;
        border-color: #b8daff;
        color: #004085;
    }

    #editDTRModal .modal-body .form-select,
    #editDTRModal .modal-body .form-control {
        font-size: 14px;
    }

    #editDTRModal .modal-body label {
        font-weight: 500;
        margin-bottom: 5px;
    }

    .employee-shifts-badge {
        display: inline-block;
        background-color: #e3f2fd;
        color: #1976d2;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        margin-left: 5px;
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

    .manual-edit-badge i {
        font-size: 9px;
    }

    /* =============================================================================
   ALERT & BADGE STYLES
============================================================================= */
    .alert-info,
    .alert-warning {
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

    .manual-edit-badge i {
        font-size: 9px;
    }

    /* =============================================================================
   EMPLOYEE SELECTION STYLES
============================================================================= */
    .employee-select-container {
        border: 1px solid #f8f9fa;
        border-radius: 6px;
        padding: 10px;
        max-height: 100px;
        overflow-y: auto;
        background-color: #f8f9fa;
    }

    .employee-checkbox-item {
        padding: 8px 10px;
        margin: 3px 0;
        background: white;
        border-radius: 4px;
        border: 1px solid #0a0a0aff;
        transition: all 0.2s;
    }

    .employee-checkbox-item:hover {
        background: #e3f2fd;
        border-color: #2196f3;
    }

    .employee-checkbox-item input[type="checkbox"] {
        margin-right: 8px;
        cursor: pointer;
    }

    .employee-checkbox-item label {
        margin: 0;
        cursor: pointer;
        user-select: none;
        font-size: 14px;
    }

    .employee-search-box {
        margin-bottom: 10px;
    }

    .bulk-action-btns {
        margin-bottom: 10px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        /* Align buttons to the right */
    }

    .bulk-action-btns button {
        font-size: 12px;
        padding: 4px 10px;
    }

    .selected-count {
        display: inline-block;
        background: #2196f3;
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employee DTR Filter</h5>
                <button type="button" class="btn btn-primary" onclick="toggleShiftForm()" id="toggleShiftBtn">
                    <i class="fas fa-plus-circle"></i> Assign Shifts to Employees
                </button>
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
                                ?>
                                <option value="<?= $emp['employee_id'] ?>" <?= ($employee_id == $emp['employee_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Start Date</label>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"
                            class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>End Date</label>
                        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control"
                            required>
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


        <!-- ASSIGN SHIFTS SECTION (Hidden by default) -->
        <div class="row mb-4" id="shiftFormContainer" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-calendar-check"></i>
                            Assign Shifts to Multiple Employees
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignShiftForm">
                            <div class="row">

                                <!-- EMPLOYEE SELECTION COLUMN -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Select Employees
                                        <span class="selected-count" id="selectedCount">0 selected</span>
                                    </label>

                                    <!-- Search Box -->
                                    <div class="employee-search-box">
                                        <input type="text" id="employeeSearch" class="form-control form-control-sm"
                                            placeholder="🔍 Search employees...">
                                    </div>

                                    <!-- Bulk Action Buttons -->
                                    <div class="bulk-action-btns">
                                        <button type="button" class="btn btn-sm btn-success"
                                            onclick="selectAllEmployees()">
                                            <i class="fas fa-check-double"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary"
                                            onclick="clearAllEmployees()">
                                            <i class="fas fa-times"></i> Clear All
                                        </button>
                                    </div>

                                    <!-- Employee Checkboxes Container -->
                                    <div class="employee-select-container" id="employeeContainer">
                                        <?php
                                        mysqli_data_seek($employees, 0);
                                        while ($emp = mysqli_fetch_assoc($employees)):
                                            ?>
                                            <div class="employee-checkbox-item"
                                                data-name="<?= strtolower($emp['last_name'] . ', ' . $emp['first_name']) ?>">
                                                <input type="checkbox" name="employee_ids[]"
                                                    value="<?= $emp['employee_id'] ?>" id="emp_<?= $emp['employee_id'] ?>"
                                                    onchange="updateSelectedCount()">
                                                <label for="emp_<?= $emp['employee_id'] ?>">
                                                    <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <!-- SHIFT DETAILS COLUMN -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <!-- Shift Selection -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Shift</label>
                                            <select name="shift_id" class="form-select" required>
                                                <option value="">--Select Shift--</option>
                                                <?php
                                                mysqli_data_seek($shifts, 0);
                                                while ($shift = mysqli_fetch_assoc($shifts)):
                                                    ?>
                                                    <option value="<?= $shift['shift_id'] ?>">
                                                        <?= $shift['shift_name'] ?>
                                                        (<?= $shift['time_in'] ?> - <?= $shift['time_out'] ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <!-- Start Date -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" class="form-control" required>
                                        </div>

                                        <!-- End Date -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" class="form-control" required>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="text-end mt-1">
                                        <button type="submit" name="assign_shifts" class="btn btn-primary">
                                            <i class="fas fa-calendar-check"></i> Assign Shift Employees
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Toggle the shift assignment form
            function toggleShiftForm() {
                const container = document.getElementById('shiftFormContainer');
                const button = document.getElementById('toggleShiftBtn');

                if (container.style.display === 'none') {
                    container.style.display = 'block';
                    button.innerHTML = '<i class="fas fa-minus-circle"></i> Hide Form';
                } else {
                    container.style.display = 'none';
                    button.innerHTML = '<i class="fas fa-plus-circle"></i> Assign Shifts to Employees';
                }
            }
        </script>

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
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Auto-Calculation Enabled:</strong> The shift with the least combined late and undertime is
                automatically selected. Changing the shift updates late time and undertime without affecting actual or break
                times. <strong>Night differential (6PM-3AM, overnight shifts only)</strong> and total work time are
                calculated from actual times (24-hour format). <strong class="text-danger">Edits apply to single day
                    only.</strong> <span class="manual-edit-badge"><i class="fas fa-user-edit"></i> MANUAL</span> =
                Protected from imports.
            </div>
            <div class="card">
                <div class="card-header">
                    <h5>DTR Records (<?= count($display_rows) ?> found)</h5>
                </div>

               <div class="card-body">
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="dtrTable">
            <thead class="table-light">
                <tr>
                    <th></th>
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
                    <th>UT</th>
                    <th>OT</th>
                    <th>N Prem</th>
                    <th>T. Hrs</th>
                    <th>Leave</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($display_rows as $r): ?>
                <tr
                    data-dtr-id="<?= $r['dtr_id'] ?>"
                    data-employee-id="<?= $r['employee_id'] ?>"
                    data-shift-id="<?= $r['shift_id'] ?? '' ?>"
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
                    data-schedule-in="<?= $r['schedule_in'] ?>"
                    data-schedule-out="<?= $r['schedule_out'] ?>"
                >

                <!-- ACTION -->
                <td>
                    <button type="button" class="btn btn-sm btn-outline-warning editDTRBtn">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>

                <!-- EMPLOYEE -->
                <td>
                    <?= htmlspecialchars($r['employee_name']) ?>
                    <?php if ($r['is_manual'] == 1): ?>
                        <span class="badge bg-warning text-dark ms-1">
                            <i class="fas fa-user-edit"></i> MANUAL
                        </span>
                    <?php endif; ?>
                </td>

                <!-- DATE -->
                <td><?= date('m-d-y', strtotime($r['date'])) ?></td>

                <!-- DAY TYPE BADGE -->
                <td>
                <?php
                    $dayType = trim($r['day_type']);
                    $dayBadges = [
                        'Regular Day' => 'badge bg-success',
                        'Rest Day' => 'badge bg-secondary',
                        'Special Holiday' => 'badge bg-warning text-dark',
                        'Regular Holiday' => 'badge bg-danger',
                        'Rest Day + Special Holiday' => 'badge bg-warning text-dark',
                        'Rest Day + Regular Holiday' => 'badge bg-danger',
                        'Double Special Holiday' => 'badge bg-info',
                        'Double Regular Holiday' => 'badge bg-dark',
                        'Regular + Special Holiday' => 'badge bg-primary',
                        'Rest Day + Regular + Special Holiday' => 'badge bg-dark text-warning',
                        'Absent' => 'badge bg-dark'
                    ];
                    $dayBadge = $dayBadges[$dayType] ?? 'badge bg-secondary';
                ?>
                    <span class="<?= $dayBadge ?>">
                        <i class="fas fa-calendar-day me-1"></i>
                        <?= htmlspecialchars($dayType) ?>
                    </span>
                </td>

                <!-- SCHEDULE / TIME -->
                <td><?= $r['schedule_in'] ?? 'N/A' ?></td>
                <td><?= $r['schedule_out'] ?? 'N/A' ?></td>
                <td><?= $r['time_in_raw'] ? date('H:i', strtotime($r['time_in_raw'])) : '' ?></td>
                <td><?= $r['time_out_raw'] ? date('H:i', strtotime($r['time_out_raw'])) : '' ?></td>
                <td><?= $r['break_out_raw'] ? date('H:i', strtotime($r['break_out_raw'])) : '' ?></td>
                <td><?= $r['break_in_raw'] ? date('H:i', strtotime($r['break_in_raw'])) : '' ?></td>

                <!-- COMPUTED -->
                <td><?= $r['late_time'] ?? '00:00' ?></td>
                <td><?= $r['undertime_time'] ?? '00:00' ?></td>
                <td><?= number_format($r['overtime_hours'], 2) ?></td>
                <td><?= $r['night_differential'] ?? '00:00' ?></td>
                <td><?= $r['total_hours_worked'] ?? '00:00' ?></td>

                <!-- LEAVE TYPE BADGE -->
                <td>
                <?php
                    $leaveName = trim($r['leave_type_name'] ?? '');
                    $leaveBadges = [
                        'Vacation Leave' => 'badge bg-success',
                        'Sick Leave' => 'badge bg-danger',
                        'Maternity Leave' => 'badge bg-primary',
                        'Paternity Leave' => 'badge bg-info',
                        'Emergency Leave' => 'badge bg-warning text-dark',
                        'Unpaid Leave' => 'badge bg-dark',
                        'Bereavement Leave' => 'badge bg-secondary',
                        'Special Leave' => 'badge bg-info'
                    ];
                    $leaveBadge = $leaveBadges[$leaveName] ?? 'badge bg-light text-muted';
                ?>

                <?php if ($leaveName): ?>
                    <span class="<?= $leaveBadge ?>">
                        <i class="fas fa-plane-departure me-1"></i>
                        <?= htmlspecialchars($leaveName) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-muted">None</span>
                <?php endif; ?>
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
<!-- Edit Modal -->
<div class="modal fade" id="editDTRModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <div>
                    <h5>Edit DTR Record</h5>
                    <small class="text-muted" id="edit_date_display">Editing single day record</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="dtr_id" id="edit_dtr_id">
                <input type="hidden" name="preserve_employee_id" value="<?= htmlspecialchars($employee_id) ?>">
                <input type="hidden" name="preserve_from_date" value="<?= htmlspecialchars($from_date) ?>">
                <input type="hidden" name="preserve_to_date" value="<?= htmlspecialchars($to_date) ?>">

                <!-- Add visual indicator for single-day edit -->
                <div class="alert alert-primary mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Single Day Edit:</strong> Changes will only apply to <span id="edit_employee_name"
                        class="fw-bold"></span> on <span id="edit_date" class="fw-bold"></span>. Other days remain
                    unchanged.
                </div>

                <div class="alert alert-warning mb-3">
                    <i class="fas fa-shield-alt me-2"></i>
                    <strong>Import Protection:</strong> This record will be marked as <span class="manual-edit-badge"><i
                            class="fas fa-user-edit"></i> MANUAL</span> and will be protected from being overwritten by
                    future biometric imports.
                </div>

                <div class="alert alert-info mb-3">
                    <i class="fas fa-calculator me-2"></i>
                    <small><strong>Auto-Calculation:</strong> The shift with the least combined late and undertime is
                        automatically selected. Changing the shift updates late time and undertime without affecting
                        actual or break times. <strong>Night differential (6PM-3AM, overnight shifts only)</strong> and
                        total work time are calculated from actual times (24-hour format).</small>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label>Select Shift <span id="employee_shifts_info" class="text-muted"></span></label>
                        <select name="shift_id" id="edit_shift_id" class="form-select">
                            <option value="">-- Select Shift --</option>
                            <?php
                            mysqli_data_seek($shifts, 0);
                            while ($shift = mysqli_fetch_assoc($shifts)):
                                ?>
                                <option value="<?= $shift['shift_id'] ?>" data-time-in="<?= $shift['time_in'] ?>"
                                    data-break-out="<?= $shift['break_out'] ?>" data-break-in="<?= $shift['break_in'] ?>"
                                    data-time-out="<?= $shift['time_out'] ?>"
                                    data-description="<?= htmlspecialchars($shift['description'] ?? '') ?>"
                                    data-is-flexible="<?= $shift['is_flexible'] ?>"
                                    data-has-break="<?= $shift['has_break'] ?>">
                                    <?= htmlspecialchars($shift['shift_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Actual In (HH:MM)</label>
                        <input type="time" name="actual_in" id="edit_actual_in" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Actual Out (HH:MM)</label>
                        <input type="time" name="actual_out" id="edit_actual_out" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Break Out (HH:MM)</label>
                        <input type="time" name="break_out" id="edit_break_out" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Break In (HH:MM)</label>
                        <input type="time" name="break_in" id="edit_break_in" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>Late Time (HH:MM)</label>
                        <input type="text" name="late_time" id="edit_late_time" class="form-control">
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                    <div class="col-md-4">
                        <label>Undertime (HH:MM)</label>
                        <input type="text" name="undertime_time" id="edit_undertime_time" class="form-control">
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                    <div class="col-md-4">
                        <label>Overtime (Hours)</label>
                        <input type="number" step="0.01" name="overtime_time" id="edit_overtime_time"
                            class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label>Night Diff (HH:MM) <small class="text-muted">6PM-3AM, overnight only</small></label>
                        <input type="time" name="night_time" id="edit_night_time" class="form-control"
                            placeholder="00:00">
                        <small class="text-muted">Auto-calculated (editable)</small>
                    </div>
                    <div class="col-md-4">
                        <label>Total Work (HH:MM)</label>
                        <input type="text" name="total_work_time" id="edit_total_work_time" class="form-control"
                            placeholder="00:00">
                        <small class="text-muted">Auto-calculated</small>
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
                    <i class="fas fa-save"></i> Update This Day Only
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
            timer: 4000,
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
    <?php if (isset($_SESSION['warning'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: '<?= $_SESSION['warning'] ?>',
            showConfirmButton: false,
            timer: 6000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        <?php unset($_SESSION['warning']); endif; ?>
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
        const table = $('#dtrTable').DataTable({
            pageLength: -1,
            lengthMenu: [[-1], ["All"]],
            dom:
                "<'row mb-3'<'col-md-6 d-flex align-items-center'f><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row mt-3'<'col-md-6'i><'col-md-6'p>>",
            buttons: [
                { extend: 'copy', className: 'btn btn-sm btn-outline-secondary me-2', text: '<i class="fas fa-copy"></i> Copy', exportOptions: { columns: ':not(:first-child)' } },
                { extend: 'csv', className: 'btn btn-sm btn-outline-primary me-2', text: '<i class="fas fa-file-csv"></i> CSV', exportOptions: { columns: ':not(:first-child)' } },
                { extend: 'excel', className: 'btn btn-sm btn-outline-success me-2', text: '<i class="fas fa-file-excel"></i> Excel', exportOptions: { columns: ':not(:first-child)' } },
                {
                    extend: 'pdf', className: 'btn btn-sm btn-outline-danger me-2', text: '<i class="fas fa-file-pdf"></i> PDF',
                    orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not(:first-child)' },
                    customize: function (doc) {
                        doc.defaultStyle.fontSize = 8;
                        doc.styles.tableHeader.fontSize = 9;
                        doc.styles.tableHeader.fillColor = '#667eea';
                        doc.content[1].margin = [0, 0, 0, 0];
                    }
                },
                { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="fas fa-print"></i> Print', exportOptions: { columns: ':not(:first-child)' } }
            ],
            language: {
                search: "",
                searchPlaceholder: "Search DTR records...",
                paginate: { previous: '<i class="fas fa-chevron-left"></i>', next: '<i class="fas fa-chevron-right"></i>' },
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                infoEmpty: "Showing 0 to 0 of 0 records",
                infoFiltered: "(filtered from _MAX_ total records)",
                lengthMenu: "Show _MENU_ records per page",
                zeroRecords: "No matching records found"
            }
        });

        $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
        $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');
        $(window).on('resize', function () { table.columns.adjust(); });

        // Calculate Late and Undertime
        function calculateLateAndUndertime(timeIn, timeOut, scheduleIn, scheduleOut, isFlexible) {
            console.log('');
            console.log('--- calculateLateAndUndertime() ---');
            console.log('INPUT: timeIn=' + timeIn + ', timeOut=' + timeOut);
            console.log('INPUT: scheduleIn=' + scheduleIn + ', scheduleOut=' + scheduleOut);
            console.log('INPUT: isFlexible=' + isFlexible);

            let lateTime = '00:00';
            let undertime = '00:00';
            let lateHours = 0;
            let undertimeHours = 0;

            // Calculate Late Time
            if (!isFlexible && timeIn && scheduleIn) {
                const timeInFormatted = timeIn.includes(':') ? timeIn : timeIn;
                const scheduleInFormatted = scheduleIn.includes(':') ? scheduleIn : scheduleIn;

                const actualInTime = new Date('2000-01-01 ' + timeInFormatted + ':00');
                const scheduledInTime = new Date('2000-01-01 ' + scheduleInFormatted + ':00');

                console.log('LATE CHECK:');
                console.log('  Actual Time In:', actualInTime.toTimeString());
                console.log('  Schedule Time In:', scheduledInTime.toTimeString());

                if (actualInTime > scheduledInTime) {
                    const diff = actualInTime - scheduledInTime;
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.round((diff % (1000 * 60 * 60)) / (1000 * 60));
                    lateTime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                    lateHours = hours + (minutes / 60);
                    console.log('  ✓ LATE DETECTED: ' + lateTime + ' (' + lateHours + ' hours)');
                } else {
                    console.log('  ✓ ON TIME or EARLY - No late');
                }
            } else {
                if (isFlexible) {
                    console.log('LATE CHECK: Skipped (Flexible shift)');
                } else {
                    console.log('LATE CHECK: Skipped (Missing timeIn or scheduleIn)');
                }
            }

            // Calculate Undertime
            if (!isFlexible && timeOut && scheduleOut) {
                const timeOutFormatted = timeOut.includes(':') ? timeOut : timeOut;
                const scheduleOutFormatted = scheduleOut.includes(':') ? scheduleOut : scheduleOut;
                const scheduleInFormatted = scheduleIn ? (scheduleIn.includes(':') ? scheduleIn : scheduleIn) : null;

                let actualOutTime = new Date('2000-01-01 ' + timeOutFormatted + ':00');
                let scheduledOutTime = new Date('2000-01-01 ' + scheduleOutFormatted + ':00');
                const scheduleInTime = scheduleInFormatted ? new Date('2000-01-01 ' + scheduleInFormatted + ':00') : null;

                console.log('UNDERTIME CHECK:');
                console.log('  Actual Time Out:', actualOutTime.toTimeString());
                console.log('  Schedule Time Out:', scheduledOutTime.toTimeString());

                // Handle overnight shifts
                const isOvernight = scheduleInTime && scheduledOutTime < scheduleInTime;
                if (isOvernight) {
                    scheduledOutTime.setDate(scheduledOutTime.getDate() + 1);
                    if (timeIn && actualOutTime < new Date('2000-01-01 ' + timeIn + ':00')) {
                        actualOutTime.setDate(actualOutTime.getDate() + 1);
                    }
                    console.log('  (Overnight shift detected)');
                    console.log('  Adjusted Schedule Out:', scheduledOutTime.toTimeString());
                }

                if (actualOutTime < scheduledOutTime) {
                    const diff = scheduledOutTime - actualOutTime;
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.round((diff % (1000 * 60 * 60)) / (1000 * 60));
                    undertime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                    undertimeHours = hours + (minutes / 60);
                    console.log('  ✓ UNDERTIME DETECTED: ' + undertime + ' (' + undertimeHours + ' hours)');
                } else {
                    console.log('  ✓ ON TIME or OVERTIME - No undertime');
                }
            } else {
                if (isFlexible) {
                    console.log('UNDERTIME CHECK: Skipped (Flexible shift)');
                } else {
                    console.log('UNDERTIME CHECK: Skipped (Missing timeOut or scheduleOut)');
                }
            }

            console.log('RESULT: Late=' + lateTime + ', Undertime=' + undertime + ', Total Penalty=' + (lateHours + undertimeHours));
            return { lateTime, undertime, totalPenalty: lateHours + undertimeHours };
        }

        // Calculate Night Differential (6PM-3AM, overnight shifts only)
        function calculateNightDifferential(timeIn, timeOut) {
            if (!timeIn || !timeOut) {
                return '00:00';
            }

            const inParts = timeIn.split(':');
            const outParts = timeOut.split(':');
            const inHour = parseInt(inParts[0]);
            const outHour = parseInt(outParts[0]);

            // Only calculate for overnight shifts (PM to AM)
            const isOvernightShift = (inHour >= 12 && outHour < 12);

            if (!isOvernightShift) {
                console.log('Day shift detected - No night differential');
                return '00:00';
            }

            console.log('Overnight shift detected - Calculating night differential');

            const nightStartHour = 18; // 6 PM
            const nightEndHour = 3;    // 3 AM

            let inTime = new Date('2000-01-01 ' + timeIn + ':00');
            let outTime = new Date('2000-01-01 ' + timeOut + ':00');

            if (outTime < inTime) {
                outTime.setDate(outTime.getDate() + 1);
            }

            let nightMinutes = 0;
            let currentTime = new Date(inTime);

            while (currentTime < outTime) {
                const hour = currentTime.getHours();

                if (hour >= nightStartHour || hour < nightEndHour) {
                    nightMinutes++;
                }

                currentTime.setMinutes(currentTime.getMinutes() + 1);
            }

            const hours = Math.floor(nightMinutes / 60);
            const minutes = nightMinutes % 60;
            const result = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
            console.log('Night differential calculated:', result);
            return result;
        }

        // Calculate Total Work Time
        function calculateTotalWorkTime() {
            const timeIn = $('#edit_actual_in').val();
            const timeOut = $('#edit_actual_out').val();
            const breakOut = $('#edit_break_out').val();
            const breakIn = $('#edit_break_in').val();

            if (timeIn && timeOut) {
                let inTime = new Date('2000-01-01 ' + timeIn);
                let outTime = new Date('2000-01-01 ' + timeOut);

                if (outTime < inTime) {
                    outTime.setDate(outTime.getDate() + 1);
                }

                let totalMinutes = (outTime - inTime) / (1000 * 60);

                if (breakOut && breakIn && breakOut !== '00:00' && breakIn !== '00:00') {
                    const breakOutTime = new Date('2000-01-01 ' + breakOut);
                    const breakInTime = new Date('2000-01-01 ' + breakIn);

                    if (breakInTime > breakOutTime) {
                        const breakMinutes = (breakInTime - breakOutTime) / (1000 * 60);
                        totalMinutes -= breakMinutes;
                    }
                }

                if (totalMinutes > 0) {
                    const hours = Math.floor(totalMinutes / 60);
                    const minutes = Math.round(totalMinutes % 60);
                    const formattedTime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                    $('#edit_total_work_time').val(formattedTime);

                    console.log('Total work time calculated:', formattedTime);
                    return formattedTime;
                } else {
                    $('#edit_total_work_time').val('00:00');
                    return '00:00';
                }
            } else {
                $('#edit_total_work_time').val('00:00');
                return '00:00';
            }
        }

        // Recalculate all fields
        function recalculateAllFields() {
            const selectedOption = $('#edit_shift_id option:selected');
            const shiftId = selectedOption.val();
            const timeIn = $('#edit_actual_in').val();
            const timeOut = $('#edit_actual_out').val();
            const scheduleIn = selectedOption.data('time-in') || '';
            const scheduleOut = selectedOption.data('time-out') || '';
            const isFlexible = selectedOption.data('is-flexible') || 0;

            console.log('=== Recalculating All Fields ===');
            console.log('Shift ID:', shiftId);
            console.log('Actual - In:', timeIn, 'Out:', timeOut);
            console.log('Schedule - In:', scheduleIn, 'Out:', scheduleOut);
            console.log('Is Flexible:', isFlexible);

            if (!timeIn || !timeOut) {
                console.log('Missing actual times - skipping calculation');
                $('#edit_late_time').val('00:00');
                $('#edit_undertime_time').val('00:00');
                $('#edit_night_time').val('00:00');
                $('#edit_total_work_time').val('00:00');
                return;
            }

            if (!scheduleIn || !scheduleOut) {
                console.log('Missing schedule times - cannot calculate late/undertime');
                $('#edit_late_time').val('00:00');
                $('#edit_undertime_time').val('00:00');
            } else {
                const { lateTime, undertime } = calculateLateAndUndertime(
                    timeIn, timeOut, scheduleIn, scheduleOut, isFlexible
                );

                $('#edit_late_time').val(lateTime);
                $('#edit_undertime_time').val(undertime);

                console.log('✓ Updated Late:', lateTime);
                console.log('✓ Updated Undertime:', undertime);
            }

            const nightDiff = calculateNightDifferential(timeIn, timeOut);
            $('#edit_night_time').val(nightDiff);
            console.log('✓ Updated Night Diff:', nightDiff);

            const totalWork = calculateTotalWorkTime();
            console.log('✓ Updated Total Work:', totalWork);
            console.log('=== Recalculation Complete ===');
        }

        // Show live calculation preview
        function showCalculationPreview() {
            const timeIn = $('#edit_actual_in').val();
            const timeOut = $('#edit_actual_out').val();

            if (timeIn && timeOut) {
                const late = $('#edit_late_time').val();
                const undertime = $('#edit_undertime_time').val();
                const nightDiff = $('#edit_night_time').val();
                const totalWork = $('#edit_total_work_time').val();

                const inParts = timeIn.split(':');
                const outParts = timeOut.split(':');
                const inHour = parseInt(inParts[0]);
                const outHour = parseInt(outParts[0]);
                const isOvernightShift = (inHour >= 12 && outHour < 12);
                const shiftType = isOvernightShift ? 'Overnight' : 'Day';

                let statusIcon = '✓';
                let statusColor = 'success';
                if (late !== '00:00' || undertime !== '00:00') {
                    statusIcon = '⚠';
                    statusColor = 'warning';
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: statusColor,
                    html: `<div class="text-start" style="font-size: 12px;">
                        <strong>${statusIcon} Live Calculation (${shiftType} Shift)</strong><br>
                        Late: <strong class="${late !== '00:00' ? 'text-warning' : 'text-success'}">${late}</strong> | 
                        UT: <strong class="${undertime !== '00:00' ? 'text-warning' : 'text-success'}">${undertime}</strong><br>
                        Night: <strong class="${nightDiff !== '00:00' ? 'text-info' : 'text-muted'}">${nightDiff}</strong> | 
                        Total: <strong class="text-primary">${totalWork}</strong>
                       </div>`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal-compact'
                    }
                });

                highlightChangedFields(late, undertime, nightDiff, totalWork);
            }
        }

        // Highlight changed fields with color coding
        function highlightChangedFields(late, undertime, nightDiff, totalWork) {
            const lateField = $('#edit_late_time');
            lateField.removeClass('bg-success bg-warning bg-light');
            if (late !== '00:00') {
                lateField.addClass('bg-warning bg-opacity-25');
            } else {
                lateField.addClass('bg-success bg-opacity-25');
            }

            const undertimeField = $('#edit_undertime_time');
            undertimeField.removeClass('bg-success bg-warning bg-light');
            if (undertime !== '00:00') {
                undertimeField.addClass('bg-warning bg-opacity-25');
            } else {
                undertimeField.addClass('bg-success bg-opacity-25');
            }

            const nightField = $('#edit_night_time');
            nightField.removeClass('bg-info bg-light');
            if (nightDiff !== '00:00') {
                nightField.addClass('bg-info bg-opacity-25');
            } else {
                nightField.addClass('bg-light');
            }

            const totalField = $('#edit_total_work_time');
            totalField.removeClass('bg-primary bg-light');
            if (totalWork !== '00:00') {
                totalField.addClass('bg-primary bg-opacity-25');
            }

            setTimeout(() => {
                lateField.removeClass('bg-success bg-warning bg-info bg-primary bg-light bg-opacity-25');
                undertimeField.removeClass('bg-success bg-warning bg-info bg-primary bg-light bg-opacity-25');
                nightField.removeClass('bg-info bg-light bg-opacity-25');
                totalField.removeClass('bg-primary bg-light bg-opacity-25');
            }, 2000);
        }

        // Fetch employee shifts
        function fetchEmployeeShifts(employeeId, callback) {
            $.ajax({
                url: 'get_employee_shifts.php',
                method: 'POST',
                data: { employee_id: employeeId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        callback(response.shifts);
                    } else {
                        callback([]);
                    }
                },
                error: function () {
                    callback([]);
                }
            });
        }

        // Display all shifts with employee's shifts highlighted
        function displayAllShiftsWithEmployeeHighlight(employeeShiftIds, allShiftsData, currentShiftId) {
            const selectBox = $('#edit_shift_id');

            selectBox.find('option:not(:first)').remove();

            const employeeShifts = [];
            const otherShifts = [];

            Object.values(allShiftsData).forEach(function (shift) {
                if (employeeShiftIds.includes(parseInt(shift.shift_id))) {
                    employeeShifts.push(shift);
                } else {
                    otherShifts.push(shift);
                }
            });

            if (currentShiftId && allShiftsData[currentShiftId]) {
                const shift = allShiftsData[currentShiftId];
                selectBox.append(
                    $('<option>', {
                        value: shift.shift_id,
                        text: shift.shift_name + ' (Current)',
                        'data-time-in': shift.time_in,
                        'data-time-out': shift.time_out,
                        'data-break-out': shift.break_out,
                        'data-break-in': shift.break_in,
                        'data-description': shift.description,
                        'data-is-flexible': shift.is_flexible,
                        'data-has-break': shift.has_break
                    })
                );
            }

            if (employeeShiftIds.length > 0) {
                selectBox.append($('<option>', {
                    value: '',
                    text: '──── Employee\'s Usual Shifts ────',
                    disabled: true,
                    'data-separator': true
                }));

                employeeShifts.forEach(function (shift) {
                    if (shift.shift_id != currentShiftId) {
                        selectBox.append(
                            $('<option>', {
                                value: shift.shift_id,
                                text: '✓ ' + shift.shift_name,
                                'data-time-in': shift.time_in,
                                'data-time-out': shift.time_out,
                                'data-break-out': shift.break_out,
                                'data-break-in': shift.break_in,
                                'data-description': shift.description,
                                'data-is-flexible': shift.is_flexible,
                                'data-has-break': shift.has_break,
                                'data-employee-shift': true
                            })
                        );
                    }
                });

                if (otherShifts.length > 0) {
                    selectBox.append($('<option>', {
                        value: '',
                        text: '──── Other Available Shifts ────',
                        disabled: true,
                        'data-separator': true
                    }));
                }
            }

            otherShifts.forEach(function (shift) {
                selectBox.append(
                    $('<option>', {
                        value: shift.shift_id,
                        text: shift.shift_name,
                        'data-time-in': shift.time_in,
                        'data-time-out': shift.time_out,
                        'data-break-out': shift.break_out,
                        'data-break-in': shift.break_in,
                        'data-description': shift.description,
                        'data-is-flexible': shift.is_flexible,
                        'data-has-break': shift.has_break
                    })
                );
            });

            if (employeeShiftIds.length > 0) {
                $('#employee_shifts_info').html(
                    `<small class="employee-shifts-badge"><i class="fas fa-user-clock"></i> Employee uses ${employeeShiftIds.length} shift(s) - marked with ✓</small>`
                );
            } else {
                $('#employee_shifts_info').html(
                    '<small class="text-muted"><i class="fas fa-info-circle"></i> No shift history for this employee</small>'
                );
            }

            selectBox.find('option[data-employee-shift="true"]').css({
                'background-color': '#e3f2fd',
                'font-weight': '500'
            });
        }

        // Store all shifts data
        const allShiftsData = {};
        $('#edit_shift_id option').each(function () {
            const shiftId = $(this).val();
            if (shiftId) {
                allShiftsData[shiftId] = {
                    shift_id: shiftId,
                    shift_name: $(this).text(),
                    time_in: $(this).data('time-in'),
                    time_out: $(this).data('time-out'),
                    break_out: $(this).data('break-out'),
                    break_in: $(this).data('break-in'),
                    description: $(this).data('description'),
                    is_flexible: $(this).data('is-flexible'),
                    has_break: $(this).data('has-break')
                };
            }
        });

        // ENHANCED: Real-time calculation on actual time changes
        $('#edit_actual_in, #edit_actual_out').on('change input', function () {
            const fieldName = $(this).attr('id').replace('edit_', '');
            const fieldValue = $(this).val();

            console.log('');
            console.log('⚡ REAL-TIME UPDATE TRIGGERED');
            console.log('Field Changed:', fieldName, '→', fieldValue);

            $(this).addClass('border-primary border-2');
            setTimeout(() => {
                $(this).removeClass('border-primary border-2');
            }, 300);

            recalculateAllFields();
            showCalculationPreview();
        });

        // ENHANCED: Real-time calculation on break time changes
        $('#edit_break_out, #edit_break_in').on('change input', function () {
            console.log('Break time changed:', $(this).attr('id'), '=', $(this).val());

            $(this).addClass('border-info border-2');
            setTimeout(() => {
                $(this).removeClass('border-info border-2');
            }, 300);

            calculateTotalWorkTime();
            showCalculationPreview();
        });

        // ENHANCED: Real-time calculation on shift change
        $('#edit_shift_id').on('change', function () {
            const selectedOption = $(this).find('option:selected');
            const hasBreak = selectedOption.data('has-break') || 0;
            const isFlexible = selectedOption.data('is-flexible') || 0;
            const shiftName = selectedOption.text().replace('✓ ', '').replace(' (Current)', '');
            const description = selectedOption.data('description') || 'No description available';
            const scheduleIn = selectedOption.data('time-in') || '';
            const scheduleOut = selectedOption.data('time-out') || '';

            console.log('');
            console.log('🔄 SHIFT CHANGED');
            console.log('New Shift:', shiftName);
            console.log('Schedule:', scheduleIn, '-', scheduleOut);
            console.log('Is Flexible:', isFlexible ? 'Yes' : 'No');

            $('#edit_break_out, #edit_break_in').prop('disabled', !hasBreak);
            $(this).attr('title', description).tooltip('dispose').tooltip();

            const timeIn = $('#edit_actual_in').val();
            const timeOut = $('#edit_actual_out').val();

            if (timeIn && timeOut && scheduleIn && scheduleOut) {
                console.log('Recalculating with new shift...');

                recalculateAllFields();

                const late = $('#edit_late_time').val();
                const undertime = $('#edit_undertime_time').val();
                const nightDiff = $('#edit_night_time').val();
                const totalWork = $('#edit_total_work_time').val();

                console.log('✓ Calculations complete:');
                console.log('  Late:', late);
                console.log('  Undertime:', undertime);
                console.log('  Night Diff:', nightDiff);
                console.log('  Total Work:', totalWork);

                let message = `<div class="text-start" style="font-size: 13px;">`;
                message += `<strong>📋 ${shiftName}</strong><br>`;
                message += `<small class="text-muted">Schedule: ${scheduleIn} - ${scheduleOut}</small><br>`;
                message += `<small class="text-muted">Actual: ${timeIn} - ${timeOut}</small><br><hr class="my-2">`;

                if (isFlexible) {
                    message += `<span class="text-info"><i class="fas fa-info-circle"></i> Flexible shift - No penalties</span><br>`;
                } else {
                    if (late !== '00:00') {
                        message += `<span class="text-warning"><i class="fas fa-clock"></i> Late: <strong>${late}</strong></span><br>`;
                    } else {
                        message += `<span class="text-success"><i class="fas fa-check-circle"></i> On Time</span><br>`;
                    }

                    if (undertime !== '00:00') {
                        message += `<span class="text-warning"><i class="fas fa-hourglass-end"></i> Undertime: <strong>${undertime}</strong></span><br>`;
                    } else {
                        message += `<span class="text-success"><i class="fas fa-check-circle"></i> No Undertime</span><br>`;
                    }
                }

                if (nightDiff !== '00:00') {
                    message += `<span class="text-info"><i class="fas fa-moon"></i> Night Diff: <strong>${nightDiff}</strong></span><br>`;
                    message += `<small class="text-muted">6PM-3AM, overnight shift</small><br>`;
                } else {
                    message += `<span class="text-muted"><i class="fas fa-sun"></i> Day shift - No night premium</span><br>`;
                }

                message += `<hr class="my-2"><span class="text-primary"><i class="fas fa-clock"></i> Total Hours: <strong>${totalWork}</strong></span>`;
                message += `</div>`;

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: (late === '00:00' && undertime === '00:00') || isFlexible ? 'success' : 'warning',
                    title: '🔄 Shift Updated',
                    html: message,
                    showConfirmButton: false,
                    timer: 6000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    },
                    customClass: {
                        popup: 'swal-wide'
                    }
                });

                highlightChangedFields(late, undertime, nightDiff, totalWork);

            } else {
                $('#edit_late_time').val('00:00');
                $('#edit_undertime_time').val('00:00');
                $('#edit_night_time').val('00:00');

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: `Shift: ${shiftName}`,
                    html: `<div class="text-start" style="font-size: 13px;">
                        Schedule: ${scheduleIn} - ${scheduleOut}<br>
                        <small class="text-muted">Enter Actual In/Out times to calculate</small>
                       </div>`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });

        // Edit DTR Button Click
        $(document).on('click', '.editDTRBtn', function () {
            const row = $(this).closest('tr');
            const dtrId = row.data('dtr-id');
            const employeeId = row.data('employee-id');

            const employeeName = row.find('td:eq(1)').text().trim().replace(/MANUAL/g, '').trim();
            const date = row.find('td:eq(2)').text().trim();

            $('#edit_employee_name').text(employeeName);
            $('#edit_date').text(date);
            $('#edit_date_display').text(`Editing: ${employeeName} - ${date}`);

            const shiftId = row.data('shift-id') || '';
            const actualIn = row.data('actual-in') || '';
            const actualOut = row.data('actual-out') || '';
            const breakOut = row.data('break-out') || '';
            const breakIn = row.data('break-in') || '';
            const late = row.data('late') || '00:00';
            const undertime = row.data('undertime') || '00:00';
            const overtime = row.data('overtime') || '0.00';
            const night = row.data('night') || '00:00';
            const totalWork = row.data('total-work') || '00:00';
            const leaveType = row.data('leave-type') || '';
            const remarks = row.data('remarks') || '';

            console.log('=== Opening Edit Modal ===');
            console.log('Employee:', employeeName);
            console.log('Date:', date);
            console.log('Shift ID:', shiftId);
            console.log('Actual In:', actualIn, 'Actual Out:', actualOut);
            console.log('Current Late:', late, 'Current Undertime:', undertime);
            console.log('Current Night Diff:', night, 'Current Total:', totalWork);

            $('#edit_dtr_id').val(dtrId);
            $('#edit_actual_in').val(actualIn);
            $('#edit_actual_out').val(actualOut);
            $('#edit_break_out').val(breakOut);
            $('#edit_break_in').val(breakIn);
            $('#edit_late_time').val(late);
            $('#edit_undertime_time').val(undertime);
            $('#edit_overtime_time').val(overtime);
            $('#edit_night_time').val(night);
            $('#edit_total_work_time').val(totalWork);
            $('#edit_leave_type_id').val(leaveType);
            $('#edit_remarks').val(remarks);

            console.log('✓ Form populated with current values');

            fetchEmployeeShifts(employeeId, function (employeeShifts) {
                displayAllShiftsWithEmployeeHighlight(employeeShifts, allShiftsData, shiftId);

                if (shiftId) {
                    $('#edit_shift_id').val(shiftId);

                    const selectedShift = $('#edit_shift_id option:selected');
                    const hasBreak = selectedShift.data('has-break') || 0;
                    $('#edit_break_out, #edit_break_in').prop('disabled', !hasBreak);

                    const description = selectedShift.data('description') || 'No description available';
                    $('#edit_shift_id').attr('title', description).tooltip('dispose').tooltip();

                    console.log('✓ Shift selected:', selectedShift.text());
                } else {
                    console.log('⚠ No shift assigned - finding best shift');
                    let bestShiftId = '';
                    let bestLateTime = '00:00';
                    let bestUndertime = '00:00';
                    let minPenalty = Infinity;

                    if (actualIn && actualOut) {
                        $('#edit_shift_id option').each(function () {
                            const currentShiftId = $(this).val();
                            if (!currentShiftId) return;
                            const shiftScheduleIn = $(this).data('time-in') || '';
                            const shiftScheduleOut = $(this).data('time-out') || '';
                            const isFlexible = $(this).data('is-flexible') || 0;

                            const { lateTime, undertime, totalPenalty } = calculateLateAndUndertime(
                                actualIn, actualOut, shiftScheduleIn, shiftScheduleOut, isFlexible
                            );

                            if (totalPenalty < minPenalty) {
                                minPenalty = totalPenalty;
                                bestShiftId = currentShiftId;
                                bestLateTime = lateTime;
                                bestUndertime = undertime;
                            }
                        });

                        console.log('✓ Best shift auto-selected:', bestShiftId);
                        $('#edit_shift_id').val(bestShiftId);
                        $('#edit_late_time').val(bestLateTime);
                        $('#edit_undertime_time').val(bestUndertime);
                    }
                }

                if (actualIn && actualOut) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        html: `<div class="text-start" style="font-size: 12px;">
                            <strong>📋 Current Record:</strong><br>
                            Late: <strong>${late}</strong><br>
                            Undertime: <strong>${undertime}</strong><br>
                            Night Diff: <strong>${night}</strong><br>
                            Total Hours: <strong>${totalWork}</strong><br>
                            <small class="text-muted">Values will recalculate if you change times</small>
                           </div>`,
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }

                console.log('=== Modal Ready ===');
            });

            $('#editDTRModal').modal('show');
        });

        // Time input validation
        $('input[type="time"]').on('input', function () {
            const value = $(this).val();
            const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (value && !timePattern.test(value)) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        // Form submission
        $('form').on('submit', function (e) {
            const invalidFields = $(this).find('.is-invalid');
            if (invalidFields.length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please enter valid 24-hour time formats (HH:MM).',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if ($(this).find('input[name="update_dtr"]').length > 0) {
                e.preventDefault();

                const employeeName = $('#edit_employee_name').text();
                const date = $('#edit_date').text();
                const nightDiff = $('#edit_night_time').val();

                let nightDiffMessage = '';
                if (nightDiff && nightDiff !== '00:00') {
                    nightDiffMessage = `<br>• Night differential: ${nightDiff} (6PM-3AM, overnight shift)`;
                } else {
                    nightDiffMessage = `<br>• Night differential: 00:00 (Day shift - no night premium)`;
                }

                Swal.fire({
                    title: 'Update DTR Record?',
                    html: `<div class="text-start">
                          <strong>Employee:</strong> ${employeeName}<br>
                          <strong>Date:</strong> ${date}<br>
                          <strong class="text-danger">⚠ Only this single day will be updated</strong><br>
                          <strong class="text-warning">🛡️ Record will be marked as MANUAL and protected from imports</strong><br><br>
                          <strong>The following will be automatically calculated:</strong><br>
                          • Late time (based on scheduled vs actual time in)<br>
                          • Undertime (based on scheduled vs actual time out)<br>
                          • Total work time (including break deductions)${nightDiffMessage}<br><br>
                          All times are in 24-hour format. Do you want to proceed?</div>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Update This Day Only',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Updating Single Day DTR...',
                            html: `Updating ${employeeName} - ${date}<br>Calculating late time, undertime, night differential, and total work hours<br><strong>Marking as MANUAL edit</strong>`,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        e.target.submit();
                    }
                });
            }
        });

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Add custom CSS for better visuals
        $('<style>')
            .text(`
            .swal-wide {
                width: 400px !important;
            }
            .swal-compact {
                width: 350px !important;
            }
            .border-2 {
                border-width: 2px !important;
            }
            .bg-opacity-25 {
                --bs-bg-opacity: 0.25;
            }
        `)
            .appendTo('head');
    });
</script>
<script>
    /**
     * Search functionality for employee list
     */
    document.getElementById('employeeSearch').addEventListener('input', function (e) {
        const searchTerm = e.target.value.toLowerCase();
        const items = document.querySelectorAll('.employee-checkbox-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name');
            item.style.display = name.includes(searchTerm) ? 'block' : 'none';
        });
    });

    /**
     * Select all visible employees
     */
    function selectAllEmployees() {
        const checkboxes = document.querySelectorAll(
            '.employee-checkbox-item:not([style*="display: none"]) input[type="checkbox"]'
        );
        checkboxes.forEach(cb => cb.checked = true);
        updateSelectedCount();
    }

    /**
     * Clear all employee selections
     */
    function clearAllEmployees() {
        const checkboxes = document.querySelectorAll(
            '.employee-checkbox-item input[type="checkbox"]'
        );
        checkboxes.forEach(cb => cb.checked = false);
        updateSelectedCount();
    }

    /**
     * Update the selected employee count display
     */
    function updateSelectedCount() {
        const checked = document.querySelectorAll(
            '.employee-checkbox-item input[type="checkbox"]:checked'
        ).length;
        document.getElementById('selectedCount').textContent = checked + ' selected';
    }

    /**
     * Form validation before submission
     */
    document.getElementById('assignShiftForm').addEventListener('submit', function (e) {
        const checked = document.querySelectorAll(
            '.employee-checkbox-item input[type="checkbox"]:checked'
        ).length;

        if (checked === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'No Employees Selected',
                text: 'Please select at least one employee to assign the shift.',
            });
        }
    });

    // Initialize count on page load
    updateSelectedCount();
</script>

<!-- =============================================================================
     SUCCESS NOTIFICATION
============================================================================= -->
<?php if (isset($_SESSION['success'])): ?>
    <script>
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
    </script>
    <?php
    unset($_SESSION['success']);
endif;
?>