<?php
session_start();
include 'connection.php'; // DB connection

if (isset($_POST['add_shift'])) {
  $name = mysqli_real_escape_string($conn, $_POST['shift_name']);
  $time_in = $_POST['time_in'];
  $break_out = $_POST['break_out'] ?? null;
  $break_in = $_POST['break_in'] ?? null;
  $time_out = $_POST['time_out'];
  $is_flexible = isset($_POST['is_flexible']) ? 1 : 0;
  $has_break = isset($_POST['has_break']) ? 1 : 0;
  $description = mysqli_real_escape_string($conn, $_POST['description']);

  // AUTO-DETECT NIGHT SHIFT
  $is_night_shift = (strtotime($time_in) >= strtotime('18:00')) ? 1 : 0;

  $insert = mysqli_query($conn, "INSERT INTO shifts 
      (shift_name, time_in, break_out, break_in, time_out, is_flexible, has_break, description, is_night_shift) 
      VALUES 
      ('$name', '$time_in', " . ($break_out ? "'$break_out'" : 'NULL') . ", " . ($break_in ? "'$break_in'" : 'NULL') . ", '$time_out', $is_flexible, $has_break, '$description', $is_night_shift)");

  $_SESSION['success'] = $insert ? 'Shift added successfully!' : 'Failed to add shift: ' . mysqli_error($conn);
  header("Location: shifts.php");
  exit();
}

if (isset($_POST['update_shift'])) {
  $id = intval($_POST['shift_id']);
  $name = mysqli_real_escape_string($conn, $_POST['shift_name']);
  $time_in = $_POST['time_in'];
  $break_out = $_POST['break_out'] ?? null;
  $break_in = $_POST['break_in'] ?? null;
  $time_out = $_POST['time_out'];
  $is_flexible = isset($_POST['is_flexible']) ? 1 : 0;
  $has_break = isset($_POST['has_break']) ? 1 : 0;
  $description = mysqli_real_escape_string($conn, $_POST['description']);

  // AUTO-DETECT NIGHT SHIFT
  $is_night_shift = (strtotime($time_in) >= strtotime('18:00')) ? 1 : 0;

  $update = mysqli_query($conn, "UPDATE shifts SET 
      shift_name='$name', time_in='$time_in', break_out=" . ($break_out ? "'$break_out'" : 'NULL') . ", break_in=" . ($break_in ? "'$break_in'" : 'NULL') . ", 
      time_out='$time_out', is_flexible=$is_flexible, has_break=$has_break, description='$description', is_night_shift=$is_night_shift
      WHERE shift_id=$id");

  $_SESSION['success'] = $update ? 'Shift updated successfully!' : 'Failed to update shift: ' . mysqli_error($conn);
  header("Location: shifts.php");
  exit();
}

if (isset($_POST['delete_shift'])) {
  $id = intval($_POST['delete_id']);
  $delete = mysqli_query($conn, "DELETE FROM shifts WHERE shift_id=$id");
  $_SESSION['success'] = $delete ? 'Shift deleted successfully!' : 'Failed to delete shift: ' . mysqli_error($conn);
  header("Location: shifts.php");
  exit();
}

function calculate_total_hours($time_in, $break_out, $break_in, $time_out) {
  $start = strtotime($time_in);
  $end = strtotime($time_out);

  // Handle overnight shifts (e.g., 22:00 to 06:00)
  if ($end <= $start) {
    $end += 86400; // Add 24 hours
  }

  $total_seconds = $end - $start;

  // Subtract break time if both break_out and break_in exist
  if (!empty($break_out) && !empty($break_in)) {
    $break_start = strtotime($break_out);
    $break_end = strtotime($break_in);

    // Handle overnight breaks
    if ($break_end <= $break_start) {
      $break_end += 86400;
    }

    $break_seconds = $break_end - $break_start;

    // Only subtract break if it's within shift time
    if ($break_start >= $start && $break_end <= $end) {
      $total_seconds -= $break_seconds;
    }
  }

  // Convert to hours
  $hours = round($total_seconds / 3600, 2);
  return $hours > 0 ? $hours : 0;
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
              <h5 class="m-b-10">Shift Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item" aria-current="page">Shifts</li>
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
              <h5 class="mb-0">Shifts</h5>
              <small class="text-muted">Manage shift schedules and times (24-hour format)</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Shift
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="shiftTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Shift Name</th>
                    <th>Time In - Time Out</th>
                    <th>Total Hours</th>
                    <th>Description</th>
                    <th>Has Break</th>
                    <th>Is Flexible</th>
                    <th>Night Shift</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT shift_id, shift_name, TIME_FORMAT(time_in, '%H:%i') as time_in, 
                    TIME_FORMAT(break_out, '%H:%i') as break_out, TIME_FORMAT(break_in, '%H:%i') as break_in, 
                    TIME_FORMAT(time_out, '%H:%i') as time_out, is_flexible, has_break, description, is_night_shift 
                    FROM shifts");
                  while ($row = mysqli_fetch_assoc($result)):
                    $total_hours = calculate_total_hours(
                      $row['time_in'], $row['break_out'], $row['break_in'], $row['time_out']
                    );
                  ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['shift_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['shift_name']); ?></td>
                    <td><?= $row['time_in'] . ' - ' . $row['time_out']; ?></td>
                    <td><?= number_format($total_hours, 1); ?> hours</td>
                    <td><?= htmlspecialchars($row['description']); ?></td>
                    <td><?= $row['has_break'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $row['is_flexible'] ? 'Yes' : 'No'; ?></td>
                    <td>
                      <?php if ($row['is_night_shift']): ?>
                        <span class="badge bg-danger">Night</span>
                      <?php else: ?>
                        <span class="badge bg-success">Day</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['shift_id']; ?>"
                          data-name="<?= htmlspecialchars($row['shift_name']); ?>"
                          data-time_in="<?= $row['time_in']; ?>"
                          data-break_out="<?= $row['break_out']; ?>"
                          data-break_in="<?= $row['break_in']; ?>"
                          data-time_out="<?= $row['time_out']; ?>"
                          data-is_flexible="<?= $row['is_flexible']; ?>"
                          data-has_break="<?= $row['has_break']; ?>"
                          data-desc="<?= htmlspecialchars($row['description']); ?>"
                          data-is_night_shift="<?= $row['is_night_shift']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['shift_id']; ?>"
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
        <h5 class="modal-title" id="addModalLabel"><i class="ti ti-plus me-2"></i>Add Shift</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="add_shift_name" class="form-label">Shift Name</label>
          <input type="text" name="shift_name" id="add_shift_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_time_in" class="form-label">Time In (HH:MM)</label>
          <input type="time" name="time_in" id="add_time_in" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="add_break_out" class="form-label">Break Out (HH:MM)</label>
          <input type="time" name="break_out" id="add_break_out" class="form-control">
        </div>
        <div class="mb-3">
          <label for="add_break_in" class="form-label">Break In (HH:MM)</label>
          <input type="time" name="break_in" id="add_break_in" class="form-control">
        </div>
        <div class="mb-3">
          <label for="add_time_out" class="form-label">Time Out (HH:MM)</label>
          <input type="time" name="time_out" id="add_time_out" class="form-control" required>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="is_flexible" id="add_is_flexible">
          <label class="form-check-label" for="add_is_flexible">Is Flexible</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="has_break" id="add_has_break">
          <label class="form-check-label" for="add_has_break">Has Break</label>
        </div>
        <div class="mb-3">
          <label for="add_description" class="form-label">Description</label>
          <textarea name="description" id="add_description" class="form-control" rows="2"></textarea>
        </div>
        <div class="alert alert-info">
          <strong>Night Shift:</strong> Auto-detected if Time In ≥ 18:00
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_shift" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel"><i class="ti ti-edit me-2"></i>Edit Shift</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="shift_id" id="edit_shift_id">
        <div class="mb-3">
          <label for="edit_shift_name" class="form-label">Shift Name</label>
          <input type="text" name="shift_name" id="edit_shift_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_time_in" class="form-label">Time In (HH:MM)</label>
          <input type="time" name="time_in" id="edit_time_in" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_break_out" class="form-label">Break Out (HH:MM)</label>
          <input type="time" name="break_out" id="edit_break_out" class="form-control">
        </div>
        <div class="mb-3">
          <label for="edit_break_in" class="form-label">Break In (HH:MM)</label>
          <input type="time" name="break_in" id="edit_break_in" class="form-control">
        </div>
        <div class="mb-3">
          <label for="edit_time_out" class="form-label">Time Out (HH:MM)</label>
          <input type="time" name="time_out" id="edit_time_out" class="form-control" required>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="is_flexible" id="edit_is_flexible">
          <label class="form-check-label" for="edit_is_flexible">Is Flexible</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="has_break" id="edit_has_break">
          <label class="form-check-label" for="edit_has_break">Has Break</label>
        </div>
        <div class="mb-3">
          <label for="edit_description" class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
        </div>
        <div id="edit_night_alert" class="alert alert-info d-none">
          <strong>Night Shift:</strong> Starts at 18:00 or later
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_shift" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_shift" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#shiftTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search shifts...",
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
      { targets: 8, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      const time_in = $(this).data('time_in');
      const break_out = $(this).data('break_out') || '';
      const break_in = $(this).data('break_in') || '';
      const time_out = $(this).data('time_out');
      const is_flexible = $(this).data('is_flexible');
      const has_break = $(this).data('has_break');
      const description = $(this).data('desc');
      const is_night_shift = $(this).data('is_night_shift');

      $('#edit_shift_id').val(id);
      $('#edit_shift_name').val(name);
      $('#edit_time_in').val(time_in);
      $('#edit_break_out').val(break_out);
      $('#edit_break_in').val(break_in);
      $('#edit_time_out').val(time_out);
      $('#edit_is_flexible').prop('checked', is_flexible == 1);
      $('#edit_has_break').prop('checked', has_break == 1);
      $('#edit_description').val(description);

      // Show night shift alert
      if (is_night_shift == 1) {
        $('#edit_night_alert').removeClass('d-none');
      } else {
        $('#edit_night_alert').addClass('d-none');
      }

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

  // Validate time inputs
  $('input[type="time"]').on('input', function() {
    const value = $(this).val();
    const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
    if (value && !timePattern.test(value)) {
      $(this).addClass('is-invalid');
    } else {
      $(this).removeClass('is-invalid');
    }
  });

  // Form submission validation
  $('form').on('submit', function(e) {
    const invalidFields = $(this).find('.is-invalid');
    if (invalidFields.length > 0) {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please enter valid 24-hour time formats (HH:MM).',
        confirmButtonText: 'OK'
      });
    }
  });

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