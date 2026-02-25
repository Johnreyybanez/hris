<?php
session_start();
include 'connection.php';

// ADD MISSING TIME LOG REQUEST
if (isset($_POST['add_missing_request'])) {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['date'];
    $missing_field = $_POST['missing_field'];
    $requested_time = $_POST['requested_time'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Convert time to datetime format for the specific date
    $requested_datetime = $date . ' ' . $requested_time . ':00';

    $insert = mysqli_query($conn, "INSERT INTO missingtimelogrequests 
        (employee_id, date, missing_field, requested_time, reason) 
        VALUES ($employee_id, '$date', '$missing_field', '$requested_datetime', '$reason')");
    $_SESSION['success'] = $insert ? 'Missing Time Log request submitted successfully!' : 'Failed to submit request.';
    header("Location: missingtimelogrequests.php");
    exit();
}

// UPDATE STATUS
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $approved_by = $_SESSION['user_id'];
    $approval_remarks = mysqli_real_escape_string($conn, $_POST['approval_remarks']);
    $approved_at = ($status != 'Pending') ? 'NOW()' : 'NULL';

    // Get request details for updating employee DTR
    $request_query = mysqli_query($conn, "SELECT * FROM missingtimelogrequests WHERE request_id = $request_id");
    $request_data = mysqli_fetch_assoc($request_query);

    $update = mysqli_query($conn, "UPDATE missingtimelogrequests 
        SET status='$status', approved_by=$approved_by, approved_at=$approved_at, approval_remarks='$approval_remarks' 
        WHERE request_id=$request_id");
    
    // If approved, update the employee DTR
    if ($update && $status == 'Approved') {
        $employee_id = $request_data['employee_id'];
        $date = $request_data['date'];
        $missing_field = $request_data['missing_field'];
        $requested_time = $request_data['requested_time'];
        
        // Check if DTR record exists for this employee and date
        $dtr_check = mysqli_query($conn, "SELECT * FROM employeedtr WHERE employee_id = $employee_id AND date = '$date'");
        
        if (mysqli_num_rows($dtr_check) > 0) {
            // Update existing DTR record and set has_missing_log to 0
            if ($missing_field == 'Time In') {
                mysqli_query($conn, "UPDATE employeedtr SET time_in = '$requested_time', has_missing_log = 0 WHERE employee_id = $employee_id AND date = '$date'");
            } elseif ($missing_field == 'Time Out') {
                mysqli_query($conn, "UPDATE employeedtr SET time_out = '$requested_time', has_missing_log = 0 WHERE employee_id = $employee_id AND date = '$date'");
            }
        } else {
            // Create new DTR record with has_missing_log = 0
            if ($missing_field == 'Time In') {
                mysqli_query($conn, "INSERT INTO employeedtr (employee_id, date, time_in, has_missing_log) VALUES ($employee_id, '$date', '$requested_time', 0)");
            } elseif ($missing_field == 'Time Out') {
                mysqli_query($conn, "INSERT INTO employeedtr (employee_id, date, time_out, has_missing_log) VALUES ($employee_id, '$date', '$requested_time', 0)");
            }
        }
        
        $_SESSION['success'] = 'Status updated successfully! DTR record has been updated and missing log flag cleared.';
    } else {
        $_SESSION['success'] = $update ? 'Status updated successfully!' : 'Failed to update status.';
    }
    
    header("Location: missingtimelogrequests.php");
    exit();
}

// DELETE REQUEST
if (isset($_POST['delete_request'])) {
    $request_id = $_POST['delete_id'];
    $check = mysqli_query($conn, "SELECT status FROM missingtimelogrequests WHERE request_id=$request_id");
    $row = mysqli_fetch_assoc($check);

    if ($row['status'] == 'Approved') {
        $_SESSION['error'] = 'Cannot delete approved requests!';
    } else {
        $delete = mysqli_query($conn, "DELETE FROM missingtimelogrequests WHERE request_id=$request_id");
        $_SESSION['success'] = $delete ? 'Request deleted successfully!' : 'Failed to delete request.';
    }
    header("Location: missingtimelogrequests.php");
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
              <h5 class="m-b-10">Missing Time Log Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Time Log Management</li>
              <li class="breadcrumb-item active" aria-current="page">Requests</li>
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
              <h5 class="mb-0">Requests</h5>
              <small class="text-muted">Manage missing time log requests</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Request
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered text-center" id="missingTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Missing Field</th>
                    <th>Requested Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT m.*, 
                      CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                      u.username as approver_name
                      FROM missingtimelogrequests m
                      LEFT JOIN Employees e ON m.employee_id = e.employee_id
                      LEFT JOIN users u ON m.approved_by = u.user_id
                      ORDER BY m.requested_at DESC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark"><?= $row['request_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['employee_name']); ?></td>
                    <td><?= date('M d, Y', strtotime($row['date'])); ?></td>
                    <td><?= htmlspecialchars($row['missing_field']); ?></td>
                    <td>
                      <?php 
                      if ($row['requested_time']) {
                          echo date('h:i A', strtotime($row['requested_time']));
                      } else {
                          echo 'N/A';
                      }
                      ?>
                    </td>
                    <td><?= htmlspecialchars($row['reason']); ?></td>
                    <td>
                      <?php if ($row['status'] == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($row['status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])); ?></td>
                    <td>
                      <div class="btn-group gap-1">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['request_id']; ?>"
                          data-employee="<?= htmlspecialchars($row['employee_name']); ?>"
                          data-date="<?= $row['date']; ?>"
                          data-missing="<?= htmlspecialchars($row['missing_field']); ?>"
                          data-requested-time="<?= $row['requested_time'] ? date('h:i A', strtotime($row['requested_time'])) : 'N/A'; ?>"
                          data-reason="<?= htmlspecialchars($row['reason']); ?>"
                          data-status="<?= $row['status']; ?>"
                          data-approver="<?= htmlspecialchars($row['approver_name']); ?>"
                          data-remarks="<?= htmlspecialchars($row['approval_remarks']); ?>"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning statusBtn"
                          data-id="<?= $row['request_id']; ?>"
                          data-status="<?= $row['status']; ?>"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>"
                          data-id="<?= $row['request_id']; ?>"
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
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Missing Time Log Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Select Employee</option>
            <?php
            $employees = mysqli_query($conn, "SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM Employees WHERE status='Active'");
            while ($emp = mysqli_fetch_assoc($employees)): ?>
            <option value="<?= $emp['employee_id']; ?>"><?= htmlspecialchars($emp['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Missing Field</label>
          <select name="missing_field" class="form-select" required>
            <option value="">Select Missing Field</option>
            <option value="Time In">Time In</option>
            <option value="Time Out">Time Out</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Requested Time</label>
          <input type="time" name="requested_time" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="3" required placeholder="Please provide a detailed reason for the missing time log..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_missing_request" class="btn btn-primary"><i class="ti ti-check me-1"></i>Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header ">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>Missing Time Log Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">Employee:</label>
          <p id="view_employee" class="mb-0"></p>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Date:</label>
          <p id="view_date" class="mb-0"></p>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Missing Field:</label>
          <p id="view_missing" class="mb-0"></p>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Requested Time:</label>
          <p id="view_requested_time" class="mb-0"></p>
        </div>
        <div class="col-12">
          <label class="form-label fw-bold">Reason:</label>
          <p id="view_reason" class="mb-0"></p>
        </div>
        <div class="col-12">
          <label class="form-label fw-bold">Status:</label>
          <p id="view_status" class="mb-0"></p>
        </div>
        <div id="approval_section" class="col-12" style="display:none;">
          <hr>
          <label class="form-label fw-bold">Approved By:</label>
          <p id="view_approver" class="mb-2"></p>
          <label class="form-label fw-bold">Approval Remarks:</label>
          <p id="view_remarks" class="mb-0"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Update Request Status</h5>
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
          <small class="text-muted">Note: Approving this request will automatically update the employee's DTR record.</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Approval Remarks</label>
          <textarea name="approval_remarks" class="form-control" rows="3" placeholder="Enter remarks (optional)"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_status" class="btn btn-warning"><i class="ti ti-check me-1"></i>Update Status</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_request" value="1">
  <input type="hidden" name="delete_id" id="delete_id">
</form>

<script>
$(document).ready(function () {
  $('#missingTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search missing time log requests...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching requests found',
      emptyTable: 'No requests available',
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
    order: [[0, 'desc']]
  });

  // Add search icon inside search box
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  // View Button
  $('.viewBtn').click(function () {
    $('#view_employee').text($(this).data('employee'));
    $('#view_date').text(new Date($(this).data('date')).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: '2-digit' 
    }));
    $('#view_missing').text($(this).data('missing'));
    $('#view_requested_time').text($(this).data('requested-time'));
    $('#view_reason').text($(this).data('reason'));
    
    const status = $(this).data('status');
    let badge = '';
    if (status == 'Pending') badge = '<span class="badge bg-warning">Pending</span>';
    else if (status == 'Approved') badge = '<span class="badge bg-success">Approved</span>';
    else badge = '<span class="badge bg-danger">Rejected</span>';
    $('#view_status').html(badge);

    if (status !== 'Pending') {
      $('#view_approver').text($(this).data('approver') || 'N/A');
      $('#view_remarks').text($(this).data('remarks') || 'No remarks');
      $('#approval_section').show();
    } else {
      $('#approval_section').hide();
    }
    new bootstrap.Modal(document.getElementById('viewModal')).show();
  });

  // Status Button
  $('.statusBtn').click(function () {
    $('#status_request_id').val($(this).data('id'));
    $('#status_select').val($(this).data('status'));
    new bootstrap.Modal(document.getElementById('statusModal')).show();
  });

  // Delete Button
  $('.deleteBtn').click(function () {
    const id = $(this).data('id');
    if ($(this).hasClass('disabled')) {
      Swal.fire('Not Allowed', 'Approved requests cannot be deleted!', 'error');
      return;
    }
    Swal.fire({
      title: 'Are you sure?',
      text: 'This will permanently delete the request.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
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

  // Success/Error Messages
  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({ 
    icon: 'success', 
    title: 'Success', 
    text: '<?= $_SESSION['success']; ?>', 
    timer: 4000, 
    toast: true, 
    position: 'top-end', 
    showConfirmButton: false 
  });
  <?php unset($_SESSION['success']); endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
  Swal.fire({ 
    icon: 'error', 
    title: 'Error', 
    text: '<?= $_SESSION['error']; ?>', 
    timer: 4000, 
    toast: true, 
    position: 'top-end', 
    showConfirmButton: false 
  });
  <?php unset($_SESSION['error']); endif; ?>
});
</script>