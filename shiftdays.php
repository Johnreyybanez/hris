<?php
session_start();
include 'connection.php'; // DB connection

// ADD SHIFT DAYS
if (isset($_POST['add_shift_days'])) {
    $shift_id = $_POST['shift_id'];
    
    // Prepare the columns and values for the insert
    $columns = ['shift_id'];
    $values = ["'$shift_id'"];
    
    // Add each day if it's checked
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        $columns[] = "is_$day";
        $values[] = isset($_POST[$day]) ? 'TRUE' : 'FALSE';
    }
    
    $insert = mysqli_query($conn, "INSERT INTO shiftdays (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")");
    $_SESSION['success'] = $insert ? 'Shift days added successfully!' : 'Failed to add shift days.';
    header("Location: shiftdays.php");
    exit();
}

// UPDATE SHIFT DAYS
if (isset($_POST['update_shift_days'])) {
    $id = $_POST['shift_day_id'];
    $shift_id = $_POST['shift_id'];
    
    // Start building the update query
    $update_query = "UPDATE shiftdays SET shift_id='$shift_id'";
    
    // Add each day's value
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        $value = isset($_POST[$day]) ? 'TRUE' : 'FALSE';
        $update_query .= ", is_$day=$value";
    }
    
    $update_query .= " WHERE shift_day_id=$id";
    
    $update = mysqli_query($conn, $update_query);
    $_SESSION['success'] = $update ? 'Shift days updated successfully!' : 'Failed to update shift days.';
    header("Location: shiftdays.php");
    exit();
}

// DELETE SHIFT DAYS
if (isset($_POST['delete_shift_days'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM shiftdays WHERE shift_day_id=$id");
    $_SESSION['success'] = $delete ? 'Shift days deleted successfully!' : 'Failed to delete shift days.';
    header("Location: shiftdays.php");
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
              <h5 class="m-b-10">Shift Days Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item" aria-current="page">Shift Days</li>
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
              <h5 class="mb-0">Shift Days</h5>
              <small class="text-muted">Manage shift day assignments</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Shift Days
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="shiftDayTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Shift</th>
                    <th>Days</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT sd.*, s.shift_name FROM shiftdays sd JOIN shifts s ON sd.shift_id = s.shift_id");
                  while ($row = mysqli_fetch_assoc($result)): 
                    // Get the active days for this shift
                    $active_days = [];
                    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    foreach ($days as $day) {
                        if ($row["is_$day"]) {
                            $active_days[] = ucfirst($day);
                        }
                    }
                  ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['shift_day_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['shift_name']); ?></td>
                    <td><?= implode(', ', $active_days); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['shift_day_id']; ?>"
                          data-shift="<?= $row['shift_id']; ?>"
                          <?php foreach ($days as $day): ?>
                          data-<?= $day ?>="<?= $row["is_$day"] ? '1' : '0' ?>"
                          <?php endforeach; ?>
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['shift_day_id']; ?>"
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
        <h5 class="modal-title" id="addModalLabel"><i class="ti ti-plus me-2"></i>Add Shift Days</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="add_shift" class="form-label">Shift</label>
          <select name="shift_id" id="add_shift" class="form-select" required>
            <option value="" disabled selected>Select shift</option>
            <?php
            $shifts = mysqli_query($conn, "SELECT * FROM shifts");
            while ($s = mysqli_fetch_assoc($shifts)):
            ?>
            <option value="<?= $s['shift_id']; ?>"><?= htmlspecialchars($s['shift_name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Days</label>
          <div class="row">
            <?php 
            $days = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday'
            ];
            foreach ($days as $key => $day): ?>
            <div class="col-md-4 mb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="add_<?= $key ?>">
                <label class="form-check-label" for="add_<?= $key ?>"><?= $day ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_shift_days" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel"><i class="ti ti-edit me-2"></i>Edit Shift Days</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="shift_day_id" id="edit_id">
        <div class="mb-3">
          <label for="edit_shift" class="form-label">Shift</label>
          <select name="shift_id" id="edit_shift" class="form-select" required>
            <?php
            $shifts = mysqli_query($conn, "SELECT * FROM shifts");
            while ($s = mysqli_fetch_assoc($shifts)):
            ?>
            <option value="<?= $s['shift_id']; ?>"><?= htmlspecialchars($s['shift_name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Days</label>
          <div class="row">
            <?php 
            // Define the days array here
            $days = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday'
            ];
            foreach ($days as $key => $day): ?>
            <div class="col-md-4 mb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="edit_<?= $key ?>">
                <label class="form-check-label" for="edit_<?= $key ?>"><?= $day ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_shift_days" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>
<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_shift_days" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#shiftDayTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search shift days...",
      zeroRecords: "No matching shift days found",
      emptyTable: "No shift days available",
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
      $('#edit_shift').val($(this).data('shift'));
      
      // Set the checkboxes based on data attributes
      const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
      days.forEach(day => {
        $(`#edit_${day}`).prop('checked', $(this).data(day) === '1');
      });
      
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

  // Add search icon to DataTable filter box
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  // SweetAlert Toast for success messages
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