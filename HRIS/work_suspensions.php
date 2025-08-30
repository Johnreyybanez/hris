<?php
session_start();
include 'connection.php';

// ADD SUSPENSION
if (isset($_POST['add_suspension'])) {
    $date = $_POST['date'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $is_half = isset($_POST['is_half_day']) ? 1 : 0;
    $is_full = isset($_POST['is_full_day']) ? 1 : 0;
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $location_id = $_POST['location_id'] ?: "NULL";

    $insert = mysqli_query($conn, "INSERT INTO WorkSuspensions (date, name, description, is_half_day, is_full_day, start_time, end_time, location_id) 
        VALUES ('$date', '$name', '$description', $is_half, $is_full, " . ($start_time ? "'$start_time'" : "NULL") . ", " . ($end_time ? "'$end_time'" : "NULL") . ", $location_id)");

    $_SESSION['success'] = $insert ? 'Suspension added successfully!' : 'Failed to add suspension.';
    header("Location: work_suspensions.php");
    exit();
}

// UPDATE SUSPENSION
if (isset($_POST['update_suspension'])) {
    $id = $_POST['edit_id'];
    $date = $_POST['date'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $is_half = isset($_POST['is_half_day']) ? 1 : 0;
    $is_full = isset($_POST['is_full_day']) ? 1 : 0;
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $location_id = $_POST['location_id'] ?: "NULL";

    $update = mysqli_query($conn, "UPDATE WorkSuspensions SET 
        date = '$date',
        name = '$name',
        description = '$description',
        is_half_day = $is_half,
        is_full_day = $is_full,
        start_time = " . ($start_time ? "'$start_time'" : "NULL") . ",
        end_time = " . ($end_time ? "'$end_time'" : "NULL") . ",
        location_id = $location_id
        WHERE suspension_id = $id");

    $_SESSION['success'] = $update ? 'Suspension updated successfully!' : 'Failed to update suspension.';
    header("Location: work_suspensions.php");
    exit();
}

// DELETE SUSPENSION
if (isset($_POST['delete_suspension'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM WorkSuspensions WHERE suspension_id=$id");
    $_SESSION['success'] = $delete ? 'Suspension deleted successfully!' : 'Failed to delete suspension.';
    header("Location: work_suspensions.php");
    exit();
}

include 'head.php';
include 'sidebar.php';
include 'header.php';
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Work Suspension Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active">Work Suspensions</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Work Suspensions</h5>
              <small class="text-muted">Manage work suspension records</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Suspension
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center w-100" id="suspensionTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Time</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $res = mysqli_query($conn, "SELECT ws.*, ol.name AS location_name FROM WorkSuspensions ws LEFT JOIN OfficeLocations ol ON ws.location_id = ol.location_id ORDER BY date DESC");
                while ($row = mysqli_fetch_assoc($res)):
                ?>
                  <tr>
                  <td><span class="badge bg-dark -secondary"><?= $row['suspension_id']; ?></span></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['location_name'] ?? 'Company-wide' ?></td>
                    <td><?= $row['is_half_day'] ? 'Half Day' : ($row['is_full_day'] ? 'Full Day' : 'Custom') ?></td>
                    <td><?= ($row['start_time'] && $row['end_time']) ? $row['start_time'].' - '.$row['end_time'] : '-' ?></td>
                    <td class="text-center">
                      <button class="btn btn-sm btn-outline-warning editBtn"
                        data-id="<?= $row['suspension_id'] ?>"
                        data-date="<?= $row['date'] ?>"
                        data-name="<?= htmlspecialchars($row['name']) ?>"
                        data-description="<?= htmlspecialchars($row['description']) ?>"
                        data-half="<?= $row['is_half_day'] ?>"
                        data-full="<?= $row['is_full_day'] ?>"
                        data-start="<?= $row['start_time'] ?>"
                        data-end="<?= $row['end_time'] ?>"
                        data-location="<?= $row['location_id'] ?>"
                      >
                        <i class="ti ti-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['suspension_id'] ?>">
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

<!-- Add Suspension Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Work Suspension</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Suspension Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_half_day" id="halfDay">
          <label class="form-check-label" for="halfDay">Half Day</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_full_day" id="fullDay" checked>
          <label class="form-check-label" for="fullDay">Full Day</label>
        </div>
        <div class="mb-2">
          <label class="form-label">Start Time</label>
          <input type="time" name="start_time" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">End Time</label>
          <input type="time" name="end_time" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Location</label>
          <select name="location_id" class="form-select">
            <option value="">-- Company-wide --</option>
            <?php
            $locs = mysqli_query($conn, "SELECT * FROM OfficeLocations");
            while ($loc = mysqli_fetch_assoc($locs)) {
                echo "<option value='{$loc['location_id']}'>{$loc['name']}</option>";
            }
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_suspension" class="btn btn-primary">Add Suspension</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Suspension Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Work Suspension</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="edit_date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Suspension Name</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_half_day" id="edit_half_day">
          <label class="form-check-label" for="edit_half_day">Half Day</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_full_day" id="edit_full_day">
          <label class="form-check-label" for="edit_full_day">Full Day</label>
        </div>
        <div class="mb-2">
          <label class="form-label">Start Time</label>
          <input type="time" name="start_time" id="edit_start_time" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">End Time</label>
          <input type="time" name="end_time" id="edit_end_time" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Location</label>
          <select name="location_id" id="edit_location_id" class="form-select">
            <option value="">-- Company-wide --</option>
            <?php
            $locs = mysqli_query($conn, "SELECT * FROM OfficeLocations");
            while ($loc = mysqli_fetch_assoc($locs)) {
                echo "<option value='{$loc['location_id']}'>{$loc['name']}</option>";
            }
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_suspension" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success']; ?>',
    timer: 3000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end'
  });
</script>
<?php unset($_SESSION['success']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
  const table = $('#suspensionTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search suspensions...",
      zeroRecords: "No matching records found",
      emptyTable: "No suspension records available",
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
      { targets: 6, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'desc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_date').val($(this).data('date'));
      $('#edit_name').val($(this).data('name'));
      $('#edit_description').val($(this).data('description'));
      $('#edit_half_day').prop('checked', $(this).data('half') == 1);
      $('#edit_full_day').prop('checked', $(this).data('full') == 1);
      $('#edit_start_time').val($(this).data('start'));
      $('#edit_end_time').val($(this).data('end'));
      $('#edit_location_id').val($(this).data('location'));
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
          $('<form method="POST">' +
            '<input type="hidden" name="delete_id" value="' + id + '">' +
            '<input type="hidden" name="delete_suspension" value="1">' +
          '</form>').appendTo('body').submit();
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
});
</script>

</body>
</html>
