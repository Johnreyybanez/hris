<?php
session_start();
include 'connection.php';

// UPDATE DAY TYPE
if (isset($_POST['update_day_type'])) {
  $day_type_id = intval($_POST['edit_id']);
  $multiplier = floatval($_POST['multiplier']);
  $ot_multiplier = floatval($_POST['ot_multiplier']);
  $night_diff_multiplier = floatval($_POST['night_diff_multiplier']);

  $stmt = $conn->prepare("UPDATE DayTypes SET multiplier=?, ot_multiplier=?, night_diff_multiplier=? WHERE day_type_id=?");
  $stmt->bind_param("dddi", $multiplier, $ot_multiplier, $night_diff_multiplier, $day_type_id);

  if ($stmt->execute()) {
    $_SESSION['success'] = 'Day type updated successfully!';
  } else {
    $_SESSION['error'] = 'Failed to update day type: ' . $conn->error;
  }
  $stmt->close();
  header("Location: day_types.php");
  exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delete_id = intval($_POST['delete_id']);

  // Set day_type_id to NULL in employeedtr first
  $update = $conn->prepare("UPDATE employeedtr SET day_type_id=NULL WHERE day_type_id=?");
  $update->bind_param("i", $delete_id);
  $update->execute();
  $update->close();

  // Now delete from DayTypes
  $stmt = $conn->prepare("DELETE FROM DayTypes WHERE day_type_id=?");
  $stmt->bind_param("i", $delete_id);

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Day type deleted successfully!']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete day type: ' . $conn->error]);
  }
  $stmt->close();
  exit();
}

include 'head.php';
include 'sidebar.php';
include 'header.php';
?>
<!-- Main Content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Day Type Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active" aria-current="page">Day Types</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Day Types Table -->
    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Day Types</h5>
              <small class="text-muted">Manage work day types and their pay multipliers</small>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="dayTypeTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Multiplier</th>
                    <th>OT Multiplier</th>
                    <th>Night Diff</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $res = mysqli_query($conn, "SELECT * FROM DayTypes ORDER BY day_type_id DESC");
                  if ($res && mysqli_num_rows($res) > 0) {
                    while ($row = mysqli_fetch_assoc($res)) :
                  ?>
                      <tr>
                        <td><span class="badge bg-dark -secondary"><?= $row['day_type_id']; ?></span></td>
                        <td><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['description']); ?></td>
                        <td><?= number_format($row['multiplier'], 2); ?></td>
                        <td><?= number_format($row['ot_multiplier'], 2); ?></td>
                        <td><?= number_format($row['night_diff_multiplier'], 2); ?></td>
                        <td>
                          <button class="btn btn-sm btn-outline-warning editBtn"
                            data-id="<?= $row['day_type_id']; ?>"
                            data-multiplier="<?= $row['multiplier']; ?>"
                            data-ot-multiplier="<?= $row['ot_multiplier']; ?>"
                            data-night-diff-multiplier="<?= $row['night_diff_multiplier']; ?>">
                            <i class="ti ti-edit"></i>
                          </button>
                         <!-- <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['day_type_id']; ?>">
                            <i class="ti ti-trash"></i>-->
                          </button>
                        </td>
                      </tr>
                  <?php
                    endwhile;
                  } else {
                    echo '<tr><td colspan="7" class="text-center">No day types found</td></tr>';
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Day Type</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Regular Multiplier</label>
                <input type="number" step="0.01" min="0" name="multiplier" id="edit_multiplier" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">OT Multiplier</label>
                <input type="number" step="0.01" min="0" name="ot_multiplier" id="edit_ot_multiplier" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Night Diff Multiplier</label>
                <input type="number" step="0.01" min="0" name="night_diff_multiplier" id="edit_night_diff_multiplier" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="update_day_type" class="btn btn-warning">Update</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- SweetAlert for Success/Error -->
<?php if (isset($_SESSION['success'])) : ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: '<?= $_SESSION['success']; ?>',
      timer: 2000,
      showConfirmButton: false,
      position: 'top-end',
      toast: true
    });
  </script>
  <?php unset($_SESSION['success']);
elseif (isset($_SESSION['error'])) : ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: '<?= $_SESSION['error']; ?>',
      timer: 3000,
      showConfirmButton: false,
      position: 'top-end',
      toast: true
    });
  </script>
  <?php unset($_SESSION['error']);
endif; ?>

<script>
  $(document).ready(function () {
    $('#dayTypeTable').DataTable({
     responsive: true,
          lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
          pageLength: 10,
          language: {
            search: "",
            searchPlaceholder: "Search day types...",
            zeroRecords: "No matching records found",
            emptyTable: "No day types available",
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
            { targets: 6, orderable: false, className: 'text-center' }
          ],
          order: [[1, 'asc']],
          drawCallback: function () {
            // Add search icon once
            const searchBox = $('.dataTables_filter');
            if (searchBox.length && searchBox.find('.ti-search').length === 0) {
              searchBox.addClass('position-relative');
              searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
              searchBox.find('input').addClass('form-control ps-5 dt-search-input');
            }
          }
        });


    // Edit button
    $(document).on('click', '.editBtn', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_multiplier').val($(this).data('multiplier'));
      $('#edit_ot_multiplier').val($(this).data('ot-multiplier'));
      $('#edit_night_diff_multiplier').val($(this).data('night-diff-multiplier'));
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    // Delete button
    $(document).on('click', '.deleteBtn', function () {
      let deleteId = $(this).data('id');
      Swal.fire({
        title: "Are you sure?",
        text: "This will permanently delete the day type.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete it!"
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: "day_types.php",
            type: "POST",
            data: { delete_id: deleteId },
            dataType: "json",
            success: function (response) {
              if (response.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: 'Deleted!',
                  text: response.message,
                  timer: 2000,
                  showConfirmButton: false,
                  position: 'top-end',
                  toast: true
                }).then(() => location.reload());
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: response.message,
                  timer: 3000,
                  showConfirmButton: false,
                  position: 'top-end',
                  toast: true
                });
              }
            },
            error: function () {
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Something went wrong during deletion.',
                timer: 3000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
              });
            }
          });
        }
      });
    });
  });
</script>

