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
// Get manager's department first - Updated query
$dept_query = mysqli_query($conn, "
    SELECT e.department_id, d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id 
    WHERE e.employee_id = $manager_id
") or die(mysqli_error($conn));
$manager_dept = mysqli_fetch_assoc($dept_query);
$department_id = $manager_dept['department_id'];

// Function to format leave days display
function formatLeaveDays($days) {
    $days = floatval($days);
    
    if ($days == 0.5) {
        return "Half day";
    } elseif ($days == 1.0) {
        return "1 day";
    } elseif ($days == floor($days)) {
        // Whole number greater than 1
        return number_format($days, 0) . " days";
    } else {
        // Has decimal part (like 1.5, 2.5, etc.)
        $wholePart = floor($days);
        $decimalPart = $days - $wholePart;
        
        if ($decimalPart == 0.5) {
            if ($wholePart == 1) {
                return "1 and half days";
            } else {
                return number_format($wholePart, 0) . " and half days";
            }
        } else {
            // For other decimals, show as decimal
            return number_format($days, 1) . " days";
        }
    }
}

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
        SELECT CONCAT(e.first_name, ' ', e.last_name) AS full_name, d.name as department
        FROM employees e
        JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = $manager_id
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
    
    // Get department heads and admins - Fixed query
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

// Function to send leave request notifications
function sendLeaveRequestNotifications($conn, $action, $leave_request_id, $manager_id, $department_id, $leave_type = '', $start_date = '', $end_date = '') {
    $manager_details = getManagerDetails($conn, $manager_id);
    $manager_name = $manager_details['full_name'];
    $department_name = $manager_details['department'];
    
    // Format dates for display
    $formatted_start = $start_date ? date('M d, Y', strtotime($start_date)) : '';
    $formatted_end = $end_date ? date('M d, Y', strtotime($end_date)) : '';
    $date_range = ($formatted_start && $formatted_end) ? " from $formatted_start to $formatted_end" : '';
    
    switch ($action) {
        case 'add':
            // Notify the manager (self)
            $manager_message = "Your leave request (#$leave_request_id) has been submitted and is Pending approval";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Leave request (#$leave_request_id) for $manager_name from department $department_name$date_range - $leave_type";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'update':
            // Notify the manager (self)
            $manager_message = "Your leave request (#$leave_request_id) has been updated";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Leave request (#$leave_request_id) for $manager_name from department $department_name has been updated$date_range - $leave_type";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'delete':
            // Notify the manager (self)
            $manager_message = "Your leave request (#$leave_request_id) has been deleted";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Leave request (#$leave_request_id) for $manager_name from department $department_name has been deleted";
            
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
    if ($action === 'delete' && isset($_POST['leave_request_id'])) {
        $leave_request_id = intval($_POST['leave_request_id']);
        
        // Get leave request details before deletion for notifications
        $leave_details_query = mysqli_query($conn, "
            SELECT elr.*, lt.name AS leave_type
            FROM EmployeeLeaveRequests elr
            LEFT JOIN leavetypes lt ON elr.leave_type_id = lt.leave_type_id
            WHERE elr.leave_request_id = $leave_request_id AND elr.employee_id = $manager_id
        ");
        
        if ($leave_details_query && mysqli_num_rows($leave_details_query) > 0) {
            $leave_details = mysqli_fetch_assoc($leave_details_query);
            
            $del_query = "DELETE FROM EmployeeLeaveRequests WHERE leave_request_id = $leave_request_id AND employee_id = $manager_id";
            $success = mysqli_query($conn, $del_query);
            
            if ($success) {
                // Send delete notifications
                sendLeaveRequestNotifications(
                    $conn, 
                    'delete', 
                    $leave_request_id, 
                    $manager_id, 
                    $department_id,
                    $leave_details['leave_type'],
                    $leave_details['start_date'],
                    $leave_details['end_date']
                );
            }
            
            // If AJAX request, return JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => $success ? 'success' : 'error',
                    'message' => $success ? 'Leave request deleted successfully' : 'Failed to delete leave request'
                ]);
                exit;
            }
        } else {
            // If AJAX request, return error
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Leave request not found or access denied'
                ]);
                exit;
            }
        }
        
        header("Location: manager_leave_request.php");
        exit;
    }
    
    // Handle add/edit actions
    if (isset($_POST['save_leave'])) {
        $leave_type_id = intval($_POST['leave_type_id']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
        $total_days = floatval($_POST['total_days']);
        $actual_leave_days = floatval($_POST['actual_leave_days']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $leave_request_id = isset($_POST['leave_request_id']) ? intval($_POST['leave_request_id']) : null;

        // Get leave type name for notifications
        $leave_type_query = mysqli_query($conn, "SELECT name FROM leavetypes WHERE leave_type_id = $leave_type_id");
        $leave_type_data = mysqli_fetch_assoc($leave_type_query);
        $leave_type_name = $leave_type_data ? $leave_type_data['name'] : 'Unknown Leave Type';

        if ($action === 'edit' && $leave_request_id) {
            // Update existing leave request
            $update_query = "
                UPDATE EmployeeLeaveRequests 
                SET leave_type_id = $leave_type_id, 
                    start_date = '$start_date', 
                    end_date = '$end_date', 
                    total_days = $total_days, 
                    actual_leave_days = $actual_leave_days, 
                    reason = '$reason'
                WHERE leave_request_id = $leave_request_id AND employee_id = $manager_id
            ";
            $success = mysqli_query($conn, $update_query);
            
            if ($success) {
                // Send update notifications
                sendLeaveRequestNotifications(
                    $conn, 
                    'update', 
                    $leave_request_id, 
                    $manager_id, 
                    $department_id,
                    $leave_type_name,
                    $start_date,
                    $end_date
                );
            }
        } else {
            // Add new leave request
            $current_time = date('Y-m-d H:i:s');
            $insert_query = "
                INSERT INTO EmployeeLeaveRequests 
                (employee_id, leave_type_id, start_date, end_date, total_days, actual_leave_days, reason, status, requested_at) 
                VALUES ($manager_id, $leave_type_id, '$start_date', '$end_date', $total_days, $actual_leave_days, '$reason', 'Pending', '$current_time')
            ";
            $success = mysqli_query($conn, $insert_query);
            
            if ($success) {
                $new_leave_request_id = mysqli_insert_id($conn);
                
                // Send add notifications
                sendLeaveRequestNotifications(
                    $conn, 
                    'add', 
                    $new_leave_request_id, 
                    $manager_id, 
                    $department_id,
                    $leave_type_name,
                    $start_date,
                    $end_date
                );
            }
        }
        
        header("Location: manager_leave_request.php");
        exit;
    }
}

// Get leave requests for the manager - Updated query
$leave_q = mysqli_query($conn, "
    SELECT elr.*, lt.name AS leave_type, 
           CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
           d.name AS department_name
    FROM EmployeeLeaveRequests elr
    LEFT JOIN leavetypes lt ON elr.leave_type_id = lt.leave_type_id
    LEFT JOIN employees e ON elr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE elr.employee_id = $manager_id
    ORDER BY elr.requested_at DESC
") or die(mysqli_error($conn));

// Get manager details - Updated query
$manager_query = mysqli_query($conn, "
    SELECT e.employee_id, e.first_name, e.last_name, d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = $manager_id
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
              <h5 class="m-b-10">Manager Leave Request</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Manager Leave Request</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Leave Request Section -->
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">LEAVE REQUEST</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal" id="addLeaveBtn">
          <i class="bi bi-plus-circle"></i> Add Leave
        </button>
      </div>
<div class="table-responsive">
  <table id="leaveTable" class="table table-striped table-bordered nowrap" style="width:100%">
    <thead class="table-dark">
      <tr>
        <th>Leave Type</th>
        <th>Start</th>
        <th>End</th>
        <th>Total Days</th>
        <th>Status</th>
        <th>Requested At</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = mysqli_fetch_assoc($leave_q)): ?>
        <tr>
          <td><?= htmlspecialchars($row['leave_type']) ?></td>
          <td data-toggle="tooltip" title="<?= date('l, F d, Y', strtotime($row['start_date'])) ?>">
            <?= date('F d, Y', strtotime($row['start_date'])) ?>
          </td>
          <td data-toggle="tooltip" title="<?= date('l, F d, Y', strtotime($row['end_date'])) ?>">
            <?= date('F d, Y', strtotime($row['end_date'])) ?>
          </td>
          <td data-sort="<?= $row['total_days'] ?>">
            <?php 
            $total = floatval($row['total_days']);
            $actual = floatval($row['actual_leave_days']);
            
            // Function to format number of days
            $displayDays = function($days) {
                if ($days == 0.5) {
                    return "Half day";
                } elseif ($days == 1.0) {
                    return "1 day";
                } elseif ($days == floor($days)) {
                    return number_format($days, 0) . " days";
                } else {
                    $whole = floor($days);
                    return $whole . " and half days";
                }
            };
            
            $totalDisplay = $displayDays($total);
            $actualDisplay = $displayDays($actual);
            
            echo "<span data-total='$total' data-actual='$actual' data-toggle='tooltip' title='Actual: $actualDisplay'>";
            echo $totalDisplay;
            echo "</span>";
            ?>
          </td>
          <td>
            <?php
          $statusColors = [
    'Pending' => ['warning', ''],
    'Approved' => ['success', ''],
    'Rejected' => ['danger', '']
];

            $status = $row['status'];
            $statusInfo = $statusColors[$status] ?? ['secondary', ''];
            ?>
            <span class="badge bg-<?= $statusInfo[0] ?>" 
                  data-toggle="tooltip" 
                  title="Status: <?= $status ?>">
                <?= $statusInfo[1] ?> <?= $status ?>
            </span>
          </td>
          <td data-toggle="tooltip" title="<?= date('l, F d, Y h:i A', strtotime($row['requested_at'])) ?>">
            <?= date('F d, Y h:i A', strtotime($row['requested_at'])) ?>
          </td>
          <td class="text-center">
            <div class="btn-group" role="group">
                <button class="btn btn-sm btn-outline-primary btn-edit-leave" 
                        data-leave='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                        data-toggle="tooltip"
                        title="Edit Leave Request">
                    <i class="bi bi-pencil-square"></i>
                </button>
               <?php
$disabled = ($row['status'] !== 'Pending') ? 'disabled' : '';
?>
<button class="btn btn-sm btn-outline-danger btn-delete-leave"
        data-leave-id="<?= $row['leave_request_id'] ?>"
        data-toggle="tooltip"
        title="<?= $row['status'] === 'Pending' ? 'Delete Leave Request' : 'Cannot delete: Request already processed' ?>"
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

      
<!-- Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="leave_request_id" id="leave_request_id">
      <input type="hidden" name="action" value="add" id="form_action">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Add Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Employee</label>
          <select name="employee_id" id="employee_id" class="form-select" required>
            <option value="">Select Employee</option>
            <?php
            mysqli_data_seek($manager_query, 0); // Reset pointer
            if ($manager = mysqli_fetch_assoc($manager_query)) {
                $manager_name = htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']);
                echo "<option value='{$manager['employee_id']}'>$manager_name</option>";
            }
            ?>
          </select>
        </div>
        <div class="mb-3">  
          <label class="form-label">Leave Type</label>
          <select name="leave_type_id" id="leave_type_id" class="form-select" required>
            <option value="">Select Type</option>
            <?php
            $types = mysqli_query($conn, "SELECT * FROM leavetypes ORDER BY name") or die(mysqli_error($conn));
            while ($type = mysqli_fetch_assoc($types)) {
              echo "<option value='{$type['leave_type_id']}'>{$type['name']}</option>";
            }
            ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" id="end_date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Total Days</label>
          <div class="input-group">
            <input type="number" name="total_days" id="total_days" step="0.5" min="0.5" class="form-control" required>
            <span class="input-group-text" id="total_days_display">days</span>
          </div>
          <div class="form-text">You can enter half days (e.g., 0.5, 1.5, 2.5)</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Actual Days</label>
          <div class="input-group">
            <input type="number" name="actual_leave_days" id="actual_leave_days" step="0.5" min="0.5" class="form-control" required>
            <span class="input-group-text" id="actual_days_display">days</span>
          </div>
          <div class="form-text">Actual leave days taken (may differ from total days for partial leaves)</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <textarea name="reason" id="reason" class="form-control" rows="3"></textarea>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Requested At</label>
          <?php 
          $manila_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
          ?>
          <input type="text" class="form-control" value="<?= $manila_time->format('Y-m-d H:i:s') ?>" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="save_leave" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- DataTables + Script -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
<style>
.dataTables_wrapper .dataTables_filter {
    float: right;
    margin-bottom: 10px;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 1rem;
}
.table {
    min-width: 100%;
    width: 100% !important;
    margin-bottom: 0;
}
@media screen and (max-width: 767px) {
    div.dataTables_wrapper div.dataTables_length,
    div.dataTables_wrapper div.dataTables_filter,
    div.dataTables_wrapper div.dataTables_info,
    div.dataTables_wrapper div.dataTables_paginate {
        text-align: center;
        margin-top: 5px;
        margin-bottom: 5px;
    }
    
    div.dataTables_wrapper div.dataTables_paginate ul.pagination {
        justify-content: center !important;
    }
    .table > :not(caption) > * > * {
        padding: 0.5rem;
    }
}
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 0.25rem;
}
.time-display {
    font-weight: bold;
    color: #0d6efd;
}
</style>

<!-- Add responsive DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<!-- Replace the existing DataTable initialization -->
<script>
$(document).ready(function () {
    // Initialize DataTable with enhanced responsive features
    const table = $('#leaveTable').DataTable({
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal({
                    header: function (row) {
                        return 'Leave Request Details';
                    }
                }),
                renderer: $.fn.dataTable.Responsive.renderer.tableAll()
            }
        },
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        pageLength: 10,
        order: [[5, 'desc']], // Order by requested_at column
        columnDefs: [
            {
                targets: [6],
                orderable: false,
                className: 'text-center'
            },
            {
                targets: '_all',
                className: 'align-middle'
            }
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search leave requests...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            zeroRecords: "No matching leave requests found"
        }
    });

    // Adjust table when window resizes
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            table.columns.adjust().responsive.recalc();
        }, 250);
    });

    // JavaScript function to format leave days display
function formatLeaveDays(days) {
    days = parseFloat(days);
    
    if (days === 0.5) {
        return "Half day";
    } else if (days === 1.0) {
        return "1 day";
    } else if (Number.isInteger(days)) {
        return days + " days";
    } else {
        const wholePart = Math.floor(days);
        const decimalPart = days - wholePart;
        
        if (Math.abs(decimalPart - 0.5) < 0.001) { // Check for 0.5
            if (wholePart === 0) {
                return "Half day";
            } else if (wholePart === 1) {
                return "1 and half days";
            } else {
                return wholePart + " and half days";
            }
        } else {
            return days.toFixed(1) + " days";
        }
    }
}

    // Function to update day display in modal
    function updateDayDisplay() {
        const totalDays = parseFloat($('#total_days').val()) || 0;
        const actualDays = parseFloat($('#actual_leave_days').val()) || 0;
        
        if (totalDays > 0) {
            $('#total_days_display').text(formatLeaveDays(totalDays));
        } else {
            $('#total_days_display').text('days');
        }
        
        if (actualDays > 0) {
            $('#actual_days_display').text(formatLeaveDays(actualDays));
        } else {
            $('#actual_days_display').text('days');
        }
    }

    // Update display when days change
    $('#total_days, #actual_leave_days').on('input change', function() {
        updateDayDisplay();
    });

    // Reset modal for Add
    $('#addLeaveBtn').on('click', function() {
        resetModal();
        $('#modalTitle').text('Add Leave Request');
        $('#form_action').val('add');
        $('#employee_id').prop('disabled', false);
        // Auto-select the manager
        $('#employee_id').val($('#employee_id option:first').next().val());
    });

    // Populate modal for Edit
    $(document).on('click', '.btn-edit-leave', function() {
        const data = JSON.parse($(this).attr('data-leave'));
        
        // Show modal first
        const leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
        leaveModal.show();
        
        // Update modal title and form action
        $('#modalTitle').text('Edit Leave Request');
        $('#form_action').val('edit');
        
        // Populate form fields
        $('#leave_request_id').val(data.leave_request_id);
        $('#employee_id').val(data.employee_id).prop('disabled', true);
        $('#leave_type_id').val(data.leave_type_id);
        $('#start_date').val(data.start_date);
        $('#end_date').val(data.end_date);
        $('#total_days').val(data.total_days);
        $('#actual_leave_days').val(data.actual_leave_days);
        $('#reason').val(data.reason || '');

        // Update day displays
        updateDayDisplay();

        // Trigger change events to update any dependent fields
        $('#start_date, #end_date').trigger('change');
    });

    // Reset modal function
    function resetModal() {
        const form = document.querySelector('form.modal-content');
        form.reset();
        
        $('#leave_request_id').val('');
        $('#form_action').val('add');
        $('#employee_id').prop('disabled', false);
        
        // Reset day displays
        $('#total_days_display').text('days');
        $('#actual_days_display').text('days');
        
        // Clear any validation messages or styles
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Reset any custom select plugins if present
        if ($.fn.select2) {
            $('#leave_type_id').select2('destroy').select2();
        }
    }

    // Delete leave request with SweetAlert
    $(document).on('click', '.btn-delete-leave', function () {
        const leaveId = $(this).data('leave-id');
        const row = $(this).closest('tr');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `This will permanently delete leave request ID: ${leaveId}`,
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
                    text: 'Please wait while we delete the leave request.',
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
                        leave_request_id: leaveId, 
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
                            text: 'An error occurred while deleting the leave request. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });

    // Auto-calculate total days when dates change
    $('#start_date, #end_date').on('change', function() {
        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            if (end >= start) {
                // Calculate business days between dates
                let totalDays = 0;
                const current = new Date(start);
                
                while (current <= end) {
                    // Check if it's not a weekend (0 = Sunday, 6 = Saturday)
                    const dayOfWeek = current.getDay();
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                        totalDays++;
                    }
                    current.setDate(current.getDate() + 1);
                }
                
                $('#total_days').val(totalDays);
                $('#actual_leave_days').val(totalDays);
                updateDayDisplay();
            } else {
                $('#total_days').val('');
                $('#actual_leave_days').val('');
                updateDayDisplay();
                Swal.fire('Warning', 'End date must be after start date', 'warning');
            }
        }
    });

    // Initialize tooltips after DataTable is drawn
    table.on('draw', function () {
        $('[data-toggle="tooltip"]').tooltip();
    });

    // Initialize tooltips on page load
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
</div>
</div>
</div>
