<?php
session_start();
include 'connection.php';
include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';

$employee_id = $_SESSION['user_id'] ?? null;

if (!$employee_id) {
    die("Unauthorized access. Please log in.");
}

$display_rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';

    if (empty($from_date) || empty($to_date)) {
        die("Both date fields are required.");
    }

    $query = "
        SELECT 
            e.biometric_id, 
            CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS name,
            d.date, d.day_of_week,
            d.time_in, d.break_out, d.break_in, d.time_out,
            d.total_work_hours, d.undertime_hours, d.overtime_hours,
            dt.name AS day_type, d.approval_status, d.remarks,
            s.shift_name, s.time_in AS scheduled_in, s.time_out AS scheduled_out
        FROM EmployeeDTR d
        JOIN Employees e ON d.employee_id = e.employee_id
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        LEFT JOIN DayTypes dt ON d.day_type_id = dt.day_type_id
        WHERE d.employee_id = ? AND d.date BETWEEN ? AND ?
        ORDER BY d.date DESC
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $employee_id, $from_date, $to_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate Late in minutes
        $scheduled_in = strtotime($row['scheduled_in']);
        $actual_in = strtotime($row['time_in']);
        $late_minutes = ($actual_in > $scheduled_in) ? round(($actual_in - $scheduled_in) / 60) : 0;

        // Simulated Night Differential (between 10 PM and 6 AM)
        $time_out = strtotime($row['time_out']);
        $nd_hours = 0;
        if ($time_out) {
            $ten_pm = strtotime(date('Y-m-d', strtotime($row['date'])) . ' 22:00:00');
            $six_am = strtotime(date('Y-m-d', strtotime($row['date'] . ' +1 day')) . ' 06:00:00');
            if ($time_out > $ten_pm) {
                $nd_end = min($time_out, $six_am);
                $nd_hours = round(($nd_end - max($ten_pm, strtotime($row['time_in']))) / 3600, 2);
            }
        }

        $row['late_minutes'] = $late_minutes;
        $row['night_diff'] = $nd_hours;
        $display_rows[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<div class="pc-container">
  <div class="pcoded-content">
    <div class="row justify-content-center">
      <div class="col-lg-10">

        <div class="card shadow-sm mt-4">
          <div class="card-header"><h5 class="mb-0">My DTR Filter</h5></div>
          <div class="card-body">
            <form method="POST" class="row g-3">
              <div class="col-md-6">
                <label for="from_date" class="form-label">Start Date</label>
                <input type="date" name="from_date" id="from_date" class="form-control" value="<?= $_POST['from_date'] ?? '' ?>" required>
              </div>

              <div class="col-md-6">
                <label for="to_date" class="form-label">End Date</label>
                <input type="date" name="to_date" id="to_date" class="form-control" value="<?= $_POST['to_date'] ?? '' ?>" required>
              </div>

              <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">Filter DTR</button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">Clear</a>
              </div>
            </form>
          </div>
        </div>

        <?php if (!empty($display_rows)): ?>
        <div class="card shadow-sm mt-4">
          <div class="card-header"><h5 class="mb-0">My DTR Records (<?= count($display_rows) ?>)</h5></div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-bordered" id="dtrTable">
                <thead class="table-light">
  <tr>
    <th>Biometrix ID</th>
    <th>Date</th>
    <th>Day Type</th>
    <th>Scheduled In</th>
    <th>Actual In</th>
    <th>Scheduled Out</th>
    <th>Actual Out</th>
    <th>Late (mins)</th>
    <th>Undertime</th>
    <th>Overtime</th>
    <th>Night Diff (hrs)</th>
    <th>Total Hours Worked</th>
    <th>Remarks</th>
  </tr>
</thead>

                <tbody>
                  <?php foreach ($display_rows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['biometric_id']) ?></td>
                    <td><?= htmlspecialchars($r['date']) ?></td>
                    <td>
                      <?php
                      $badge_colors = [
                        'Regular Day' => 'primary',
                        'Rest Day' => 'success',
                        'Special Non-Working Holiday' => 'warning',
                        'Regular Holiday' => 'danger',
                        'Suspended Workday' => 'dark'
                      ];
                      $badge_color = $badge_colors[$r['day_type']] ?? 'secondary';
                      ?>
                      <span class="badge bg-<?= $badge_color ?>"><?= htmlspecialchars($r['day_type']) ?></span>
                    </td>
                    <td><?= $r['scheduled_in'] ? date('h:i A', strtotime($r['scheduled_in'])) : '' ?></td>
                    <td><?= $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '' ?></td>
                 <td>
  <?= $r['scheduled_out'] ? date('h:i A', strtotime($r['scheduled_out'] . ' +12 hours')) : '' ?>
</td>


                    <td><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '' ?></td>
                    <td><?= $r['late_minutes'] ?> min</td>
                    <td><?= htmlspecialchars($r['undertime_hours']) ?></td>
                    <td><?= htmlspecialchars($r['overtime_hours']) ?></td>
                    <td><?= $r['night_diff'] ?> hrs</td>
                    <td>
                      <?php
                        $total = $r['total_work_hours'];
                        $hours = floor($total);
                        $minutes = round(($total - $hours) * 60);
                        echo "{$hours}h {$minutes}m";
                      ?>
                    </td>
                    <td><?= htmlspecialchars($r['remarks']) ?></td>

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
<?php
// Prepare variables
$employee_name = ''; // default
if (isset($_SESSION['user_id'])) {
    $id = $_SESSION['user_id'];
    $get = mysqli_query($conn, "SELECT CONCAT(first_name, ' ', last_name) AS name FROM Employees WHERE employee_id = $id LIMIT 1");
    $employee = mysqli_fetch_assoc($get);
    $employee_name = $employee['name'] ?? '';
}
$from_date_js = isset($_POST['from_date']) ? htmlspecialchars($_POST['from_date']) : '';
$to_date_js = isset($_POST['to_date']) ? htmlspecialchars($_POST['to_date']) : '';
$logo_base64 = base64_encode(file_get_contents('logo.png'));
?>
<script>
const employeeName = "<?= $employee_name ?>";
const fromDate = "<?= $from_date_js ?>";
const toDate = "<?= $to_date_js ?>";
const logoBase64 = "data:image/png;base64,<?= $logo_base64 ?>";


$(document).ready(function () {
    var table = $('#dtrTable').DataTable({
        scrollX: true,
        scrollCollapse: true,
        lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
        pageLength: 10,
        dom:
            "<'row mb-3'" +
                "<'col-md-6 d-flex align-items-center gap-2'B>" +
                // Removed search bar from this line
            ">" +
            "<'row'<'col-12'tr>>" +
            "<'row mt-3'" +
                "<'col-md-6'l>" +
                "<'col-md-6 text-end'p>" +
            ">",
        buttons: [
            {
                extend: 'copy',
                title: '',
                messageTop: `Daily Time Record\n${employeeName}\nDate Range: ${fromDate} to ${toDate}`,
                text: '<i class="fas fa-copy me-1"></i> Copy',
                className: 'btn btn-light border btn-sm rounded-2 me-2'
            },
            {
                extend: 'csv',
                title: '',
                messageTop: `Daily Time Record - ${employeeName} | ${fromDate} to ${toDate}`,
                text: '<i class="fas fa-file-csv me-1"></i> CSV',
                className: 'btn btn-primary btn-sm text-white rounded-2 me-2'
            },
            {
                extend: 'excel',
                title: '',
                messageTop: `Daily Time Record - ${employeeName} | ${fromDate} to ${toDate}`,
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                className: 'btn btn-success btn-sm text-white rounded-2 me-2'
            },
            {
                extend: 'pdf',
                title: '',
                messageTop: `Daily Time Record\n${employeeName}\nDate Range: ${fromDate} to ${toDate}`,
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                className: 'btn btn-danger btn-sm text-white rounded-2 me-2'
            },
            {
                extend: 'print',
                title: '',
                messageTop: function () {
                    return `
                        <div style="text-align:center;">
                            <img src="${logoBase64}" style="height:60px;"><br>
                            <h3 style="margin: 10px 0;">Daily Time Record</h3>
                            <strong>${employeeName}</strong><br>
                            Date Range: ${fromDate} to ${toDate}
                        </div>`;
                },
                customize: function (win) {
                    $(win.document.body).css('font-size', '12pt');
                    $(win.document.body).find('table thead').css('background-color', '#0e7a81').css('color', '#fff');
                    $(win.document.body).find('table').css('border-collapse', 'collapse');
                },
                text: '<i class="fas fa-print me-1"></i> Print',
                className: 'btn btn-info btn-sm text-white rounded-2 me-2'
            }
        ],
        language: {
            // Removed search config since search bar is hidden
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            },
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ records"
        }
    });

    table.buttons().container().appendTo($('.col-md-6.d-flex'));
});
</script>
