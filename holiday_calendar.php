<?php
session_start();
include 'connection.php';

// ADD HOLIDAY
if (isset($_POST['add_holiday'])) {
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $day_type_id = intval($_POST['day_type_id']);

    $stmt = $conn->prepare("INSERT INTO HolidayCalendar (date, name, day_type_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $date, $name, $day_type_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Holiday added successfully!';
    } else {
        $_SESSION['error'] = 'Failed to add holiday: ' . $conn->error;
    }
    $stmt->close();
    header("Location: holiday_calendar.php");
    exit();
}

// UPDATE HOLIDAY
if (isset($_POST['update_holiday'])) {
    $id = intval($_POST['holiday_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $day_type_id = intval($_POST['day_type_id']);

    $stmt = $conn->prepare("UPDATE HolidayCalendar SET date=?, name=?, day_type_id=? WHERE holiday_id=?");
    $stmt->bind_param("ssii", $date, $name, $day_type_id, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Holiday updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update holiday: ' . $conn->error;
    }
    $stmt->close();
    header("Location: holiday_calendar.php");
    exit();
}

// DELETE HOLIDAY
if (isset($_POST['delete_holiday'])) {
    $id = intval($_POST['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM HolidayCalendar WHERE holiday_id=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Holiday deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete holiday: ' . $conn->error;
    }
    $stmt->close();
    header("Location: holiday_calendar.php");
    exit();
}

// Fetch DayTypes for dropdowns
$day_types = [];
$type_result = mysqli_query($conn, "SELECT day_type_id, name FROM DayTypes ORDER BY day_type_id ASC");
while ($type_row = mysqli_fetch_assoc($type_result)) {
    $day_types[$type_row['day_type_id']] = $type_row['name'];
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
              <h5 class="m-b-10">Holiday Calendar</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active" aria-current="page">Holidays</li>
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
              <h5 class="mb-0">Holiday Calendar</h5>
              <small class="text-muted">Manage official holidays</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Holiday
            </button>
          </div>
          <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              <?php unset($_SESSION['error']); endif; ?>
            <div class="table-responsive">
              <table id="holidayTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Day Type</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT hc.*, dt.name AS day_type_name 
                                                FROM HolidayCalendar hc
                                                JOIN DayTypes dt ON hc.day_type_id = dt.day_type_id
                                                ORDER BY hc.date ASC");
                  if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                     <td><span class="badge bg-dark -secondary"><?= $row['holiday_id']; ?></span></td>
                      <td><?= date('F d, Y', strtotime($row['date'])); ?></td>
                      <td><?= htmlspecialchars($row['name']); ?></td>
                      <td><?= htmlspecialchars($row['day_type_name']); ?></td>
                      <td class="text-center">
                        <div class="btn-group gap-1" role="group">
                          <button class="btn btn-sm btn-outline-warning editBtn"
                            data-id="<?= $row['holiday_id']; ?>"
                            data-date="<?= $row['date']; ?>"
                            data-name="<?= htmlspecialchars($row['name']); ?>"
                            data-daytype="<?= $row['day_type_id']; ?>"
                            title="Edit"><i class="ti ti-edit"></i></button>
                          <button class="btn btn-sm btn-outline-danger deleteBtn"
                            data-id="<?= $row['holiday_id']; ?>"
                            data-name="<?= htmlspecialchars($row['name']); ?>"
                            title="Delete"><i class="ti ti-trash"></i></button>
                        </div>
                      </td>
                    </tr>
                    <?php endwhile;
                  } else {
                    echo '<tr><td colspan="5" class="text-center">No holidays found</td></tr>';
                  }
                  ?>
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
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Holiday</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Date <span class="text-danger">*</span></label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Day Type <span class="text-danger">*</span></label>
          <select name="day_type_id" class="form-select" required>
            <option value="">Select day type</option>
            <?php foreach ($day_types as $id => $name): ?>
              <option value="<?= $id; ?>"><?= htmlspecialchars($name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_holiday" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add Holiday</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Holiday</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="holiday_id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Date <span class="text-danger">*</span></label>
          <input type="date" name="date" id="edit_date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Day Type <span class="text-danger">*</span></label>
          <select name="day_type_id" id="edit_day_type" class="form-select" required>
            <?php foreach ($day_types as $id => $name): ?>
              <option value="<?= $id; ?>"><?= htmlspecialchars($name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_holiday" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_holiday" value="1">
</form>

<?php if (isset($_SESSION['success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success'] ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
  });
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<script>
$(document).ready(function() {
  // Initialize DataTable
  const table = $('#holidayTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search holidays...",
      zeroRecords: "No matching records found",
      emptyTable: "No holidays available",
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
      { targets: 4, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function() {
      // Add search icon after each draw
      const searchBox = $('.dataTables_filter');
      if (searchBox.length && searchBox.find('.ti-search').length === 0) {
        searchBox.addClass('position-relative');
        searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
        searchBox.find('input').addClass('form-control ps-5 dt-search-input');
      }
    }
  });

  // Edit button click handler
  $(document).on('click', '.editBtn', function() {
    $('#edit_id').val($(this).data('id'));
    $('#edit_date').val($(this).data('date'));
    $('#edit_name').val($(this).data('name'));
    $('#edit_day_type').val($(this).data('daytype'));
    new bootstrap.Modal(document.getElementById('editModal')).show();
  });

  // Delete button click handler
  $(document).on('click', '.deleteBtn', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    Swal.fire({
      title: 'Delete Holiday?',
      html: `Are you sure you want to delete <strong>${name}</strong>?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        $('#delete_id').val(id);
        $('#deleteForm').submit();
      }
    });
  });
});
</script>

</body>
</html>