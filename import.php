<?php
session_start();
ini_set('max_execution_time', 600);
set_time_limit(600);
include 'connection.php'; // MySQL connection
include 'head.php';
include 'sidebar.php';
include 'header.php';

// SQL Server Connection Details
$sql_server = "WIN-0LN1GVAFPE9";
$sql_database = "SentryLocal";

$status = "";
$display_rows = [];
$debug_info = [];
$employees_with_no_logs = [];
$preserved_records_count = 0;

// Utility Functions
function minutesToTime($minutes) {
    if ($minutes <= 0) return '00:00';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

function timeDifferenceInMinutes($start_time, $end_time) {
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $diff = $start->diff($end);
    return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
}

function applyAttendanceRules($conn, $late_minutes, $undertime_minutes) {
    $rules = ['late_grace' => 0, 'undertime_grace' => 0, 'late_round' => 0, 'undertime_round' => 0];
    $result = mysqli_query($conn, "SELECT rule_type, threshold_minutes FROM timeattendancerules WHERE is_active = 1");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rules[$row['rule_type']] = (int)$row['threshold_minutes'];
        }
    }

    if ($late_minutes <= $rules['late_grace']) $late_minutes = 0;
    elseif ($rules['late_round'] > 0) $late_minutes = ceil($late_minutes / $rules['late_round']) * $rules['late_round'];

    if ($undertime_minutes <= $rules['undertime_grace']) $undertime_minutes = 0;
    elseif ($rules['undertime_round'] > 0) $undertime_minutes = ceil($undertime_minutes / $rules['undertime_round']) * $rules['undertime_round'];

    return ['late' => minutesToTime($late_minutes), 'undertime' => minutesToTime($undertime_minutes)];
}

function calculateOvertime($actual_out, $expected_out) {
    if ($actual_out <= $expected_out) return ['time' => '00:00', 'decimal_hours' => 0.00];
    
    $diff = $expected_out->diff($actual_out);
    $overtime_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    if ($overtime_minutes > 30) {
        $overtime_hours = $overtime_minutes / 60;
        $rounded_overtime = floor($overtime_hours * 2) / 2;
        
        if ($rounded_overtime >= 1.00) {
            return [
                'time' => minutesToTime($rounded_overtime * 60),
                'decimal_hours' => round($rounded_overtime, 2)
            ];
        }
    }
    
    return ['time' => '00:00', 'decimal_hours' => 0.00];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office = $_POST['office'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $preserve_manual_entries = isset($_POST['preserve_manual_entries']) && $_POST['preserve_manual_entries'] == '1';
    
    // Connect to SQL Server
    $connectionInfo = [
        "Database" => $sql_database,
        "TrustServerCertificate" => true,
        "CharacterSet" => "UTF-8",
        "ReturnDatesAsStrings" => true
    ];
    
    $conn_sql = sqlsrv_connect($sql_server, $connectionInfo);
    
    if (!$conn_sql) {
        $errors = sqlsrv_errors();
        $error_msg = "Failed to connect to SQL Server:<br>";
        foreach ($errors as $error) {
            $error_msg .= "SQLSTATE: " . $error['SQLSTATE'] . "<br>";
            $error_msg .= "Code: " . $error['code'] . "<br>";
            $error_msg .= "Message: " . $error['message'] . "<br>";
        }
        $status = "❌ " . $error_msg;
    } else {
        $debug_info[] = "✅ Connected to SQL Server successfully";
        
        try {
            // STEP 1: Get employees from MySQL
            $mysql_employees = [];
            
            // Build employee query based on office filter
            // First check Employees table structure
            $check_structure = mysqli_query($conn, "SHOW COLUMNS FROM Employees");
            $employee_columns = [];
            while ($col = mysqli_fetch_assoc($check_structure)) {
                $employee_columns[] = $col['Field'];
            }
            
            $debug_info[] = "Employees table has columns: " . implode(', ', $employee_columns);
            
            // Build the base query
            $emp_query = "SELECT e.employee_id, e.biometric_id, e.first_name, e.last_name, e.shift_id";
            
            // Check if department_id exists in Employees table
            if (in_array('department_id', $employee_columns)) {
                $emp_query .= ", e.department_id FROM Employees e WHERE e.biometric_id IS NOT NULL AND e.biometric_id != ''";
                
                if ($office != "All") {
                    // Get department_id from departments table based on name
                    $dept_query = mysqli_query($conn, "SELECT department_id FROM departments WHERE name = '" . mysqli_real_escape_string($conn, $office) . "'");
                    if ($dept_row = mysqli_fetch_assoc($dept_query)) {
                        $department_id = $dept_row['department_id'];
                        $emp_query .= " AND e.department_id = '" . mysqli_real_escape_string($conn, $department_id) . "'";
                        $debug_info[] = "Filtering by department_id: " . $department_id;
                    } else {
                        $debug_info[] = "⚠️ Department not found: " . $office;
                    }
                }
            } else {
                // If no department_id column, check for other department columns
                $emp_query .= " FROM Employees e WHERE e.biometric_id IS NOT NULL AND e.biometric_id != ''";
                
                if ($office != "All") {
                    // Try to find any department-related column
                    $dept_columns = array_filter($employee_columns, function($col) {
                        return stripos($col, 'department') !== false || stripos($col, 'dept') !== false;
                    });
                    
                    if (!empty($dept_columns)) {
                        $dept_col = $dept_columns[0];
                        $emp_query .= " AND e.$dept_col = '" . mysqli_real_escape_string($conn, $office) . "'";
                        $debug_info[] = "Filtering by column: " . $dept_col;
                    } else {
                        // No department column found, fetch all
                        $debug_info[] = "⚠️ No department column found in Employees table";
                    }
                }
            }
            
            $emp_result = mysqli_query($conn, $emp_query);
            if (!$emp_result) {
                $debug_info[] = "Query error: " . mysqli_error($conn);
                throw new Exception("MySQL query failed: " . mysqli_error($conn));
            }
            
            while ($emp_row = mysqli_fetch_assoc($emp_result)) {
                $mysql_employees[$emp_row['biometric_id']] = $emp_row;
            }
            
            $debug_info[] = "✅ Found " . count($mysql_employees) . " employees in MySQL";
            
            if (empty($mysql_employees)) {
                $status = "⚠️ No employees found with biometric IDs";
            } else {
                // STEP 2: Get matching employees from SentryLocal
                $personnel_nos = array_map(function($id) {
                    return "'" . str_replace("'", "''", $id) . "'";
                }, array_keys($mysql_employees));
                $personnel_nos_str = implode(',', $personnel_nos);
                
                $personnel_query = "
                    SELECT Id, PersonnelNo, AccessNumber, LastName, FirstName 
                    FROM Personnels 
                    WHERE PersonnelNo IN ($personnel_nos_str)
                    AND IsDeleted = 0
                ";
                
                $debug_info[] = "Fetching matching employees from SentryLocal...";
                
                $personnel_stmt = sqlsrv_query($conn_sql, $personnel_query);
                if (!$personnel_stmt) {
                    throw new Exception("SentryLocal query failed: " . print_r(sqlsrv_errors(), true));
                }
                
                $personnel_map = []; // AccessNumber => biometric_id
                $employee_count = 0;
                
                while ($row = sqlsrv_fetch_array($personnel_stmt, SQLSRV_FETCH_ASSOC)) {
                    $access_number = $row['AccessNumber'];
                    $personnel_no = $row['PersonnelNo'];
                    
                    if ($access_number && isset($mysql_employees[$personnel_no])) {
                        $personnel_map[$access_number] = [
                            'biometric_id' => $personnel_no,
                            'personnel_id' => $row['Id']
                        ];
                        $employee_count++;
                    }
                }
                sqlsrv_free_stmt($personnel_stmt);
                
                $debug_info[] = "✅ Found $employee_count matching employees in SentryLocal";
                
                if (empty($personnel_map)) {
                    $status = "⚠️ No matching employees found. Check if biometric_id matches PersonnelNo";
                    $debug_info[] = "Sample biometric IDs from MySQL: " . implode(', ', array_slice(array_keys($mysql_employees), 0, 5)) . (count($mysql_employees) > 5 ? "..." : "");
                } else {
                    // STEP 3: Get attendance logs
                    $access_numbers = implode(',', array_map(function($num) {
                        return "'" . str_replace("'", "''", $num) . "'";
                    }, array_keys($personnel_map)));
                    
                    $from_datetime = date('Y-m-d 00:00:00', strtotime($from_date));
                    $to_datetime = date('Y-m-d 23:59:59', strtotime($to_date));
                    
                    $logs_query = "
                        SELECT AccessNumber, RecordDate, LogType, TimeLogStamp
                        FROM TimeLogs
                        WHERE AccessNumber IN ($access_numbers)
                        AND RecordDate >= ?
                        AND RecordDate <= ?
                        AND IsDeleted = 0
                        ORDER BY AccessNumber, RecordDate
                    ";
                    
                    $params = [
                        [$from_datetime, SQLSRV_PARAM_IN],
                        [$to_datetime, SQLSRV_PARAM_IN]
                    ];
                    
                    $logs_stmt = sqlsrv_query($conn_sql, $logs_query, $params);
                    if (!$logs_stmt) {
                        throw new Exception("Failed to fetch logs: " . print_r(sqlsrv_errors(), true));
                    }
                    
                    // Process logs
                    $processed_logs = [];
                    $log_count = 0;
                    
                    while ($log = sqlsrv_fetch_array($logs_stmt, SQLSRV_FETCH_ASSOC)) {
                        $log_count++;
                        $access_number = $log['AccessNumber'];
                        
                        if (!isset($personnel_map[$access_number])) continue;
                        
                        $biometric_id = $personnel_map[$access_number]['biometric_id'];
                        
                        // Use RecordDate as the primary date/time
                        $log_datetime = $log['RecordDate'];
                        if (!$log_datetime && $log['TimeLogStamp']) {
                            $log_datetime = $log['TimeLogStamp'];
                        }
                        
                        if (!$log_datetime) continue;
                        
                        $date = date('Y-m-d', strtotime($log_datetime));
                        $log_type = $log['LogType'];
                        
                        if (!isset($processed_logs[$biometric_id][$date])) {
                            $processed_logs[$biometric_id][$date] = ['in' => [], 'out' => []];
                        }
                        
                        // Categorize based on LogType
                        if ($log_type == 0 || $log_type == '0' || strtoupper($log_type) == 'I') {
                            $processed_logs[$biometric_id][$date]['in'][] = $log_datetime;
                        } elseif ($log_type == 1 || $log_type == '1' || strtoupper($log_type) == 'O') {
                            $processed_logs[$biometric_id][$date]['out'][] = $log_datetime;
                        } else {
                            // Fallback: guess based on time
                            $log_hour = date('H', strtotime($log_datetime));
                            if ($log_hour < 12) {
                                $processed_logs[$biometric_id][$date]['in'][] = $log_datetime;
                            } else {
                                $processed_logs[$biometric_id][$date]['out'][] = $log_datetime;
                            }
                        }
                    }
                    sqlsrv_free_stmt($logs_stmt);
                    
                    $debug_info[] = "✅ Processed $log_count logs into " . count($processed_logs) . " employee-date groups";
                    
                    // STEP 4: Import to MySQL EmployeeDTR
                    $imported = 0;
                    $errors = 0;
                    $skipped_manual = 0;
                    $employees_with_logs = [];
                    $overtime_records_count = 0;
                    
                    foreach ($processed_logs as $biometric_id => $dates) {
                        if (!isset($mysql_employees[$biometric_id])) continue;
                        
                        $employee = $mysql_employees[$biometric_id];
                        $employee_id = $employee['employee_id'];
                        $employees_with_logs[] = $employee_id;
                        $shift_id = $employee['shift_id'] ?? 1;
                        
                        // Get shift details
                        $stmt_shift = $conn->prepare("SELECT time_in, break_out, break_in, time_out, total_hours, is_flexible, has_break FROM Shifts WHERE shift_id = ?");
                        if (!$stmt_shift) throw new Exception("Shift query failed: " . $conn->error);
                        $stmt_shift->bind_param("i", $shift_id);
                        $stmt_shift->execute();
                        $shift_row = $stmt_shift->get_result()->fetch_assoc();
                        $stmt_shift->close();
                        
                        foreach ($dates as $log_date => $log_data) {
                            // Check for existing manual records
                            $existing_record = null;
                            if ($preserve_manual_entries) {
                                $check_existing = $conn->prepare("SELECT * FROM EmployeeDTR WHERE employee_id = ? AND date = ?");
                                if ($check_existing) {
                                    $check_existing->bind_param("is", $employee_id, $log_date);
                                    $check_existing->execute();
                                    $existing_result = $check_existing->get_result();
                                    if ($existing_row = $existing_result->fetch_assoc()) {
                                        if ($existing_row['is_manual'] == 1) {
                                            $existing_record = $existing_row;
                                            $skipped_manual++;
                                            $preserved_records_count++;
                                            
                                            // Add overtime_hours to preserved records for display
                                            $overtime_hours = isset($existing_row['overtime_time']) ? (float)$existing_row['overtime_time'] : 0.00;
                                            $overtime_display = minutesToTime($overtime_hours * 60);
                                            
                                            $display_rows[] = [
                                                'biometric_id' => $biometric_id,
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
                                                'overtime_time' => $overtime_display,
                                                'overtime_hours' => $overtime_hours,
                                                'night_time' => $existing_row['night_time'],
                                                'day_type' => $existing_row['day_type_id'],
                                                'log_count' => 0,
                                                'in_count' => 0,
                                                'out_count' => 0,
                                                'is_preserved' => true
                                            ];
                                            continue;
                                        }
                                    }
                                    $check_existing->close();
                                }
                            }
                            
                            // Sort logs
                            $in_logs = $log_data['in'];
                            $out_logs = $log_data['out'];
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
                            
                            // Determine day type
                            $day_type_id = 1;
                            
                            // Check holidays
                            $stmt_hol = $conn->prepare("SELECT day_type_id FROM HolidayCalendar WHERE DATE(date) = ?");
                            if ($stmt_hol) {
                                $stmt_hol->bind_param("s", $log_date);
                                $stmt_hol->execute();
                                $hol_result = $stmt_hol->get_result();
                                if ($hol_row = $hol_result->fetch_assoc()) {
                                    $day_type_id = $hol_row['day_type_id'];
                                }
                                $stmt_hol->close();
                            }
                            
                            // Calculate work time
                            $total_work_time = $late_time = $undertime_time = '00:00';
                            $overtime_decimal = 0.00;
                            $overtime_display = '00:00';
                            $night_time = '00:00';
                            $total_minutes = 0;
                            $late_minutes = 0;
                            $undertime_minutes = 0;
                            
                            if ($time_in && $time_out) {
                                $total_minutes = timeDifferenceInMinutes($time_in, $time_out);
                                
                                if ($shift_row) {
                                    $is_flexible = $shift_row['is_flexible'] ?? 0;
                                    $has_break = $shift_row['has_break'] ?? 1;
                                    
                                    if ($has_break && $break_out && $break_in) {
                                        $break_minutes = timeDifferenceInMinutes($break_out, $break_in);
                                        $total_minutes -= $break_minutes;
                                    } elseif ($has_break && !$break_out && !$break_in) {
                                        $total_minutes -= 60; // Default 1 hour break
                                    }
                                    
                                    $total_work_time = minutesToTime(max(0, $total_minutes));
                                    
                                    // Calculate late/undertime if schedule exists
                                    if (!empty($shift_row['time_in']) && !empty($shift_row['time_out'])) {
                                        $expected_in = new DateTime("$log_date " . $shift_row['time_in']);
                                        $expected_out = new DateTime("$log_date " . $shift_row['time_out']);
                                        $actual_in = new DateTime($time_in);
                                        $actual_out = new DateTime($time_out);
                                        
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
                                        
                                        // Calculate overtime
                                        $overtime_result = calculateOvertime($actual_out, $expected_out);
                                        $overtime_display = $overtime_result['time'];
                                        $overtime_decimal = $overtime_result['decimal_hours'];
                                        
                                        if ($overtime_decimal > 0) {
                                            $overtime_records_count++;
                                        }
                                    }
                                }
                            }
                            
                            // Delete existing record (if not manual)
                            if (!$preserve_manual_entries) {
                                mysqli_query($conn, "DELETE FROM EmployeeDTR WHERE employee_id = '$employee_id' AND date = '$log_date'");
                            } else {
                                mysqli_query($conn, "DELETE FROM EmployeeDTR WHERE employee_id = '$employee_id' AND date = '$log_date' AND (is_manual IS NULL OR is_manual = 0)");
                            }
                            
                            // Insert new record
                            $day_of_week = date('l', strtotime($log_date));
                            $has_missing = ($time_in && $time_out) ? 0 : 1;
                            $is_flexible = $shift_row['is_flexible'] ?? 0;
                            
                            $stmt_insert = $conn->prepare("
                                INSERT INTO EmployeeDTR (
                                    employee_id, date, day_of_week, shift_id,
                                    time_in, break_out, break_in, time_out,
                                    total_work_time, undertime_time, overtime_time, late_time, night_time,
                                    day_type_id, is_flexible, is_manual, has_missing_log, approval_status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pending')
                            ");
                            
                            if ($stmt_insert) {
                                $stmt_insert->bind_param("isssssssssdsiiii",
                                    $employee_id, $log_date, $day_of_week, $shift_id,
                                    $time_in, $break_out, $break_in, $time_out,
                                    $total_work_time, $undertime_time, $overtime_decimal, $late_time, $night_time,
                                    $day_type_id, $is_flexible, $has_missing
                                );
                                
                                if ($stmt_insert->execute()) {
                                    $imported++;
                                    $display_rows[] = [
                                        'biometric_id' => $biometric_id,
                                        'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                                        'date' => $log_date,
                                        'logs' => "IN: " . count($in_logs) . " | OUT: " . count($out_logs),
                                        'time_in' => $time_in ? date('H:i', strtotime($time_in)) : null,
                                        'break_out' => $break_out ? date('H:i', strtotime($break_out)) : null,
                                        'break_in' => $break_in ? date('H:i', strtotime($break_in)) : null,
                                        'time_out' => $time_out ? date('H:i', strtotime($time_out)) : null,
                                        'total_work_time' => $total_work_time,
                                        'late_time' => $late_time,
                                        'undertime_time' => $undertime_time,
                                        'overtime_time' => $overtime_display,
                                        'overtime_hours' => $overtime_decimal,
                                        'night_time' => $night_time,
                                        'day_type' => $day_type_id,
                                        'log_count' => count($in_logs) + count($out_logs),
                                        'in_count' => count($in_logs),
                                        'out_count' => count($out_logs),
                                        'is_preserved' => false
                                    ];
                                } else {
                                    $errors++;
                                    $debug_info[] = "Insert error for $biometric_id on $log_date: " . $stmt_insert->error;
                                }
                                $stmt_insert->close();
                            } else {
                                $errors++;
                                $debug_info[] = "Prepare failed for $biometric_id: " . $conn->error;
                            }
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
                    
                    $status = "✅ Imported <strong>$imported</strong> records from SentryLocal";
                    $status .= $errors > 0 ? ", Errors: <strong>$errors</strong>" : "";
                    
                    if ($overtime_records_count > 0) {
                        $status .= "<br>📈 <strong>$overtime_records_count</strong> records with overtime";
                    }
                    
                    if ($preserve_manual_entries && $skipped_manual > 0) {
                        $status .= "<br>🛡️ <strong>$skipped_manual</strong> manual records preserved";
                    }
                    
                    $status .= "<br><small class='text-success'><i class='fas fa-database'></i> Source: SentryLocal SQL Server (via AccessNumber link)</small>";
                    $status .= "<br><small class='text-info'><i class='fas fa-key'></i> Linking: Employees.biometric_id = Personnels.PersonnelNo = TimeLogs.AccessNumber</small>";
                    
                }
            }
            
            sqlsrv_close($conn_sql);
            
        } catch (Exception $e) {
            $status = "❌ Error: " . $e->getMessage();
            $debug_info[] = "Exception: " . $e->getTraceAsString();
            if ($conn_sql) sqlsrv_close($conn_sql);
        }
    }
}

// Get day types for display
$day_types = [];
$result = mysqli_query($conn, "SELECT day_type_id, name FROM DayTypes");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $day_types[$row['day_type_id']] = $row['name'];
    }
}
?>

<!-- HTML FORM -->
<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Import DTR from SentryLocal</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item">DB</li>
                            <li class="breadcrumb-item">SentryLocal Import</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container my-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>SentryLocal Import</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Database Schema Discovered:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Personnels Table:</strong></p>
                                <ul class="mb-2">
                                    <li><code>Id</code> - Primary key</li>
                                    <li><code>PersonnelNo</code> - Employee number (matches biometric_id)</li>
                                    <li><code>AccessNumber</code> - Links to TimeLogs</li>
                                    <li><code>LastName</code>, <code>FirstName</code></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>TimeLogs Table:</strong></p>
                                <ul class="mb-2">
                                    <li><code>AccessNumber</code> - Links to Personnels</li>
                                    <li><code>RecordDate</code> - Date/Time of log</li>
                                    <li><code>LogType</code> - IN/OUT type</li>
                                    <li><code>TimeLogStamp</code> - Alternative timestamp</li>
                                </ul>
                            </div>
                        </div>
                        <p class="mb-0"><strong>Linking:</strong> MySQL <code>Employees.biometric_id</code> = SentryLocal <code>Personnels.PersonnelNo</code> = <code>TimeLogs.AccessNumber</code></p>
                    </div>
                    
                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Department (Office)</label>
                            <select name="office" class="form-select" required>
                                <option value="">-- Select Office --</option>
                                <option value="All" <?= (isset($_POST['office']) && $_POST['office'] == 'All') ? 'selected' : '' ?>>All Departments</option>
                                <?php
                                $dept_result = mysqli_query($conn, "SELECT name FROM departments ORDER BY name");
                                if (!$dept_result) {
                                    echo "<option value=''>Error loading departments: " . mysqli_error($conn) . "</option>";
                                } else {
                                    while ($dept = mysqli_fetch_assoc($dept_result)) {
                                        $selected = (isset($_POST['office']) && $_POST['office'] == $dept['name']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($dept['name']) . "' $selected>" . htmlspecialchars($dept['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">From Date</label>
                            <input type="date" name="from_date" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['from_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">To Date</label>
                            <input type="date" name="to_date" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['to_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="preserve_manual_entries" 
                                       id="preserve_manual_entries" value="1" 
                                       <?= (!isset($_POST['submit']) || (isset($_POST['preserve_manual_entries']) && $_POST['preserve_manual_entries'] == '1')) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="preserve_manual_entries">
                                    <i class="fas fa-shield-alt text-success me-1"></i> Preserve Manual Entries
                                </label>
                                <small class="text-muted d-block">When checked, manually encoded records (is_manual = 1) will not be overwritten.</small>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <button type="submit" name="submit" class="btn btn-success px-4">
                                <i class="fas fa-database me-2"></i>Import from SentryLocal
                            </button>
                            <small class="text-muted ms-3">
                                <i class="fas fa-link me-1"></i>Using AccessNumber linking (corrected schema)
                            </small>
                        </div>
                    </form>
                    
                    <?php if (!empty($status)): ?>
                        <div class="alert <?= strpos($status, '✅') !== false ? 'alert-success' : 'alert-danger' ?> mt-4">
                            <?= $status ?>
                        </div>
                    <?php endif; ?>
                    
                    
                    
                    <?php if (!empty($employees_with_no_logs)): ?>
                        <div class="alert alert-warning mt-4">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Employees with No Logs (<?= count($employees_with_no_logs) ?>)</h6>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Biometric ID</th>
                                            <th>Name</th>
                                            <th>Shift ID</th>
                                            <th>Possible Issues</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees_with_no_logs as $index => $emp): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($emp['biometric_id']) ?></span></td>
                                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                                <td><?= htmlspecialchars($emp['shift_id']) ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        • No matching PersonnelNo in SentryLocal<br>
                                                        • No TimeLogs records in date range<br>
                                                        • AccessNumber might be different
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($display_rows)): ?>
                        <div class="table-responsive mt-4">
                            <h6>Imported Records (<?= count($display_rows) ?>)</h6>
                            <?php if ($preserved_records_count > 0): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <strong><?= $preserved_records_count ?></strong> manual records preserved
                                </div>
                            <?php endif; ?>
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Biometric ID</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Total Work</th>
                                        <th>Late</th>
                                        <th>UT</th>
                                        <th>OT</th>
                                        <th>Day Type</th>
                                        <th>Logs (I/O)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($display_rows as $r): ?>
                                        <tr class="<?= !empty($r['is_preserved']) ? 'table-success' : '' ?>">
                                            <td>
                                                <?php if (!empty($r['is_preserved'])): ?>
                                                    <span class="badge bg-success">PRESERVED</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">IMPORTED</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($r['biometric_id']) ?></td>
                                            <td><?= htmlspecialchars($r['name']) ?></td>
                                            <td><?= htmlspecialchars($r['date']) ?></td>
                                            <td><?= $r['time_in'] ?: '<span class="text-danger">N/A</span>' ?></td>
                                            <td><?= $r['time_out'] ?: '<span class="text-danger">N/A</span>' ?></td>
                                            <td><?= htmlspecialchars($r['total_work_time']) ?></td>
                                            <td><?= htmlspecialchars($r['late_time']) ?></td>
                                            <td><?= htmlspecialchars($r['undertime_time']) ?></td>
                                            <td>
                                                <?php 
                                                // Safely get overtime_hours with a default value of 0
                                                $overtime_hours = isset($r['overtime_hours']) ? $r['overtime_hours'] : 0.00;
                                                if ($overtime_hours > 0): ?>
                                                    <strong class="text-warning"><?= number_format($overtime_hours, 2) ?> hrs</strong>
                                                <?php else: ?>
                                                    <?= number_format($overtime_hours, 2) ?> hrs
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $day_types[$r['day_type']] ?? 'Unknown' ?></td>
                                            <td>
                                                <span class="badge bg-primary" style="font-size: 0.7rem;">I: <?= $r['in_count'] ?></span>
                                                <span class="badge bg-danger" style="font-size: 0.7rem;">O: <?= $r['out_count'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>