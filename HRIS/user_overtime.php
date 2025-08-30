<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'] ?? 0;

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    $action = $_POST['action'] ?? 'add';
    $log_id = $_POST['log_id'] ?? null;
    $date = $_POST['date'];
    $start_time = $_POST['start_time'] . ":00";
    $end_time = $_POST['end_time'] . ":00";
    $reason = $_POST['reason'];

    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $interval = $start->diff($end);
    $total_hours = $interval->h + ($interval->i / 60);

    if ($action === 'edit' && $log_id) {
        $stmt = $conn->prepare("UPDATE overtime SET date=?, start_time=?, end_time=?, total_hours=?, reason=? WHERE overtime_id=? AND employee_id=?");
        $stmt->bind_param("sssdsii", $date, $start_time, $end_time, $total_hours, $reason, $log_id, $employee_id);
        $stmt->execute();
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Overtime request updated successfully." : "No changes made.";
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO overtime (employee_id, date, start_time, end_time, total_hours, reason, approval_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isssds", $employee_id, $date, $start_time, $end_time, $total_hours, $reason);
        $stmt->execute();
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Overtime request filed successfully." : "Failed to file request.";
        $stmt->close();
    }

    header("Location: user_overtime.php");
    exit;
}

// Fetch records
$logs_q = mysqli_query($conn, "SELECT * FROM overtime WHERE employee_id = '$employee_id' ORDER BY created_at DESC");
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Overtime Requests</h5>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#logModal">
          <i class="bi bi-plus-circle me-1"></i> File Request
        </button>
      </div>

      <div class="table-responsive mt-3">
        <table id="overtimeTable" class="table table-bordered table-striped align-middle">
          <thead class="table-dark text-center">
            <tr>
              <th>Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Total Hours</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($logs_q)) : ?>
              <tr>
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td><?= date('h:i A', strtotime($row['start_time'])) ?></td>
                <td><?= date('h:i A', strtotime($row['end_time'])) ?></td>
                <td class="text-center"><?= number_format($row['total_hours'], 2) ?> hrs</td>
                <td><?= htmlspecialchars($row['reason']) ?></td>
                <td class="text-center">
                  <?php
                  $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'][$row['approval_status']] ?? 'secondary';
                  echo "<span class='badge bg-$badge'>{$row['approval_status']}</span>";
                  ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                <td><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['approval_status'] == 'Pending') : ?>
                    <button class="btn btn-sm btn-primary" onclick='editLog(<?= json_encode($row) ?>)'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="log_id" value="<?= $row['overtime_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this request?')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php else : ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="log_id" id="log_id">
      <input type="hidden" name="action" id="form_action" value="add">
      <input type="hidden" name="save_log" value="1">
      <div class="modal-header">
        <h5 class="modal-title">Overtime Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="date" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Start Time</label>
          <input type="time" name="start_time" id="start_time" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">End Time</label>
          <input type="time" name="end_time" id="end_time" class="form-control" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Reason</label>
          <textarea name="reason" id="reason" class="form-control" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
    const table = $('#overtimeTable').DataTable({
      lengthMenu: [5, 10, 25, 50],
      pageLength: 10,
      language: {
        search: "",
        searchPlaceholder: "Search records...",
        lengthMenu: "Show _MENU_ entries",
        info: "Showing _START_ to _END_ of _TOTAL_ records",
        infoEmpty: "No overtime requests available",
        zeroRecords: "No matching overtime requests found"
      },
      dom: "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>" +
           "<'row'<'col-sm-12'tr>>" +
           "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
    });

    setTimeout(() => {
      const customSearch = `
        <div class="input-group w-auto ms-auto" style="max-width: 300px;">
          <span class="input-group-text bg-white border-end-0 rounded-start">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search..." id="dt-search-input">
        </div>`;
      $('.search-wrapper').html(customSearch);
      $('#dt-search-input').on('keyup', function () {
        table.search(this.value).draw();
      });
    }, 0);
  });

  function editLog(data) {
    document.getElementById('form_action').value = 'edit';
    document.getElementById('log_id').value = data.overtime_id;
    document.getElementById('date').value = data.date;
    document.getElementById('start_time').value = data.start_time.slice(0,5);
    document.getElementById('end_time').value = data.end_time.slice(0,5);
    document.getElementById('reason').value = data.reason;
    new bootstrap.Modal(document.getElementById('logModal')).show();
  }
</script>

<?php if (isset($_SESSION['success'])) : ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success'] ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])) : ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?= $_SESSION['error'] ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
</script>
<?php unset($_SESSION['error']); endif; ?>

<style>
  #dt-search-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: #ced4da !important;
  }
</style>
