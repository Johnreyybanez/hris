<?php
session_start();
ini_set('max_execution_time', 600); // 10 minutes
set_time_limit(600);
include 'connection.php'; // $conn (MySQLi)
include 'head.php';
include 'sidebar.php';
include 'header.php';
$status = "";
$display_rows = [];
$debug_info = [];
$default_mdb_path = "C:\Users\Administrator\Desktop\attBackup.mdb";
$employees_with_no_logs = [];
$preserved_records_count = 0;

function minutesToTime($minutes)
{
    if ($minutes <= 0) return '00:00';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

function timeDifferenceInMinutes($start_time, $end_time)
{
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $diff = $start->diff($end);
    return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
}

function applyAttendanceRules($conn, $late_minutes, $undertime_minutes)
{
    $rules = [
        'late_grace' => 0,
        'undertime_grace' => 0,
        'late_round' => 0,
        'undertime_round' => 0
    ];
    $result = mysqli_query($conn, "SELECT rule_type, threshold_minutes FROM timeattendancerules WHERE is_active = 1");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rules[$row['rule_type']] = (int)$row['threshold_minutes'];
        }
    }

    if ($late_minutes <= $rules['late_grace']) {
        $late_minutes = 0;
    } elseif ($rules['late_round'] > 0) {
        $late_minutes = ceil($late_minutes / $rules['late_round']) * $rules['late_round'];
    }

    if ($undertime_minutes <= $rules['undertime_grace']) {
        $undertime_minutes = 0;
    } elseif ($rules['undertime_round'] > 0) {
        $undertime_minutes = ceil($undertime_minutes / $rules['undertime_round']) * $rules['undertime_round'];
    }

    return [
        'late' => minutesToTime($late_minutes),
        'undertime' => minutesToTime($undertime_minutes)
    ];
}

/**
 * Calculate overtime hours based ONLY on time worked after scheduled time_out
 * (only above 30 minutes beyond scheduled end time)
 * and round DOWN to nearest 30 minutes (0.5 hours) with minimum 1.00 hours
 * @param DateTime $actual_out Actual clock out time
 * @param DateTime $expected_out Expected schedule time out
 * @return array Returns both formatted time string and decimal hours
 */
function calculateOvertime($actual_out, $expected_out)
{
    // Only calculate overtime if clocked out AFTER scheduled time_out
    if ($actual_out <= $expected_out) {
        return [
            'time' => '00:00',
            'decimal_hours' => 0.00
        ];
    }
    
    // Calculate minutes worked AFTER scheduled end time
    $diff = $expected_out->diff($actual_out);
    $overtime_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    // Only compute overtime if it's more than 30 minutes
    if ($overtime_minutes > 30) {
        // Convert to hours and round DOWN to nearest 30 minutes (0.5 hours)
        $overtime_hours = $overtime_minutes / 60;
        
        // Round DOWN to nearest 0.5 (30 minutes)
        $rounded_overtime = floor($overtime_hours * 2) / 2;
        
        // Only return if 1.00 hours or more
        if ($rounded_overtime >= 1.00) {
            return [
                'time' => minutesToTime($rounded_overtime * 60), // For display
                'decimal_hours' => round($rounded_overtime, 2)   // For database storage
            ];
        }
    }
    
    return [
        'time' => '00:00',
        'decimal_hours' => 0.00
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office = $_POST['office'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $db_path = $default_mdb_path;
    $preserve_manual_entries = isset($_POST['preserve_manual_entries']) && $_POST['preserve_manual_entries'] == '1';

    if (!file_exists($db_path)) {
        $status = "❌ MDB file not found at: <code>$db_path</code>";
    } else {
        $connStr = "Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$db_path;";
        $access_conn = @odbc_connect($connStr, '', '');
        if (!$access_conn) {
            $status = "❌ Failed to connect to Access database. <br><strong>ODBC Error:</strong> " . odbc_errormsg();
        } else {
            try {
                $mysql_employees = [];
                
                // Check if Employees table has department column or department_id
                $check_column = mysqli_query($conn, "SHOW COLUMNS FROM Employees LIKE 'department%'");
                $has_dept_column = false;
                $dept_column_name = '';
                
                if ($check_column && mysqli_num_rows($check_column) > 0) {
                    $col = mysqli_fetch_assoc($check_column);
                    $dept_column_name = $col['Field'];
                    $has_dept_column = true;
                }
                
                // Build query based on table structure
                if ($has_dept_column && $dept_column_name == 'department_id') {
                    $emp_query = "SELECT e.employee_id, e.biometric_id, e.first_name, e.last_name, e.shift_id, d.name as department_name 
                                  FROM Employees e 
                                  LEFT JOIN departments d ON e.department_id = d.department_id 
                                  WHERE e.biometric_id IS NOT NULL AND e.biometric_id != ''";
                    if ($office != "All") {
                        $emp_query .= " AND d.name = '" . mysqli_real_escape_string($conn, $office) . "'";
                    }
                } elseif ($has_dept_column && $dept_column_name == 'department') {
                    $emp_query = "SELECT employee_id, biometric_id, first_name, last_name, shift_id FROM Employees WHERE biometric_id IS NOT NULL AND biometric_id != ''";
                    if ($office != "All") {
                        $emp_query .= " AND department = '" . mysqli_real_escape_string($conn, $office) . "'";
                    }
                } else {
                    $emp_query = "SELECT employee_id, biometric_id, first_name, last_name, shift_id FROM Employees WHERE biometric_id IS NOT NULL AND biometric_id != ''";
                    if ($office != "All") {
                        $debug_info[] = "⚠️ Warning: Employees table has no department column. Fetching all employees.";
                    }
                }
                
                $emp_result = mysqli_query($conn, $emp_query);
                
                if (!$emp_result) {
                    throw new Exception("Employee query failed: " . mysqli_error($conn));
                }
                
                while ($emp_row = mysqli_fetch_assoc($emp_result)) {
                    $mysql_employees[$emp_row['biometric_id']] = $emp_row;
                }
                $debug_info[] = "✅ Found " . count($mysql_employees) . " employees in MySQL";

                $access_users = [];
                $userinfo_query = "SELECT USERID, BADGENUMBER, [NAME] FROM USERINFO";
                $userinfo_result = @odbc_exec($access_conn, $userinfo_query);
                
                if (!$userinfo_result) {
                    throw new Exception("USERINFO query failed: " . odbc_errormsg());
                }
                
                while ($user_row = odbc_fetch_array($userinfo_result)) {
                    $badge_number = trim($user_row['BADGENUMBER']);
                    if (isset($mysql_employees[$badge_number])) {
                        $access_users[$user_row['USERID']] = [
                            'badge_number' => $badge_number,
                            'mysql_data' => $mysql_employees[$badge_number]
                        ];
                    }
                }
                $debug_info[] = "✅ Matched " . count($access_users) . " users between Access and MySQL";

                if (empty($access_users)) {
                    $status = "⚠️ No matching employees found.";
                } else {
                    $user_ids = implode(',', array_keys($access_users));
                    $from_date_access = date('m/d/Y', strtotime($from_date));
                    $to_date_access = date('m/d/Y', strtotime($to_date));
                    
                    $checkinout_query = "SELECT USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT 
                                         WHERE USERID IN ($user_ids) 
                                         AND CHECKTIME BETWEEN #$from_date_access 00:00:00# AND #$to_date_access 23:59:59# 
                                         ORDER BY USERID, CHECKTIME";
                    
                    $checkinout_result = @odbc_exec($access_conn, $checkinout_query);
                    if (!$checkinout_result) throw new Exception("Failed to query CHECKINOUT: " . odbc_errormsg());

                    $raw_logs = [];
                    while ($row = odbc_fetch_array($checkinout_result)) {
                        $user_id = $row['USERID'];
                        $check_time = $row['CHECKTIME'];
                        $check_type = isset($row['CHECKTYPE']) ? trim($row['CHECKTYPE']) : '';
                        $date = date('Y-m-d', strtotime($check_time));
                        
                        $raw_logs[$user_id][$date][] = [
                            'time' => $check_time,
                            'type' => $check_type
                        ];
                    }
                    $debug_info[] = "✅ Retrieved raw logs for " . count($raw_logs) . " users with CHECKTYPE information";

                    $imported = 0;
                    $errors = 0;
                    $skipped_manual = 0;
                    $employees_with_logs = [];
                    $overtime_records_count = 0;
                    
                    foreach ($raw_logs as $user_id => $dates) {
                        $employee = $access_users[$user_id]['mysql_data'];
                        $employee_id = $employee['employee_id'];
                        $employees_with_logs[] = $employee_id;
                        $shift_id = $employee['shift_id'] ?? 1;

                        $stmt_shift = $conn->prepare("SELECT time_in, break_out, break_in, time_out, total_hours, is_flexible, has_break FROM Shifts WHERE shift_id = ?");
                        if (!$stmt_shift) {
                            throw new Exception("Prepare statement failed for Shifts: " . $conn->error);
                        }
                        $stmt_shift->bind_param("i", $shift_id);
                        $stmt_shift->execute();
                        $shift_row = $stmt_shift->get_result()->fetch_assoc();
                        $stmt_shift->close();

                        foreach ($dates as $log_date => $log_entries) {
                            // CHECK IF RECORD EXISTS AND IS MANUAL
                            $existing_record = null;
                            if ($preserve_manual_entries) {
                                $check_existing = $conn->prepare("SELECT * FROM EmployeeDTR WHERE employee_id = ? AND date = ?");
                                if ($check_existing) {
                                    $check_existing->bind_param("is", $employee_id, $log_date);
                                    $check_existing->execute();
                                    $existing_result = $check_existing->get_result();
                                    if ($existing_row = $existing_result->fetch_assoc()) {
                                        // Check if record has been manually modified
                                        // is_manual = 1 means it was manually created/edited
                                        if ($existing_row['is_manual'] == 1) {
                                            $existing_record = $existing_row;
                                            $skipped_manual++;
                                            $preserved_records_count++;
                                            
                                            $display_rows[] = [
                                                'biometric_id' => $employee['biometric_id'],
                                                'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                                                'date' => $log_date,
                                                'logs' => "PRESERVED",
                                                'time_in' => $existing_row['time_in'],
                                                'break_out' => $existing_row['break_out'],
                                                'break_in' => $existing_row['break_in'],
                                                'time_out' => $existing_row['time_out'],
                                                'total_work_time' => $existing_row['total_work_time'],
                                                'late_time' => $existing_row['late_time'],
                                                'undertime_time' => $existing_row['undertime_time'],
                                                'overtime_time' => $existing_row['overtime_time'],
                                                'night_time' => $existing_row['night_time'],
                                                'day_type' => $existing_row['day_type_id'],
                                                'log_count' => 0,
                                                'expected_hours' => 0,
                                                'actual_minutes' => 0,
                                                'late_minutes_raw' => 0,
                                                'undertime_minutes_raw' => 0,
                                                'overtime_hours' => $existing_row['overtime_time'],
                                                'in_count' => 0,
                                                'out_count' => 0,
                                                'is_preserved' => true
                                            ];
                                            
                                            continue; // Skip import, keep existing manual record
                                        }
                                    }
                                    $check_existing->close();
                                }
                            }
                            
                            // Separate IN and OUT logs based on CHECKTYPE
                            $in_logs = [];
                            $out_logs = [];
                            
                            foreach ($log_entries as $entry) {
                                if ($entry['type'] === 'I' || $entry['type'] === 'i') {
                                    $in_logs[] = $entry['time'];
                                } elseif ($entry['type'] === 'O' || $entry['type'] === 'o') {
                                    $out_logs[] = $entry['time'];
                                } else {
                                    $log_hour = (int)date('H', strtotime($entry['time']));
                                    if ($log_hour < 12) {
                                        $in_logs[] = $entry['time'];
                                    } else {
                                        $out_logs[] = $entry['time'];
                                    }
                                }
                            }
                            
                            sort($in_logs);
                            sort($out_logs);
                            
                            $time_in = !empty($in_logs) ? $in_logs[0] : null;
                            $break_out = null;
                            $break_in = null;
                            $time_out = !empty($out_logs) ? end($out_logs) : null;
                            
                            if (count($in_logs) >= 2 && count($out_logs) >= 2) {
                                $break_out = $out_logs[0];
                                $break_in = $in_logs[1];
                            }

                            $day_type_id = 1;

                            // Check for work suspensions (full day or half day)
                            $stmt_susp = $conn->prepare("SELECT is_full_day, is_half_day, start_time, end_time FROM WorkSuspensions WHERE DATE(date) = ?");
                            if (!$stmt_susp) {
                                throw new Exception("Prepare statement failed for WorkSuspensions: " . $conn->error);
                            }
                            $stmt_susp->bind_param("s", $log_date);
                            $stmt_susp->execute();
                            $susp_result = $stmt_susp->get_result();
                            if ($susp_row = $susp_result->fetch_assoc()) {
                                // If full day suspension, set day type to 11 (Work Suspension)
                                if ($susp_row['is_full_day'] == 1) {
                                    $day_type_id = 11;
                                    $debug_info[] = "📅 Full-day suspension found for {$employee['biometric_id']} on $log_date";
                                }
                                // If half day suspension, keep as regular day (1) but note the suspension exists
                                // The time_in/time_out logic will handle the attendance based on actual logs
                                elseif ($susp_row['is_half_day'] == 1) {
                                    // Half-day suspension - attendance still processed normally
                                    // You may want to add special handling here if needed
                                    $debug_info[] = "ℹ️ Half-day suspension found for {$employee['biometric_id']} on $log_date";
                                }
                            }
                            $stmt_susp->close();

                            $stmt_hol = $conn->prepare("SELECT day_type_id FROM HolidayCalendar WHERE DATE(date) = ?");
                            if (!$stmt_hol) {
                                throw new Exception("Prepare statement failed for HolidayCalendar: " . $conn->error);
                            }
                            $stmt_hol->bind_param("s", $log_date);
                            $stmt_hol->execute();
                            $holidays = $stmt_hol->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt_hol->close();
                            if (!empty($holidays)) {
                                $day_type_id = max(array_column($holidays, 'day_type_id'));
                            }

                            // Check if Sunday is a scheduled work day for this employee's shift
                            $day_name = strtolower(date('l', strtotime($log_date)));
                            $stmt_rest = $conn->prepare("SELECT is_$day_name FROM ShiftDays WHERE shift_id = ?");
                            if (!$stmt_rest) {
                                throw new Exception("Prepare statement failed for ShiftDays: " . $conn->error);
                            }
                            $stmt_rest->bind_param("i", $shift_id);
                            $stmt_rest->execute();
                            $rest_row = $stmt_rest->get_result()->fetch_assoc();

                            // NEW LOGIC: If it's Sunday AND Sunday is NOT a scheduled work day, set as rest day
                            if ($day_name == 'sunday') {
                                if (empty($rest_row["is_$day_name"]) || $rest_row["is_$day_name"] == 0) {
                                    // Sunday is not a work day for this shift, set as rest day
                                    if ($day_type_id == 1) $day_type_id = 2;
                                } else {
                                    // Sunday IS a work day for this shift, keep as regular day (1) unless overridden by holiday/suspension
                                    // day_type_id remains as is (could be 1, or overridden by holiday/suspension above)
                                }
                            } else {
                                // For other days, use the existing logic
                                if (!empty($rest_row["is_$day_name"]) && $rest_row["is_$day_name"] == 0) {
                                    if ($day_type_id == 1) $day_type_id = 2;
                                }
                            }

                            $stmt_rest->close();

                            $total_work_time = $late_time = $undertime_time = '00:00';
                            $overtime_decimal = 0.00;
                            $overtime_display = '00:00';
                            $night_time = '00:00';
                            $total_minutes = 0;
                            $late_minutes = 0;
                            $undertime_minutes = 0;

                            if ($time_in && $time_out) {
                                $total_minutes = timeDifferenceInMinutes($time_in, $time_out);
                                $is_flexible = $shift_row['is_flexible'] ?? 0;
                                $has_break = $shift_row['has_break'] ?? 1;
                                $shift_total_hours = (float)($shift_row['total_hours'] ?? 8.0);
                                $expected_work_minutes = $shift_total_hours * 60;
                                $actual_in = new DateTime($time_in);
                                $actual_out = new DateTime($time_out);

                                if ($has_break && $break_out && $break_in) {
                                    $break_minutes = timeDifferenceInMinutes($break_out, $break_in);
                                    $total_minutes -= $break_minutes;
                                } elseif ($has_break && !$break_out && !$break_in) {
                                    $default_break_minutes = 60;
                                    $total_minutes -= $default_break_minutes;
                                }

                                $total_work_time = minutesToTime(max(0, $total_minutes));

                                if ($shift_row && !empty($shift_row['time_in']) && !empty($shift_row['time_out'])) {
                                    $expected_in = new DateTime("$log_date " . $shift_row['time_in']);
                                    $expected_out = new DateTime("$log_date " . $shift_row['time_out']);

                                    if ($actual_in > $expected_in) {
                                        $late_minutes = timeDifferenceInMinutes($expected_in->format('Y-m-d H:i:s'), $actual_in->format('Y-m-d H:i:s'));
                                    }
                                    if ($actual_out < $expected_out) {
                                        $undertime_minutes = timeDifferenceInMinutes($actual_out->format('Y-m-d H:i:s'), $expected_out->format('Y-m-d H:i:s'));
                                    }

                                    if (!$is_flexible) {
                                        $adjusted = applyAttendanceRules($conn, $late_minutes, $undertime_minutes);
                                        $late_time = $adjusted['late'];
                                        $undertime_time = $adjusted['undertime'];
                                    } else {
                                        $late_time = minutesToTime($late_minutes);
                                        $undertime_time = minutesToTime($undertime_minutes);
                                    }
                                }

                                // CALCULATE OVERTIME - Only based on time_out AFTER scheduled time_out
                                // Early time_in does NOT count as overtime
                                $overtime_result = calculateOvertime($actual_out, $expected_out);
                                $overtime_display = $overtime_result['time'];
                                $overtime_decimal = $overtime_result['decimal_hours'];
                                
                                // Count records with overtime for summary
                                if ($overtime_decimal > 0) {
                                    $overtime_records_count++;
                                }
                                
                                // Add detailed debug info for overtime calculation
                                if ($overtime_decimal > 0) {
                                    $debug_info[] = sprintf(
                                        "✅ OVERTIME: %s on %s | Expected OUT: %s | Actual OUT: %s | OT: %.2f hrs (%s)",
                                        $employee['biometric_id'],
                                        $log_date,
                                        $expected_out->format('H:i'),
                                        $actual_out->format('H:i'),
                                        $overtime_decimal,
                                        $overtime_display
                                    );
                                }
                            }

                            // Only delete if NOT preserving manual entries OR if record is not manual
                            if (!$preserve_manual_entries) {
                                $conn->query("DELETE FROM EmployeeDTR WHERE employee_id = '$employee_id' AND date = '$log_date'");
                            } else {
                                // Delete only non-manual records
                                $conn->query("DELETE FROM EmployeeDTR WHERE employee_id = '$employee_id' AND date = '$log_date' AND (is_manual IS NULL OR is_manual = 0)");
                            }

                            $stmt_insert = $conn->prepare("INSERT INTO EmployeeDTR (
                                employee_id, date, day_of_week, shift_id,
                                time_in, break_out, break_in, time_out,
                                total_work_time, undertime_time, overtime_time, late_time, night_time,
                                day_type_id, is_flexible, is_manual, has_missing_log, approval_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pending')");
                            
                            if (!$stmt_insert) {
                                throw new Exception("Prepare statement failed for EmployeeDTR: " . $conn->error);
                            }
                            
                            $day_of_week = date('l', strtotime($log_date));
                            $has_missing = ($time_in && $time_out) ? 0 : 1;

                            $stmt_insert->bind_param("isssssssssdsiiii",
                                $employee_id, $log_date, $day_of_week, $shift_id,
                                $time_in, $break_out, $break_in, $time_out,
                                $total_work_time, $undertime_time, $overtime_decimal, $late_time, $night_time,
                                $day_type_id, $is_flexible, $has_missing
                            );

                            if ($stmt_insert->execute()) {
                                $imported++;
                                $display_rows[] = [
                                    'biometric_id' => $employee['biometric_id'],
                                    'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                                    'date' => $log_date,
                                    'logs' => "IN: " . count($in_logs) . " | OUT: " . count($out_logs),
                                    'time_in' => $time_in,
                                    'break_out' => $break_out,
                                    'break_in' => $break_in,
                                    'time_out' => $time_out,
                                    'total_work_time' => $total_work_time,
                                    'late_time' => $late_time,
                                    'undertime_time' => $undertime_time,
                                    'overtime_time' => $overtime_display,
                                    'overtime_hours' => $overtime_decimal,
                                    'night_time' => $night_time,
                                    'day_type' => $day_type_id,
                                    'log_count' => count($in_logs) + count($out_logs),
                                    'expected_hours' => $shift_total_hours,
                                    'actual_minutes' => $total_minutes,
                                    'late_minutes_raw' => $late_minutes,
                                    'undertime_minutes_raw' => $undertime_minutes,
                                    'in_count' => count($in_logs),
                                    'out_count' => count($out_logs),
                                    'is_preserved' => false
                                ];
                            } else {
                                $errors++;
                                $debug_info[] = "Insert error for employee {$employee['biometric_id']} on $log_date: " . $stmt_insert->error;
                            }
                            $stmt_insert->close();
                        }
                    }
                    
                    // Track employees with no logs
                    foreach ($mysql_employees as $biometric_id => $emp_data) {
                        if (!in_array($emp_data['employee_id'], $employees_with_logs)) {
                            $employees_with_no_logs[] = [
                                'biometric_id' => $biometric_id,
                                'employee_id' => $emp_data['employee_id'],
                                'name' => $emp_data['first_name'] . ' ' . $emp_data['last_name'],
                                'shift_id' => $emp_data['shift_id']
                            ];
                        }
                    }
                    
                    $debug_info[] = "⚠️ Found " . count($employees_with_no_logs) . " employees with NO attendance logs in the selected date range";
                    $status = "✅ Imported <strong>$imported</strong> records, Errors <strong>$errors</strong>";
                    
                    if ($overtime_records_count > 0) {
                        $status .= "<br><small class='text-info'><i class='fas fa-clock'></i> <strong>$overtime_records_count</strong> records with overtime detected and saved.</small>";
                    }
                    
                    if ($preserve_manual_entries) {
                        $status .= "<br><small class='text-success'><i class='fas fa-shield-alt'></i> <strong>Manual Entry Protection Active:</strong> $skipped_manual manually encoded records were preserved and not overwritten.</small>";
                    }
                    $status .= "<br><small class='text-success'><i class='fas fa-clock'></i> <strong>Overtime Calculation:</strong> Only counts time worked AFTER scheduled time_out. Early time_in does NOT count. Must exceed 30 minutes, rounded DOWN to nearest 30 minutes, minimum 1.00 hours.</small>";
                    $status .= "<br><small class='text-muted'><i class='fas fa-moon'></i> Night time calculation is disabled - all values set to 00:00.</small>";
                    $status .= "<br><small class='text-success'><i class='fas fa-check-circle'></i> CHECKTYPE column is being used to correctly identify IN (I) and OUT (O) logs.</small>";
                    $status .= "<br><small class='text-info'><i class='fas fa-calendar'></i> <strong>Sunday Detection:</strong> Automatically detects Sunday schedules and sets day type to Rest Day (2) when Sunday is not a scheduled work day.</small>";
                    $status .= "<br><small class='text-warning'><i class='fas fa-ban'></i> <strong>Work Suspension Detection:</strong> Automatically detects full-day and half-day work suspensions from WorkSuspensions table.</small>";
                }
            } catch (Exception $e) {
                $status = "❌ Error: " . $e->getMessage();
                $debug_info[] = "Exception details: " . $e->getTraceAsString();
            }
            odbc_close($access_conn);
        }
    }
}

$day_types = [];
$result = mysqli_query($conn, "SELECT day_type_id, name FROM DayTypes");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $day_types[$row['day_type_id']] = $row['name'];
    }
}
?>

<style>
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f8f9fa;
}
.form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}
.preserved-row {
    background-color: #d1f2eb !important;
    font-weight: 500;
}
.preserved-badge {
    background-color: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
}
.manual-protection-card {
    border-left: 4px solid #28a745;
    background-color: #f8fff9;
}
.manual-protection-card .card-header {
    background-color: #d4edda;
    border-bottom: 2px solid #c3e6cb;
}
.overtime-highlight {
    background-color: #fff3cd !important;
    font-weight: 700;
    color: #856404;
}
.overtime-cell {
    background-color: #fffacd !important;
}
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Import Employee DTR</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item">DB</li>
                            <li class="breadcrumb-item">Import Employee DB</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="container my-4 d-flex justify-content-center">
            <div class="card shadow w-100" style="max-width: 1700px;">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Import Employee DTR Logs</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Access Database Path</label>
                            <input type="text" name="db_path" class="form-control" value="<?= htmlspecialchars($default_mdb_path) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Department (Office)</label>
                            <select name="office" class="form-select" required>
                                <option value="">-- Select Office --</option>
                                <option value="All" <?= (isset($_POST['office']) && $_POST['office'] == 'All') ? 'selected' : '' ?>>All</option>
                                <?php
                                $dept_result = mysqli_query($conn, "SELECT * FROM departments ORDER BY name ASC");
                                if ($dept_result) {
                                    while ($row = mysqli_fetch_assoc($dept_result)) {
                                        $selected = (isset($_POST['office']) && $_POST['office'] == $row['name']) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($row['name']) . "\" $selected>" . htmlspecialchars($row['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date Range</label>
                            <div class="d-flex gap-2">
                                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($_POST['from_date'] ?? date('Y-m-d')) ?>" required>
                                <span class="align-self-center">to</span>
                                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($_POST['to_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                        </div>
                        
                        <!-- Enhanced Manual Protection Section -->
                        <div class="col-12">
                            <div class="card manual-protection-card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt me-2 text-success"></i>
                                        Manual Entry Protection Settings
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" name="preserve_manual_entries" id="preserve_manual_entries" value="1" 
                                            <?= (!isset($_POST['submit']) || (isset($_POST['preserve_manual_entries']) && $_POST['preserve_manual_entries'] == '1')) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="preserve_manual_entries">
                                            <strong class="text-success">Enable Manual Entry Protection</strong>
                                        </label>
                                    </div>
                                    
                                    <div id="protection_details" style="display: <?= (!isset($_POST['submit']) || (isset($_POST['preserve_manual_entries']) && $_POST['preserve_manual_entries'] == '1')) ? 'block' : 'none' ?>;">
                                        <div class="alert alert-success mb-0">
                                            <h6><i class="fas fa-info-circle me-2"></i>What gets protected?</h6>
                                            <p class="mb-2">When enabled, the system will <strong>skip and preserve</strong> any attendance records that have been manually created or edited (where <code>is_manual = 1</code>). This protects:</p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <ul class="mb-2">
                                                        <li><i class="fas fa-check text-success me-1"></i> Manually adjusted time in/out</li>
                                                        <li><i class="fas fa-check text-success me-1"></i> Manual shift changes</li>
                                                        <li><i class="fas fa-check text-success me-1"></i> Manual overtime entries</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <ul class="mb-2">
                                                        <li><i class="fas fa-check text-success me-1"></i> Manual break time adjustments</li>
                                                        <li><i class="fas fa-check text-success me-1"></i> Corrected late/undertime values</li>
                                                        <li><i class="fas fa-check text-success me-1"></i> Any other manual corrections</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="alert alert-warning mb-0 mt-2">
                                                <small>
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <strong>Important:</strong> Records edited through the "Employee DTR" page are automatically marked with <code>is_manual = 1</code> and will be protected when this option is enabled.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="no_protection_warning" style="display: <?= (isset($_POST['submit']) && (!isset($_POST['preserve_manual_entries']) || $_POST['preserve_manual_entries'] != '1')) ? 'block' : 'none' ?>;">
                                        <div class="alert alert-danger mb-0">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning: Protection Disabled</h6>
                                            <p class="mb-0">
                                                <strong>All existing records will be overwritten</strong>, including manually edited ones. 
                                                This may result in loss of manual corrections and adjustments.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <button type="submit" name="submit" class="btn btn-success px-4">
                                <i class="fas fa-upload me-2"></i>Import DTR
                            </button>
                            <span class="text-muted ms-3 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Make sure MS Access DB is closed before importing.
                            </span>
                        </div>
                    </form>
                    
                    <?php if (!empty($status)): ?>
                        <div class="alert alert-info mt-4"><?= $status ?></div>
                    <?php endif; ?>
                    <?php if (!empty($debug_info)): ?>
                        <div class="alert alert-warning mt-3">
                            <h6>Debug Information</h6>
                            <ul style="max-height:300px; overflow:auto;">
                                <?php foreach ($debug_info as $line): ?>
                                    <li><?= htmlspecialchars($line) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($employees_with_no_logs)): ?>
                        <div class="alert alert-warning mt-4">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Employees with No Attendance Logs (<?= count($employees_with_no_logs) ?> employees)</h6>
                            <p class="mb-2">The following employees have biometric IDs in the system but no attendance logs were found in the Access database for the selected date range (<strong><?= htmlspecialchars($from_date ?? '') ?></strong> to <strong><?= htmlspecialchars($to_date ?? '') ?></strong>):</p>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-bordered table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>#</th>
                                            <th>Biometric ID</th>
                                            <th>Employee ID</th>
                                            <th>Employee Name</th>
                                            <th>Shift ID</th>
                                            <th>Possible Reasons</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees_with_no_logs as $index => $emp): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($emp['biometric_id']) ?></span></td>
                                                <td><?= htmlspecialchars($emp['employee_id']) ?></td>
                                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                                <td><?= htmlspecialchars($emp['shift_id']) ?></td>
                                                <td><small class="text-muted">
                                                    • Not present during date range<br>
                                                    • Biometric device not synced<br>
                                                    • Wrong biometric ID mapping
                                                </small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
     
                    <?php if (!empty($display_rows)): ?>
                        <div class="table-responsive mt-4">
                            <h6>Imported DTR Records (<?= count($display_rows) ?> records)</h6>
                            <?php if ($preserved_records_count > 0): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <strong><?= $preserved_records_count ?> manually encoded records were protected and preserved</strong> (shown in green below)
                                </div>
                            <?php endif; ?>
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Status</th>
                                        <th>Biometric ID</th>
                                        <th>Employee Name</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Break Out</th>
                                        <th>Break In</th>
                                        <th>Time Out</th>
                                        <th>Total Work</th>
                                        <th>Late Time</th>
                                        <th>UT</th>
                                        <th>OT (Hours)</th>
                                        <th>Night Shift</th>
                                        <th>Day Type</th>
                                        <th>Logs (I/O)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($display_rows as $r): ?>
                                        <tr class="<?= !empty($r['is_preserved']) ? 'preserved-row' : (($r['in_count'] == 1 && $r['out_count'] == 1) ? '' : (($r['in_count'] == 0 || $r['out_count'] == 0) ? 'table-warning' : '')) ?>">
                                            <td>
                                                <?php if (!empty($r['is_preserved'])): ?>
                                                    <span class="preserved-badge"><i class="fas fa-shield-alt"></i> PROTECTED</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">IMPORTED</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($r['biometric_id']) ?></td>
                                            <td><?= htmlspecialchars($r['name']) ?></td>
                                            <td><?= htmlspecialchars($r['date']) ?></td>
                                            <td><?= $r['time_in'] ? htmlspecialchars(date('H:i', strtotime($r['time_in']))) : '<span class="text-danger">NULL</span>' ?></td>
                                            <td><?= $r['break_out'] ? htmlspecialchars(date('H:i', strtotime($r['break_out']))) : '<span class="text-muted">N/A</span>' ?></td>
                                            <td><?= $r['break_in'] ? htmlspecialchars(date('H:i', strtotime($r['break_in']))) : '<span class="text-muted">N/A</span>' ?></td>
                                            <td><?= $r['time_out'] ? htmlspecialchars(date('H:i', strtotime($r['time_out']))) : '<span class="text-danger">NULL</span>' ?></td>
                                            <td><?= htmlspecialchars($r['total_work_time']) ?></td>
                                            <td><?= htmlspecialchars($r['late_time']) ?></td>
                                            <td><?= htmlspecialchars($r['undertime_time']) ?></td>
                                            <td class="<?= (!empty($r['overtime_hours']) && $r['overtime_hours'] > 0) ? 'overtime-cell' : '' ?>">
                                                <?php if (!empty($r['overtime_hours']) && $r['overtime_hours'] > 0): ?>
                                                    <strong class="overtime-highlight"><?= number_format($r['overtime_hours'], 2) ?> hrs</strong>
                                                    <br><small class="text-muted">(<?= htmlspecialchars($r['overtime_time']) ?>)</small>
                                                <?php else: ?>
                                                    <?= number_format($r['overtime_hours'], 2) ?> hrs
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['night_time']) ?></span></td>
                                            <td><?= $day_types[$r['day_type']] ?? 'Unknown' ?></td>
                                            <td>
                                                <?php if (!empty($r['is_preserved'])): ?>
                                                    <span class="badge bg-success">MANUAL</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary" style="font-size: 0.7rem;">I: <?= $r['in_count'] ?></span>
                                                    <span class="badge bg-danger" style="font-size: 0.7rem;">O: <?= $r['out_count'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <span class="badge bg-success me-2"></span><strong>Green rows:</strong> Manually encoded records that were protected from being overwritten<br>
                                    <span class="badge bg-warning me-2"></span><strong>Yellow rows:</strong> Incomplete logs (missing IN or OUT)<br>
                                    <span style="background-color: #fffacd; padding: 2px 8px; border-radius: 3px;" class="me-2"></span><strong>Yellow highlight in OT column:</strong> Records with overtime detected<br>
                                    <span class="badge bg-primary me-1" style="font-size: 0.7rem;">I</span> = Clock IN logs | 
                                    <span class="badge bg-danger me-2" style="font-size: 0.7rem;">O</span> = Clock OUT logs<br>
                                    <strong>CHECKTYPE Column:</strong> System uses CHECKTYPE ('I' for IN, 'O' for OUT) to correctly assign attendance times<br>
                                    <strong>Overtime Rule:</strong> Only counts time worked AFTER scheduled time_out (early time_in does NOT count). Must exceed 30 minutes, rounded DOWN to nearest 30 minutes, minimum 1.00 hours<br>
                                    <strong>Database Storage:</strong> Overtime is stored as decimal hours (e.g., 1.50 for 1 hour 30 minutes) in the <code>overtime_time</code> field<br>
                                    <strong>Sunday Detection:</strong> Automatically detects Sunday schedules and sets day type to Rest Day (2) when Sunday is not a scheduled work day<br>
                                    <strong>Work Suspension:</strong> Full-day suspensions set day type to 11. Half-day suspensions process normally.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle protection details based on checkbox state
document.getElementById('preserve_manual_entries').addEventListener('change', function() {
    const protectionDetails = document.getElementById('protection_details');
    const noProtectionWarning = document.getElementById('no_protection_warning');
    
    if (this.checked) {
        protectionDetails.style.display = 'block';
        noProtectionWarning.style.display = 'none';
    } else {
        protectionDetails.style.display = 'none';
        noProtectionWarning.style.display = 'block';
    }
});
</script>