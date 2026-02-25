<?php
session_start();
include 'connection.php';

// ADD LEAVE POLICY
if (isset($_POST['add_policy'])) {
    $leave_type = $_POST['leave_type'];
    $employment_type = $_POST['employment_type'];
    $accrual_rate = $_POST['accrual_rate'];
    $max_balance = $_POST['max_balance'];
    $carry_over = isset($_POST['can_carry_over']) ? 1 : 0;

    $insert = mysqli_query($conn, "INSERT INTO LeavePolicies (leave_type_id, employment_type_id, accrual_rate, max_balance, can_carry_over) 
                                   VALUES ('$leave_type', '$employment_type', '$accrual_rate', '$max_balance', '$carry_over')");
    $_SESSION['success'] = $insert ? 'Leave policy added successfully!' : 'Failed to add leave policy.';
    header("Location: leave_policies.php");
    exit();
}

// UPDATE LEAVE POLICY
if (isset($_POST['update_policy'])) {
    $id = $_POST['policy_id'];
    $leave_type = $_POST['leave_type'];
    $employment_type = $_POST['employment_type'];
    $accrual_rate = $_POST['accrual_rate'];
    $max_balance = $_POST['max_balance'];
    $carry_over = isset($_POST['can_carry_over']) ? 1 : 0;

    $update = mysqli_query($conn, "UPDATE LeavePolicies SET 
        leave_type_id='$leave_type',
        employment_type_id='$employment_type',
        accrual_rate='$accrual_rate',
        max_balance='$max_balance',
        can_carry_over='$carry_over'
        WHERE policy_id=$id");
    
    $_SESSION['success'] = $update ? 'Leave policy updated successfully!' : 'Failed to update leave policy.';
    header("Location: leave_policies.php");
    exit();
}

// DELETE LEAVE POLICY
if (isset($_POST['delete_policy'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM LeavePolicies WHERE policy_id=$id");
    $_SESSION['success'] = $delete ? 'Leave policy deleted successfully!' : 'Failed to delete leave policy.';
    header("Location: leave_policies.php");
    exit();
}

// Get dropdown data
$leave_types = mysqli_query($conn, "SELECT * FROM LeaveTypes");
$employment_types = mysqli_query($conn, "SELECT * FROM EmploymentTypes");
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <h5 class="m-b-10">Leave Policy Management</h5>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Leave Policies</li>
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
              <h5 class="mb-0">Leave Policies</h5>
              <small class="text-muted">Manage accrual and limits for leave types</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Policy
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center w-100" id="typeTable" >
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Leave Type</th>
                    <th>Employment Type</th>
                    <th>Accrual Rate</th>
                    <th>Max Balance</th>
                    <th>Carry Over</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $query = "
                    SELECT lp.*, lt.name AS leave_type, et.name AS employment_type 
                    FROM LeavePolicies lp
                    JOIN LeaveTypes lt ON lp.leave_type_id = lt.leave_type_id
                    JOIN EmploymentTypes et ON lp.employment_type_id = et.type_id
                    ORDER BY lp.policy_id ASC
                  ";
                  $result = mysqli_query($conn, $query);
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                  <td><span class="badge bg-dark -secondary"><?= $row['policy_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['leave_type']); ?></td>
                    <td><?= htmlspecialchars($row['employment_type']); ?></td>
                    <td><?= $row['accrual_rate']; ?></td>
                    <td><?= $row['max_balance']; ?></td>
                    <td><?= $row['can_carry_over'] ? 'Yes' : 'No'; ?></td>
                    <td class="text-center">
                    
                    <div class="btn-group gap-1">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['policy_id']; ?>"
                          data-leave_type="<?= $row['leave_type_id']; ?>"
                          data-employment_type="<?= $row['employment_type_id']; ?>"
                          data-accrual="<?= $row['accrual_rate']; ?>"
                          data-max="<?= $row['max_balance']; ?>"
                          data-carry="<?= $row['can_carry_over']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['policy_id']; ?>"
                          title="Delete"><i class="ti ti-trash"></i></button>
                      </div>
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
        <h5 class="modal-title">Add Leave Policy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Leave Type</label>
          <select name="leave_type" class="form-control" required>
            <?php while ($lt = mysqli_fetch_assoc($leave_types)): ?>
              <option value="<?= $lt['leave_type_id']; ?>"><?= $lt['name']; ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Employment Type</label>
          <select name="employment_type" class="form-control" required>
            <?php while ($et = mysqli_fetch_assoc($employment_types)): ?>
              <option value="<?= $et['type_id']; ?>"><?= $et['name']; ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Accrual Rate</label>
          <input type="number" name="accrual_rate" class="form-control" step="0.01" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Max Balance</label>
          <input type="number" name="max_balance" class="form-control" step="0.01">
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="can_carry_over" value="1">
          <label class="form-check-label">Can Carry Over</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_policy" class="btn btn-primary">Add Policy</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="policy_id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Leave Policy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Leave Type</label>
          <select name="leave_type" id="edit_leave_type" class="form-control" required></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Employment Type</label>
          <select name="employment_type" id="edit_employment_type" class="form-control" required></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Accrual Rate</label>
          <input type="number" name="accrual_rate" id="edit_accrual" class="form-control" step="0.01" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Max Balance</label>
          <input type="number" name="max_balance" id="edit_max" class="form-control" step="0.01">
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="can_carry_over" id="edit_carry">
          <label class="form-check-label">Can Carry Over</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_policy" class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_policy" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#typeTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search policies...",
      zeroRecords: "No matching records found",
      emptyTable: "No leave policies available",
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
      { targets: -1, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      const modal = new bootstrap.Modal(document.getElementById('editModal'));

      $('#edit_id').val($(this).data('id'));
      $('#edit_accrual').val($(this).data('accrual'));
      $('#edit_max').val($(this).data('max'));
      $('#edit_carry').prop('checked', $(this).data('carry') == 1);

      const leaveSelect = $('select[name="leave_type"]');
      const empSelect = $('select[name="employment_type"]');

      $('#edit_leave_type').html(leaveSelect.html());
      $('#edit_leave_type').val($(this).data('leave_type'));

      $('#edit_employment_type').html(empSelect.html());
      $('#edit_employment_type').val($(this).data('employment_type'));

      modal.show();
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
