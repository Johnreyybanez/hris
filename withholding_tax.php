<?php
session_start();
include 'connection.php';

// ADD WITHHOLDING TAX ENTRY
if (isset($_POST['add_tax'])) {
    $salary_type = $_POST['salary_type'];
    $tax_status = $_POST['tax_status'];
    $compensation_range_from = $_POST['compensation_range_from'];
    $compensation_range_to = $_POST['compensation_range_to'];
    $fixed_tax = $_POST['fixed_tax'];
    $percentage_over = $_POST['percentage_over'];

    $insert = mysqli_query($conn, "INSERT INTO withholding_tax_table (salary_type, tax_status, compensation_range_from, compensation_range_to, fixed_tax, percentage_over) VALUES ('$salary_type', '$tax_status', '$compensation_range_from', '$compensation_range_to', '$fixed_tax', '$percentage_over')");
    $_SESSION['success'] = $insert ? 'Withholding tax entry added successfully!' : 'Failed to add withholding tax entry.';
    header("Location: withholding_tax.php");
    exit();
}

// UPDATE WITHHOLDING TAX ENTRY
if (isset($_POST['update_tax'])) {
    $id = $_POST['tax_id'];
    $salary_type = $_POST['salary_type'];
    $tax_status = $_POST['tax_status'];
    $compensation_range_from = $_POST['compensation_range_from'];
    $compensation_range_to = $_POST['compensation_range_to'];
    $fixed_tax = $_POST['fixed_tax'];
    $percentage_over = $_POST['percentage_over'];

    $update = mysqli_query($conn, "UPDATE withholding_tax_table SET salary_type='$salary_type', tax_status='$tax_status', compensation_range_from='$compensation_range_from', compensation_range_to='$compensation_range_to', fixed_tax='$fixed_tax', percentage_over='$percentage_over' WHERE id=$id");
    $_SESSION['success'] = $update ? 'Withholding tax entry updated successfully!' : 'Failed to update withholding tax entry.';
    header("Location: withholding_tax.php");
    exit();
}

// DELETE WITHHOLDING TAX ENTRY
if (isset($_POST['delete_tax'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM withholding_tax_table WHERE id=$id");
    $_SESSION['success'] = $delete ? 'Withholding tax entry deleted successfully!' : 'Failed to delete withholding tax entry.';
    header("Location: withholding_tax.php");
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
              <h5 class="m-b-10">Withholding Tax Table Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Withholding Tax Table</li>
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
              <h5 class="mb-0">Withholding Tax Table</h5>
              <small class="text-muted">Manage tax brackets and rates for payroll</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Tax Entry
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="taxTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Salary Type</th>
                    <th>Tax Status</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Fixed Tax</th>
                    <th>% Over</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT * FROM withholding_tax_table ORDER BY salary_type, tax_status, compensation_range_from ASC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark"><?= $row['id']; ?></span></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($row['salary_type']); ?></span></td>
                    <td><?= htmlspecialchars($row['tax_status']); ?></td>
                    <td>₱<?= number_format($row['compensation_range_from'], 2); ?></td>
                    <td><?= $row['compensation_range_to'] == 999999999.99 ? 'Above' : '₱' . number_format($row['compensation_range_to'], 2); ?></td>
                    <td>₱<?= number_format($row['fixed_tax'], 2); ?></td>
                    <td><?= number_format($row['percentage_over'], 2); ?>%</td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['id']; ?>"
                          data-salary-type="<?= htmlspecialchars($row['salary_type']); ?>"
                          data-tax-status="<?= htmlspecialchars($row['tax_status']); ?>"
                          data-from="<?= $row['compensation_range_from']; ?>"
                          data-to="<?= $row['compensation_range_to']; ?>"
                          data-fixed-tax="<?= $row['fixed_tax']; ?>"
                          data-percentage="<?= $row['percentage_over']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['id']; ?>"
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
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Withholding Tax Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Salary Type</label>
            <select name="salary_type" class="form-select" required>
              <option value="">Select Salary Type</option>
              <option value="Monthly">Monthly</option>
              <option value="Daily">Daily</option>
              <option value="Weekly">Weekly</option>
              <option value="Semi-Monthly">Semi-Monthly</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Tax Status <span class="text-danger">*</span></label>
            <select name="tax_status" class="form-select" required>
              <option value="">Select Status</option>
              <option value="Single" selected>Single</option>
              <option value="Married">Married</option>
              <option value="Head of Family">Head of Family</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Compensation Range From</label>
            <input type="number" name="compensation_range_from" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Compensation Range To</label>
            <input type="number" name="compensation_range_to" class="form-control" step="0.01" min="0" required>
            <small class="text-muted">Use 999999999.99 for "Above" ranges</small>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Fixed Tax Amount</label>
            <input type="number" name="fixed_tax" class="form-control" step="0.01" min="0" value="0.00" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Percentage Over (%)</label>
            <input type="number" name="percentage_over" class="form-control" step="0.01" min="0" max="100" value="0.00" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_tax" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Withholding Tax Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="tax_id" id="edit_id">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Salary Type</label>
            <select name="salary_type" id="edit_salary_type" class="form-select" required>
              <option value="">Select Salary Type</option>
              <option value="Monthly">Monthly</option>
              <option value="Daily">Daily</option>
              <option value="Weekly">Weekly</option>
              <option value="Semi-Monthly">Semi-Monthly</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
             <label class="form-label">Tax Status <span class="text-danger">*</span></label>
            <select name="tax_status" id="edit_tax_status" class="form-select" required>
              <option value="">Select Status</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Head of Family">Head of Family</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Compensation Range From</label>
            <input type="number" name="compensation_range_from" id="edit_from" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Compensation Range To</label>
            <input type="number" name="compensation_range_to" id="edit_to" class="form-control" step="0.01" min="0" required>
            <small class="text-muted">Use 999999999.99 for "Above" ranges</small>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Fixed Tax Amount</label>
            <input type="number" name="fixed_tax" id="edit_fixed_tax" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Percentage Over (%)</label>
            <input type="number" name="percentage_over" id="edit_percentage" class="form-control" step="0.01" min="0" max="100" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_tax" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>


<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_tax" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#taxTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search tax entries...",
      zeroRecords: "No matching records found",
      emptyTable: "No tax entries available",
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
      { targets: [3, 4, 5, 6], className: 'text-end' },
      { targets: 7, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc'], [2, 'asc'], [3, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
   $('.editBtn').off().on('click', function () {
  $('#edit_id').val($(this).data('id'));
  $('#edit_salary_type').val($(this).data('salary-type'));
  $('#edit_tax_status').val($(this).data('tax-status'));
  $('#edit_from').val($(this).data('from'));
  $('#edit_to').val($(this).data('to'));
  $('#edit_fixed_tax').val($(this).data('fixed-tax'));
  $('#edit_percentage').val($(this).data('percentage'));
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