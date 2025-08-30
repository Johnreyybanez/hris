<?php
session_start();
include 'connection.php'; // $conn (MySQLi)

include 'head.php';
include 'sidebar.php';
include 'header.php';

$status = "";
$display_rows = [];
$debug_info = [];
$default_mdb_path = "C:\\Users\\admin\\Desktop\\BIR\\attBackup.mdb"; // Static MDB path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office = $_POST['office'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $db_path = $default_mdb_path;

    if (!file_exists($db_path)) {
        $status = "❌ MDB file not found at: <code>$db_path</code>";
    } else {
        $connStr = "Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$db_path;";
        $access_conn = @odbc_connect($connStr, '', '');

        if (!$access_conn) {
            $status = "❌ Failed to connect to Access database. <br><strong>ODBC Error:</strong> " . odbc_errormsg();
        } else {
            try {
                // STEP 1: Fetch all Employees from MySQL with biometric_id
                $mysql_employees = [];
                $emp_query = "SELECT employee_id, biometric_id, first_name, last_name FROM Employees WHERE biometric_id IS NOT NULL AND biometric_id != ''";
                $emp_result = mysqli_query($conn, $emp_query);
                
                while ($emp_row = mysqli_fetch_assoc($emp_result)) {
                    $mysql_employees[$emp_row['biometric_id']] = $emp_row;
                }
                
                $debug_info[] = "Step 1: Found " . count($mysql_employees) . " employees with biometric IDs in MySQL";
                
                // STEP 2: Query MS Access (USERINFO) to find matching BADGENUMBER
                $access_users = [];
                $userinfo_query = "SELECT USERID, BADGENUMBER, [NAME] FROM USERINFO";
                $userinfo_result = @odbc_exec($access_conn, $userinfo_query);
                
                if (!$userinfo_result) {
                    throw new Exception("Failed to query USERINFO table: " . odbc_errormsg());
                }
                
                while ($user_row = odbc_fetch_array($userinfo_result)) {
                    $badge_number = trim($user_row['BADGENUMBER']);
                    if (isset($mysql_employees[$badge_number])) {
                        $access_users[$user_row['USERID']] = [
                            'badge_number' => $badge_number,
                            'access_name' => $user_row['NAME'],
                            'mysql_data' => $mysql_employees[$badge_number]
                        ];
                    }
                }
                
                $debug_info[] = "Step 2: Found " . count($access_users) . " matching users in USERINFO";
                
                if (empty($access_users)) {
                    $status = "⚠️ No matching employees found between MySQL and Access databases.";
                } else {
                    // DEBUG QUERIES: Check CHECKINOUT table structure and data
                    $debug_queries = [
                        // Check if table exists and get column info
                        "SELECT TOP 5 * FROM CHECKINOUT",
                        
                        // Get date range of existing data
                        "SELECT MIN(CHECKTIME) as earliest, MAX(CHECKTIME) as latest FROM CHECKINOUT",
                        
                        // Check specific user IDs
                        "SELECT DISTINCT USERID FROM CHECKINOUT ORDER BY USERID",
                        
                        // Sample recent records
                        "SELECT TOP 10 USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT ORDER BY CHECKTIME DESC"
                    ];

                    foreach ($debug_queries as $index => $debug_query) {
                        try {
                            $debug_result = @odbc_exec($access_conn, $debug_query);
                            if ($debug_result) {
                                $debug_data = [];
                                while ($debug_row = odbc_fetch_array($debug_result)) {
                                    $debug_data[] = $debug_row;
                                }
                                
                                switch ($index) {
                                    case 0:
                                        $debug_info[] = "DEBUG: CHECKINOUT table has " . count($debug_data) . " sample records";
                                        if (!empty($debug_data)) {
                                            $sample = $debug_data[0];
                                            $debug_info[] = "DEBUG: Sample columns: " . implode(', ', array_keys($sample));
                                        }
                                        break;
                                        
                                    case 1:
                                        if (!empty($debug_data)) {
                                            $range = $debug_data[0];
                                            $debug_info[] = "DEBUG: Date range in DB: {$range['earliest']} to {$range['latest']}";
                                        }
                                        break;
                                        
                                    case 2:
                                        $all_userids = array_column($debug_data, 'USERID');
                                        $debug_info[] = "DEBUG: All USER IDs in CHECKINOUT: " . implode(', ', array_slice($all_userids, 0, 10)) . (count($all_userids) > 10 ? '...' : '');
                                        break;
                                        
                                    case 3:
                                        $debug_info[] = "DEBUG: Recent " . count($debug_data) . " log entries found";
                                        foreach (array_slice($debug_data, 0, 3) as $recent) {
                                            $debug_info[] = "  • User {$recent['USERID']}: {$recent['CHECKTIME']} (Type: {$recent['CHECKTYPE']})";
                                        }
                                        break;
                                }
                            }
                        } catch (Exception $e) {
                            $debug_info[] = "DEBUG Query $index failed: " . $e->getMessage();
                        }
                    }

                    // Check if our target user IDs actually exist in CHECKINOUT
                    $user_ids = array_keys($access_users);
                    $user_ids_str = implode(',', $user_ids);
                    
                    $user_check_query = "SELECT USERID, COUNT(*) as log_count FROM CHECKINOUT WHERE USERID IN ($user_ids_str) GROUP BY USERID";
                    $user_check_result = @odbc_exec($access_conn, $user_check_query);
                    
                    if ($user_check_result) {
                        $debug_info[] = "DEBUG: Log counts for matched users:";
                        while ($user_check_row = odbc_fetch_array($user_check_result)) {
                            $debug_info[] = "  • User ID {$user_check_row['USERID']}: {$user_check_row['log_count']} total logs";
                        }
                    }
                    
                    // STEP 3: Use USERID to pull logs from CHECKINOUT with improved date handling
                    // Convert dates to proper Access format
                    $from_date_access = date('m/d/Y', strtotime($from_date));
                    $to_date_access = date('m/d/Y', strtotime($to_date));

                    $debug_info[] = "Step 3a: Querying date range: $from_date_access to $to_date_access";
                    $debug_info[] = "Step 3b: User IDs to query: $user_ids_str";

                    // Try multiple date format approaches
                    $checkinout_queries = [
                        // Format 1: US date format with # delimiters
                        "SELECT USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT 
                         WHERE USERID IN ($user_ids_str) 
                         AND CHECKTIME >= #$from_date_access# AND CHECKTIME <= #$to_date_access 23:59:59# 
                         ORDER BY USERID, CHECKTIME",
                         
                        // Format 2: ISO date format
                        "SELECT USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT 
                         WHERE USERID IN ($user_ids_str) 
                         AND CHECKTIME >= #$from_date 00:00:00# AND CHECKTIME <= #$to_date 23:59:59# 
                         ORDER BY USERID, CHECKTIME",
                         
                        // Format 3: Date function approach
                        "SELECT USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT 
                         WHERE USERID IN ($user_ids_str) 
                         AND DateValue(CHECKTIME) >= DateValue('$from_date_access') 
                         AND DateValue(CHECKTIME) <= DateValue('$to_date_access')
                         ORDER BY USERID, CHECKTIME",
                         
                        // Format 4: Simple date comparison
                        "SELECT USERID, CHECKTIME, CHECKTYPE FROM CHECKINOUT 
                         WHERE USERID IN ($user_ids_str) 
                         AND CHECKTIME BETWEEN #$from_date_access 00:00:00# AND #$to_date_access 23:59:59#
                         ORDER BY USERID, CHECKTIME"
                    ];

                    $checkinout_result = null;
                    $successful_query = null;

                    foreach ($checkinout_queries as $index => $query) {
                        $debug_info[] = "Step 3c: Trying query format " . ($index + 1);
                        $checkinout_result = @odbc_exec($access_conn, $query);
                        
                        if ($checkinout_result) {
                            $successful_query = $index + 1;
                            $debug_info[] = "Step 3d: Query format $successful_query succeeded!";
                            break;
                        } else {
                            $debug_info[] = "Step 3d: Query format " . ($index + 1) . " failed: " . odbc_errormsg();
                        }
                    }

                    if (!$checkinout_result) {
                        throw new Exception("All date query formats failed. Last error: " . odbc_errormsg());
                    }

                    // Add this debug query to check what data exists
                    $count_query = "SELECT COUNT(*) as total FROM CHECKINOUT WHERE USERID IN ($user_ids_str)";
                    $count_result = @odbc_exec($access_conn, $count_query);
                    if ($count_result) {
                        $count_row = odbc_fetch_array($count_result);
                        $total_logs = $count_row['total'];
                        $debug_info[] = "Step 3e: Total logs for these users (any date): $total_logs";
                    }

                    $raw_logs = [];
                    $log_count = 0;
                    while ($log_row = odbc_fetch_array($checkinout_result)) {
                        $user_id = $log_row['USERID'];
                        $check_time = $log_row['CHECKTIME'];
                        $check_type = $log_row['CHECKTYPE'];
                        
                        $date = date('Y-m-d', strtotime($check_time));
                        $raw_logs[$user_id][$date][] = [
                            'time' => $check_time,
                            'type' => $check_type
                        ];
                        $log_count++;
                    }
                    
                    $debug_info[] = "Step 3f: Successfully retrieved $log_count raw logs from CHECKINOUT using query format " . ($successful_query ?: 'unknown');
                    
                    if ($log_count == 0) {
                        $status = "⚠️ No attendance logs found for the selected date range and employees.";
                    } else {
                        // STEP 4: Process CHECKTIME & CHECKTYPE to determine time_in, break_out, break_in, and time_out
                        $processed_logs = [];
                        foreach ($raw_logs as $user_id => $dates) {
                            foreach ($dates as $date => $logs) {
                                // Sort logs by time for each date
                                usort($logs, function($a, $b) {
                                    return strtotime($a['time']) - strtotime($b['time']);
                                });
                                
                                $time_in = null;
                                $break_out = null;
                                $break_in = null;
                                $time_out = null;
                                
                                // Process based on CHECKTYPE and number of logs
                                $log_count_for_date = count($logs);
                                
                                if ($log_count_for_date >= 1) {
                                    $time_in = $logs[0]['time']; // First log is time in
                                }
                                
                                if ($log_count_for_date >= 2) {
                                    if ($log_count_for_date == 2) {
                                        // Only 2 logs: in and out
                                        $time_out = $logs[1]['time'];
                                    } else if ($log_count_for_date == 3) {
                                        // 3 logs: could be in, break, out OR in, out, overtime
                                        // Assume middle log is break out, last is time out
                                        $break_out = $logs[1]['time'];
                                        $time_out = $logs[2]['time'];
                                    } else if ($log_count_for_date >= 4) {
                                        // 4+ logs: in, break_out, break_in, out
                                        $break_out = $logs[1]['time'];
                                        $break_in = $logs[2]['time'];
                                        $time_out = $logs[3]['time'];
                                        
                                        // If more than 4 logs, use the last one as final time out
                                        if ($log_count_for_date > 4) {
                                            $time_out = $logs[$log_count_for_date - 1]['time'];
                                        }
                                    }
                                }
                                
                                $processed_logs[$user_id][$date] = [
                                    'time_in' => $time_in,
                                    'break_out' => $break_out,
                                    'break_in' => $break_in,
                                    'time_out' => $time_out,
                                    'raw_logs_count' => $log_count_for_date
                                ];
                            }
                        }
                        
                        $debug_info[] = "Step 4: Processed logs for " . count($processed_logs) . " users";
                        
                        // STEP 5: Populate your EmployeeDTR table
                        $imported = 0;
                        $skipped = 0;
                        $errors = 0;
                        
                        foreach ($processed_logs as $user_id => $dates) {
                            if (!isset($access_users[$user_id])) continue;
                            
                            $employee_data = $access_users[$user_id]['mysql_data'];
                            $employee_id = $employee_data['employee_id'];
                            $full_name = $employee_data['first_name'] . ' ' . $employee_data['last_name'];
                            $biometric_id = $employee_data['biometric_id'];
                            
                            foreach ($dates as $log_date => $log_data) {
                                // Check if record already exists
                                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM EmployeeDTR WHERE employee_id = ? AND date = ?");
                                $check_stmt->bind_param("is", $employee_id, $log_date);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                $exists = $check_result->fetch_assoc()['count'] > 0;
                                
                                if ($exists) {
                                    $skipped++;
                                    continue;
                                }
                                
                                $time_in = $log_data['time_in'];
                                $break_out = $log_data['break_out'];
                                $break_in = $log_data['break_in'];
                                $time_out = $log_data['time_out'];
                                
                                // Calculate total work hours
                                $total = 0;
                                if ($time_in && $time_out) {
                                    $start = new DateTime($time_in);
                                    $end = new DateTime($time_out);
                                    $interval = $end->diff($start);
                                    $total = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
                                    
                                    // Subtract break time if both break logs exist
                                    if ($break_out && $break_in) {
                                        $b_start = new DateTime($break_out);
                                        $b_end = new DateTime($break_in);
                                        $b_interval = $b_end->diff($b_start);
                                        $break_duration = $b_interval->h + ($b_interval->i / 60) + ($b_interval->s / 3600);
                                        $total -= $break_duration;
                                    }
                                    
                                    // Ensure total is not negative
                                    $total = max(0, $total);
                                }
                                
                                $day_of_week = date('l', strtotime($log_date));
                                $shift_id = 1;
                                $day_type_id = 1;
                                $has_missing = ($log_data['raw_logs_count'] < 4) ? 1 : 0;
                                
                                // Prepare insert statement - FIXED VERSION
                                $stmt = $conn->prepare("INSERT INTO EmployeeDTR (
                                    employee_id, date, day_of_week, shift_id,
                                    time_in, break_out, break_in, time_out,
                                    total_work_hours, undertime_hours, overtime_hours,
                                    day_type_id, is_flexible, is_manual, has_missing_log, approval_status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, 0, ?, 'Pending')");
                                
                                if (!$stmt) {
                                    $debug_info[] = "Prepare failed: " . $conn->error;
                                    $errors++;
                                    continue;
                                }
                                
                                // FIXED bind_param with correct type string and parameter count
                                $stmt->bind_param("ississssdii",
                                    $employee_id,      // i - integer
                                    $log_date,         // s - string
                                    $day_of_week,      // s - string  
                                    $shift_id,         // i - integer
                                    $time_in,          // s - string
                                    $break_out,        // s - string
                                    $break_in,         // s - string
                                    $time_out,         // s - string
                                    $total,            // d - decimal
                                    $day_type_id,      // i - integer
                                    $has_missing       // i - integer
                                );
                                
                                if ($stmt->execute()) {
                                    $imported++;
                                    $display_rows[] = [
                                        'biometric_id' => $biometric_id,
                                        'name' => $full_name,
                                        'date' => $log_date,
                                        'logs' => $log_data['raw_logs_count'],
                                        'time_in' => $time_in ? date('H:i', strtotime($time_in)) : '-',
                                        'time_out' => $time_out ? date('H:i', strtotime($time_out)) : '-',
                                        'total_hours' => number_format($total, 2)
                                    ];
                                } else {
                                    $debug_info[] = "Insert failed for employee $employee_id on $log_date: " . $stmt->error;
                                    $errors++;
                                }
                                
                                $stmt->close();
                                $check_stmt->close();
                            }
                        }
                        
                        $debug_info[] = "Step 5: Imported $imported records, skipped $skipped duplicates, $errors errors";
                        
                        if ($errors > 0) {
                            $status = "⚠️ Partially successful: Imported <strong>$imported</strong> DTR records, skipped <strong>$skipped</strong> duplicates, but encountered <strong>$errors</strong> errors.";
                        } else {
                            $status = "✅ Successfully imported <strong>$imported</strong> DTR records. Skipped <strong>$skipped</strong> duplicates.";
                        }
                    }
                }
                
            } catch (Exception $e) {
                $status = "❌ Error: " . $e->getMessage();
                $debug_info[] = "Exception occurred: " . $e->getMessage();
            }
            
            odbc_close($access_conn);
        }
    }
}
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Import Employee DTR</h5>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="container my-4 d-flex justify-content-center">
      <div class="card shadow w-100" style="max-width: 1000px;">
        <div class="card-header bg-primary text-white">
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
                $result = mysqli_query($conn, "SELECT * FROM departments ORDER BY name ASC");
                while ($row = mysqli_fetch_assoc($result)) {
                  $selected = (isset($_POST['office']) && $_POST['office'] == $row['name']) ? 'selected' : '';
                  echo "<option value=\"" . htmlspecialchars($row['name']) . "\" $selected>" . htmlspecialchars($row['name']) . "</option>";
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

            <div class="col-12 mt-2">
              <button type="submit" class="btn btn-success px-4"><i class="fas fa-upload me-2"></i>Import DTR</button>
              <span class="text-muted ms-3 small">Make sure MS Access DB is closed before importing.</span>
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

          <?php if (!empty($display_rows)): ?>
            <div class="table-responsive mt-4">
              <h6>Imported DTR Records (<?= count($display_rows) ?> records)</h6>
              <table class="table table-sm table-bordered">
                <thead>
                  <tr>
                    <th>Biometric ID</th>
                    <th>Employee Name</th>
                    <th>Date</th>
                    <th>Raw Logs</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Total Hours</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($display_rows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['biometric_id']) ?></td>
                      <td><?= htmlspecialchars($r['name']) ?></td>
                      <td><?= htmlspecialchars($r['date']) ?></td>
                      <td><span class="badge bg-info"><?= $r['logs'] ?></span></td>
                      <td><?= htmlspecialchars($r['time_in']) ?></td>
                      <td><?= htmlspecialchars($r['time_out']) ?></td>
                      <td><?= htmlspecialchars($r['total_hours']) ?>h</td>
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
