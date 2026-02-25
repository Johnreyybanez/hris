<?php
session_start();
include 'connection.php'; // DB connection

// ADD DESIGNATION
if (isset($_POST['add_designation'])) {
    $title = $_POST['title'];
    $level = $_POST['level'];
    $insert = mysqli_query($conn, "INSERT INTO designations (title, level) VALUES ('$title', '$level')");
    $_SESSION['success'] = $insert ? 'Designation added successfully!' : 'Failed to add designation.';
    header("Location: designations.php");
    exit();
}

// UPDATE DESIGNATION
if (isset($_POST['update_designation'])) {
    $id = $_POST['designation_id'];
    $title = $_POST['title'];
    $level = $_POST['level'];
    $update = mysqli_query($conn, "UPDATE designations SET title='$title', level='$level' WHERE designation_id=$id");
    $_SESSION['success'] = $update ? 'Designation updated successfully!' : 'Failed to update designation.';
    header("Location: designations.php");
    exit();
}

// DELETE DESIGNATION
if (isset($_POST['delete_designation'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM designations WHERE designation_id=$id");
    $_SESSION['success'] = $delete ? 'Designation deleted successfully!' : 'Failed to delete designation.';
    header("Location: designations.php");
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
              <h5 class="m-b-10">Designation Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item" aria-current="page">Designations</li>
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
              <h5 class="mb-0">Designations</h5>
              <small class="text-muted">Manage job titles and levels</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Designation
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="designationTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Level</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $result = mysqli_query($conn, "SELECT * FROM designations");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['designation_id']; ?></span></td>
                    
                    <td><?= htmlspecialchars($row['title']); ?></td>
                    
                    <td><?= htmlspecialchars($row['level']); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['designation_id']; ?>"
                          data-title="<?= htmlspecialchars($row['title']); ?>"
                          data-level="<?= htmlspecialchars($row['level']); ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['designation_id']; ?>"
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
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addModalLabel"><i class="ti ti-plus me-2"></i>Add New Designation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="add_title" class="form-label">Designation Title</label>
          <input type="text" name="title" id="add_title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_level" class="form-label">Level</label>
          <input type="text" name="level" id="add_level" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_designation" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel"><i class="ti ti-edit me-2"></i>Edit Designation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="designation_id" id="edit_id">
        <div class="mb-3">
          <label for="edit_title" class="form-label">Designation Title</label>
          <input type="text" name="title" id="edit_title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_level" class="form-label">Level</label>
          <input type="text" name="level" id="edit_level" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_designation" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_designation" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#designationTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search designations...",
      zeroRecords: "No matching designations found",
      emptyTable: "No designations available",
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
      { targets: 0, width: '80px', className: 'text-center' },
      { targets: 3, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_title').val($(this).data('title'));
      $('#edit_level').val($(this).data('level'));
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

  // Add search icon once
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  // Toast on success
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
