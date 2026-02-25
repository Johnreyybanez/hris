<?php
session_start();
include 'connection.php';

// ADD SSS CONTRIBUTION
if (isset($_POST['add_sss'])) {
    $insert = mysqli_query($conn, "INSERT INTO sss_contribution_table (range_from, range_to, monthly_salary_credit, ee_share, er_share, ec_share, total_contribution) VALUES ('{$_POST['range_from']}', '{$_POST['range_to']}', '{$_POST['monthly_salary_credit']}', '{$_POST['ee_share']}', '{$_POST['er_share']}', '{$_POST['ec_share']}', '{$_POST['total_contribution']}')");
    $_SESSION['success'] = $insert ? 'SSS Contribution added successfully!' : 'Failed to add SSS contribution.';
    header("Location: SSS_contribution.php");
    exit();
}

// UPDATE SSS CONTRIBUTION
if (isset($_POST['update_sss'])) {
    $id = $_POST['id'];
    $update = mysqli_query($conn, "UPDATE sss_contribution_table SET range_from='{$_POST['range_from']}', range_to='{$_POST['range_to']}', monthly_salary_credit='{$_POST['monthly_salary_credit']}', ee_share='{$_POST['ee_share']}', er_share='{$_POST['er_share']}', ec_share='{$_POST['ec_share']}', total_contribution='{$_POST['total_contribution']}' WHERE id=$id");
    $_SESSION['success'] = $update ? 'SSS Contribution updated successfully!' : 'Failed to update SSS contribution.';
    header("Location: sss_contribution.php");
    exit();
}

// DELETE SSS CONTRIBUTION
if (isset($_POST['delete_sss'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM sss_contribution_table WHERE id=$id");
    $_SESSION['success'] = $delete ? 'SSS Contribution deleted successfully!' : 'Failed to delete SSS contribution.';
    header("Location: SSS_contribution.php");
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
              <h5 class="m-b-10">SSS Contribution Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">SSS Contribution </li>
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
              <h5 class="mb-0">SSS Contribution Table</h5>
              <small class="text-muted">Manage SSS Contribution</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add New
            </button>
          </div>
      <div class="card-body">
        <table id="sssTable" class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Range From</th>
              <th>Range To</th>
              <th>Monthly Credit</th>
              <th>EE Share</th>
              <th>ER Share</th>
              <th>EC Share</th>
              <th>Total</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $sss = mysqli_query($conn, "SELECT * FROM sss_contribution_table ORDER BY range_from ASC");
            while ($row = mysqli_fetch_assoc($sss)): ?>
              <tr>
                <td><?= $row['id'] ?></td>
               <td><?= number_format((float)$row['range_from'], 2) ?></td>
<td><?= number_format((float)$row['range_to'], 2) ?></td>
<td><?= number_format((float)$row['monthly_salary_credit'], 2) ?></td>
<td><?= number_format((float)$row['ee_share'], 2) ?></td>
<td><?= number_format((float)$row['er_share'], 2) ?></td>
<td><?= number_format((float)$row['ec_share'], 2) ?></td>
<td><?= number_format((float)$row['total_contribution'], 2) ?></td>

                <td class="text-center">
                  <button class="btn btn-outline-warning btn-sm editBtn" data-id="<?= $row['id'] ?>" data-range_from="<?= $row['range_from'] ?>" data-range_to="<?= $row['range_to'] ?>" data-monthly="<?= $row['monthly_salary_credit'] ?>" data-ee="<?= $row['ee_share'] ?>" data-er="<?= $row['er_share'] ?>" data-ec="<?= $row['ec_share'] ?>" data-total="<?= $row['total_contribution'] ?>">
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
 </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add SSS Contribution</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-2">
        <?php foreach (["range_from", "range_to", "monthly_salary_credit", "ee_share", "er_share", "ec_share", "total_contribution"] as $field): ?>
        <div class="col-md-6">
          <input type="number" step="0.01" name="<?= $field ?>" class="form-control" placeholder="<?= ucwords(str_replace("_", " ", $field)) ?>" required>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_sss" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit SSS Contribution</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="edit_id">
        <?php foreach (["range_from", "range_to", "monthly_salary_credit", "ee_share", "er_share", "ec_share", "total_contribution"] as $field): ?>
        <div class="col-md-6">
          <input type="number" step="0.01" name="<?= $field ?>" id="edit_<?= $field ?>" class="form-control" placeholder="<?= ucwords(str_replace("_", " ", $field)) ?>" required>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_sss" class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_sss" value="1">
</form>

<script>
$(document).ready(function () {
const table =   $('#sssTable').DataTable({
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

  function bindActionButtons() {
  $('.editBtn').off().on('click', function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_range_from').val($(this).data('range_from'));
    $('#edit_range_to').val($(this).data('range_to'));
    $('#edit_monthly_salary_credit').val($(this).data('monthly'));
    $('#edit_ee_share').val($(this).data('ee'));
    $('#edit_er_share').val($(this).data('er'));
    $('#edit_ec_share').val($(this).data('ec'));
    $('#edit_total_contribution').val($(this).data('total'));

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
