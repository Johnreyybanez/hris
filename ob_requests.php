<?php
session_start();
include 'connection.php';

// ADD OFFICIAL BUSINESS REQUEST
if (isset($_POST['add_ob_request'])) {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['date'];
    $time_from = $_POST['time_from'];
    $time_to = $_POST['time_to'];
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);

    $insert = mysqli_query($conn, "INSERT INTO employeeofficialbusiness 
        (employee_id, date, time_from, time_to, purpose, location, requested_at, status) 
        VALUES ($employee_id, '$date', '$time_from', '$time_to', '$purpose', '$location', NOW(), 'Pending')");
    $_SESSION['success'] = $insert ? 'OB request submitted successfully!' : 'Failed to submit OB request.';
    header("Location: ob_requests.php");
    exit();
}

// Function to calculate time difference in hours
function calculateTimeDifference($start_time, $end_time) {
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $diff = $start->diff($end);
    return $diff->h + ($diff->i / 60); // Convert to decimal hours
}

// Function to convert decimal hours to TIME format
function decimalToTime($decimal_hours) {
    $hours = floor($decimal_hours);
    $minutes = round(($decimal_hours - $hours) * 60);
    return sprintf('%02d:%02d:00', $hours, $minutes);
}

// Function to update DTR when OB is approved
function updateDTRForApprovedOB($conn, $employee_id, $ob_date, $ob_time_from, $ob_time_to) {
    // Check if DTR record exists for this date
    $dtr_check = mysqli_query($conn, "SELECT * FROM employeedtr WHERE employee_id = $employee_id AND date = '$ob_date'");
    
    if (mysqli_num_rows($dtr_check) > 0) {
        $dtr_record = mysqli_fetch_assoc($dtr_check);
        
        // Convert OB times to datetime format for the specific date
        $ob_datetime_from = $ob_date . ' ' . $ob_time_from;
        $ob_datetime_to = $ob_date . ' ' . $ob_time_to;
        
        // Get current time_in and time_out
        $current_time_in = $dtr_record['time_in'];
        $current_time_out = $dtr_record['time_out'];
        
        // Determine new time_in and time_out based on OB times
        $new_time_in = $current_time_in;
        $new_time_out = $current_time_out;
        
        // If OB starts before current time_in or time_in is null, update time_in
        if (empty($current_time_in) || $ob_datetime_from < $current_time_in) {
            $new_time_in = $ob_datetime_from;
        }
        
        // If OB ends after current time_out or time_out is null, update time_out
        if (empty($current_time_out) || $ob_datetime_to > $current_time_out) {
            $new_time_out = $ob_datetime_to;
        }
        
        // Calculate new total work time
        $total_work_hours = 0;
        if (!empty($new_time_in) && !empty($new_time_out)) {
            $total_work_hours = calculateTimeDifference($new_time_in, $new_time_out);
            
            // Subtract break time if exists
            if (!empty($dtr_record['break_out']) && !empty($dtr_record['break_in'])) {
                $break_hours = calculateTimeDifference($dtr_record['break_out'], $dtr_record['break_in']);
                $total_work_hours -= $break_hours;
            }
        }
        
        $new_total_work_time = decimalToTime($total_work_hours);
        
        // Update the DTR record
        $update_dtr = mysqli_query($conn, "UPDATE employeedtr SET 
            time_in = " . ($new_time_in ? "'$new_time_in'" : "NULL") . ",
            time_out = " . ($new_time_out ? "'$new_time_out'" : "NULL") . ",
            total_work_time = '$new_total_work_time',
            has_missing_log = 0,
            remarks = CONCAT(IFNULL(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '; '), 'Updated for approved OB'),
            updated_at = NOW()
            WHERE dtr_id = " . $dtr_record['dtr_id']);
            
        return $update_dtr;
    } else {
        // Create new DTR record if none exists
        $ob_datetime_from = $ob_date . ' ' . $ob_time_from;
        $ob_datetime_to = $ob_date . ' ' . $ob_time_to;
        
        // Calculate total work time for OB
        $total_work_hours = calculateTimeDifference($ob_datetime_from, $ob_datetime_to);
        $total_work_time = decimalToTime($total_work_hours);
        
        // Get day of week
        $day_of_week = date('l', strtotime($ob_date));
        
        // Get default shift_id (you may need to adjust this based on your business logic)
        $shift_query = mysqli_query($conn, "SELECT shift_id FROM employees WHERE employee_id = $employee_id");
        $shift_data = mysqli_fetch_assoc($shift_query);
        $shift_id = $shift_data['shift_id'] ?? 1; // Default to shift_id 1 if not found
        
        $insert_dtr = mysqli_query($conn, "INSERT INTO employeedtr 
            (employee_id, date, day_of_week, shift_id, time_in, time_out, 
             total_work_time, has_missing_log, remarks, created_at) 
            VALUES 
            ($employee_id, '$ob_date', '$day_of_week', $shift_id, '$ob_datetime_from', '$ob_datetime_to', 
             '$total_work_time', 0, 'Created for approved OB', NOW())");
             
        return $insert_dtr;
    }
}

// UPDATE OB REQUEST STATUS
if (isset($_POST['update_status'])) {
    $ob_id = $_POST['ob_id'];
    $status = $_POST['status'];
    $approved_by = $_SESSION['user_id']; // Auto set to current user
    $approval_remarks = mysqli_real_escape_string($conn, $_POST['approval_remarks']);
    $approved_at = ($status != 'Pending') ? 'NOW()' : 'NULL';

    // Get OB request details before updating
    $ob_details = mysqli_query($conn, "SELECT * FROM employeeofficialbusiness WHERE ob_id = $ob_id");
    $ob_data = mysqli_fetch_assoc($ob_details);
    
    $update = mysqli_query($conn, "UPDATE employeeofficialbusiness
        SET status='$status', approved_by=$approved_by, approved_at=$approved_at, approval_remarks='$approval_remarks'
        WHERE ob_id=$ob_id");
    
    if ($update) {
        // If status is approved, update the corresponding DTR record
        if ($status == 'Approved' && $ob_data) {
            $dtr_update = updateDTRForApprovedOB(
                $conn, 
                $ob_data['employee_id'], 
                $ob_data['date'], 
                $ob_data['time_from'], 
                $ob_data['time_to']
            );
            
            if ($dtr_update) {
                $_SESSION['success'] = 'OB request approved and DTR updated successfully!';
            } else {
                $_SESSION['success'] = 'OB request approved, but DTR update failed.';
            }
        } else {
            $_SESSION['success'] = 'OB request status updated successfully!';
        }
    } else {
        $_SESSION['error'] = 'Failed to update OB request.';
    }
    
    header("Location: ob_requests.php");
    exit();
}

// DELETE OB REQUEST
if (isset($_POST['delete_ob_request'])) {
    $id = $_POST['delete_id'];
    $check_status = mysqli_query($conn, "SELECT status FROM employeeofficialbusiness WHERE ob_id=$id");
    $status_row = mysqli_fetch_assoc($check_status);

    if ($status_row['status'] == 'Approved') {
        $_SESSION['error'] = 'Cannot delete approved OB requests!';
    } else {
        $delete = mysqli_query($conn, "DELETE FROM employeeofficialbusiness WHERE ob_id=$id");
        $_SESSION['success'] = $delete ? 'OB request deleted successfully!' : 'Failed to delete OB request.';
    }
    header("Location: ob_requests.php");
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
              <h5 class="m-b-10">Official Business Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">OB Management</li>
              <li class="breadcrumb-item active">OB Requests</li>
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
              <h5 class="mb-0">OB Requests</h5>
              <small class="text-muted">Manage official business requests</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add OB Request
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="obTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>DTR Updated</th>
                    <th>Requested At</th>
                    <th style="width: 160px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $result = mysqli_query($conn, "SELECT ob.*, 
                      CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                      u.username AS approver_name,
                      CASE 
                        WHEN ob.status = 'Approved' AND dtr.dtr_id IS NOT NULL THEN 'Yes'
                        WHEN ob.status = 'Approved' AND dtr.dtr_id IS NULL THEN 'No'
                        ELSE 'N/A'
                      END AS dtr_updated
                      FROM employeeofficialbusiness ob
                      LEFT JOIN Employees e ON ob.employee_id = e.employee_id
                      LEFT JOIN users u ON ob.approved_by = u.user_id
                      LEFT JOIN employeedtr dtr ON ob.employee_id = dtr.employee_id AND ob.date = dtr.date
                      ORDER BY ob.requested_at DESC");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                   <td><span class="badge bg-dark -secondary"><?= $row['ob_id']; ?></span></td>
                    <td><?= htmlspecialchars($row['employee_name']); ?></td>
                    <td><?= date('M d, Y', strtotime($row['date'])); ?></td>
                    <td><?= date('h:i A', strtotime($row['time_from'])) . ' - ' . date('h:i A', strtotime($row['time_to'])); ?></td>
                    <td><?= htmlspecialchars($row['purpose']); ?></td>
                    <td>
                      <?php if ($row['status'] == 'Pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($row['status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($row['dtr_updated'] == 'Yes'): ?>
                        <span class="badge bg-success">Yes</span>
                      <?php elseif ($row['dtr_updated'] == 'No'): ?>
                        <span class="badge bg-warning">No</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">N/A</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])); ?></td>
                    <td>
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['ob_id']; ?>"
                          data-employee="<?= htmlspecialchars($row['employee_name']); ?>"
                          data-date="<?= $row['date']; ?>"
                          data-time="<?= date('h:i A', strtotime($row['time_from'])) . ' - ' . date('h:i A', strtotime($row['time_to'])); ?>"
                          data-purpose="<?= htmlspecialchars($row['purpose']); ?>"
                          data-location="<?= htmlspecialchars($row['location']); ?>"
                          data-status="<?= $row['status']; ?>"
                          data-approver="<?= htmlspecialchars($row['approver_name']); ?>"
                          data-remarks="<?= htmlspecialchars($row['approval_remarks']); ?>"
                          title="View Details"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning statusBtn"
                          data-id="<?= $row['ob_id']; ?>"
                          data-status="<?= $row['status']; ?>"
                          title="Update Status"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn <?= ($row['status'] == 'Approved') ? 'disabled' : ''; ?>"
                          data-id="<?= $row['ob_id']; ?>"
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
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add OB Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" class="form-select select2-employee" required>
            <option value="">Select Employee</option>
            <?php
            $employees = mysqli_query($conn, "SELECT employee_id, CONCAT(first_name, ' ', last_name) AS name FROM Employees WHERE status='Active' ORDER BY first_name ASC, last_name ASC");
            while ($emp = mysqli_fetch_assoc($employees)): ?>
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
              <label class="form-label">Time From</label>
              <input type="time" name="time_from" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Time To</label>
              <input type="time" name="time_to" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Purpose</label>
          <textarea name="purpose" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Location</label>
          <textarea name="location" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_ob_request" class="btn btn-primary"><i class="ti ti-check me-1"></i>Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- View OB Request Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>OB Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <!-- Left Column -->
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Employee:</label>
              <p id="view_employee" class="mb-0"></p>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Date:</label>
              <p id="view_date" class="mb-0"></p>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Time:</label>
              <p id="view_time" class="mb-0"></p>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Purpose:</label>
              <p id="view_purpose" class="mb-0"></p>
            </div>
          </div>

          <!-- Right Column -->
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-bold">Location:</label>
              <p id="view_location" class="mb-0"></p>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Status:</label>
              <p id="view_status" class="mb-0"></p>
            </div>
            <div id="approval_section" style="display:none;">
              <div class="mb-3">
                <label class="form-label fw-bold">Approved By:</label>
                <p id="view_approver" class="mb-0"></p>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold">Approval Remarks:</label>
                <p id="view_approval_remarks" class="mb-0"></p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Update OB Request Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="ob_id" id="status_ob_id">
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
        <div class="alert alert-info">
          <i class="ti ti-info-circle me-2"></i>
          <small><strong>Note:</strong> Approving this request will automatically update the employee's DTR record for the specified date and time.</small>
        </div>
        <input type="hidden" name="approved_by" value="<?= $_SESSION['user_id']; ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_status" class="btn btn-warning"><i class="ti ti-check me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden form for delete -->
<form id="deleteForm" method="POST" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_ob_request" value="1">
</form>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {
  // Initialize Select2 for employee dropdown with search
  $('.select2-employee').select2({
    dropdownParent: $('#addModal'),
    placeholder: 'Select Employee',
    allowClear: true,
    width: '100%',
    theme: 'bootstrap-5'
  });

  // Reinitialize Select2 when modal is shown
  $('#addModal').on('shown.bs.modal', function () {
    $('.select2-employee').select2({
      dropdownParent: $('#addModal'),
      placeholder: 'Select Employee',
      allowClear: true,
      width: '100%',
      theme: 'bootstrap-5'
    });
  });

  // Clear Select2 when modal is hidden
  $('#addModal').on('hidden.bs.modal', function () {
    $('.select2-employee').val(null).trigger('change');
  });

  // Initialize DataTable
  const table = $('#obTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search OB requests...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching OB requests found',
      emptyTable: 'No OB requests available',
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
  
  // Bind all action buttons
  function bindActionButtons() {
    // View Button
    $('.viewBtn').off().on('click', function () {
      $('#view_employee').text($(this).data('employee'));
      $('#view_date').text(new Date($(this).data('date')).toLocaleDateString());
      $('#view_time').text($(this).data('time'));
      $('#view_purpose').text($(this).data('purpose'));
      $('#view_location').text($(this).data('location'));

      const status = $(this).data('status');
      let badge = '';
      if (status === 'Pending') {
        badge = '<span class="badge bg-warning">Pending</span>';
      } else if (status === 'Approved') {
        badge = '<span class="badge bg-success">Approved</span>';
      } else {
        badge = '<span class="badge bg-danger">Rejected</span>';
      }
      $('#view_status').html(badge);

      const approver = $(this).data('approver') || 'N/A';
      const remarks = $(this).data('remarks') || 'No remarks';

      if (status !== 'Pending') {
        $('#view_approver').text(approver);
        $('#view_approval_remarks').text(remarks);
        $('#approval_section').show();
      } else {
        $('#approval_section').hide();
      }

      new bootstrap.Modal(document.getElementById('viewModal')).show();
    });

    // Status Button
    $('.statusBtn').off().on('click', function () {
      $('#status_ob_id').val($(this).data('id'));
      $('#status_select').val($(this).data('status'));
      $('#approval_remarks').val('');
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    });

    // Delete Button
    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      const isDisabled = $(this).hasClass('disabled');

      if (isDisabled) {
        Swal.fire({
          icon: 'error',
          title: 'Cannot Delete',
          text: 'Approved OB requests cannot be deleted!',
          confirmButtonText: 'OK'
        });
        return;
      }

      Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the OB request.',
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

  // Add search icon inside search box
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }

  // SweetAlert for Success
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

  // SweetAlert for Error
  <?php if (isset($_SESSION['error'])): ?>
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?= $_SESSION['error']; ?>',
    timer: 3000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end'
  });
  <?php unset($_SESSION['error']); endif; ?>
});
</script>
