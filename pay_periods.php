<?php
session_start();
include 'connection.php';

// ADD PAY PERIOD
if (isset($_POST['add_period'])) {
    $name = $_POST['name'];
    $start = $_POST['start_day'];
    $end = $_POST['end_day'];

    $insert = mysqli_query($conn, "INSERT INTO PayPeriods (name, start_day, end_day) VALUES ('$name', '$start', '$end')");
    $_SESSION['success'] = $insert ? 'Pay period added successfully!' : 'Failed to add pay period.';
    header("Location: pay_periods.php");
    exit();
}

// UPDATE PAY PERIOD
if (isset($_POST['update_period'])) {
    $id = $_POST['period_id'];
    $name = $_POST['name'];
    $start = $_POST['start_day'];
    $end = $_POST['end_day'];

    $update = mysqli_query($conn, "UPDATE PayPeriods SET name='$name', start_day='$start', end_day='$end' WHERE period_id=$id");
    $_SESSION['success'] = $update ? 'Pay period updated successfully!' : 'Failed to update pay period.';
    header("Location: pay_periods.php");
    exit();
}

// DELETE PAY PERIOD
if (isset($_POST['delete_period'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM PayPeriods WHERE period_id=$id");
    $_SESSION['success'] = $delete ? 'Pay period deleted successfully!' : 'Failed to delete pay period.';
    header("Location: pay_periods.php");
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
              <h5 class="m-b-10">Pay Period Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Pay Periods</li>
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
              <h5 class="mb-0">Pay Periods</h5>
              <small class="text-muted">Manage payroll periods</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Pay Period
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="typeTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Period Name</th>
                    <th>Start Day</th>
                    <th>End Day</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT * FROM PayPeriods ORDER BY period_id ASC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['period_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    <td><?= $row['start_day']; ?></td>
                    <td><?= $row['end_day']; ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['period_id']; ?>"
                          data-name="<?= htmlspecialchars($row['name']); ?>"
                          data-start="<?= $row['start_day']; ?>"
                          data-end="<?= $row['end_day']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['period_id']; ?>"
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
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Pay Period</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Period Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Start Day</label>
          <input type="number" name="start_day" class="form-control" min="1" max="31" required>
        </div>
        <div class="mb-3">
          <label class="form-label">End Day</label>
          <input type="number" name="end_day" class="form-control" min="1" max="31" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_period" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Pay Period</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="period_id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Period Name</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Start Day</label>
          <input type="number" name="start_day" id="edit_start" class="form-control" min="1" max="31" required>
        </div>
        <div class="mb-3">
          <label class="form-label">End Day</label>
          <input type="number" name="end_day" id="edit_end" class="form-control" min="1" max="31" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_period" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_period" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#typeTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search periods...",
      zeroRecords: "No matching periods found",
      emptyTable: "No pay periods available",
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
      { targets: 4, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });
// Add search icon once
const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }
  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_name').val($(this).data('name'));
      $('#edit_start').val($(this).data('start'));
      $('#edit_end').val($(this).data('end'));
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
