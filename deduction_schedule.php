<?php
session_start();
include 'connection.php';

// ADD DEDUCTION SCHEDULE
if (isset($_POST['add_schedule'])) {
    $deduction_id = $_POST['deduction_id'];
    $cutoff_type = $_POST['cutoff_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $remarks = $_POST['remarks'];

    $insert = mysqli_query($conn, "INSERT INTO deduction_schedule (deduction_id, cutoff_type, is_active, remarks) VALUES ('$deduction_id', '$cutoff_type', '$is_active', '$remarks')");
    $_SESSION['success'] = $insert ? 'Deduction schedule added successfully!' : 'Failed to add deduction schedule.';
    header("Location: deduction_schedule.php");
    exit();
}

// UPDATE DEDUCTION SCHEDULE
if (isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $deduction_id = $_POST['deduction_id'];
    $cutoff_type = $_POST['cutoff_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $remarks = $_POST['remarks'];

    $update = mysqli_query($conn, "UPDATE deduction_schedule SET deduction_id='$deduction_id', cutoff_type='$cutoff_type', is_active='$is_active', remarks='$remarks' WHERE schedule_id=$schedule_id");
    $_SESSION['success'] = $update ? 'Deduction schedule updated successfully!' : 'Failed to update deduction schedule.';
    header("Location: deduction_schedule.php");
    exit();
}

// DELETE DEDUCTION SCHEDULE
if (isset($_POST['delete_schedule'])) {
    $schedule_id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM deduction_schedule WHERE schedule_id=$schedule_id");
    $_SESSION['success'] = $delete ? 'Deduction schedule deleted successfully!' : 'Failed to delete deduction schedule.';
    header("Location: deduction_schedule.php");
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
              <h5 class="m-b-10">Deduction Schedule Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Deduction Schedule</li>
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
              <h5 class="mb-0">Deduction Schedules</h5>
              <small class="text-muted">Manage deduction cutoff schedules</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Schedule
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="scheduleTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Deduction Type</th>
                    <th>Cutoff Type</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT ds.*, dt.name as deduction_name FROM deduction_schedule ds LEFT JOIN deductiontypes dt ON ds.deduction_id = dt.deduction_id ORDER BY ds.schedule_id ASC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark"><?= $row['schedule_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['deduction_name'] ?? 'N/A'); ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($row['cutoff_type']); ?></span></td>
                    <td>
                      <?php if ($row['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['remarks'] ?? ''); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['schedule_id']; ?>"
                          data-deduction-id="<?= $row['deduction_id']; ?>"
                          data-cutoff-type="<?= htmlspecialchars($row['cutoff_type']); ?>"
                          data-is-active="<?= $row['is_active']; ?>"
                          data-remarks="<?= htmlspecialchars($row['remarks']); ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['schedule_id']; ?>"
                          title="Delete"><i class="ti ti-trash"></i></button>
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
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Deduction Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Deduction Type</label>
          <select name="deduction_id" class="form-select" required>
            <option value="">Select Deduction Type</option>
            <?php
            $deductions = mysqli_query($conn, "SELECT * FROM deductiontypes ORDER BY name ASC");
            while ($deduction = mysqli_fetch_assoc($deductions)): ?>
              <option value="<?= $deduction['deduction_id']; ?>"><?= htmlspecialchars($deduction['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Cutoff Type</label>
          <select name="cutoff_type" class="form-select" required>
            <option value="">Select Cutoff Type</option>
            <option value="1st Half">1st Half</option>
            <option value="2nd Half">2nd Half</option>
            <option value="Every Cutoff">Every Cutoff</option>
          </select>
        </div>
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" checked>
            <label class="form-check-label" for="add_is_active">
              Active
            </label>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_schedule" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Deduction Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="schedule_id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Deduction Type</label>
          <select name="deduction_id" id="edit_deduction_id" class="form-select" required>
            <option value="">Select Deduction Type</option>
            <?php
            $deductions = mysqli_query($conn, "SELECT * FROM deductiontypes ORDER BY name ASC");
            while ($deduction = mysqli_fetch_assoc($deductions)): ?>
              <option value="<?= $deduction['deduction_id']; ?>"><?= htmlspecialchars($deduction['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Cutoff Type</label>
          <select name="cutoff_type" id="edit_cutoff_type" class="form-select" required>
            <option value="">Select Cutoff Type</option>
            <option value="1st Half">1st Half</option>
            <option value="2nd Half">2nd Half</option>
            <option value="Every Cutoff">Every Cutoff</option>
          </select>
        </div>
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
            <label class="form-check-label" for="edit_is_active">
              Active
            </label>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" id="edit_remarks" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_schedule" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_schedule" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#scheduleTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search schedules...",
      zeroRecords: "No matching records found",
      emptyTable: "No schedules available",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      paginate: {
        first: "First", last: "Last", next: "Next", previous: "Previous"
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, className: 'text-center', width: '60px' },
      { targets: 5, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_deduction_id').val($(this).data('deduction-id'));
      $('#edit_cutoff_type').val($(this).data('cutoff-type'));
      $('#edit_is_active').prop('checked', $(this).data('is-active') == 1);
      $('#edit_remarks').val($(this).data('remarks'));
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
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

  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success']; ?>',
    timer: 3000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end'
  });
  <?php unset($_SESSION['success']); endif; ?>
});
</script>
</body>
</html>