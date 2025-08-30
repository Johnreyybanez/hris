<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'connection.php';

// ✅ Check if manager is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

$manager_id = $_SESSION['user_id'];

// Get manager's department
$dept_query = mysqli_query($conn, "
    SELECT department_id, department 
    FROM employees 
    WHERE employee_id = $manager_id
") or die(mysqli_error($conn));
$manager_dept = mysqli_fetch_assoc($dept_query);
$department_id = $manager_dept['department_id'];

// Function to insert notification
function insertNotification($conn, $employee_id, $message, $link = null) {
    $current_time = date('Y-m-d H:i:s');
    $message = mysqli_real_escape_string($conn, $message);
    $link = $link ? "'" . mysqli_real_escape_string($conn, $link) . "'" : 'NULL';
    
    $query = "INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at) 
              VALUES ($employee_id, '$message', $link, 0, '$current_time')";
    
    return mysqli_query($conn, $query);
}

// Function to get manager details for notifications
function getManagerDetails($conn, $manager_id) {
    $query = mysqli_query($conn, "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, department
        FROM employees 
        WHERE employee_id = $manager_id
    ");
    
    if (!$query) {
        error_log("Error in getManagerDetails: " . mysqli_error($conn));
        return ['full_name' => 'Unknown Manager', 'department' => 'Unknown Department'];
    }
    
    $result = mysqli_fetch_assoc($query);
    return $result ?: ['full_name' => 'Unknown Manager', 'department' => 'Unknown Department'];
}

// Function to get department heads and admins for notifications
function getNotificationRecipients($conn, $department_id, $exclude_employee_id = null) {
    $recipients = [];
    
    // Ensure department_id is properly escaped
    $department_id = intval($department_id);
    $exclude_id = $exclude_employee_id ? intval($exclude_employee_id) : 0;
    
    // Get department heads and admins
    $query = "
        SELECT DISTINCT employee_id 
        FROM employees 
        WHERE (role IN ('admin', 'hr') OR (department_id = $department_id AND role = 'manager')) 
        AND employee_id != $exclude_id
        AND status = 'Active'
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("Error in getNotificationRecipients: " . mysqli_error($conn));
        error_log("Query was: " . $query);
        return $recipients; // Return empty array on error
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        $recipients[] = $row['employee_id'];
    }
    
    return $recipients;
}

// Function to send overtime request notifications
function sendOvertimeRequestNotifications($conn, $action, $overtime_id, $manager_id, $department_id, $date = '', $start_time = '', $end_time = '', $total_hours = '', $reason = '') {
    $manager_details = getManagerDetails($conn, $manager_id);
    $manager_name = $manager_details['full_name'];
    $department_name = $manager_details['department'];
    
    // Format date and time for display
    $formatted_date = $date ? date('M d, Y', strtotime($date)) : '';
    $formatted_start_time = $start_time ? date('h:i A', strtotime($start_time)) : '';
    $formatted_end_time = $end_time ? date('h:i A', strtotime($end_time)) : '';
    
    $date_info = $formatted_date ? " on $formatted_date" : '';
    $time_range = '';
    if ($formatted_start_time && $formatted_end_time) {
        $time_range = " from $formatted_start_time to $formatted_end_time";
    }
    $hours_info = $total_hours ? " ($total_hours hours)" : '';
    $reason_info = $reason ? " - $reason" : '';
    
    switch ($action) {
        case 'add':
            // Notify the manager (self)
            $manager_message = "Your overtime request (#$overtime_id) has been submitted and is Pending approval";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Overtime request (#$overtime_id) for $manager_name from department $department_name$date_info$time_range$hours_info$reason_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'update':
            // Notify the manager (self)
            $manager_message = "Your overtime request (#$overtime_id) has been updated";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Overtime request (#$overtime_id) for $manager_name from department $department_name has been updated$date_info$time_range$hours_info$reason_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'delete':
            // Notify the manager (self)
            $manager_message = "Your overtime request (#$overtime_id) has been deleted";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Overtime request (#$overtime_id) for $manager_name from department $department_name has been deleted";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    
    // Handle delete action
    if ($action === 'delete' && isset($_POST['overtime_id'])) {
        $overtime_id = intval($_POST['overtime_id']);
        
        // Get overtime request details before deletion for notifications
        $overtime_details_query = mysqli_query($conn, "
            SELECT * FROM overtime 
            WHERE overtime_id = $overtime_id AND employee_id = $manager_id
        ");
        
        if ($overtime_details_query && mysqli_num_rows($overtime_details_query) > 0) {
            $overtime_details = mysqli_fetch_assoc($overtime_details_query);
            
            $del_query = "DELETE FROM overtime WHERE overtime_id = $overtime_id AND employee_id = $manager_id";
            $success = mysqli_query($conn, $del_query);
            
            if ($success) {
                // Send delete notifications
                sendOvertimeRequestNotifications(
                    $conn, 
                    'delete', 
                    $overtime_id, 
                    $manager_id, 
                    $department_id,
                    $overtime_details['date'],
                    $overtime_details['start_time'],
                    $overtime_details['end_time'],
                    $overtime_details['total_hours'],
                    $overtime_details['reason']
                );
            }
            
            // If AJAX request, return JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => $success ? 'success' : 'error',
                    'message' => $success ? 'Overtime request deleted successfully' : 'Failed to delete overtime request'
                ]);
                exit;
            }
        } else {
            // If AJAX request, return error
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Overtime request not found or access denied'
                ]);
                exit;
            }
        }
        
        header("Location: manager_overtime.php");
        exit;
    }
    
    // Handle add/edit actions
    if (isset($_POST['save_overtime'])) {
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
        $total_hours = floatval($_POST['total_hours']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $overtime_id = isset($_POST['overtime_id']) ? intval($_POST['overtime_id']) : null;

        if ($action === 'edit' && $overtime_id) {
            // Update existing overtime request
            $update_query = "
                UPDATE overtime 
                SET date = '$date', 
                    start_time = '$start_time', 
                    end_time = '$end_time', 
                    total_hours = $total_hours, 
                    reason = '$reason',
                    updated_at = NOW()
                WHERE overtime_id = $overtime_id AND employee_id = $manager_id
            ";
            $success = mysqli_query($conn, $update_query);
            
            if ($success) {
                // Send update notifications
                sendOvertimeRequestNotifications(
                    $conn, 
                    'update', 
                    $overtime_id, 
                    $manager_id, 
                    $department_id,
                    $date,
                    $start_time,
                    $end_time,
                    $total_hours,
                    $reason
                );
            }
        } else {
            // Add new overtime request
            $insert_query = "
                INSERT INTO overtime 
                (employee_id, date, start_time, end_time, total_hours, reason, approval_status, created_at, updated_at) 
                VALUES ($manager_id, '$date', '$start_time', '$end_time', $total_hours, '$reason', 'Pending', NOW(), NOW())
            ";
            $success = mysqli_query($conn, $insert_query);
            
            if ($success) {
                $new_overtime_id = mysqli_insert_id($conn);
                
                // Send add notifications
                sendOvertimeRequestNotifications(
                    $conn, 
                    'add', 
                    $new_overtime_id, 
                    $manager_id, 
                    $department_id,
                    $date,
                    $start_time,
                    $end_time,
                    $total_hours,
                    $reason
                );
            }
        }
        
        header("Location: manager_overtime.php");
        exit;
    }
}

// Get overtime requests for the manager
$overtime_q = mysqli_query($conn, "
    SELECT o.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM overtime o
    LEFT JOIN employees e ON o.employee_id = e.employee_id
    WHERE o.employee_id = $manager_id
    ORDER BY o.created_at DESC
") or die(mysqli_error($conn));

// Get manager details
$manager_query = mysqli_query($conn, "
    SELECT employee_id, first_name, last_name 
    FROM employees 
    WHERE employee_id = $manager_id
") or die(mysqli_error($conn));

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Manager Overtime Request</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Manager Overtime Request</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Overtime Request Section -->
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">OVERTIME REQUEST</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal" id="addOvertimeBtn">
          <i class="bi bi-plus-circle"></i> Add Overtime
        </button>
      </div>
      <div class="table-responsive">
        <table id="leaveTable" class="table table-striped table-bordered nowrap" style="width:100%">
          <thead class="table-dark">
            <tr>
              <th>Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Total Hours</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($overtime_q)): ?>
              <tr>
                <td><?= date('Y-m-d', strtotime($row['date'])) ?></td>
                <td><?= date('h:i A', strtotime($row['start_time'])) ?></td>
                <td><?= date('h:i A', strtotime($row['end_time'])) ?></td>
                <td><?= number_format($row['total_hours'], 1) ?></td>
                <td>
                  <span class="badge bg-<?= $row['approval_status'] === 'Pending' ? 'warning' : 
                                         ($row['approval_status'] === 'Approved' ? 'success' : 'danger') ?>">
                    <?= $row['approval_status'] ?>
                  </span>
                </td>
                <td><?= date('Y-m-d h:i A', strtotime($row['created_at'])) ?></td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                      <button class="btn btn-sm btn-outline-primary btn-edit-overtime" 
                              data-overtime='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                              data-toggle="tooltip"
                              title="Edit Overtime Request">
                          <i class="bi bi-pencil-square"></i>
                      </button>
                     <?php
      $disabled = ($row['approval_status'] !== 'Pending') ? 'disabled' : '';
      ?>
      <button class="btn btn-sm btn-outline-danger btn-delete-overtime"
              data-overtime-id="<?= $row['overtime_id'] ?>"
              data-toggle="tooltip"
              title="<?= $row['approval_status'] === 'Pending' ? 'Delete Overtime Request' : 'Cannot delete: Request already processed' ?>"
              <?= $disabled ?>>
          <i class="bi bi-trash"></i>
      </button>

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

      
<!-- Overtime Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="overtimeForm">
        <input type="hidden" name="overtime_id" id="overtime_id">
        <input type="hidden" name="action" value="add" id="form_action">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add Overtime Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Add Manager Info -->
          <div class="mb-3">
            <label class="form-label">Employee</label>
            <input type="text" class="form-control" value="<?php 
              $manager = mysqli_fetch_assoc($manager_query);
              echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']);
            ?>" readonly>
            <input type="hidden" name="employee_id" value="<?= $manager_id ?>">
          </div>

          <div class="mb-3">
              <label for="date" class="form-label">Date:</label>
              <input type="date" id="date" name="date" class="form-control" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Start Time</label>
            <div class="input-group">
              <input type="time" name="start_time" id="modal_start_time" class="form-control" 
                     required onchange="formatTimeDisplay(this, 'start_time_display')">
              <span class="input-group-text">
                <span id="start_time_display" class="text-muted small">12-hour format</span>
              </span>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">End Time</label>
            <div class="input-group">
              <input type="time" name="end_time" id="modal_end_time" class="form-control" 
                     required onchange="formatTimeDisplay(this, 'end_time_display')">
              <span class="input-group-text">
                <span id="end_time_display" class="text-muted small">12-hour format</span>
              </span>
            </div>
          </div>

          <div class="mb-3">
              <label for="total_hours" class="form-label">Total Overtime Hours:</label>
              <div class="input-group">
                  <input type="number" id="total_hours" name="total_hours" class="form-control" step="0.25" min="0" readonly 
                         placeholder="Will calculate automatically...">
                  <span class="input-group-text">hours</span>
              </div>
              <small class="form-text text-muted">
                <i class="bi bi-info-circle"></i> Automatically calculated based on hours worked beyond 8-hour regular workday (rounded to nearest 15 minutes)
              </small>
              <div id="calculation-breakdown" class="mt-2" style="display: none;">
                  <div class="alert alert-info py-2 px-3 mb-0">
                      <small>
                          <strong>Calculation:</strong> <span id="breakdown-text"></span>
                      </small>
                  </div>
              </div>
          </div>

          <div class="mb-3">
              <label for="reason" class="form-label">Reason:</label>
              <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="save_overtime">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DataTables + Script -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
  .dataTables_wrapper .dataTables_filter {
    float: right;
  }
  .table-responsive {
    overflow-x: auto;
  }
  .input-group-text {
  min-width: 120px;
  justify-content: center;
}
.time-display {
  font-weight: bold;
  color: #0d6efd;
}
</style>

<script>
$(document).ready(function () {
    // Initialize DataTable
    const table = $('#leaveTable').DataTable({
        lengthMenu: [5, 10, 25, 50, 100],
        pageLength: 10,
        order: [[5, 'desc']], // Order by created_at (index 5)
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search overtime requests...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            zeroRecords: "No matching overtime requests found",
        },
        columnDefs: [
            { targets: [6], orderable: false }, // Disable sorting for Action column (index 6)
            { targets: [4], width: '100px' }, // Set width for Status column
            { targets: [6], width: '120px' }  // Set width for Action column
        ]
    });

    // Reset modal for Add
    $('#addOvertimeBtn').on('click', function() {
        resetModal();
        $('#modalTitle').text('Add Overtime Request');
        $('#form_action').val('add');
    });

    // Populate modal for Edit
    $(document).on('click', '.btn-edit-overtime', function() {
        const data = JSON.parse($(this).attr('data-overtime'));
        
        // Show modal first
        const leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
        leaveModal.show();
        
        // Update modal title and form action
        $('#modalTitle').text('Edit Overtime Request');
        $('#form_action').val('edit');
        
        // Populate form fields
        $('#overtime_id').val(data.overtime_id);
        $('#date').val(data.date);
        $('#modal_start_time').val(data.start_time);
        $('#modal_end_time').val(data.end_time);
        $('#total_hours').val(data.total_hours);
        $('#reason').val(data.reason || '');
        
        // Update time displays
        if (data.start_time) {
            formatTimeDisplay($('#modal_start_time')[0], 'start_time_display');
        }
        if (data.end_time) {
            formatTimeDisplay($('#modal_end_time')[0], 'end_time_display');
        }

        // Trigger calculation for existing data
        setTimeout(() => {
            calculateOvertimeHours();
        }, 100);
    });

    // Reset modal function
    function resetModal() {
        const form = document.querySelector('#overtimeForm');
        form.reset();
        
        $('#overtime_id').val('');
        $('#form_action').val('add');
        
        // Clear any validation messages or styles
        $('.is-invalid').removeClass('is-invalid');
        $('.is-valid').removeClass('is-valid');
        $('.invalid-feedback').remove();
        $('#calculation-breakdown').hide();
        
        // Reset time displays
        $('#start_time_display').text('12-hour format').removeClass('time-display');
        $('#end_time_display').text('12-hour format').removeClass('time-display');
        
        // Reset total hours placeholder
        $('#total_hours').attr('placeholder', 'Will calculate automatically...');
        
        // Reset any custom select plugins if present
        if ($.fn.select2) {
            $('#leave_type_id').select2('destroy').select2();
        }
    }

    // Delete overtime request with SweetAlert
    $(document).on('click', '.btn-delete-overtime', function () {
        const overtimeId = $(this).data('overtime-id');
        const row = $(this).closest('tr');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `This will permanently delete overtime request ID: ${overtimeId}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait while we delete the overtime request.',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // AJAX delete request
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { 
                        overtime_id: overtimeId, 
                        action: 'delete'
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Remove row from DataTable
                            table.row(row).remove().draw();
                            
                            Swal.fire({
                                title: 'Deleted!',
                                text: response.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while deleting the overtime request. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });

    // Calculate overtime hours when time fields change
    function calculateOvertimeHours() {
        const date = $('#date').val();
        const startTime = $('#modal_start_time').val();
        const endTime = $('#modal_end_time').val();

        // Hide calculation breakdown initially
        $('#calculation-breakdown').hide();

        // Show placeholder and clear value if any field is empty
        if (!date || !startTime || !endTime) {
            $('#total_hours').val('').attr('placeholder', 'Enter date and times first...');
            return;
        }

        // Update placeholder to show calculating
        $('#total_hours').attr('placeholder', 'Calculating...');

        try {
            // Create date objects for calculation
            let startDateTime = new Date(`${date}T${startTime}`);
            let endDateTime = new Date(`${date}T${endTime}`);

            // Handle overnight shifts (end time is next day)
            if (endDateTime <= startDateTime) {
                endDateTime.setDate(endDateTime.getDate() + 1);
            }

            // Calculate total worked hours
            const timeDifferenceMs = endDateTime - startDateTime;
            const totalWorkedHours = timeDifferenceMs / (1000 * 60 * 60); // Convert to hours

            // Constants
            const REGULAR_WORK_HOURS = 8;
            const MAX_REASONABLE_HOURS = 24; // Increased for overnight shifts
            const MIN_OVERTIME_THRESHOLD = 0.25; // Minimum 15 minutes for overtime

            // Validate reasonable working hours
            if (totalWorkedHours > MAX_REASONABLE_HOURS) {
                $('#total_hours').val('0.00').attr('placeholder', 'Invalid time range - exceeds 24 hours');
                
                // Brief toast notification
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                
                Toast.fire({
                    icon: 'warning',
                    title: `${totalWorkedHours.toFixed(1)}h exceeds maximum - please verify times`
                });
                return;
            }

            // Validate minimum working time
            if (totalWorkedHours < 0.25) { // Less than 15 minutes
                $('#total_hours').val('0.00').attr('placeholder', 'Minimum 15 minutes required');
                return;
            }

            // Calculate and display overtime hours
            if (totalWorkedHours > REGULAR_WORK_HOURS) {
                const overtimeHours = totalWorkedHours - REGULAR_WORK_HOURS;
                
                // Round to nearest quarter hour (0.25)
                const roundedOvertimeHours = Math.round(overtimeHours * 4) / 4;
                
                // Only count if meets minimum threshold
                if (roundedOvertimeHours >= MIN_OVERTIME_THRESHOLD) {
                    $('#total_hours').val(roundedOvertimeHours.toFixed(2));
                    
                    // Show calculation breakdown
                    const breakdownText = `${totalWorkedHours.toFixed(1)}h total work - ${REGULAR_WORK_HOURS}h regular = ${roundedOvertimeHours.toFixed(2)}h overtime`;
                    $('#breakdown-text').text(breakdownText);
                    $('#calculation-breakdown').show();
                    
                    // Visual feedback for successful calculation
                    $('#total_hours').removeClass('is-invalid').addClass('is-valid');
                    setTimeout(() => $('#total_hours').removeClass('is-valid'), 2000);
                } else {
                    $('#total_hours').val('0.00');
                    $('#total_hours').attr('placeholder', `${overtimeHours.toFixed(2)}h overtime (rounded to 0 - minimum 15min required)`);
                }
                
            } else if (totalWorkedHours > 0) {
                $('#total_hours').val('0.00');
                
                // Show why no overtime in placeholder
                const hoursNeeded = REGULAR_WORK_HOURS - totalWorkedHours;
                const placeholderText = `${totalWorkedHours.toFixed(1)}h total (need ${hoursNeeded.toFixed(1)}h more for overtime)`;
                $('#total_hours').attr('placeholder', placeholderText);
                
            } else {
                $('#total_hours').val('0.00').attr('placeholder', 'Invalid time range');
                $('#total_hours').addClass('is-invalid');
                setTimeout(() => $('#total_hours').removeClass('is-invalid'), 3000);
            }

        } catch (error) {
            console.error('Error calculating overtime:', error);
            $('#total_hours').val('0.00').attr('placeholder', 'Error calculating - check your inputs');
            $('#total_hours').addClass('is-invalid');
            setTimeout(() => $('#total_hours').removeClass('is-invalid'), 3000);
        }
    }

    // Bind calculation to form field changes with debouncing for better performance
    let calculationTimer;
    $('#date, #modal_start_time, #modal_end_time').on('change input', function() {
        clearTimeout(calculationTimer);
        calculationTimer = setTimeout(calculateOvertimeHours, 300); // 300ms delay
    });
    
    // Immediate calculation on change (not input) for faster response
    $('#date, #modal_start_time, #modal_end_time').on('change', function() {
        clearTimeout(calculationTimer);
        calculateOvertimeHours();
    });
    
    // Also trigger on modal show if editing existing data
    $('#leaveModal').on('shown.bs.modal', function() {
        setTimeout(() => {
            if ($('#date').val() && $('#modal_start_time').val() && $('#modal_end_time').val()) {
                calculateOvertimeHours();
            } else {
                $('#total_hours').attr('placeholder', 'Will calculate automatically...');
            }
        }, 100);
    });

    // Form validation before submission
    $('#overtimeForm').on('submit', function (e) {
        const totalHours = parseFloat($('#total_hours').val());
        
        // Validate that overtime hours are calculated and greater than 0
        if (!totalHours || totalHours <= 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Overtime Hours',
                text: 'Please ensure you have valid start and end times that result in overtime hours (more than 8 hours total work).',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Optional: Show a brief loading message
        Swal.fire({
            title: 'Submitting...',
            text: 'Please wait while we process your request.',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            timer: 1000,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
});

// Add time formatting function
function formatTimeDisplay(input, displayId) {
    if (input.value) {
        const time = new Date(`2000-01-01T${input.value}`);
        const formatted = time.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
        const displayElement = document.getElementById(displayId);
        displayElement.textContent = formatted;
        displayElement.classList.add('time-display');
    } else {
        const displayElement = document.getElementById(displayId);
        displayElement.textContent = '12-hour format';
        displayElement.classList.remove('time-display');
    }
}

// Update modal reset handlers
$('#leaveModal').on('hidden.bs.modal', function () {
    // ...existing code...
    $('#start_time_display').text('12-hour format').removeClass('time-display');
    $('#end_time_display').text('12-hour format').removeClass('time-display');
});

// Update populateOvertimeModal function
function populateOvertimeModal(data) {
    // ...existing code...
    if (data.start_time) {
        $('#modal_start_time').val(data.start_time);
        formatTimeDisplay($('#modal_start_time')[0], 'start_time_display');
    }
    if (data.end_time) {
        $('#modal_end_time').val(data.end_time);
        formatTimeDisplay($('#modal_end_time')[0], 'end_time_display');
    }
    // ...existing code...
}
</script>
