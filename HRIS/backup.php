<?php
session_start();
ini_set('max_execution_time', 300); // 300 seconds = 5 minutes
include 'connection.php'; // $conn (MySQLi)
include 'head.php';
include 'sidebar.php';
include 'header.php';

$status = "";
$display_rows = [];
$debug_info = [];
$default_mdb_path = "C:\Users\Administrator\Desktop\attBackup.mdb";


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
  return ($diff->h * 60) + $diff->i;
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
  while ($row = mysqli_fetch_assoc($result)) {
    $rules[$row['rule_type']] = (int)$row['threshold_minutes'];
  }
  
  if ($late_minutes <= $rules['late_grace']) {
    $late_minutes = 0;
  } elseif ($rules['late_round']) {
    $late_minutes = ceil($late_minutes / $rules['late_round']) * $rules['late_round'];
  }
  
  if ($undertime_minutes <= $rules['undertime_grace']) {
    $undertime_minutes = 0;
  } elseif ($rules['undertime_round']) {
    $undertime_minutes = ceil($undertime_minutes / $rules['undertime_round']) * $rules['undertime_round'];
  }
  
  return [
    'late' => minutesToTime($late_minutes),
    'undertime' => minutesToTime($undertime_minutes)
  ];
}

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
        $mysql_employees = [];
        $emp_query = "SELECT employee_id, biometric_id, first_name, last_name, shift_id FROM Employees WHERE biometric_id IS NOT NULL AND biometric_id != ''";
        if ($office != "All") {
          $emp_query .= " AND department = '" . mysqli_real_escape_string($conn, $office) . "'";
        }
        $emp_result = mysqli_query($conn, $emp_query);

        while ($emp_row = mysqli_fetch_assoc($emp_result)) {
          $mysql_employees[$emp_row['biometric_id']] = $emp_row;
        }
        $debug_info[] = "✅ Found " . count($mysql_employees) . " employees in MySQL";

        $access_users = [];
        $userinfo_query = "SELECT USERID, BADGENUMBER, [NAME] FROM USERINFO";
        $userinfo_result = @odbc_exec($access_conn, $userinfo_query);

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

          $checkinout_query = "SELECT USERID, CHECKTIME FROM CHECKINOUT WHERE USERID IN ($user_ids) AND CHECKTIME BETWEEN #$from_date_access 00:00:00# AND #$to_date_access 23:59:59# ORDER BY USERID, CHECKTIME";

          $checkinout_result = @odbc_exec($access_conn, $checkinout_query);
          if (!$checkinout_result) throw new Exception("Failed to query CHECKINOUT: " . odbc_errormsg());

          $raw_logs = [];
          while ($row = odbc_fetch_array($checkinout_result)) {
            $user_id = $row['USERID'];
            $check_time = $row['CHECKTIME'];
            $date = date('Y-m-d', strtotime($check_time));
            $raw_logs[$user_id][$date][] = $check_time;
          }
          $debug_info[] = "✅ Retrieved raw logs for " . count($raw_logs) . " users";

          $imported = 0;
          $errors = 0;

          foreach ($raw_logs as $user_id => $dates) {
            $employee = $access_users[$user_id]['mysql_data'];
            $employee_id = $employee['employee_id'];
            $shift_id = $employee['shift_id'] ?? 1;

            foreach ($dates as $log_date => $logs) {
              sort($logs);
              $time_in = $logs[0] ?? null;
              $time_out = count($logs) > 1 ? end($logs) : null;

              $break_out = $break_in = null;
              if (count($logs) >= 4) {
                $break_out = $logs[1];
                $break_in = $logs[2];
              } elseif (count($logs) == 3) {
                $break_out = $logs[1];
              }

              $day_type_id = 1;
              $stmt_susp = $conn->prepare("SELECT 1 FROM WorkSuspensions WHERE DATE(date) = ?");
              $stmt_susp->bind_param("s", $log_date);
              $stmt_susp->execute();
              if ($stmt_susp->get_result()->num_rows > 0) $day_type_id = 11;
              $stmt_susp->close();

              $stmt_hol = $conn->prepare("SELECT day_type_id FROM HolidayCalendar WHERE DATE(date) = ?");
              $stmt_hol->bind_param("s", $log_date);
              $stmt_hol->execute();
              $holidays = $stmt_hol->get_result()->fetch_all(MYSQLI_ASSOC);
              $stmt_hol->close();

              if (!empty($holidays)) {
                $day_type_id = max(array_column($holidays, 'day_type_id'));
              }

              $day_name = strtolower(date('l', strtotime($log_date)));
              $stmt_rest = $conn->prepare("SELECT is_$day_name FROM ShiftDays WHERE shift_id = ?");
              $stmt_rest->bind_param("i", $shift_id);
              $stmt_rest->execute();
              $rest_row = $stmt_rest->get_result()->fetch_assoc();
              if (!empty($rest_row["is_$day_name"])) {
                if ($day_type_id == 1) $day_type_id = 2;
              }
              $stmt_rest->close();

              $total_work_time = $late_time = $undertime_time = $overtime_time = $night_time = '00:00';

              if ($time_in && $time_out) {
                $total_minutes = timeDifferenceInMinutes($time_in, $time_out);
                $stmt_shift = $conn->prepare("SELECT time_in, break_out, break_in, time_out, total_hours, is_flexible, has_break FROM Shifts WHERE shift_id = ?");
                $stmt_shift->bind_param("i", $shift_id);
                $stmt_shift->execute();
                $shift_row = $stmt_shift->get_result()->fetch_assoc();
                $stmt_shift->close();

                $is_flexible = $shift_row['is_flexible'] ?? 0;
                $has_break = $shift_row['has_break'] ?? 1;
                $shift_total_hours = $shift_row['total_hours'] ?? 8.0;

                $actual_in = new DateTime($time_in);
                $actual_out = new DateTime($time_out);

                if ($has_break && $break_out && $break_in) {
                  $break_minutes = timeDifferenceInMinutes($break_out, $break_in);
                  $total_minutes -= $break_minutes;
                }
                $total_work_time = minutesToTime(max(0, $total_minutes));

                $late_minutes = 0;
                $undertime_minutes = 0;

                if (!$is_flexible) {
                  $expected_in = new DateTime("$log_date " . $shift_row['time_in']);
                  $expected_out = new DateTime("$log_date " . $shift_row['time_out']);

                  if ($actual_in > $expected_in) {
                    $late_minutes = timeDifferenceInMinutes($expected_in->format('Y-m-d H:i:s'), $actual_in->format('Y-m-d H:i:s'));
                  }

                  if ($actual_out < $expected_out) {
                    $undertime_minutes = timeDifferenceInMinutes($actual_out->format('Y-m-d H:i:s'), $expected_out->format('Y-m-d H:i:s'));
                  }
                }

                $adjusted = applyAttendanceRules($conn, $late_minutes, $undertime_minutes);
                $late_time = $adjusted['late'];
                $undertime_time = $adjusted['undertime'];

                if ($total_minutes > ($shift_total_hours * 60)) {
                  $overtime_time = round(($total_minutes - ($shift_total_hours * 60)) / 60, 2);
                }

                $night_start = new DateTime("$log_date 22:00:00");
                $night_end = new DateTime(date('Y-m-d', strtotime($log_date . ' +1 day')) . " 06:00:00");
                if ($actual_in < $night_end && $actual_out > $night_start) {
                  $night_work_start = max($actual_in, $night_start);
                  $night_work_end = min($actual_out, $night_end);
                  if ($night_work_start < $night_work_end) {
                    $night_time = minutesToTime(timeDifferenceInMinutes($night_work_start->format('Y-m-d H:i:s'), $night_work_end->format('Y-m-d H:i:s')));
                  }
                }
              }

              $conn->query("DELETE FROM EmployeeDTR WHERE employee_id = '$employee_id' AND date = '$log_date'");

              $stmt_insert = $conn->prepare("INSERT INTO EmployeeDTR (
                  employee_id, date, day_of_week, shift_id,
                  time_in, break_out, break_in, time_out,
                  total_work_time, undertime_time, overtime_time, late_time, night_time,
                  day_type_id, is_flexible, is_manual, has_missing_log, approval_status
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pending')");

              $day_of_week = date('l', strtotime($log_date));
              $has_missing = ($time_in && $time_out) ? 0 : 1;
              $stmt_insert->bind_param("isssssssssssiiii",
                $employee_id, $log_date, $day_of_week, $shift_id,
                $time_in, $break_out, $break_in, $time_out,
                $total_work_time, $undertime_time, $overtime_time, $late_time, $night_time,
                $day_type_id, $is_flexible, $has_missing
              );

              if ($stmt_insert->execute()) {
                $imported++;
                $display_rows[] = [
                  'biometric_id' => $employee['biometric_id'],
                  'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                  'date' => $log_date,
                  'logs' => implode(', ', array_map(fn($t) => date('H:i', strtotime($t)), $logs)),
                  'time_in' => $time_in,
                  'break_out' => $break_out,
                  'break_in' => $break_in,
                  'time_out' => $time_out,
                  'total_work_time' => $total_work_time,
                  'late_time' => $late_time,
                  'undertime_time' => $undertime_time,
                  'overtime_time' => $overtime_time,
                  'night_time' => $night_time,
                  'day_type' => $day_type_id
                ];
              } else {
                $errors++;
                $debug_info[] = "Insert error: " . $stmt_insert->error;
              }
              $stmt_insert->close();
            }
          }
          $status = "✅ Imported <strong>$imported</strong> records, Overwritten old records where needed, Errors <strong>$errors</strong>";
        }
      } catch (Exception $e) {
        $status = "❌ Error: " . $e->getMessage();
      }
      odbc_close($access_conn);
    }
  }
}

$day_types = [];
$result = mysqli_query($conn, "SELECT day_type_id, name FROM DayTypes");
while ($row = mysqli_fetch_assoc($result)) {
  $day_types[$row['day_type_id']] = $row['name'];
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
      <div class="card shadow w-100" style="max-width: 1200px;">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-database me-2"></i>Import Employee DTR Logs</h5>
        </div>
        <div class="card-body">
          <form method="POST" class="row g-3">
            <div class="col-12">
              <label class="form-label fw-bold">Access Database Path</label>
              <input type="text" name="db_path" class="form-control" value="<?= htmlspecialchars($default_mdb_path) ?>"
                readonly>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Department (Office)</label>
              <select name="office" class="form-select" required>
                <option value="">-- Select Office --</option>
                <option value="All" <?= (isset($_POST['office']) && $_POST['office'] == 'All') ? 'selected' : '' ?>>All
                </option>
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
                <input type="date" name="from_date" class="form-control"
                  value="<?= htmlspecialchars($_POST['from_date'] ?? date('Y-m-d')) ?>" required>
                <span class="align-self-center">to</span>
                <input type="date" name="to_date" class="form-control"
                  value="<?= htmlspecialchars($_POST['to_date'] ?? date('Y-m-d')) ?>" required>
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
                    <th>Time In</th>
                    <th>Break Out</th>
                    <th>Break In</th>
                    <th>Time Out</th>
                    <th>Total Work</th>
                    <th>Late Time</th>
                    <th>Undertime</th>
                    <th>Overtime</th>
                    <th>Night Shift</th>
                    <th>Day Type</th>
                    <th>Raw Logs</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($display_rows as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['biometric_id']) ?></td>
                      <td><?= htmlspecialchars($r['name']) ?></td>
                      <td><?= htmlspecialchars($r['date']) ?></td>
                      <td><?= $r['time_in'] ? htmlspecialchars($r['time_in']) : '<span class="text-muted">NULL</span>' ?>
                      </td>
                      <td><?= $r['break_out'] ? htmlspecialchars($r['break_out']) : '<span class="text-muted">N/A</span>' ?>
                      </td>
                      <td><?= $r['break_in'] ? htmlspecialchars($r['break_in']) : '<span class="text-muted">N/A</span>' ?>
                      </td>
                      <td><?= $r['time_out'] ? htmlspecialchars($r['time_out']) : '<span class="text-muted">N/A</span>' ?>
                      </td>
                      <td><?= htmlspecialchars($r['total_work_time']) ?></td>
                      <td><?= htmlspecialchars($r['late_time']) ?></td>
                      <td><?= htmlspecialchars($r['undertime_time']) ?></td>
                      <td><?= htmlspecialchars($r['overtime_time']) ?></td>
                      <td><?= htmlspecialchars($r['night_time']) ?></td>
                      <td><?= $day_types[$r['day_type']] ?? 'Unknown' ?></td>
                      <td><span class="badge bg-info"><?= $r['logs'] ?></span></td>
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