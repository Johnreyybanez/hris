<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'];

// Handle Add/Edit/Delete Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_leave'])) {
    $action = $_POST['action'] ?? 'add';
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $total_days = $_POST['total_days'];
    $actual_leave_days = $_POST['actual_leave_days'];
    $remarks = $_POST['remarks'];
    $leave_request_id = $_POST['leave_request_id'] ?? null;

    if ($action === 'delete' && $leave_request_id) {
        $del = $conn->prepare("DELETE FROM EmployeeLeaveRequests WHERE leave_request_id = ? AND employee_id = ?");
        $del->bind_param("ii", $leave_request_id, $employee_id);
        $del->execute();
        $del->close();
        $_SESSION['leave_success'] = 'Leave request deleted.';
    } else {
        if ($action === 'edit' && $leave_request_id) {
            $stmt = $conn->prepare("UPDATE EmployeeLeaveRequests SET leave_type_id=?, start_date=?, end_date=?, total_days=?, actual_leave_days=?, approval_remarks=? WHERE leave_request_id=? AND employee_id=? AND status='Pending'");
            $stmt->bind_param("isssdsii", $leave_type_id, $start_date, $end_date, $total_days, $actual_leave_days, $remarks, $leave_request_id, $employee_id);
            $_SESSION['leave_success'] = 'Leave request updated.';
        } else {
            $stmt = $conn->prepare("INSERT INTO EmployeeLeaveRequests (employee_id, leave_type_id, start_date, end_date, total_days, actual_leave_days, status, requested_at, approval_remarks) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?)");
            $stmt->bind_param("iissdds", $employee_id, $leave_type_id, $start_date, $end_date, $total_days, $actual_leave_days, $remarks);
            $_SESSION['leave_success'] = 'Leave request submitted.';
        }
        $stmt->execute();
        $stmt->close();
    }

    header("Location: user_leave_request.php");
    exit;
}

$leave_q = mysqli_query($conn, "
    SELECT elr.*, lt.name AS leave_type
    FROM EmployeeLeaveRequests elr
    LEFT JOIN LeaveTypes lt ON elr.leave_type_id = lt.leave_type_id
    WHERE elr.employee_id = '$employee_id'
    ORDER BY elr.requested_at DESC
");

include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';
?>

<!-- Leave Request Section -->
<div class="pc-container">
  <div class="pc-content">
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Leave Requests</h5>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal">
          <i class="bi bi-plus-circle"></i> Add Leave
        </button>
      </div>

      <div class="table-responsive mt-3">
        <table id="leaveTable" class="table table-bordered align-middle">
          <thead class="table-dark text-center">
            <tr>
              <th>Leave Type</th>
              <th>Date Range</th>
              <th>Total Days</th>
              <th>Actual Days</th>
              <th>Status</th>
              <th>Requested At</th>
              <th>Approved At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($leave_q)) : ?>
              <tr>
                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                <td><?= date('M d, Y', strtotime($row['start_date'])) ?> to <?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                <td><?= $row['total_days'] ?></td>
                <td><?= $row['actual_leave_days'] ?></td>
                <td><span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= $row['status'] ?></span></td>
                <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['status'] === 'Pending') : ?>
                    <button class="btn btn-sm btn-primary btn-edit-leave" 
                            data-bs-toggle="modal" 
                            data-bs-target="#leaveModal" 
                            data-leave='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="leave_request_id" value="<?= $row['leave_request_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" name="save_leave" class="btn btn-sm btn-danger" onclick="return confirm('Delete this leave request?')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php elseif ($row['status'] === 'Rejected') : ?>
                    <span class="text-muted">—</span>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="leave_request_id" value="<?= $row['leave_request_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" name="save_leave" class="btn btn-sm btn-danger" onclick="return confirm('Delete this rejected request?')">
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

<!-- Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="leave_request_id" id="leave_request_id">
      <input type="hidden" name="action" value="add" id="form_action">
      <div class="modal-header">
        <h5 class="modal-title">Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Leave Type</label>
          <select name="leave_type_id" id="leave_type_id" class="form-select" required>
            <option value="">Select Type</option>
            <?php
            $types = mysqli_query($conn, "SELECT * FROM LeaveTypes");
            while ($type = mysqli_fetch_assoc($types)) {
              echo "<option value='{$type['leave_type_id']}'>{$type['name']}</option>";
            }
            ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" id="end_date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Total Days</label>
          <input type="number" name="total_days" id="total_days" step="0.5" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Actual Days</label>
          <input type="number" name="actual_leave_days" id="actual_leave_days" step="0.5" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" id="remarks" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="save_leave" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- DataTables + Script -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
    const table = $('#leaveTable').DataTable({
      lengthMenu: [5, 10, 25, 50, 100],
      pageLength: 10,
      language: {
        search: "_INPUT_",
        searchPlaceholder: "Search leave...",
        lengthMenu: "Show _MENU_ entries",
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        infoEmpty: "No records available",
        zeroRecords: "No matching leave found",
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
                <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search leave..." id="dt-search-input">
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        // Link search input to DataTable search
        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
        });
    }, 0);
});
</script>
<script>
  document.querySelectorAll('.btn-edit-leave').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const data = JSON.parse(btn.getAttribute('data-leave'));
      if (data.status !== 'Pending') {
        Swal.fire({
          icon: 'info',
          title: 'Not Editable',
          text: 'Only pending leave requests can be edited.',
        });
        e.preventDefault();
        return;
      }
      document.getElementById('form_action').value = 'edit';
      document.getElementById('leave_request_id').value = data.leave_request_id;
      document.getElementById('leave_type_id').value = data.leave_type_id;
      document.getElementById('start_date').value = data.start_date;
      document.getElementById('end_date').value = data.end_date;
      document.getElementById('total_days').value = data.total_days;
      document.getElementById('actual_leave_days').value = data.actual_leave_days;
      document.getElementById('remarks').value = data.approval_remarks;
    });
  });
</script>

<?php if (isset($_SESSION['leave_success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $_SESSION['leave_success'] ?>',
  toast: true,
  position: 'top-end',
  timer: 3000,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['leave_success']); endif; ?>
