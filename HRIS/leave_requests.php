<?php
session_start();
include 'connection.php';

// Function to calculate working days (excluding weekends and holidays)
function calculateWorkingDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1; // Include both start and end dates
    
    // Count weekends
    $weekends = 0;
    $current = clone $start;
    while ($current <= $end) {
        if ($current->format('N') >= 6) { // Saturday = 6, Sunday = 7
            $weekends++;
        }
        $current->add(new DateInterval('P1D'));
    }
    
    // TODO: Subtract holidays from database if you have a holidays table
    return $days - $weekends;
}

// ADD LEAVE REQUEST
if (isset($_POST['add_leave_request'])) {
    $employee_id = $_POST['employee_id'];
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $total_days = $start->diff($end)->days + 1;
    $actual_leave_days = calculateWorkingDays($start_date, $end_date);
    
    $insert = mysqli_query($conn, "INSERT INTO EmployeeLeaveRequests (employee_id, leave_type_id, start_date, end_date, total_days, actual_leave_days, reason) 
                                   VALUES ($employee_id, $leave_type_id, '$start_date', '$end_date', $total_days, $actual_leave_days, '$reason')");
    $_SESSION['success'] = $insert ? 'Leave request submitted successfully!' : 'Failed to submit leave request.';
    header("Location: leave_requests.php");
    exit();
}
// Function to get shift total hours
function getShiftTotalHours($conn, $shift_id) {
    $query = "SELECT TIMEDIFF(end_time, start_time) as shift_duration FROM shifts WHERE shift_id = $shift_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['shift_duration'];
    }
    return '08:00:00'; // Default 8 hours if shift not found
}

// Function to update DTR for approved leave dates
function updateDTRForLeave($conn, $employee_id, $start_date, $end_date, $leave_type_id) {
    // Get employee's shift_id
    $shift_query = "SELECT shift_id FROM employees WHERE employee_id = $employee_id";
    $shift_result = mysqli_query($conn, $shift_query);
    $shift_row = mysqli_fetch_assoc($shift_result);
    $shift_id = $shift_row['shift_id'] ?? 1; // Default shift if not found
    
    // Get total hours for the shift
    $total_work_time = getShiftTotalHours($conn, $shift_id);
    
    // Create date range for leave period (excluding weekends)
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $current = clone $start;
    
    while ($current <= $end) {
        // Skip weekends
        if ($current->format('N') < 6) { // Monday = 1, Friday = 5
            $date = $current->format('Y-m-d');
            $day_of_week = $current->format('l');
            
            // Check if DTR entry already exists for this date
            $check_dtr = mysqli_query($conn, "SELECT dtr_id FROM employeedtr WHERE employee_id = $employee_id AND date = '$date'");
            
            if (mysqli_num_rows($check_dtr) > 0) {
                // Update existing DTR entry
                $update_dtr = "UPDATE employeedtr SET 
                    day_type_id = 12,
                    leave_type_id = $leave_type_id,
                    total_work_time = '$total_work_time',
                    time_in = NULL,
                    time_out = NULL,
                    break_in = NULL,
                    break_out = NULL,
                    undertime_time = '00:00:00',
                    late_time = '00:00:00',
                    overtime_time = 0.00,
                    night_time = '00:00:00',
                    approval_status = 'Approved',
                    remarks = 'On Leave',
                    updated_at = NOW()
                    WHERE employee_id = $employee_id AND date = '$date'";
            } else {
                // Insert new DTR entry
                $update_dtr = "INSERT INTO employeedtr (
                    employee_id, date, day_of_week, shift_id, day_type_id, leave_type_id,
                    total_work_time, time_in, time_out, break_in, break_out,
                    undertime_time, late_time, overtime_time, night_time,
                    approval_status, remarks, is_flexible, is_manual, has_missing_log,
                    created_at, updated_at
                ) VALUES (
                    $employee_id, '$date', '$day_of_week', $shift_id, 13, $leave_type_id,
                    '$total_work_time', NULL, NULL, NULL, NULL,
                    '00:00:00', '00:00:00', 0.00, '00:00:00',
                    'Approved', 'On Leave', 0, 1, 0,
                    NOW(), NOW()
                )";
            }
            
            mysqli_query($conn, $update_dtr);
        }
        $current->add(new DateInterval('P1D'));
    }
}

// UPDATE LEAVE REQUEST STATUS
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $approved_by = $_POST['approved_by'];
    $approval_remarks = $_POST['approval_remarks'];
    
    $approved_at = ($status != 'Pending') ? 'NOW()' : 'NULL';
    
    // Get leave request details before updating
    $get_request = mysqli_query($conn, "SELECT * FROM EmployeeLeaveRequests WHERE leave_request_id = $request_id");
    $request_data = mysqli_fetch_assoc($get_request);
    
    $update = mysqli_query($conn, "UPDATE EmployeeLeaveRequests 
                                   SET status='$status', approved_by=$approved_by, approved_at=$approved_at, approval_remarks='$approval_remarks' 
                                   WHERE leave_request_id=$request_id");
    
    if ($update) {
        // If status is approved, update DTR records
        if ($status == 'Approved') {
            updateDTRForLeave(
                $conn, 
                $request_data['employee_id'], 
                $request_data['start_date'], 
                $request_data['end_date'], 
                $request_data['leave_type_id']
            );
            $_SESSION['success'] = 'Leave request approved and DTR updated successfully!';
        } else {
            $_SESSION['success'] = 'Leave request status updated successfully!';
        }
    } else {
        $_SESSION['error'] = 'Failed to update leave request.';
    }
    
    header("Location: leave_requests.php");
    exit();
} 
// DELETE LEAVE REQUEST
if (isset($_POST['delete_leave_request'])) {
    $id = $_POST['delete_id'];
    
    // Check if the request is approved
    $check_status = mysqli_query($conn, "SELECT status FROM EmployeeLeaveRequests WHERE leave_request_id=$id");
    $status_row = mysqli_fetch_assoc($check_status);
    
    if ($status_row['status'] == 'Approved') {
        $_SESSION['error'] = 'Cannot delete approved leave requests!';
    } else {
        $delete = mysqli_query($conn, "DELETE FROM EmployeeLeaveRequests WHERE leave_request_id=$id");
        $_SESSION['success'] = $delete ? 'Leave request deleted successfully!' : 'Failed to delete leave request.';
    }
    
    header("Location: leave_requests.php");
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
              <h5 class="m-b-10">Employee Leave Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Leave Management</li>
              <li class="breadcrumb-item active" aria-current="page">Leave Requests</li>
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
              <h5 class="mb-0">Leave Requests</h5>
              <small class="text-muted">Manage employee leave requests</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Leave Request
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center w-100" id="requestTable" >
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $result = mysqli_query($conn, "SELECT lr.*, 
                                                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                                                 lt.name as leave_type_name,
                                                 u.username as approver_name
                                                 FROM EmployeeLeaveRequests lr
                                                 LEFT JOIN Employees e ON lr.employee_id = e.employee_id
                                                 LEFT JOIN LeaveTypes lt ON lr.leave_type_id = lt.leave_type_id
                                                 LEFT JOIN users u ON lr.approved_by = u.user_id
                                                 ORDER BY lr.requested_at DESC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-light-primary"><?= $row['leave_request_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['employee_name']); ?></td>
                    <td><?= htmlspecialchars($row['leave_type_name']); ?></td>
                    <td><?= date('M d, Y', strtotime($row['start_date'])); ?></td>
                    <td><?= date('M d, Y', strtotime($row['end_date'])); ?></td>
                    <td><?= $row['actual_leave_days']; ?> days</td>
                    <td>
                      <?php if ($row['status'] == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($row['status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($row['requested_at'])); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-employee="<?= htmlspecialchars($row['employee_name']); ?>"
                          data-leave-type="<?= htmlspecialchars($row['leave_type_name']); ?>"
                          data-start="<?= $row['start_date']; ?>"
                          data-end="<?= $row['end_date']; ?>"
                          data-days="<?= $row['actual_leave_days']; ?>"
                          data-reason="<?= htmlspecialchars($row['reason']); ?>"
                          data-status="<?= $row['status']; ?>"
                          data-approver="<?= htmlspecialchars($row['approver_name']); ?>"
                          data-approval-remarks="<?= htmlspecialchars($row['approval_remarks']); ?>"
                          title="View Details"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning statusBtn"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-status="<?= $row['status']; ?>"
                          title="Update Status"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>"
                          data-id="<?= $row['leave_request_id']; ?>"
                          data-status="<?= $row['status']; ?>"
                          title="<?= ($row['status'] == 'Approved') ? 'Cannot delete approved requests' : 'Delete'; ?>"
                          <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>><i class="ti ti-trash"></i></button>
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
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Select Employee</option>
            <?php
            $employees = mysqli_query($conn, "SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM Employees WHERE status='Active'");
            while ($emp = mysqli_fetch_assoc($employees)):
            ?>
            <option value="<?= $emp['employee_id']; ?>"><?= htmlspecialchars($emp['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Leave Type</label>
          <select name="leave_type_id" class="form-select" required>
            <option value="">Select Leave Type</option>
            <?php
            $leave_types = mysqli_query($conn, "SELECT leave_type_id, name FROM LeaveTypes");
            while ($lt = mysqli_fetch_assoc($leave_types)):
            ?>
            <option value="<?= $lt['leave_type_id']; ?>"><?= htmlspecialchars($lt['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
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
        <button type="submit" name="add_leave_request" class="btn btn-primary"><i class="ti ti-check me-1"></i>Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>Leave Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <p><strong>Employee:</strong> <span id="view_employee"></span></p>
            <p><strong>Leave Type:</strong> <span id="view_leave_type"></span></p>
            <p><strong>Start Date:</strong> <span id="view_start_date"></span></p>
          </div>
          <div class="col-md-6">
            <p><strong>End Date:</strong> <span id="view_end_date"></span></p>
            <p><strong>Days:</strong> <span id="view_days"></span></p>
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
            <strong>Approval Remarks:</strong>
            <p id="view_approval_remarks" class="mt-2"></p>
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
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Update Leave Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="request_id" id="status_request_id">
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
  <input type="hidden" name="delete_leave_request" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#requestTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search leave requests...',
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

  function bindActionButtons() {
    $('.viewBtn').off().on('click', function () {
      $('#view_employee').text($(this).data('employee'));
      $('#view_leave_type').text($(this).data('leave-type'));
      $('#view_start_date').text(new Date($(this).data('start')).toLocaleDateString());
      $('#view_end_date').text(new Date($(this).data('end')).toLocaleDateString());
      $('#view_days').text($(this).data('days') + ' days');
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
        const approverName = $(this).data('approver');
        $('#view_approver').text(approverName || 'N/A');
        $('#view_approval_remarks').text($(this).data('approval-remarks') || 'No remarks');
        $('#approval_section').show();
      } else {
        $('#approval_section').hide();
      }
      
      new bootstrap.Modal(document.getElementById('viewModal')).show();
    });

    $('.statusBtn').off().on('click', function () {
      $('#status_request_id').val($(this).data('id'));
      $('#status_select').val($(this).data('status'));
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      const status = $(this).data('status');
      
      // Check if the request is approved
      if (status === 'Approved') {
        Swal.fire({
          title: 'Cannot Delete',
          text: 'Approved leave requests cannot be deleted!',
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

  // Date validation
  $('input[name="start_date"]').on('change', function() {
    const startDate = $(this).val();
    $('input[name="end_date"]').attr('min', startDate);
  });

  $('input[name="end_date"]').on('change', function() {
    const endDate = $(this).val();
    $('input[name="start_date"]').attr('max', endDate);
  });

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