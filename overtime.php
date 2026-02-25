<?php
session_start();
include 'connection.php';

// Function to calculate total overtime hours
function calculateOvertimeHours($start_time, $end_time)
{
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    $diff = ($end - $start) / 3600; // convert seconds to hours
    return round($diff, 2);
}

// Function to add overtime to employeedtr if approved
function addOvertimeToDTR($employee_id, $date, $overtime_hours, $conn)
{
    $check = mysqli_query($conn, "SELECT * FROM employeedtr WHERE employee_id=$employee_id AND date='$date'");

    if ($check && mysqli_num_rows($check) > 0) {
        // Update existing record if overtime_time already exists
        $update = mysqli_query($conn, "UPDATE employeedtr 
                                        SET overtime_time = overtime_time + $overtime_hours 
                                        WHERE employee_id=$employee_id AND date='$date'");
        return $update;
    } else {
        // Insert new record if no DTR for that date
        $insert = mysqli_query($conn, "INSERT INTO employeedtr (employee_id, date, overtime_time) 
                                        VALUES ($employee_id, '$date', $overtime_hours)");
        return $insert;
    }
}

// ADD OVERTIME REQUEST
if (isset($_POST['add_overtime_request'])) {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $reason = $_POST['reason'];

    // Calculate total hours
    $total_hours = calculateOvertimeHours($start_time, $end_time);

    $insert = mysqli_query($conn, "INSERT INTO overtime (employee_id, date, start_time, end_time, total_hours, reason) 
                                   VALUES ($employee_id, '$date', '$start_time', '$end_time', $total_hours, '$reason')");
    $_SESSION['success'] = $insert ? 'Overtime request submitted successfully!' : 'Failed to submit overtime request.';
    header("Location: overtime.php");
    exit();
}

// UPDATE OVERTIME REQUEST STATUS
if (isset($_POST['update_status'])) {
    $overtime_id = $_POST['overtime_id'];
    $status = $_POST['status'];
    $approved_by = $_POST['approved_by'];
    $remarks = $_POST['remarks'];

    $update = mysqli_query($conn, "UPDATE overtime 
                                   SET approval_status='$status', approved_by=$approved_by, remarks='$remarks' 
                                   WHERE overtime_id=$overtime_id");

    if ($update && $status == 'Approved') {
        // Fetch overtime record details
        $record = mysqli_query($conn, "SELECT employee_id, date, total_hours FROM overtime WHERE overtime_id=$overtime_id");
        if ($record && mysqli_num_rows($record) > 0) {
            $row = mysqli_fetch_assoc($record);
            $employee_id = $row['employee_id'];
            $date = $row['date'];
            $overtime_hours = $row['total_hours'];
            // Add or update overtime_time in employeedtr
            addOvertimeToDTR($employee_id, $date, $overtime_hours, $conn);
        }
    }

    $_SESSION['success'] = $update ? 'Overtime request status updated successfully!' : 'Failed to update overtime request.';
    header("Location: overtime.php");
    exit();
}

// DELETE OVERTIME REQUEST
if (isset($_POST['delete_overtime_request'])) {
    $id = $_POST['delete_id'];

    // Check if the request is approved
    $check_status = mysqli_query($conn, "SELECT approval_status FROM overtime WHERE overtime_id=$id");
    $status_row = mysqli_fetch_assoc($check_status);

    if ($status_row['approval_status'] == 'Approved') {
        $_SESSION['error'] = 'Cannot delete approved overtime requests!';
    } else {
        $delete = mysqli_query($conn, "DELETE FROM overtime WHERE overtime_id=$id");
        $_SESSION['success'] = $delete ? 'Overtime request deleted successfully!' : 'Failed to delete overtime request.';
    }

    header("Location: overtime.php");
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
              <h5 class="m-b-10">Employee Overtime Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Overtime Management</li>
              <li class="breadcrumb-item active" aria-current="page">Overtime Requests</li>
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
              <h5 class="mb-0">Overtime Requests</h5>
              <small class="text-muted">Manage employee overtime requests</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Overtime Request
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="overtimeTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Total Hours</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $result = mysqli_query($conn, "SELECT o.*, 
                                                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                                                 u.username as approver_name
                                                 FROM overtime o
                                                 LEFT JOIN employees e ON o.employee_id = e.employee_id
                                                 LEFT JOIN users u ON o.approved_by = u.user_id
                                                 ORDER BY o.created_at DESC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark -secondary"><?= $row['overtime_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['employee_name']); ?></td>
                    <td><?= date('M d, Y', strtotime($row['date'])); ?></td>
                    <td><?= htmlspecialchars($row['start_time']); ?></td>
                    <td><?= htmlspecialchars($row['end_time']); ?></td>
                    <td><?= $row['total_hours']; ?> hrs</td>
                    <td>
                      <?php if ($row['approval_status'] == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($row['approval_status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($row['created_at'])); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['overtime_id']; ?>"
                          data-employee="<?= htmlspecialchars($row['employee_name']); ?>"
                          data-date="<?= $row['date']; ?>"
                          data-start="<?= $row['start_time']; ?>"
                          data-end="<?= $row['end_time']; ?>"
                          data-hours="<?= $row['total_hours']; ?>"
                          data-reason="<?= htmlspecialchars($row['reason']); ?>"
                          data-status="<?= $row['approval_status']; ?>"
                          data-approver="<?= htmlspecialchars($row['approver_name']); ?>"
                          data-remarks="<?= htmlspecialchars($row['remarks']); ?>"
                          title="View Details"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning statusBtn"
                          data-id="<?= $row['overtime_id']; ?>"
                          data-status="<?= $row['approval_status']; ?>"
                          title="Update Status"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn <?= ($row['approval_status'] == 'Approved') ? 'disabled' : ''; ?>"
                          data-id="<?= $row['overtime_id']; ?>"
                          data-status="<?= $row['approval_status']; ?>"
                          title="<?= ($row['approval_status'] == 'Approved') ? 'Cannot delete approved requests' : 'Delete'; ?>"
                          <?= ($row['approval_status'] == 'Approved') ? 'disabled' : ''; ?>><i class="ti ti-trash"></i></button>
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

<!-- Add Overtime Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Overtime Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Select Employee</option>
            <?php
            $employees = mysqli_query($conn, "SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE status='Active'");
            while ($emp = mysqli_fetch_assoc($employees)):
            ?>
            <option value="<?= $emp['employee_id']; ?>"><?= htmlspecialchars($emp['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Start Time</label>
              <input type="time" name="start_time" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">End Time</label>
              <input type="time" name="end_time" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="3" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_overtime_request" class="btn btn-primary"><i class="ti ti-check me-1"></i>Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- View Overtime Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>Overtime Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <p><strong>Employee:</strong> <span id="view_employee"></span></p>
            <p><strong>Date:</strong> <span id="view_date"></span></p>
            <p><strong>Start Time:</strong> <span id="view_start_time"></span></p>
          </div>
          <div class="col-md-6">
            <p><strong>End Time:</strong> <span id="view_end_time"></span></p>
            <p><strong>Total Hours:</strong> <span id="view_hours"></span></p>
            <p><strong>Status:</strong> <span id="view_status"></span></p>
          </div>
        </div>
        <div class="mb-3">
          <strong>Reason:</strong>
          <p id="view_reason" class="mt-2"></p>
        </div>
        <div id="approval_section" style="display: none;">
          <hr>
          <p><strong>Approved by:</strong> <span id="view_approver"></span></p>
          <div class="mb-3">
            <strong>Remarks:</strong>
            <p id="view_remarks" class="mt-2"></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Update Overtime Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="overtime_id" id="status_overtime_id">
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select name="status" id="status_select" class="form-select" required>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Approval Remarks</label>
          <textarea name="approval_remarks" id="approval_remarks" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
        </div>
        <input type="hidden" name="approved_by" value="<?= $_SESSION['user_id']; ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_status" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_overtime_request" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#overtimeTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search overtime requests...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching leave requests found',
      emptyTable: 'No leave requests available',
      paginate: {
        first: 'First',
        last: 'Last', 
        next: 'Next',
        previous: 'Previous'
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, width: '60px', className: 'text-center' },
      { targets: 8, orderable: false, className: 'text-center' }
    ],
    order: [[0, 'desc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }
  function bindActionButtons() {
    $('.viewBtn').off().on('click', function () {
      $('#view_employee').text($(this).data('employee'));
      $('#view_date').text(new Date($(this).data('date')).toLocaleDateString());
      $('#view_start_time').text($(this).data('start'));
      $('#view_end_time').text($(this).data('end'));
      $('#view_hours').text($(this).data('hours') + ' hrs');
      $('#view_reason').text($(this).data('reason'));
      
      const status = $(this).data('status');
      let statusBadge = '';
      if (status === 'Pending') {
        statusBadge = '<span class="badge bg-warning">Pending</span>';
      } else if (status === 'Approved') {
        statusBadge = '<span class="badge bg-success">Approved</span>';
      } else {
        statusBadge = '<span class="badge bg-danger">Rejected</span>';
      }
      $('#view_status').html(statusBadge);

      if (status !== 'Pending') {
        $('#view_approver').text($(this).data('approver') || 'N/A');
        $('#view_remarks').text($(this).data('remarks') || 'No remarks');
        $('#approval_section').show();
      } else {
        $('#approval_section').hide();
      }

      new bootstrap.Modal(document.getElementById('viewModal')).show();
    });

    $('.statusBtn').off().on('click', function () {
      $('#status_overtime_id').val($(this).data('id'));
      $('#status_select').val($(this).data('status'));
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      const status = $(this).data('status');
      
      if (status === 'Approved') {
        Swal.fire({
          title: 'Cannot Delete',
          text: 'Approved overtime requests cannot be deleted!',
          icon: 'error',
          confirmButtonText: 'OK'
        });
        return;
      }
      
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
