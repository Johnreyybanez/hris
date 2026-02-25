<?php
session_start();
include 'connection.php';

// ADD GSIS CONTRIBUTION
if (isset($_POST['add_gsis'])) {
    $insert = mysqli_query($conn, "INSERT INTO gsis_contribution_table (ee_share, er_share, notes) VALUES ('{$_POST['ee_share']}', '{$_POST['er_share']}', '{$_POST['notes']}')");
    $_SESSION['success'] = $insert ? 'GSIS Contribution added successfully!' : 'Failed to add GSIS contribution.';
    header("Location: GSIS_contribution.php");
    exit();
}

// UPDATE GSIS CONTRIBUTION
if (isset($_POST['update_gsis'])) {
    $id = $_POST['id'];
    $update = mysqli_query($conn, "UPDATE gsis_contribution_table SET ee_share='{$_POST['ee_share']}', er_share='{$_POST['er_share']}', notes='{$_POST['notes']}' WHERE id=$id");
    $_SESSION['success'] = $update ? 'GSIS Contribution updated successfully!' : 'Failed to update GSIS contribution.';
    header("Location: gsis_contribution.php");
    exit();
}

// DELETE GSIS CONTRIBUTION
if (isset($_POST['delete_gsis'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM gsis_contribution_table WHERE id=$id");
    $_SESSION['success'] = $delete ? 'GSIS Contribution deleted successfully!' : 'Failed to delete GSIS contribution.';
    header("Location: gsis_contribution.php");
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
              <h5 class="m-b-10">GSIS Contribution Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">GSIS Contribution </li>
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
              <h5 class="mb-0">GSIS Contribution Table</h5>
              <small class="text-muted">Manage GSIS Contribution</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add New
            </button>
          </div>
      <div class="card-body">
        <table id="gsisTable" class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>EE Share (%)</th>
              <th>ER Share (%)</th>
              <th>Notes</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $gsis = mysqli_query($conn, "SELECT * FROM gsis_contribution_table ORDER BY id ASC");
            while ($row = mysqli_fetch_assoc($gsis)): ?>
              <tr>
                <td><?= $row['id'] ?></td>
               <td><?= number_format((float)$row['ee_share'], 2) ?>%</td>
<td><?= number_format((float)$row['er_share'], 2) ?>%</td>

                <td><?= htmlspecialchars($row['notes']) ?></td>
                <td class="text-center">
                  <button class="btn btn-outline-warning btn-sm editBtn" data-id="<?= $row['id'] ?>" data-ee="<?= $row['ee_share'] ?>" data-er="<?= $row['er_share'] ?>" data-notes="<?= htmlspecialchars($row['notes']) ?>">
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
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add GSIS Contribution</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">EE Share (%)</label>
          <input type="number" step="0.01" name="ee_share" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">ER Share (%)</label>
          <input type="number" step="0.01" name="er_share" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_gsis" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit GSIS Contribution</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">EE Share (%)</label>
          <input type="number" step="0.01" name="ee_share" id="edit_ee_share" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">ER Share (%)</label>
          <input type="number" step="0.01" name="er_share" id="edit_er_share" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" id="edit_notes" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_gsis" class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_gsis" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#gsisTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search...",
      zeroRecords: "No matching records found",
      emptyTable: "No training categories available",
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
      bindActionButtons();
    }
  });

  // UPDATE: Bind correct modal fields
function bindActionButtons() {
  $('.editBtn').off().on('click', function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_ee_share').val($(this).data('ee'));
    $('#edit_er_share').val($(this).data('er'));
    $('#edit_notes').val($(this).data('notes'));
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
