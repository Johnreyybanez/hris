<?php
session_start();
include 'connection.php';

// ADD USER
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    $insert = mysqli_query($conn, "INSERT INTO users (username, email, password, role, image) 
                                   VALUES ('$username', '$email', '$password', '$role', '$image_path')");

    $_SESSION['success'] = $insert ? 'User added successfully!' : 'Failed to add user.';
    header("Location: users.php");
    exit();
}

// UPDATE USER
if (isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $image_sql = "";
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_sql = ", image = '$target_file'";
        }
    }

    $update = mysqli_query($conn, "UPDATE users SET username='$username', email='$email', role='$role' $image_sql WHERE user_id=$id");

    $_SESSION['success'] = $update ? 'User updated successfully!' : 'Failed to update user.';
    header("Location: users.php");
    exit();
}

// DELETE USER
if (isset($_POST['delete_user'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM users WHERE user_id=$id");
    $_SESSION['success'] = $delete ? 'User deleted successfully!' : 'Failed to delete user.';
    header("Location: users.php");
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
              <h5 class="m-b-10">User Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item" aria-current="page">Users</li>
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
              <h5 class="mb-0">Users</h5>
              <small class="text-muted">Manage system users and roles</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add User
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center w-100"  id="userTable" >
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $result = mysqli_query($conn, "SELECT * FROM users");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                   <td><span class="badge bg-dark -secondary"><?= $row['user_id']; ?></span></td>
                    <td>
                      <img src="<?= !empty($row['image']) ? $row['image'] : 'assets/images/logo-dark.svg'; ?>" width="40" height="40" class="rounded-circle" alt="user">
                    </td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td><?= htmlspecialchars($row['email']); ?></td>
                    <td><?= htmlspecialchars($row['role']); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['user_id']; ?>"
                          data-username="<?= htmlspecialchars($row['username']); ?>"
                          data-email="<?= htmlspecialchars($row['email']); ?>"
                          data-role="<?= htmlspecialchars($row['role']); ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['user_id']; ?>"
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
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="add_username" class="form-label">Username</label>
          <input type="text" name="username" id="add_username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_email" class="form-label">Email</label>
          <input type="email" name="email" id="add_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_password" class="form-label">Password</label>
          <input type="password" name="password" id="add_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_role" class="form-label">Role</label>
          <select name="role" id="add_role" class="form-select" required>
            <option value="" disabled selected>Select role</option>
            <option value="Admin">Admin</option>
            <option value="Staff">Staff</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="add_image" class="form-label">Profile Image</label>
          <input type="file" name="image" id="add_image" class="form-control" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_user" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="user_id" id="edit_id">
        <div class="mb-3">
          <label for="edit_username" class="form-label">Username</label>
          <input type="text" name="username" id="edit_username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_email" class="form-label">Email</label>
          <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_role" class="form-label">Role</label>
          <select name="role" id="edit_role" class="form-select" required>
            <option value="Admin">Admin</option>
            <option value="Staff">Staff</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="edit_image" class="form-label">Profile Image</label>
          <input type="file" name="image" id="edit_image" class="form-control" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_user" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_user" value="1">
</form>

<!-- JS -->
<script>
$(document).ready(function () {
  $('#userTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search users...",
      zeroRecords: "No matching shifts found",
      emptyTable: "No shifts available",
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
      { targets: 4, orderable: false, className: 'text-center' }
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
    $('#edit_username').val($(this).data('username'));
    $('#edit_email').val($(this).data('email'));
    $('#edit_role').val($(this).data('role'));
    $('#editModal').modal('show');
  });

  $('.deleteBtn').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Are you sure?',
      text: 'This action cannot be undone!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        $('#delete_id').val(id);
        $('#deleteForm').submit();
      }
    });
  });

  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success']; ?>',
    toast: true,
    timer: 3000,
    showConfirmButton: false,
    position: 'top-end'
  });
  <?php unset($_SESSION['success']); endif; ?>
});
</script>
