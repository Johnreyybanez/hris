<?php
session_start();
include 'connection.php';

// AJAX endpoint for calculating working days with day-off validation
if (isset($_POST['ajax_calculate_days'])) {
    $employee_id = $_POST['employee_id'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    if (!$employee_id || !$start_date || !$end_date) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    if (strtotime($start_date) > strtotime($end_date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date range']);
        exit;
    }
    
    try {
        // Get employee's shift information
        $shift_query = "SELECT shift_id FROM employees WHERE employee_id = ?";
        $stmt = mysqli_prepare($conn, $shift_query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $shift_result = mysqli_stmt_get_result($stmt);
        
        if (!$shift_result || mysqli_num_rows($shift_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Employee not found']);
            exit;
        }
        
        $shift_row = mysqli_fetch_assoc($shift_result);
        $shift_id = $shift_row['shift_id'];
        
        // Get shift working days - 0 = working day, 1 = day off
        $shift_days_query = "SELECT is_monday, is_tuesday, is_wednesday, is_thursday, 
                                   is_friday, is_saturday, is_sunday 
                            FROM shiftdays WHERE shift_id = ?";
        $stmt = mysqli_prepare($conn, $shift_days_query);
        mysqli_stmt_bind_param($stmt, "i", $shift_id);
        mysqli_stmt_execute($stmt);
        $shift_days_result = mysqli_stmt_get_result($stmt);
        
        if (!$shift_days_result || mysqli_num_rows($shift_days_result) == 0) {
            // No shift days configured - this is an error state
            echo json_encode(['success' => false, 'error' => 'Shift schedule not configured for this employee']);
            exit;
        } else {
            $shift_days = mysqli_fetch_assoc($shift_days_result);
            $working_days = [
                1 => (int)$shift_days['is_monday'],      // Monday (0=working, 1=off)
                2 => (int)$shift_days['is_tuesday'],     // Tuesday (0=working, 1=off)
                3 => (int)$shift_days['is_wednesday'],   // Wednesday (0=working, 1=off)
                4 => (int)$shift_days['is_thursday'],    // Thursday (0=working, 1=off)
                5 => (int)$shift_days['is_friday'],      // Friday (0=working, 1=off)
                6 => (int)$shift_days['is_saturday'],    // Saturday (0=working, 1=off)
                7 => (int)$shift_days['is_sunday']       // Sunday (0=working, 1=off)
            ];
        }
        
        // Get holidays within the date range
        $holidays_query = "SELECT date FROM holidaycalendar WHERE date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $holidays_query);
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $holidays_result = mysqli_stmt_get_result($stmt);
        
        $holidays = [];
        while ($holiday = mysqli_fetch_assoc($holidays_result)) {
            $holidays[] = $holiday['date'];
        }
        
        // Calculate working days and check for days off
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $current = clone $start;
        $working_days_count = 0;
        $total_days = $start->diff($end)->days + 1;
        $days_off_in_range = [];
        $holidays_in_range = [];
        
        while ($current <= $end) {
            $current_date = $current->format('Y-m-d');
            $day_of_week = (int)$current->format('N');
            $day_name = $current->format('l');
            
            // Check if it's a holiday
            if (in_array($current_date, $holidays)) {
                $holidays_in_range[] = [
                    'date' => $current_date,
                    'day' => $day_name
                ];
            }
            // Check if it's a day off (value = 1)
            else if (isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 1) {
                $days_off_in_range[] = [
                    'date' => $current_date,
                    'day' => $day_name
                ];
            }
            // Check if it's a working day (value = 0)
            else if (isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 0) {
                $working_days_count++;
            }
            
            $current->add(new DateInterval('P1D'));
        }
        
        // Check if there are any days off in the selected range
        if (!empty($days_off_in_range)) {
            $day_off_dates = [];
            foreach ($days_off_in_range as $day_off) {
                $day_off_dates[] = $day_off['date'] . " ({$day_off['day']})";
            }
            
            echo json_encode([
                'success' => false,
                'error' => 'Cannot apply for leave on scheduled days off!',
                'details' => 'The following dates are your scheduled days off: ' . implode(', ', $day_off_dates) . '. Please adjust your leave dates.',
                'days_off' => $days_off_in_range,
                'working_days' => $working_days_count
            ]);
            exit;
        }
        
        // Check if there are any holidays in the selected range
        if (!empty($holidays_in_range)) {
            $holiday_dates = [];
            foreach ($holidays_in_range as $holiday) {
                $holiday_dates[] = $holiday['date'] . " ({$holiday['day']})";
            }
            
            echo json_encode([
                'success' => true,
                'working_days' => $working_days_count,
                'total_days' => $total_days,
                'warning' => 'Note: The following dates are holidays: ' . implode(', ', $holiday_dates) . '. These will not be counted as leave days.',
                'holidays' => $holidays_in_range
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'working_days' => $working_days_count,
            'total_days' => $total_days
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Enhanced function to calculate working days with day-off validation
function calculateWorkingDays($conn, $start_date, $end_date, $employee_id) {
    error_log("Calculating working days for employee_id: $employee_id, from: $start_date to: $end_date");
    
    // Get employee's shift information
    $shift_query = "SELECT shift_id FROM employees WHERE employee_id = ?";
    $stmt = mysqli_prepare($conn, $shift_query);
    mysqli_stmt_bind_param($stmt, "i", $employee_id);
    mysqli_stmt_execute($stmt);
    $shift_result = mysqli_stmt_get_result($stmt);
    
    if (!$shift_result || mysqli_num_rows($shift_result) == 0) {
        error_log("Employee not found or no shift assigned for employee_id: $employee_id");
        return 0;
    }
    
    $shift_row = mysqli_fetch_assoc($shift_result);
    $shift_id = $shift_row['shift_id'];
    
    // Get shift working days - 0 = working day, 1 = day off
    $shift_days_query = "SELECT is_monday, is_tuesday, is_wednesday, is_thursday, 
                               is_friday, is_saturday, is_sunday 
                        FROM shiftdays WHERE shift_id = ?";
    $stmt = mysqli_prepare($conn, $shift_days_query);
    mysqli_stmt_bind_param($stmt, "i", $shift_id);
    mysqli_stmt_execute($stmt);
    $shift_days_result = mysqli_stmt_get_result($stmt);
    
    if (!$shift_days_result || mysqli_num_rows($shift_days_result) == 0) {
        // No shift days configured - employee has no working days
        error_log("Shift days not configured for shift_id: $shift_id. Employee has no working days.");
        return 0;
    } else {
        $shift_days = mysqli_fetch_assoc($shift_days_result);
        $working_days = [
            1 => (int)$shift_days['is_monday'],      // Monday (0=working, 1=off)
            2 => (int)$shift_days['is_tuesday'],     // Tuesday (0=working, 1=off)
            3 => (int)$shift_days['is_wednesday'],   // Wednesday (0=working, 1=off)
            4 => (int)$shift_days['is_thursday'],    // Thursday (0=working, 1=off)
            5 => (int)$shift_days['is_friday'],      // Friday (0=working, 1=off)
            6 => (int)$shift_days['is_saturday'],    // Saturday (0=working, 1=off)
            7 => (int)$shift_days['is_sunday']       // Sunday (0=working, 1=off)
        ];
        
        // Log the shift schedule for debugging
        error_log("Shift schedule for employee $employee_id (shift_id: $shift_id):");
        error_log("  Mon (0=work, 1=off): " . $working_days[1]);
        error_log("  Tue (0=work, 1=off): " . $working_days[2]);
        error_log("  Wed (0=work, 1=off): " . $working_days[3]);
        error_log("  Thu (0=work, 1=off): " . $working_days[4]);
        error_log("  Fri (0=work, 1=off): " . $working_days[5]);
        error_log("  Sat (0=work, 1=off): " . $working_days[6]);
        error_log("  Sun (0=work, 1=off): " . $working_days[7]);
    }
    
    // Get holidays within the date range
    $holidays_query = "SELECT date FROM holidaycalendar WHERE date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $holidays_query);
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $holidays_result = mysqli_stmt_get_result($stmt);
    
    $holidays = [];
    while ($holiday = mysqli_fetch_assoc($holidays_result)) {
        $holidays[] = $holiday['date'];
    }
    
    // Check for days off in the selected range
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $current = clone $start;
    $days_off_in_range = [];
    $holidays_in_range = [];
    
    while ($current <= $end) {
        $current_date = $current->format('Y-m-d');
        $day_of_week = (int)$current->format('N');
        $day_name = $current->format('l');
        
        // Check if it's a holiday
        if (in_array($current_date, $holidays)) {
            $holidays_in_range[] = [
                'date' => $current_date,
                'day' => $day_name
            ];
        }
        // Check if it's a day off (value = 1)
        else if (isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 1) {
            $days_off_in_range[] = [
                'date' => $current_date,
                'day' => $day_name
            ];
        }
        
        $current->add(new DateInterval('P1D'));
    }
    
    // If there are days off in the range, return error
    if (!empty($days_off_in_range)) {
        error_log("Employee $employee_id tried to apply leave on scheduled days off:");
        foreach ($days_off_in_range as $day_off) {
            error_log("  - " . $day_off['date'] . " ({$day_off['day']})");
        }
        return -1; // Special return value indicating days off in range
    }
    
    // Now calculate working days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $current = clone $start;
    $working_days_count = 0;
    
    while ($current <= $end) {
        $current_date = $current->format('Y-m-d');
        $day_of_week = (int)$current->format('N');
        
        // Count if it's a working day (is_[day] = 0) AND not a holiday
        $is_working_day = isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 0;
        $is_not_holiday = !in_array($current_date, $holidays);
        
        if ($is_working_day && $is_not_holiday) {
            $working_days_count++;
        }
        
        $current->add(new DateInterval('P1D'));
    }
    
    error_log("Result for employee $employee_id: $working_days_count working days");
    return $working_days_count;
}

// CORRECTED function to get shift total hours with better error handling
function getShiftTotalHours($conn, $shift_id) {
    // First, let's check what columns exist in the shifts table
    $check_query = "SHOW COLUMNS FROM shifts";
    $check_result = mysqli_query($conn, $check_query);
    $columns = [];
    while ($row = mysqli_fetch_assoc($check_result)) {
        $columns[] = $row['Field'];
    }
    
    // Check if we have time_in and time_out columns instead of start_time and end_time
    if (in_array('time_in', $columns) && in_array('time_out', $columns)) {
        $query = "SELECT TIMEDIFF(time_out, time_in) as shift_duration FROM shifts WHERE shift_id = ?";
    } 
    // Check if we have start_time and end_time columns
    else if (in_array('start_time', $columns) && in_array('end_time', $columns)) {
        $query = "SELECT TIMEDIFF(end_time, start_time) as shift_duration FROM shifts WHERE shift_id = ?";
    }
    // Check if we have total_hours column
    else if (in_array('total_hours', $columns)) {
        $query = "SELECT total_hours as shift_duration FROM shifts WHERE shift_id = ?";
    }
    // Check if we have hours column
    else if (in_array('hours', $columns)) {
        $query = "SELECT hours as shift_duration FROM shifts WHERE shift_id = ?";
    }
    // Default to a hardcoded value if no time columns found
    else {
        error_log("No time columns found in shifts table for shift_id: $shift_id, using default 8 hours");
        return '08:00:00';
    }
    
    $stmt = mysqli_prepare($conn, $query);
    
    // Check if prepare failed
    if (!$stmt) {
        error_log("Failed to prepare query in getShiftTotalHours: " . mysqli_error($conn));
        error_log("Query was: $query");
        error_log("Shift ID: $shift_id");
        return '08:00:00';
    }
    
    if (!mysqli_stmt_bind_param($stmt, "i", $shift_id)) {
        error_log("Failed to bind parameters in getShiftTotalHours: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return '08:00:00';
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Failed to execute query in getShiftTotalHours: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return '08:00:00';
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $shift_duration = $row['shift_duration'] ?? '08:00:00';
        
        // If the duration is in decimal hours (like 8.00), convert to time format
        if (strpos($shift_duration, ':') === false && is_numeric($shift_duration)) {
            $hours = floor($shift_duration);
            $minutes = ($shift_duration - $hours) * 60;
            $shift_duration = sprintf('%02d:%02d:00', $hours, $minutes);
        }
        
        mysqli_stmt_close($stmt);
        return $shift_duration;
    }
    
    mysqli_stmt_close($stmt);
    error_log("Shift not found for shift_id: $shift_id, using default 8 hours");
    return '08:00:00';
}

// CORRECTED function to update DTR for approved leave dates (ONLY working days)
function updateDTRForLeave($conn, $employee_id, $start_date, $end_date, $leave_type_id) {
    // Get employee's shift_id
    $shift_query = "SELECT shift_id FROM employees WHERE employee_id = ?";
    $stmt = mysqli_prepare($conn, $shift_query);
    
    if (!$stmt) {
        error_log("Failed to prepare shift query in updateDTRForLeave: " . mysqli_error($conn));
        return ['success' => false, 'processed_days' => 0];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $employee_id);
    mysqli_stmt_execute($stmt);
    $shift_result = mysqli_stmt_get_result($stmt);
    
    if (!$shift_result || mysqli_num_rows($shift_result) == 0) {
        error_log("Employee not found or no shift assigned for employee_id: $employee_id");
        mysqli_stmt_close($stmt);
        return ['success' => false, 'processed_days' => 0];
    }
    
    $shift_row = mysqli_fetch_assoc($shift_result);
    $shift_id = $shift_row['shift_id'] ?? 1;
    mysqli_stmt_close($stmt);
    
    // Get shift working days - 0 = working day, 1 = day off
    $shift_days_query = "SELECT is_monday, is_tuesday, is_wednesday, is_thursday, 
                               is_friday, is_saturday, is_sunday 
                        FROM shiftdays WHERE shift_id = ?";
    $stmt = mysqli_prepare($conn, $shift_days_query);
    
    if (!$stmt) {
        error_log("Failed to prepare shift days query in updateDTRForLeave: " . mysqli_error($conn));
        return ['success' => false, 'processed_days' => 0];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $shift_id);
    mysqli_stmt_execute($stmt);
    $shift_days_result = mysqli_stmt_get_result($stmt);
    
    // Initialize working days array
    $working_days = [];
    if ($shift_days_result && mysqli_num_rows($shift_days_result) > 0) {
        $shift_days = mysqli_fetch_assoc($shift_days_result);
        $working_days = [
            1 => (int)$shift_days['is_monday'],      // Monday (0=working, 1=off)
            2 => (int)$shift_days['is_tuesday'],     // Tuesday (0=working, 1=off)
            3 => (int)$shift_days['is_wednesday'],   // Wednesday (0=working, 1=off)
            4 => (int)$shift_days['is_thursday'],    // Thursday (0=working, 1=off)
            5 => (int)$shift_days['is_friday'],      // Friday (0=working, 1=off)
            6 => (int)$shift_days['is_saturday'],    // Saturday (0=working, 1=off)
            7 => (int)$shift_days['is_sunday']       // Sunday (0=working, 1=off)
        ];
    } else {
        // No shift days configured - cannot process DTR
        error_log("No shift days configured for shift_id: $shift_id. Cannot update DTR.");
        return ['success' => false, 'processed_days' => 0];
    }
    
    mysqli_stmt_close($stmt);
    
    // Get holidays within the date range
    $holidays_query = "SELECT date FROM holidaycalendar WHERE date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $holidays_query);
    
    if (!$stmt) {
        error_log("Failed to prepare holidays query in updateDTRForLeave: " . mysqli_error($conn));
        return ['success' => false, 'processed_days' => 0];
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $holidays_result = mysqli_stmt_get_result($stmt);
    
    $holidays = [];
    while ($holiday = mysqli_fetch_assoc($holidays_result)) {
        $holidays[] = $holiday['date'];
    }
    mysqli_stmt_close($stmt);
    
    // Get total hours for the shift
    $total_work_time = getShiftTotalHours($conn, $shift_id);
    
    // Create date range for leave period
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $current = clone $start;
    
    $success_count = 0;
    $error_count = 0;
    $holiday_skipped_count = 0;
    $day_off_skipped_count = 0;
    $processed_days = 0;
    
    while ($current <= $end) {
        $date = $current->format('Y-m-d');
        $day_of_week = (int)$current->format('N');
        $day_name = $current->format('l');
        
        // Skip holidays - don't create DTR for holidays
        if (in_array($date, $holidays)) {
            $holiday_skipped_count++;
            $current->add(new DateInterval('P1D'));
            continue;
        }
        
        // Check if it's a day off (value = 1) - DO NOT apply leave on days off
        if (isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 1) {
            // This is a day off - DO NOT apply leave
            $day_off_skipped_count++;
            $current->add(new DateInterval('P1D'));
            continue;
        }
        
        // Check if it's a working day (value = 0) - ONLY apply leave on working days
        $is_working_day = isset($working_days[$day_of_week]) && $working_days[$day_of_week] === 0;
        if (!$is_working_day) {
            // Not a working day (should not happen with proper data)
            $day_off_skipped_count++;
            $current->add(new DateInterval('P1D'));
            continue;
        }
        
        // Check if DTR entry already exists for this date
        $check_dtr_query = "SELECT dtr_id FROM employeedtr WHERE employee_id = ? AND date = ?";
        $stmt = mysqli_prepare($conn, $check_dtr_query);
        
        if (!$stmt) {
            error_log("Failed to prepare check DTR query: " . mysqli_error($conn));
            $error_count++;
            $current->add(new DateInterval('P1D'));
            continue;
        }
        
        mysqli_stmt_bind_param($stmt, "is", $employee_id, $date);
        mysqli_stmt_execute($stmt);
        $check_dtr = mysqli_stmt_get_result($stmt);
        
        if (!$check_dtr) {
            error_log("Error checking DTR for employee_id: $employee_id, date: $date - " . mysqli_error($conn));
            mysqli_stmt_close($stmt);
            $error_count++;
            $current->add(new DateInterval('P1D'));
            continue;
        }
        
        if (mysqli_num_rows($check_dtr) > 0) {
            mysqli_stmt_close($stmt);
            
            // Update existing DTR entry - ONLY for working days
            $update_dtr = "UPDATE employeedtr SET 
                day_type_id = 12,
                leave_type_id = ?,
                total_work_time = ?,
                time_in = NULL,
                time_out = NULL,
                break_in = NULL,
                break_out = NULL,
                undertime_time = '00:00:00',
                late_time = '00:00:00',
                overtime_time = 0.00,
                night_time = '00:00:00',
                approval_status = 'Approved',
                remarks = 'On Leave',
                is_manual = 1,
                updated_at = NOW()
                WHERE employee_id = ? AND date = ?";
            
            $stmt = mysqli_prepare($conn, $update_dtr);
            if (!$stmt) {
                error_log("Failed to prepare update DTR query: " . mysqli_error($conn));
                $error_count++;
                $current->add(new DateInterval('P1D'));
                continue;
            }
            
            mysqli_stmt_bind_param($stmt, "isis", $leave_type_id, $total_work_time, $employee_id, $date);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result) {
                $success_count++;
                $processed_days++;
            } else {
                error_log("Error updating DTR for employee_id: $employee_id, date: $date - " . mysqli_stmt_error($stmt));
                $error_count++;
            }
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);
            
            // Insert new DTR entry - ONLY for working days
            $insert_dtr = "INSERT INTO employeedtr (
                employee_id, date, day_of_week, shift_id, day_type_id, leave_type_id,
                total_work_time, time_in, time_out, break_in, break_out,
                undertime_time, late_time, overtime_time, night_time,
                approval_status, remarks, is_flexible, is_manual, has_missing_log,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, 12, ?,
                ?, NULL, NULL, NULL, NULL,
                '00:00:00', '00:00:00', 0.00, '00:00:00',
                'Approved', 'On Leave', 0, 1, 0,
                NOW(), NOW()
            )";
            
            $stmt = mysqli_prepare($conn, $insert_dtr);
            if (!$stmt) {
                error_log("Failed to prepare insert DTR query: " . mysqli_error($conn));
                $error_count++;
                $current->add(new DateInterval('P1D'));
                continue;
            }
            
            mysqli_stmt_bind_param($stmt, "issiis", $employee_id, $date, $day_name, $shift_id, $leave_type_id, $total_work_time);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result) {
                $success_count++;
                $processed_days++;
            } else {
                error_log("Error inserting DTR for employee_id: $employee_id, date: $date - " . mysqli_stmt_error($stmt));
                $error_count++;
            }
            mysqli_stmt_close($stmt);
        }
        
        $current->add(new DateInterval('P1D'));
    }
    
    error_log("DTR Update Summary - Employee ID: $employee_id");
    error_log("  Success: $success_count, Errors: $error_count");
    error_log("  Holidays skipped: $holiday_skipped_count");
    error_log("  Days off skipped (value=1): $day_off_skipped_count");
    error_log("  Working days processed (value=0): $processed_days");
    
    return [
        'success' => $error_count == 0,
        'processed_days' => $processed_days
    ];
}

// ADD LEAVE REQUEST
if (isset($_POST['add_leave_request'])) {
    $employee_id = $_POST['employee_id'];
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    if (empty($employee_id) || empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
        $_SESSION['error'] = 'All fields are required!';
        header("Location: leave_requests.php");
        exit();
    }
    
    if (strtotime($start_date) > strtotime($end_date)) {
        $_SESSION['error'] = 'End date must be after start date!';
        header("Location: leave_requests.php");
        exit();
    }
    
    // Check if employee exists
    $check_employee_query = "SELECT employee_id FROM employees WHERE employee_id = ?";
    $stmt = mysqli_prepare($conn, $check_employee_query);
    mysqli_stmt_bind_param($stmt, "i", $employee_id);
    mysqli_stmt_execute($stmt);
    $check_employee = mysqli_stmt_get_result($stmt);
    
    if (!$check_employee || mysqli_num_rows($check_employee) == 0) {
        $_SESSION['error'] = 'Employee not found!';
        header("Location: leave_requests.php");
        exit();
    }
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $total_days = $start->diff($end)->days + 1;
    $actual_leave_days = calculateWorkingDays($conn, $start_date, $end_date, $employee_id);
    
    // Check if employee tried to apply for leave on scheduled days off
    if ($actual_leave_days === -1) {
        // Get the specific days off in the range
        $shift_info_query = "SELECT s.name as shift_name, 
                             sd.is_monday, sd.is_tuesday, sd.is_wednesday, 
                             sd.is_thursday, sd.is_friday, sd.is_saturday, sd.is_sunday
                      FROM employees e
                      LEFT JOIN shifts s ON e.shift_id = s.shift_id
                      LEFT JOIN shiftdays sd ON e.shift_id = sd.shift_id
                      WHERE e.employee_id = ?";
        $stmt = mysqli_prepare($conn, $shift_info_query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $shift_info = mysqli_stmt_get_result($stmt);
        
        if ($shift_info && mysqli_num_rows($shift_info) > 0) {
            $shift_data = mysqli_fetch_assoc($shift_info);
            $shift_name = $shift_data['shift_name'] ?? 'Unknown Shift';
            
            // Find which specific days in the range are days off
            $days_off_list = [];
            $days_map = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $start_obj = new DateTime($start_date);
            $end_obj = new DateTime($end_date);
            $current = clone $start_obj;
            
            while ($current <= $end_obj) {
                $current_date = $current->format('Y-m-d');
                $day_of_week = (int)$current->format('N');
                $day_name = $current->format('l');
                
                $day_field = 'is_' . strtolower($day_name);
                if (isset($shift_data[$day_field]) && $shift_data[$day_field] == 1) {
                    $days_off_list[] = $current_date . " ($day_name)";
                }
                
                $current->add(new DateInterval('P1D'));
            }
            
            if (!empty($days_off_list)) {
                $error_msg = '<strong>Cannot apply for leave on scheduled days off!</strong><br><br>';
                $error_msg .= 'The following dates are your scheduled days off in shift "' . $shift_name . '":<br>';
                $error_msg .= '<ul>';
                foreach ($days_off_list as $day_off) {
                    $error_msg .= '<li>' . $day_off . '</li>';
                }
                $error_msg .= '</ul><br>';
                $error_msg .= 'You cannot apply for leave on your scheduled days off. Please adjust your leave dates to only include your working days.';
                
                $_SESSION['error'] = $error_msg;
                header("Location: leave_requests.php");
                exit();
            }
        }
        
        $_SESSION['error'] = 'Cannot apply for leave on scheduled days off! Please adjust your leave dates.';
        header("Location: leave_requests.php");
        exit();
    }
    
    if ($actual_leave_days == 0) {
        // Get shift info to provide a helpful error message
        $shift_info_query = "SELECT s.name as shift_name, 
                             sd.is_monday, sd.is_tuesday, sd.is_wednesday, 
                             sd.is_thursday, sd.is_friday, sd.is_saturday, sd.is_sunday
                      FROM employees e
                      LEFT JOIN shifts s ON e.shift_id = s.shift_id
                      LEFT JOIN shiftdays sd ON e.shift_id = sd.shift_id
                      WHERE e.employee_id = ?";
        $stmt = mysqli_prepare($conn, $shift_info_query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        $shift_info = mysqli_stmt_get_result($stmt);
        
        $error_msg = 'Selected date range contains no working days for this employee! ';
        $error_msg .= '<strong>Important:</strong> Leave can only be applied to working days (where shift value = 0). ';
        $error_msg .= 'Days off (where shift value = 1) and holidays are NOT counted as leave days.';
        
        if ($shift_info && mysqli_num_rows($shift_info) > 0) {
            $shift_data = mysqli_fetch_assoc($shift_info);
            $shift_name = $shift_data['shift_name'] ?? 'Unknown Shift';
            
            // Build list of working days and days off
            $working_days_list = [];
            $days_off_list = [];
            $days_map = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            for ($i = 1; $i <= 7; $i++) {
                $day_field = 'is_' . strtolower($days_map[$i-1]);
                if (isset($shift_data[$day_field])) {
                    if ($shift_data[$day_field] == 0) {
                        $working_days_list[] = $days_map[$i-1];  // Working day
                    } else {
                        $days_off_list[] = $days_map[$i-1];      // Day off
                    }
                }
            }
            
            if (!empty($working_days_list)) {
                $error_msg .= "<br><br><strong>Employee Shift Schedule ($shift_name):</strong><br>";
                $error_msg .= "✓ Working Days (0): " . implode(', ', $working_days_list) . "<br>";
                if (!empty($days_off_list)) {
                    $error_msg .= "✗ Days Off (1): " . implode(', ', $days_off_list) . "<br>";
                }
                $error_msg .= "<br><strong>Note:</strong> You cannot apply for leave on days marked as 'Days Off' (✗).";
                $error_msg .= "<br>Selected dates may be holidays, days off, or outside working days.";
            } else {
                $error_msg .= "<br><br><strong>Employee has no working days configured in shift '$shift_name'.</strong><br>";
                $error_msg .= "All days are marked as days off (value = 1).<br>";
                $error_msg .= "Please configure shift schedule with working days (value = 0) first.";
            }
        } else {
            $error_msg .= "<br><br>Employee shift schedule not configured.";
        }
        
        $_SESSION['error'] = $error_msg;
        header("Location: leave_requests.php");
        exit();
    }
    
    $insert_query = "INSERT INTO EmployeeLeaveRequests (employee_id, leave_type_id, start_date, end_date, total_days, actual_leave_days, reason) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "iisssis", $employee_id, $leave_type_id, $start_date, $end_date, $total_days, $actual_leave_days, $reason);
    $insert = mysqli_stmt_execute($stmt);
    
    if ($insert) {
        $_SESSION['success'] = 'Leave request submitted successfully! ';
        $_SESSION['success'] .= '<strong>Note:</strong> Leave is only deducted from working days (shift value = 0). ';
        $_SESSION['success'] .= 'Days off (shift value = 1) and holidays are NOT counted.';
    } else {
        $_SESSION['error'] = 'Failed to submit leave request. Database error: ' . mysqli_error($conn);
    }
    
    header("Location: leave_requests.php");
    exit();
}

// UPDATE LEAVE REQUEST STATUS
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $approved_by = $_POST['approved_by'];
    $approval_remarks = $_POST['approval_remarks'];
    
    if (empty($request_id) || empty($status) || empty($approved_by)) {
        $_SESSION['error'] = 'All required fields must be filled!';
        header("Location: leave_requests.php");
        exit();
    }
    
    $get_request_query = "SELECT * FROM EmployeeLeaveRequests WHERE leave_request_id = ?";
    $stmt = mysqli_prepare($conn, $get_request_query);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $get_request = mysqli_stmt_get_result($stmt);
    
    if (!$get_request || mysqli_num_rows($get_request) == 0) {
        $_SESSION['error'] = 'Leave request not found!';
        header("Location: leave_requests.php");
        exit();
    }
    
    $request_data = mysqli_fetch_assoc($get_request);
    
    if ($request_data['status'] == 'Approved' && $status != 'Approved') {
        $_SESSION['error'] = 'Cannot change status of already approved leave request!';
        header("Location: leave_requests.php");
        exit();
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        if ($status != 'Pending') {
            $update_query = "UPDATE EmployeeLeaveRequests 
                            SET status=?, approved_by=?, approved_at=NOW(), approval_remarks=? 
                            WHERE leave_request_id=?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $approved_by, $approval_remarks, $request_id);
        } else {
            $update_query = "UPDATE EmployeeLeaveRequests 
                            SET status=?, approved_by=?, approved_at=NULL, approval_remarks=? 
                            WHERE leave_request_id=?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $approved_by, $approval_remarks, $request_id);
        }
        
        $update = mysqli_stmt_execute($stmt);
        
        if (!$update) {
            throw new Exception('Failed to update leave request status: ' . mysqli_error($conn));
        }
        
        if ($status == 'Approved') {
            $dtr_result = updateDTRForLeave(
                $conn, 
                $request_data['employee_id'], 
                $request_data['start_date'], 
                $request_data['end_date'], 
                $request_data['leave_type_id']
            );
            
            if (!$dtr_result['success']) {
                throw new Exception('Failed to update DTR records for approved leave');
            }
            
            // Update the actual_leave_days with the processed count
            $update_days_query = "UPDATE EmployeeLeaveRequests 
                                 SET actual_leave_days = ? 
                                 WHERE leave_request_id = ?";
            $stmt = mysqli_prepare($conn, $update_days_query);
            mysqli_stmt_bind_param($stmt, "ii", $dtr_result['processed_days'], $request_id);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['success'] = 'Leave request approved and DTR updated successfully! ' . 
                                  $dtr_result['processed_days'] . ' working days processed. ';
            $_SESSION['success'] .= '<strong>Note:</strong> Leave is only applied to working days (shift value = 0). ';
            $_SESSION['success'] .= 'Days off (shift value = 1) and holidays are skipped.';
        } else if ($status == 'Rejected') {
            $_SESSION['success'] = 'Leave request rejected successfully!';
        } else {
            $_SESSION['success'] = 'Leave request status updated successfully!';
        }
        
        mysqli_commit($conn);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        error_log("Leave request update error: " . $e->getMessage());
    }
    
    header("Location: leave_requests.php");
    exit();
}

// DELETE LEAVE REQUEST
if (isset($_POST['delete_leave_request'])) {
    $id = $_POST['delete_id'];
    
    if (empty($id)) {
        $_SESSION['error'] = 'Invalid leave request ID!';
        header("Location: leave_requests.php");
        exit();
    }
    
    $check_status_query = "SELECT status FROM EmployeeLeaveRequests WHERE leave_request_id=?";
    $stmt = mysqli_prepare($conn, $check_status_query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $check_status = mysqli_stmt_get_result($stmt);
    
    if (!$check_status || mysqli_num_rows($check_status) == 0) {
        $_SESSION['error'] = 'Leave request not found!';
        header("Location: leave_requests.php");
        exit();
    }
    
    $status_row = mysqli_fetch_assoc($check_status);
    
    if ($status_row['status'] == 'Approved') {
        $_SESSION['error'] = 'Cannot delete approved leave requests!';
    } else {
        $delete_query = "DELETE FROM EmployeeLeaveRequests WHERE leave_request_id=?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $delete = mysqli_stmt_execute($stmt);
        
        if ($delete) {
            $_SESSION['success'] = 'Leave request deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete leave request. Database error: ' . mysqli_error($conn);
        }
    }
    
    header("Location: leave_requests.php");
    exit();
}
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<!-- Main Content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Employee Leave Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Leave Management</li>
              <li class="breadcrumb-item active" aria-current="page">Leave Requests</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Leave Requests</h5>
              <small class="text-muted">Manage employee leave requests</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Leave Request
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="requestTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Processed</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $result = mysqli_query($conn, "SELECT lr.*, 
                                                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                                                 lt.name as leave_type_name,
                                                 u.username as approver_name
                                                 FROM EmployeeLeaveRequests lr
                                                 LEFT JOIN Employees e ON lr.employee_id = e.employee_id
                                                 LEFT JOIN LeaveTypes lt ON lr.leave_type_id = lt.leave_type_id
                                                 LEFT JOIN users u ON lr.approved_by = u.user_id
                                                 ORDER BY lr.requested_at DESC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark -secodary"><?= $row['leave_request_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['employee_name']); ?></td>
                    <td><?= htmlspecialchars($row['leave_type_name']); ?></td>
                    <td><?= date('M d, Y', strtotime($row['start_date'])); ?></td>
                    <td><?= date('M d, Y', strtotime($row['end_date'])); ?></td>
                    <td><?= $row['total_days']; ?> days</td>
                    <td>
                      <?php if ($row['status'] == 'Approved'): ?>
                        <?= $row['actual_leave_days']; ?> days
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($row['status'] == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($row['status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($row['requested_at'])); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-employee="<?= htmlspecialchars($row['employee_name']); ?>"
                          data-leave-type="<?= htmlspecialchars($row['leave_type_name']); ?>"
                          data-start="<?= $row['start_date']; ?>"
                          data-end="<?= $row['end_date']; ?>"
                          data-days="<?= $row['actual_leave_days']; ?>"
                          data-reason="<?= htmlspecialchars($row['reason']); ?>"
                          data-status="<?= $row['status']; ?>"
                          data-approver="<?= htmlspecialchars($row['approver_name']); ?>"
                          data-approval-remarks="<?= htmlspecialchars($row['approval_remarks']); ?>"
                          title="View Details"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning statusBtn"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-status="<?= $row['status']; ?>"
                          title="Update Status"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-status="<?= $row['status']; ?>"
                          title="<?= ($row['status'] == 'Approved') ? 'Cannot delete approved requests' : 'Delete'; ?>"
                          <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>><i class="ti ti-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Select Employee</option>
            <?php
            $employees = mysqli_query($conn, "SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM Employees WHERE status='Active' ORDER BY first_name");
            while ($emp = mysqli_fetch_assoc($employees)):
            ?>
            <option value="<?= $emp['employee_id']; ?>"><?= htmlspecialchars($emp['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Leave Type</label>
          <select name="leave_type_id" class="form-select" required>
            <option value="">Select Leave Type</option>
            <?php
            $leave_types = mysqli_query($conn, "SELECT leave_type_id, name FROM LeaveTypes ORDER BY name");
            while ($lt = mysqli_fetch_assoc($leave_types)):
            ?>
            <option value="<?= $lt['leave_type_id']; ?>"><?= htmlspecialchars($lt['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="3" required placeholder="Enter reason for leave..."></textarea>
        </div>
        <div class="alert alert-warning">
          <i class="ti ti-alert-triangle me-2"></i>
          <strong>Important:</strong> You cannot apply for leave on your scheduled days off (shift value = 1). 
          Leave can only be applied to working days (shift value = 0). Holidays are also not counted.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_leave_request" class="btn btn-primary"><i class="ti ti-check me-1"></i>Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>Leave Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <p><strong>Employee:</strong> <span id="view_employee"></span></p>
            <p><strong>Leave Type:</strong> <span id="view_leave_type"></span></p>
            <p><strong>Start Date:</strong> <span id="view_start_date"></span></p>
          </div>
          <div class="col-md-6">
            <p><strong>End Date:</strong> <span id="view_end_date"></span></p>
            <p><strong>Days:</strong> <span id="view_days"></span></p>
            <p><strong>Status:</strong> <span id="view_status"></span></p>
          </div>
        </div>
        <div class="mb-3">
          <strong>Reason:</strong>
          <p id="view_reason" class="mt-2"></p>
        </div>
        <div id="approval_section" style="display: none;">
          <hr>
          <p><strong>Approved by:</strong> <span id="view_approver"></span></p>
          <div class="mb-3">
            <strong>Approval Remarks:</strong>
            <p id="view_approval_remarks" class="mt-2"></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="ti ti-edit me-2"></i>Update Leave Status
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="request_id" id="status_request_id">

        <div class="mb-3">
          <label class="form-label">Status</label>
          <select name="status" id="status_select" class="form-select" required>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Approval Remarks</label>
          <textarea
            name="approval_remarks"
            id="approval_remarks"
            class="form-control"
            rows="3"
            placeholder="Optional remarks..."
          ></textarea>
        </div>

        <input type="hidden" name="approved_by" value="<?= $_SESSION['user_id']; ?>">

        <div class="alert alert-info mt-3">
          <i class="ti ti-info-circle me-2"></i>
          <strong>Note:</strong>
          When approving leave, it will only be applied to working days
          (shift value = 0). Days off (shift value = 1) and holidays will be skipped.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" name="update_status" class="btn btn-warning">
          <i class="ti ti-device-floppy me-1"></i>Update
        </button>
      </div>

    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_leave_request" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#requestTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search leave requests...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching leave requests found',
      emptyTable: 'No leave requests available',
      paginate: {
        first: 'First',
        last: 'Last', 
        next: 'Next',
        previous: 'Previous'
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, width: '60px', className: 'text-center' },
      { targets: 9, orderable: false, className: 'text-center' }
    ],
    order: [[0, 'desc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.viewBtn').off().on('click', function () {
      $('#view_employee').text($(this).data('employee'));
      $('#view_leave_type').text($(this).data('leave-type'));
      $('#view_start_date').text(formatDate($(this).data('start')));
      $('#view_end_date').text(formatDate($(this).data('end')));
      $('#view_days').text($(this).data('days') + ' days');
      $('#view_reason').text($(this).data('reason'));
      
      const status = $(this).data('status');
      let statusBadge = '';
      if (status === 'Pending') {
        statusBadge = '<span class="badge bg-warning">Pending</span>';
      } else if (status === 'Approved') {
        statusBadge = '<span class="badge bg-success">Approved</span>';
      } else {
        statusBadge = '<span class="badge bg-danger">Rejected</span>';
      }
      $('#view_status').html(statusBadge);
      
      if (status !== 'Pending') {
        const approverName = $(this).data('approver');
        $('#view_approver').text(approverName || 'N/A');
        $('#view_approval_remarks').text($(this).data('approval-remarks') || 'No remarks');
        $('#approval_section').show();
      } else {
        $('#approval_section').hide();
      }
      
      new bootstrap.Modal(document.getElementById('viewModal')).show();
    });

    $('.statusBtn').off().on('click', function () {
      const requestId = $(this).data('id');
      const currentStatus = $(this).data('status');
      
      $('#status_request_id').val(requestId);
      $('#status_select').val(currentStatus);
      
      // If already approved, disable changing to other statuses
      if (currentStatus === 'Approved') {
        $('#status_select').prop('disabled', true);
        Swal.fire({
          title: 'Cannot Change Status',
          text: 'This leave request has already been approved and cannot be changed.',
          icon: 'warning',
          confirmButtonText: 'OK'
        });
      } else {
        $('#status_select').prop('disabled', false);
      }
      
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      const status = $(this).data('status');
      
      // Check if the request is approved
      if (status === 'Approved') {
        Swal.fire({
          title: 'Cannot Delete',
          text: 'Approved leave requests cannot be deleted!',
          icon: 'error',
          confirmButtonText: 'OK'
        });
        return;
      }
      
      Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          $('#delete_id').val(id);
          $('#deleteForm').submit();
        }
      });
    });
  }

  // Date validation with AJAX check for days off
  $('input[name="start_date"], input[name="end_date"], select[name="employee_id"]').on('change', function() {
    const employeeId = $('#addModal select[name="employee_id"]').val();
    const startDate = $('#addModal input[name="start_date"]').val();
    const endDate = $('#addModal input[name="end_date"]').val();
    
    // Set date ranges
    if (startDate) {
      $('#addModal input[name="end_date"]').attr('min', startDate);
    }
    if (endDate) {
      $('#addModal input[name="start_date"]').attr('max', endDate);
    }
    
    // Clear previous warnings
    $('#daysOffWarning, #holidaysWarning, #workingDaysInfo').remove();
    
    // Check for days off via AJAX if all fields are filled
    if (employeeId && startDate && endDate && startDate <= endDate) {
      $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
          ajax_calculate_days: 1,
          employee_id: employeeId,
          start_date: startDate,
          end_date: endDate
        },
        dataType: 'json',
        success: function(response) {
          if (response.success === false) {
            // Show error for days off
            $('#addModal .modal-body').prepend(
              '<div class="alert alert-danger alert-dismissible fade show" id="daysOffWarning">' +
              '<i class="ti ti-ban me-2"></i>' +
              '<strong>Cannot Apply Leave!</strong><br>' + 
              response.error + '<br>' +
              (response.details || '') +
              '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
              '</div>'
            );
          } else if (response.success === true) {
            if (response.working_days === 0) {
              $('#addModal .modal-body').prepend(
                '<div class="alert alert-warning alert-dismissible fade show" id="workingDaysInfo">' +
                '<i class="ti ti-alert-triangle me-2"></i>' +
                'This date range contains <strong>0 working days</strong>.' +
                (response.warning ? '<br>' + response.warning : '') +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>'
              );
            } else {
              $('#addModal .modal-body').append(
                '<div class="alert alert-success alert-dismissible fade show" id="workingDaysInfo">' +
                '<i class="ti ti-calendar-check me-2"></i>' +
                'This date range contains <strong>' + response.working_days + ' working days</strong> for leave.' +
                (response.warning ? '<br>' + response.warning : '') +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>'
              );
            }
          }
        },
        error: function() {
          console.log('Error checking working days');
        }
      });
    }
  });

  // Set minimum date to today for new requests
  const today = new Date().toISOString().split('T')[0];
  $('input[name="start_date"], input[name="end_date"]').attr('min', today);

  // Form validation before submit
  $('#addModal form').on('submit', function(e) {
    const startDate = $('input[name="start_date"]').val();
    const endDate = $('input[name="end_date"]').val();
    const employeeId = $('select[name="employee_id"]').val();
    
    if (startDate && endDate && startDate > endDate) {
      e.preventDefault();
      Swal.fire({
        title: 'Invalid Date Range',
        text: 'End date must be after or equal to start date!',
        icon: 'error',
        confirmButtonText: 'OK'
      });
      return false;
    }
    
    if (!employeeId) {
      e.preventDefault();
      Swal.fire({
        title: 'Missing Information',
        text: 'Please select an employee!',
        icon: 'error',
        confirmButtonText: 'OK'
      });
      return false;
    }
    
  if ($('#daysOffWarning').length > 0) {
  e.preventDefault();
  Swal.fire({
    toast: true,
    icon: 'error',
    title: 'Cannot apply leave',
    text: $('#daysOffWarning').text().replace('Cannot Apply Leave!', '').trim(),
    position: 'top-end',
    showConfirmButton: false,
    timer: 6000,
    timerProgressBar: true
  });
      return false;
    }
  });

  $('#statusModal form').on('submit', function (e) {
  const status = $('#status_select').val();
  const approver = $('input[name="approved_by"]').val();

  if (!status || !approver) {
    e.preventDefault();
    Swal.fire({
      title: 'Missing Information',
      text: 'Please select both status and approver!',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return false;
  }
});


  // Search box styling
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  // Reset form when modal is closed
  $('#addModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    $('input[name="end_date"]').removeAttr('min');
    $('input[name="start_date"]').removeAttr('max');
    $('#daysOffWarning, #holidaysWarning, #workingDaysInfo').remove();
  });

  $('#statusModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    $('#status_select').prop('disabled', false);
  });
// Success toast
  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    toast: true,
    icon: 'success',
    title: '<?= addslashes($_SESSION['success']); ?>',
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
  });
  <?php unset($_SESSION['success']); endif; ?>

  // Error toast
  <?php if (isset($_SESSION['error'])): ?>
  Swal.fire({
    toast: true,
    icon: 'error',
    title: '<?= addslashes($_SESSION['error']); ?>',
    position: 'top-end',
    showConfirmButton: false,
    timer: 6000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
  });
  <?php unset($_SESSION['error']); endif; ?>

  // Initialize tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();

});
// Additional utility functions
function formatDate(dateString) {
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('en-US', options);
}

function calculateDays() {
  const startDate = $('input[name="start_date"]').val();
  const endDate = $('input[name="end_date"]').val();
  
  if (startDate && endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    
    // You could display this to the user if needed
    console.log('Total days selected:', diffDays);
  }
}
</script>

</body>
</html>