<?php
session_start();
include 'connection.php'; // DB connection

// ADD DEPARTMENT
if (isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $insert = mysqli_query($conn, "INSERT INTO departments (name, description) VALUES ('$name', '$desc')");
    $_SESSION['success'] = $insert ? 'Department added successfully!' : 'Failed to add department.';
    header("Location: departments.php");
    exit();
}

// UPDATE DEPARTMENT
if (isset($_POST['update_department'])) {
    $id = $_POST['department_id'];
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $update = mysqli_query($conn, "UPDATE departments SET name='$name', description='$desc' WHERE department_id=$id");
    $_SESSION['success'] = $update ? 'Department updated successfully!' : 'Failed to update department.';
    header("Location: departments.php");
    exit();
}

// DELETE DEPARTMENT
if (isset($_POST['delete_department'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM departments WHERE department_id=$id");
    $_SESSION['success'] = $delete ? 'Department deleted successfully!' : 'Failed to delete department.';
    header("Location: departments.php");
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
              <h5 class="m-b-10">Department Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item" aria-current="page">Departments</li>
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
              <h5 class="mb-0">Departments</h5>
              <small class="text-muted">Manage your organization departments</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Department
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="departmentTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $result = mysqli_query($conn, "SELECT * FROM departments");
                  while ($row = mysqli_fetch_assoc($result)):
                  ?>
                  <tr>
                    <td>
                     <span class="badge bg-dark -secondary"><?= $row['department_id']; ?></span>
                    </td>
                    <td>
                      
                          <h6 class="mb-0"><?= htmlspecialchars($row['name']); ?></h6>
                        
                    </td>
                    <td>
                      <span class="text-muted"><?= htmlspecialchars($row['description']); ?></span>
                    </td>
                    <td>
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['department_id']; ?>"
                          data-name="<?= htmlspecialchars($row['name']); ?>"
                          data-description="<?= htmlspecialchars($row['description']); ?>"
                          title="Edit">
                          <i class="ti ti-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn" 
                          data-id="<?= $row['department_id']; ?>"
                          title="Delete">
                          <i class="ti ti-trash"></i>
                        </button>
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
        <h5 class="modal-title" id="addModalLabel">
          <i class="ti ti-plus me-2"></i>Add New Department
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="add_name" class="form-label">Department Name</label>
          <input type="text" name="name" id="add_name" class="form-control" placeholder="Enter department name" required>
        </div>
        <div class="mb-3">
          <label for="add_description" class="form-label">Description</label>
          <textarea name="description" id="add_description" class="form-control" rows="3" placeholder="Enter department description"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_department" class="btn btn-primary">
          <i class="ti ti-check me-1"></i>Add Department
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">
          <i class="ti ti-edit me-2"></i>Edit Department
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="department_id" id="edit_id">
        <div class="mb-3">
          <label for="edit_name" class="form-label">Department Name</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_description" class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_department" class="btn btn-warning">
          <i class="ti ti-device-floppy me-1"></i>Update Department
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_department" value="1">
</form>

<script>
$(document).ready(function () {
  // Initialize DataTable with enhanced styling and same row layout
  $('#departmentTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: "",
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
    // Modified DOM structure to put search and length on same row at top, info and pagination on same row at bottom
    dom: 
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      {
        targets: 0,
        width: '80px',
        className: 'text-center'
      },
      {
        targets: 3,
        orderable: false,
        className: 'text-center'
      }
    ],
    order: [[1, 'asc']],
    drawCallback: function() {
      // Add custom classes after each draw
      $('.dataTables_filter input').addClass('dt-search-input');
      
      // Reinitialize event handlers for dynamically created elements
      $('.editBtn').off('click').on('click', function () {
        $('#edit_id').val($(this).data('id'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_description').val($(this).data('description'));
        new bootstrap.Modal(document.getElementById('editModal')).show();
      });

      $('.deleteBtn').off('click').on('click', function () {
        let id = $(this).data('id');
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
  });
// Add search icon once
const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }
  // Add custom search styling
  $('.dataTables_filter input').addClass('dt-search-input');

  // Initial event handlers
  $('.editBtn').click(function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_name').val($(this).data('name'));
    $('#edit_description').val($(this).data('description'));
    new bootstrap.Modal(document.getElementById('editModal')).show();
  });

  $('.deleteBtn').click(function () {
    let id = $(this).data('id');
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

  // Success message display
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