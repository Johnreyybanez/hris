<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'] ?? 0;

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    $action = $_POST['action'] ?? 'add';
    $log_id = $_POST['log_id'] ?? null;
    $date = $_POST['date'];
    $missing_field = $_POST['missing_field'];
    $requested_time = $_POST['requested_time'] ?? null;
    $reason = $_POST['reason'];

    // Ensure format HH:MM:SS for MySQL TIME
    if (!empty($requested_time)) {
        $requested_time .= ":00";
    }

    if ($action === 'edit' && $log_id) {
        $stmt = $conn->prepare("UPDATE MissingTimeLogRequests SET date=?, missing_field=?, requested_time=?, reason=? WHERE request_id=? AND employee_id=?");
        $stmt->bind_param("ssssii", $date, $missing_field, $requested_time, $reason, $log_id, $employee_id);
        $stmt->execute();
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Request updated successfully." : "No changes made.";
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO MissingTimeLogRequests (employee_id, date, missing_field, requested_time, reason, status, requested_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("issss", $employee_id, $date, $missing_field, $requested_time, $reason);
        $stmt->execute();
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Request filed successfully." : "Failed to file request.";
        $stmt->close();
    }
    header("Location: user_missing_time_log.php");
    exit;
}

// Fetch logs
$logs_q = mysqli_query($conn, "SELECT * FROM MissingTimeLogRequests WHERE employee_id = '$employee_id' ORDER BY requested_at DESC");
?>

<!-- Page Layout -->
<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Missing Time Log Requests</h5>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#logModal">
          <i class="bi bi-plus-circle me-1"></i> File Request
        </button>
      </div>

      <div class="table-responsive mt-3">
        <table id="missingLogsTable" class="table table-bordered table-striped align-middle">
          <thead class="table-dark text-center">
            <tr>
              <th>Date</th>
              <th>Missing Field</th>
              <th>Requested Time</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Requested At</th>
              <th>Approved At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($logs_q)) : ?>
              <tr>
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td class="text-center"><?= strtoupper(htmlspecialchars($row['missing_field'])) ?></td>
                <td class="text-center">
                  <?= ($row['requested_time'] && $row['requested_time'] !== '00:00:00') ? date('h:i A', strtotime($row['requested_time'])) : '—'; ?>
                </td>
                <td><?= htmlspecialchars($row['reason']) ?></td>
                <td class="text-center">
                  <?php
                  $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'][$row['status']] ?? 'secondary';
                  echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                  ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['status'] == 'Pending') : ?>
                    <button class="btn btn-sm btn-primary" onclick='editLog(<?= json_encode($row) ?>)'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="log_id" value="<?= $row['request_id'] ?>">
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
        <h5 class="modal-title">Missing Time Log</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="date" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Missing Field</label>
          <select name="missing_field" id="missing_field" class="form-select" required>
            <option value="">Select</option>
            <option value="time_in">Time In</option>
            <option value="time_out">Time Out</option>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">Requested Time</label>
          <input type="time" name="requested_time" id="requested_time" class="form-control" required>
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
  const table = $('#missingLogsTable').DataTable({
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search documents...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ documents",
            infoEmpty: "No missing time logs available",
            zeroRecords: "No matching missing time logs found"
        },
        dom:
            "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>"+
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
    });

    // Build modern Bootstrap search input group with icon
    setTimeout(() => {
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0 rounded-start">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search missing time logs..." id="dt-search-input">
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        // Link search input to DataTable search
        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
        });
    }, 0);
});




  function editLog(data) {
    document.getElementById('form_action').value = 'edit';
    document.getElementById('log_id').value = data.request_id;
    document.getElementById('date').value = data.date;
    document.getElementById('missing_field').value = data.missing_field;

    if (data.requested_time && data.requested_time !== '00:00:00') {
      let timeParts = data.requested_time.split(':');
      document.getElementById('requested_time').value = timeParts[0].padStart(2, '0') + ':' + timeParts[1].padStart(2, '0');
    } else {
      document.getElementById('requested_time').value = '';
    }

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
    border-color: #ced4da !important; /* default border */
  }
</style>