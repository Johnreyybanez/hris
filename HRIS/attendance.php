
<?php
session_start();
include 'connection.php';

// ADD RULE
if (isset($_POST['add_rule'])) {
    $name = mysqli_real_escape_string($conn, $_POST['rule_name']);
    $type = mysqli_real_escape_string($conn, $_POST['rule_type']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $threshold = (int)$_POST['threshold_minutes'];
    $active = isset($_POST['is_active']) ? 1 : 0;

    $insert = mysqli_query($conn, "INSERT INTO TimeAttendanceRules (rule_name, rule_type, description, threshold_minutes, is_active) VALUES ('$name', '$type', '$desc', $threshold, $active)");
    $_SESSION['success'] = $insert ? '✅ Rule added successfully!' : '❌ Failed to add rule.';
    header("Location: attendance.php");
    exit();
}

// UPDATE RULE
if (isset($_POST['update_rule'])) {
    $id = (int)$_POST['edit_id'];
    $name = mysqli_real_escape_string($conn, $_POST['rule_name']);
    $type = mysqli_real_escape_string($conn, $_POST['rule_type']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $threshold = (int)$_POST['threshold_minutes'];
    $active = isset($_POST['is_active']) ? 1 : 0;

    $update = mysqli_query($conn, "UPDATE TimeAttendanceRules SET rule_name='$name', rule_type='$type', description='$desc', threshold_minutes=$threshold, is_active=$active, updated_at=NOW() WHERE rule_id=$id");
    $_SESSION['success'] = $update ? '✅ Rule updated successfully!' : '❌ Failed to update rule.';
    header("Location: attendance.php");
    exit();
}

// DELETE RULE
if (isset($_POST['delete_rule'])) {
    $id = (int)$_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM TimeAttendanceRules WHERE rule_id=$id");
    $_SESSION['success'] = $delete ? '✅ Rule deleted successfully!' : '❌ Failed to delete rule.';
    header("Location: attendance.php");
    exit();
}

include 'head.php';
include 'sidebar.php';
include 'header.php';
?>

<!-- Main Content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Time Attendance Rules</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Time Attendance Rules</li>
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
              <h5 class="mb-0">Time Attendance Rules</h5>
              <small class="text-muted">Manage rules for late, undertime, overbreak, etc.</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Rule
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="ruleTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Threshold (min)</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $res = mysqli_query($conn, "SELECT * FROM TimeAttendanceRules ORDER BY updated_at DESC");
                  while ($row = mysqli_fetch_assoc($res)):
                  ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['rule_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['rule_name']) ?></td>
                    <td><?= htmlspecialchars($row['rule_type']) ?></td>
                    <td><?= $row['threshold_minutes'] ?></td>
                    <td>
                      <span class="badge <?= $row['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td><?= $row['updated_at'] ?></td>
                    <td class="text-center">
                      <button class="btn btn-sm btn-outline-warning editBtn"
                        data-id="<?= $row['rule_id'] ?>"
                        data-name="<?= htmlspecialchars($row['rule_name']) ?>"
                        data-type="<?= htmlspecialchars($row['rule_type']) ?>"
                        data-description="<?= htmlspecialchars($row['description']) ?>"
                        data-threshold="<?= $row['threshold_minutes'] ?>"
                        data-status="<?= $row['is_active'] ?>">
                        <i class="ti ti-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger deleteBtn" 
                        data-id="<?= $row['rule_id'] ?>"
                        data-name="<?= htmlspecialchars($row['rule_name']) ?>">
                        <i class="ti ti-trash"></i>
                      </button>
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
        <h5 class="modal-title">Add Rule</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label>Rule Name</label>
          <input type="text" name="rule_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label>Rule Type</label>
          <select name="rule_type" class="form-select" required>
            <option value="">-- Select --</option>
            <option value="late_grace">Late Grace Period</option>
            <option value="undertime_grace">Undertime Grace Period</option>
            <option value="late_round">Late Round Off</option>
            <option value="undertime_round">Undertime Round Off</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="mb-2">
          <label>Threshold (minutes)</label>
          <input type="number" name="threshold_minutes" min="0" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
          <label class="form-check-label" for="isActive">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_rule" class="btn btn-primary">Add Rule</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Rule</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="mb-2">
          <label>Rule Name</label>
          <input type="text" name="rule_name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label>Rule Type</label>
          <select name="rule_type" id="edit_type" class="form-select" required>
            <option value="late_grace">Late Grace Period</option>
            <option value="undertime_grace">Undertime Grace Period</option>
            <option value="late_round">Late Round Off</option>
            <option value="undertime_round">Undertime Round Off</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
        </div>
        <div class="mb-2">
          <label>Threshold (minutes)</label>
          <input type="number" name="threshold_minutes" id="edit_threshold" min="0" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="edit_active">
          <label class="form-check-label" for="edit_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_rule" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_rule">
</form>

<?php if (isset($_SESSION['success'])): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success'] ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
  });
</script>
<?php unset($_SESSION['success']); endif; ?>

<script>
$(document).ready(function () {
  $('#ruleTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search benefit types...",
      zeroRecords: "No matching records found",
      emptyTable: "No benefit types available",
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
      { targets: 0, className: 'text-center', width: '80px' },
      { targets: 3, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
    }
  });

  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  $('.editBtn').on('click', function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_name').val($(this).data('name'));
    $('#edit_type').val($(this).data('type'));
    $('#edit_description').val($(this).data('description'));
    $('#edit_threshold').val($(this).data('threshold'));
    $('#edit_active').prop('checked', $(this).data('status') == 1);
    new bootstrap.Modal(document.getElementById('editModal')).show();
  });

  $('.deleteBtn').on('click', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    Swal.fire({
      title: 'Delete Rule?',
      html: `Are you sure you want to delete <strong>${name}</strong>? This action cannot be undone!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        $('#delete_id').val(id);
        $('#deleteForm').submit();
      }
    });
  });
});
</script>

</body>
</html>