<?php
session_start();
include 'connection.php';

// Handle image upload and return filename or false on failure
function uploadImage($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return false; // no file uploaded
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES[$fileInputName]['type'], $allowedTypes)) {
        return false; // invalid file type
    }
    $uploadDir = 'uploads/signatures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $fileName = uniqid() . '_' . basename($_FILES[$fileInputName]['name']);
    $targetFile = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFile)) {
        return $fileName;
    }
    return false;
}

// ADD
if (isset($_POST['add_signatory'])) {
    $imageFile = uploadImage('signature_image');
    if (!$imageFile) {
        $_SESSION['error'] = 'Please upload a valid signature image (jpg, png, gif).';
        header("Location: signatory_settings.php");
        exit();
    }

    $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
    $signatory_name = mysqli_real_escape_string($conn, $_POST['signatory_name']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $insert = mysqli_query($conn, "INSERT INTO signatory_settings 
      (document_type, signatory_name, designation, department, signature_image, is_active) VALUES 
      ('$document_type', '$signatory_name', '$designation', '$department', '$imageFile', $is_active)");

    $_SESSION['success'] = $insert ? 'Signatory added successfully!' : 'Failed to add signatory.';
    header("Location: signatory_settings.php");
    exit();
}

// UPDATE
if (isset($_POST['update_signatory'])) {
    $id = (int)$_POST['id'];
    $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
    $signatory_name = mysqli_real_escape_string($conn, $_POST['signatory_name']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Check if new image uploaded
    $imageFile = uploadImage('signature_image');
    if ($imageFile) {
        // Delete old image file (optional, but recommended)
        $old = mysqli_query($conn, "SELECT signature_image FROM signatory_settings WHERE id=$id");
        if ($old && mysqli_num_rows($old) > 0) {
            $oldRow = mysqli_fetch_assoc($old);
            if (file_exists('uploads/signatures/'.$oldRow['signature_image'])) {
                unlink('uploads/signatures/'.$oldRow['signature_image']);
            }
        }
        $img_sql = ", signature_image='$imageFile'";
    } else {
        $img_sql = "";
    }

    $update = mysqli_query($conn, "UPDATE signatory_settings SET
        document_type='$document_type',
        signatory_name='$signatory_name',
        designation='$designation',
        department='$department',
        is_active=$is_active
        $img_sql
        WHERE id=$id
    ");

    $_SESSION['success'] = $update ? 'Signatory updated successfully!' : 'Failed to update signatory.';
    header("Location: signatory_settings.php");
    exit();
}

// DELETE
if (isset($_POST['delete_signatory'])) {
    $id = (int)$_POST['delete_id'];

    // Delete signature image file first
    $old = mysqli_query($conn, "SELECT signature_image FROM signatory_settings WHERE id=$id");
    if ($old && mysqli_num_rows($old) > 0) {
        $oldRow = mysqli_fetch_assoc($old);
        if (file_exists('uploads/signatures/'.$oldRow['signature_image'])) {
            unlink('uploads/signatures/'.$oldRow['signature_image']);
        }
    }

    $delete = mysqli_query($conn, "DELETE FROM signatory_settings WHERE id=$id");
    $_SESSION['success'] = $delete ? 'Signatory deleted successfully!' : 'Failed to delete signatory.';
    header("Location: signatory_settings.php");
    exit();
}
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
            <div class="page-header-title">
              <h5 class="m-b-10">Signatory Settings Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Signatory Settings</li>
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
          <h5 class="mb-0">Signatory Settings Table</h5>
          <small class="text-muted">Manage Signatories</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
          <i class="ti ti-plus me-1"></i>Add New
        </button>
      </div>
      <div class="card-body">
        <table id="signatoryTable" class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Document Type</th>
              <th>Signatory Name</th>
              <th>Designation</th>
              <th>Department</th>
              <th>Signature</th>
              <th>Active</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $res = mysqli_query($conn, "SELECT * FROM signatory_settings ORDER BY id ASC");
            while ($row = mysqli_fetch_assoc($res)): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['document_type']) ?></td>
                <td><?= htmlspecialchars($row['signatory_name']) ?></td>
                <td><?= htmlspecialchars($row['designation']) ?></td>
                <td><?= htmlspecialchars($row['department']) ?></td>
                <td><img src="uploads/signatures/<?= htmlspecialchars($row['signature_image']) ?>" alt="Signature" width="100"></td>
                <td class="text-center"><?= $row['is_active'] ? 'Yes' : 'No' ?></td>
                <td class="text-center">
                  <button 
                    class="btn btn-outline-warning btn-sm editBtn"
                    data-id="<?= $row['id'] ?>"
                    data-document_type="<?= htmlspecialchars($row['document_type']) ?>"
                    data-signatory_name="<?= htmlspecialchars($row['signatory_name']) ?>"
                    data-designation="<?= htmlspecialchars($row['designation']) ?>"
                    data-department="<?= htmlspecialchars($row['department']) ?>"
                    data-is_active="<?= $row['is_active'] ?>"
                  >
                    <i class="ti ti-edit"></i>
                  </button>
                  <button class="btn btn-outline-danger btn-sm deleteBtn" data-id="<?= $row['id'] ?>">
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Add Signatory</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Document Type</label>
          <input type="text" name="document_type" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Signatory Name</label>
          <input type="text" name="signatory_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Designation</label>
          <input type="text" name="designation" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Department</label>
          <input type="text" name="department" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Signature Image</label>
          <input type="file" name="signature_image" accept="image/*" class="form-control" required>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" checked>
          <label class="form-check-label" for="add_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_signatory" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Edit Signatory</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Document Type</label>
          <input type="text" name="document_type" id="edit_document_type" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Signatory Name</label>
          <input type="text" name="signatory_name" id="edit_signatory_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Designation</label>
          <input type="text" name="designation" id="edit_designation" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Department</label>
          <input type="text" name="department" id="edit_department" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Signature Image (Leave empty to keep current)</label>
          <input type="file" name="signature_image" accept="image/*" class="form-control">
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
          <label class="form-check-label" for="edit_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_signatory" class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_signatory" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#signatoryTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search...",
      zeroRecords: "No matching records found",
      emptyTable: "No signatories available",
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
      { targets: [6,7], orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_document_type').val($(this).data('document_type'));
      $('#edit_signatory_name').val($(this).data('signatory_name'));
      $('#edit_designation').val($(this).data('designation'));
      $('#edit_department').val($(this).data('department'));
      $('#edit_is_active').prop('checked', $(this).data('is_active') == 1);

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

  <?php if (isset($_SESSION['error'])): ?>
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?= $_SESSION['error']; ?>',
    timer: 3000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end'
  });
  <?php unset($_SESSION['error']); endif; ?>
});
</script>
</body>
</html>
