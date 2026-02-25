<?php
session_start();
include 'connection.php';

// ADD LEAVE TYPE
if (isset($_POST['add_leave_type'])) {
    $leave_code = $_POST['leave_code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $credit_setup = $_POST['credit_setup'];
    $default_credits = $_POST['default_credits'] ?: 0;
    $carry_over_cap = $_POST['carry_over_cap'] ?: 'NULL';
    $carry_over_expiry = $_POST['carry_over_expiry'] ?: 'NULL';
    $allow_negative = isset($_POST['allow_negative']) ? 1 : 0;
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $is_convertible = isset($_POST['is_convertible']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $insert = mysqli_query($conn, "INSERT INTO leavetypes (
        LeaveCode, name, description, CreditSetupType, DefaultCredits,
        CarryOverCap, CarryOverExpiry, AllowNegativeBalance,
        is_paid, IsConvertible, IsActive
    ) VALUES (
        '$leave_code', '$name', '$description', '$credit_setup', $default_credits,
        $carry_over_cap, $carry_over_expiry, $allow_negative,
        $is_paid, $is_convertible, $is_active
    )");

    $_SESSION['success'] = $insert ? 'Leave type added successfully!' : 'Failed to add leave type.';
    header("Location: leave_types.php");
    exit();
}

// UPDATE LEAVE TYPE
if (isset($_POST['update_leave_type'])) {
    $id = $_POST['leave_type_id'];
    $leave_code = $_POST['leave_code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $credit_setup = $_POST['credit_setup'];
    $default_credits = $_POST['default_credits'] ?: 0;
    $carry_over_cap = $_POST['carry_over_cap'] ?: 'NULL';
    $carry_over_expiry = $_POST['carry_over_expiry'] ?: 'NULL';
    $allow_negative = isset($_POST['allow_negative']) ? 1 : 0;
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $is_convertible = isset($_POST['is_convertible']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $update = mysqli_query($conn, "UPDATE leavetypes SET
        LeaveCode='$leave_code',
        name='$name',
        description='$description',
        CreditSetupType='$credit_setup',
        DefaultCredits=$default_credits,
        CarryOverCap=$carry_over_cap,
        CarryOverExpiry=$carry_over_expiry,
        AllowNegativeBalance=$allow_negative,
        is_paid=$is_paid,
        IsConvertible=$is_convertible,
        IsActive=$is_active
        WHERE leave_type_id=$id
    ");

    $_SESSION['success'] = $update ? 'Leave type updated successfully!' : 'Failed to update leave type.';
    header("Location: leave_types.php");
    exit();
}

// DELETE LEAVE TYPE
if (isset($_POST['delete_leave_type'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM leavetypes WHERE leave_type_id=$id");
    $_SESSION['success'] = $delete ? 'Leave type deleted successfully!' : 'Failed to delete leave type.';
    header("Location: leave_types.php");
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
              <h5 class="m-b-10">Leave Type Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active" aria-current="page">Leave Types</li>
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
              <h5 class="mb-0">Leave Types</h5>
              <small class="text-muted">Manage types of employee leaves</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Leave Type
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100"  id="typeTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                   
                    <th>Credit Setup</th>
                    <th>Default Credits</th>
                    <th>Carry Over Cap</th>
                    <th>Expiry (Days)</th>
                    <th>Allow Negative</th>
                    <th>Paid</th>
                    <th>Convertible</th>
                    <th>Active</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $result = mysqli_query($conn, "SELECT * FROM leavetypes");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['leave_type_id']; ?></td>
                    <td><?= htmlspecialchars($row['LeaveCode']); ?></td>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    
                    <td><?= $row['CreditSetupType']; ?></td>
                    <td><?= $row['DefaultCredits']; ?></td>
                    <td><?= $row['CarryOverCap'] ?? 'Unlimited'; ?></td>
                    <td><?= $row['CarryOverExpiry'] ?? 'Never'; ?></td>
                    <td><?= $row['AllowNegativeBalance'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $row['is_paid'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $row['IsConvertible'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $row['IsActive'] ? 'Yes' : 'No'; ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['leave_type_id']; ?>"
                          data-leave_code="<?= htmlspecialchars($row['LeaveCode']); ?>"
                          data-name="<?= htmlspecialchars($row['name']); ?>"
                          data-description="<?= htmlspecialchars($row['description']); ?>"
                          data-credit_setup="<?= $row['CreditSetupType']; ?>"
                          data-default_credits="<?= $row['DefaultCredits']; ?>"
                          data-carry_over_cap="<?= $row['CarryOverCap']; ?>"
                          data-carry_over_expiry="<?= $row['CarryOverExpiry']; ?>"
                          data-allow_negative="<?= $row['AllowNegativeBalance']; ?>"
                          data-paid="<?= $row['is_paid']; ?>"
                          data-convertible="<?= $row['IsConvertible']; ?>"
                          data-active="<?= $row['IsActive']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                          <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['leave_type_id']; ?>"
                          data-leave_code="<?= htmlspecialchars($row['LeaveCode']); ?>"
                          data-name="<?= htmlspecialchars($row['name']); ?>"
                          data-description="<?= htmlspecialchars($row['description']); ?>"
                          data-credit_setup="<?= $row['CreditSetupType']; ?>"
                          data-default_credits="<?= $row['DefaultCredits']; ?>"
                          data-carry_over_cap="<?= $row['CarryOverCap']; ?>"
                          data-carry_over_expiry="<?= $row['CarryOverExpiry']; ?>"
                          data-allow_negative="<?= $row['AllowNegativeBalance']; ?>"
                          data-paid="<?= $row['is_paid']; ?>"
                          data-convertible="<?= $row['IsConvertible']; ?>"
                          data-active="<?= $row['IsActive']; ?>"
                          title="View"><i class="ti ti-eye"></i></button>

                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['leave_type_id']; ?>"
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
<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>View Leave Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Leave Code</label>
          <input type="text" id="view_leave_code" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Type Name</label>
          <input type="text" id="view_name" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea id="view_description" class="form-control" readonly></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Credit Setup Type</label>
          <input type="text" id="view_credit_setup" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Default Credits</label>
          <input type="text" id="view_default_credits" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Cap</label>
          <input type="text" id="view_carry_over_cap" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Expiry (days)</label>
          <input type="text" id="view_carry_over_expiry" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Allow Negative Balance</label>
          <input type="text" id="view_allow_negative" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Paid Leave</label>
          <input type="text" id="view_is_paid" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Convertible to Cash</label>
          <input type="text" id="view_is_convertible" class="form-control" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Active</label>
          <input type="text" id="view_is_active" class="form-control" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Leave Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Leave Code</label>
          <input type="text" name="leave_code" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Type Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Credit Setup Type</label>
          <select name="credit_setup" class="form-select" required>
            <option value="Annual">Annual</option>
            <option value="Monthly">Monthly</option>
            <option value="Manual">Manual</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Default Credits</label>
          <input type="number" step="0.01" name="default_credits" class="form-control" value="0">
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Cap</label>
          <input type="number" step="0.01" name="carry_over_cap" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Expiry (days)</label>
          <input type="number" name="carry_over_expiry" class="form-control">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="allow_negative" id="add_allow_negative">
          <label class="form-check-label" for="add_allow_negative">Allow Negative Balance</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_paid" id="add_is_paid" checked>
          <label class="form-check-label" for="add_is_paid">Paid Leave</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_convertible" id="add_is_convertible">
          <label class="form-check-label" for="add_is_convertible">Convertible to Cash</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" checked>
          <label class="form-check-label" for="add_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_leave_type" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Leave Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="leave_type_id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Leave Code</label>
          <input type="text" name="leave_code" id="edit_leave_code" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Type Name</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Credit Setup Type</label>
          <select name="credit_setup" id="edit_credit_setup" class="form-select" required>
            <option value="Annual">Annual</option>
            <option value="Monthly">Monthly</option>
            <option value="Manual">Manual</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Default Credits</label>
          <input type="number" step="0.01" name="default_credits" id="edit_default_credits" class="form-control" value="0">
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Cap</label>
          <input type="number" step="0.01" name="carry_over_cap" id="edit_carry_over_cap" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Carry Over Expiry (days)</label>
          <input type="number" name="carry_over_expiry" id="edit_carry_over_expiry" class="form-control">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="allow_negative" id="edit_allow_negative">
          <label class="form-check-label" for="edit_allow_negative">Allow Negative Balance</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_paid" id="edit_is_paid">
          <label class="form-check-label" for="edit_is_paid">Paid Leave</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_convertible" id="edit_is_convertible">
          <label class="form-check-label" for="edit_is_convertible">Convertible to Cash</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
          <label class="form-check-label" for="edit_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_leave_type" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_leave_type" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#typeTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search departments...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching departments found',
      emptyTable: 'No departments available',
      paginate: {
        first: 'First',
        last: 'Last', 
        next: 'Next',
        previous: 'Previous'
      
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, width: '80px', className: 'text-center' },
      { targets: 4, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });


  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  } 
  
  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_leave_code').val($(this).data('leave_code'));
      $('#edit_name').val($(this).data('name'));
      $('#edit_description').val($(this).data('description'));
      $('#edit_credit_setup').val($(this).data('credit_setup'));
      $('#edit_default_credits').val($(this).data('default_credits'));
      $('#edit_carry_over_cap').val($(this).data('carry_over_cap'));
      $('#edit_carry_over_expiry').val($(this).data('carry_over_expiry'));
      $('#edit_allow_negative').prop('checked', $(this).data('allow_negative') == 1);
      $('#edit_is_paid').prop('checked', $(this).data('paid') == 1);
      $('#edit_is_convertible').prop('checked', $(this).data('convertible') == 1);
      $('#edit_is_active').prop('checked', $(this).data('active') == 1);
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
      $('.viewBtn').off().on('click', function () {
        $('#view_leave_code').val($(this).data('leave_code'));
        $('#view_name').val($(this).data('name'));
        $('#view_description').val($(this).data('description'));
        $('#view_credit_setup').val($(this).data('credit_setup'));
        $('#view_default_credits').val($(this).data('default_credits'));
        $('#view_carry_over_cap').val($(this).data('carry_over_cap') ?? 'Unlimited');
        $('#view_carry_over_expiry').val($(this).data('carry_over_expiry') ?? 'Never');
        $('#view_allow_negative').val($(this).data('allow_negative') == 1 ? 'Yes' : 'No');
        $('#view_is_paid').val($(this).data('paid') == 1 ? 'Yes' : 'No');
        $('#view_is_convertible').val($(this).data('convertible') == 1 ? 'Yes' : 'No');
        $('#view_is_active').val($(this).data('active') == 1 ? 'Yes' : 'No');
        new bootstrap.Modal(document.getElementById('viewModal')).show();
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
