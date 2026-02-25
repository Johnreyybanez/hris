<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'connection.php';

// ✅ Check if manager is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

$manager_id = (int)$_SESSION['user_id']; // Cast to integer for security

// Get manager's department - Using prepared statements
$dept_stmt = $conn->prepare("
    SELECT e.department_id, d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = ?
");

if (!$dept_stmt) {
    error_log("Error preparing department query: " . $conn->error);
    die("Database error occurred. Please try again later.");
}

$dept_stmt->bind_param("i", $manager_id);

if (!$dept_stmt->execute()) {
    error_log("Error executing department query: " . $dept_stmt->error);
    die("Database error occurred. Please try again later.");
}

$dept_result = $dept_stmt->get_result();
$manager_dept = $dept_result->fetch_assoc();

if (!$manager_dept) {
    error_log("Manager department not found for ID: " . $manager_id);
    header("Location: login.php");
    exit;
}

$department_id = (int)$manager_dept['department_id'];

// Function to insert notification
function insertNotification($conn, $employee_id, $message, $link = null) {
    $current_time = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at) 
              VALUES (?, ?, ?, 0, ?)";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Error preparing statement in insertNotification: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("isss", $employee_id, $message, $link, $current_time);
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error executing statement in insertNotification: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

// Function to get manager details - Updated query
function getManagerDetails($conn, $manager_id) {
    $query = "
        SELECT CONCAT(e.first_name, ' ', e.last_name) AS full_name,
               d.name as department
        FROM employees e
        JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Error preparing manager details query: " . $conn->error);
        return ['full_name' => 'Unknown Manager', 'department' => 'Unknown Department'];
    }
    
    $stmt->bind_param("i", $manager_id);
    
    if (!$stmt->execute()) {
        error_log("Error executing manager details query: " . $stmt->error);
        $stmt->close();
        return ['full_name' => 'Unknown Manager', 'department' => 'Unknown Department'];
    }
    
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data ?: ['full_name' => 'Unknown Manager', 'department' => 'Unknown Department'];
}

// Function to get department heads and admins for notifications - Fixed query
function getNotificationRecipients($conn, $department_id, $exclude_employee_id = null) {
    $recipients = [];
    
    // Ensure parameters are integers
    $department_id = intval($department_id);
    $exclude_id = $exclude_employee_id ? intval($exclude_employee_id) : 0;
    
    // Improved query with proper error handling
    $query = "
        SELECT DISTINCT e.employee_id 
        FROM employees e
        JOIN employeelogins el ON e.employee_id = el.employee_id
        WHERE (el.role IN ('admin', 'hr') 
           OR (e.department_id = ? AND el.role = 'manager'))
        AND e.employee_id != ?
        AND e.status = 'Active'
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Error preparing notification query: " . $conn->error);
        return $recipients;
    }
    
    $stmt->bind_param("ii", $department_id, $exclude_id);
    
    if (!$stmt->execute()) {
        error_log("Error executing notification query: " . $stmt->error);
        $stmt->close();
        return $recipients;
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recipients[] = (int)$row['employee_id'];
    }
    
    $stmt->close();
    return $recipients;
}

// Function to send overtime request notifications
function sendOvertimeRequestNotifications($conn, $action, $overtime_id, $manager_id, $department_id, $date = '', $start_time = '', $end_time = '', $total_hours = '', $reason = '') {
    $manager_details = getManagerDetails($conn, $manager_id);
    $manager_name = htmlspecialchars($manager_details['full_name']);
    $department_name = htmlspecialchars($manager_details['department']);
    
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
    $reason_info = $reason ? " - " . htmlspecialchars($reason) : '';
    
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
    // CSRF protection - you should add this
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            exit;
        }
        header("Location: manager_overtime.php");
        exit;
    }
    
    $action = $_POST['action'] ?? 'add';
    
    // Handle delete action
    if ($action === 'delete' && isset($_POST['overtime_id'])) {
        $overtime_id = (int)$_POST['overtime_id'];
        
        // Get overtime request details before deletion for notifications
        $stmt = $conn->prepare("SELECT * FROM overtime WHERE overtime_id = ? AND employee_id = ?");
        $stmt->bind_param("ii", $overtime_id, $manager_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $overtime_details = $result->fetch_assoc();
            
            $del_stmt = $conn->prepare("DELETE FROM overtime WHERE overtime_id = ? AND employee_id = ?");
            $del_stmt->bind_param("ii", $overtime_id, $manager_id);
            $success = $del_stmt->execute();
            
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
        // Validate and sanitize inputs
        $date = trim($_POST['date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $total_hours = floatval($_POST['total_hours'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $overtime_id = isset($_POST['overtime_id']) ? (int)$_POST['overtime_id'] : null;

        // Validation
        if (empty($date) || empty($start_time) || empty($end_time) || empty($reason) || $total_hours <= 0) {
            $_SESSION['error'] = 'All fields are required and total hours must be greater than 0';
            header("Location: manager_overtime.php");
            exit;
        }

        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $date)) {
            $_SESSION['error'] = 'Invalid date format';
            header("Location: manager_overtime.php");
            exit;
        }

        // Validate time format
        if (!DateTime::createFromFormat('H:i', $start_time) || !DateTime::createFromFormat('H:i', $end_time)) {
            $_SESSION['error'] = 'Invalid time format';
            header("Location: manager_overtime.php");
            exit;
        }

        if ($action === 'edit' && $overtime_id) {
            // Update existing overtime request
            $stmt = $conn->prepare("
                UPDATE overtime 
                SET date = ?, start_time = ?, end_time = ?, total_hours = ?, reason = ?, updated_at = NOW()
                WHERE overtime_id = ? AND employee_id = ?
            ");
            $stmt->bind_param("sssdiii", $date, $start_time, $end_time, $total_hours, $reason, $overtime_id, $manager_id);
            $success = $stmt->execute();
            
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
                $_SESSION['success'] = 'Overtime request updated successfully';
            } else {
                $_SESSION['error'] = 'Failed to update overtime request';
            }
        } else {
            // Add new overtime request
            $stmt = $conn->prepare("
                INSERT INTO overtime 
                (employee_id, date, start_time, end_time, total_hours, reason, approval_status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
            ");
            $stmt->bind_param("isssds", $manager_id, $date, $start_time, $end_time, $total_hours, $reason);
            $success = $stmt->execute();
            
            if ($success) {
                $new_overtime_id = $conn->insert_id;
                
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
                $_SESSION['success'] = 'Overtime request submitted successfully';
            } else {
                $_SESSION['error'] = 'Failed to submit overtime request';
            }
        }
        
        header("Location: manager_overtime.php");
        exit;
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get overtime requests for the manager - Using prepared statements
$stmt = $conn->prepare("
    SELECT o.*, 
           CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
           d.name AS department_name
    FROM overtime o
    LEFT JOIN employees e ON o.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE o.employee_id = ?
    ORDER BY o.created_at DESC
");

if (!$stmt) {
    error_log("Error preparing overtime query: " . $conn->error);
    die("Database error occurred. Please try again later.");
}

$stmt->bind_param("i", $manager_id);

if (!$stmt->execute()) {
    error_log("Error executing overtime query: " . $stmt->error);
    die("Database error occurred. Please try again later.");
}

$overtime_q = $stmt->get_result();

// Get manager details - Using prepared statements
$manager_stmt = $conn->prepare("
    SELECT e.employee_id, e.first_name, e.last_name,
           d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = ?
");

if (!$conn || !$manager_stmt) {
    $error = $conn ? $conn->error : "Database connection failed";
    error_log("Error preparing manager query: " . $error);
    die("Database error occurred. Please try again later.");
}

$manager_stmt->bind_param("i", $manager_id);

if (!$manager_stmt->execute()) {
    error_log("Error executing manager query: " . $manager_stmt->error);
    die("Database error occurred. Please try again later.");
}

$manager_query = $manager_stmt->get_result();

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

?>

<div class="pc-container">
  <div class="pc-content">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
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
            <?php while ($row = $overtime_q->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars(date('F d, Y', strtotime($row['date']))) ?></td>
                <td><?= htmlspecialchars(date('h:i A', strtotime($row['start_time']))) ?></td>
                <td><?= htmlspecialchars(date('h:i A', strtotime($row['end_time']))) ?></td>
                <td><?= number_format($row['total_hours'], 1) ?></td>
                <td>
                  <span class="badge bg-<?= $row['approval_status'] === 'Pending' ? 'warning' : 
                                         ($row['approval_status'] === 'Approved' ? 'success' : 'danger') ?>">
                    <?= htmlspecialchars($row['approval_status']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars(date('F d, Y h:i A', strtotime($row['created_at']))) ?></td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                      <button class="btn btn-sm btn-outline-primary btn-edit-overtime" 
                              data-overtime='<?= htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                              data-toggle="tooltip"
                              title="Edit Overtime Request">
                          <i class="bi bi-pencil-square"></i>
                      </button>
                     <?php
      $disabled = ($row['approval_status'] !== 'Pending') ? 'disabled' : '';
      ?>
      <button class="btn btn-sm btn-outline-danger btn-delete-overtime"
              data-overtime-id="<?= (int)$row['overtime_id'] ?>"
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
              $manager = $manager_query->fetch_assoc();
              echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']);
            ?>" readonly>
            <input type="hidden" name="employee_id" value="<?= $manager_id ?>">
          </div>

          <div class="mb-3">
              <label for="date" class="form-label">Date:</label>
              <input type="date" id="date" name="date" class="form-control" required max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
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
              <label for="total_hours" class="form-label">Total Overtime Hours: <span class="text-danger">*</span></label>
              <div class="input-group">
                  <input type="number" id="total_hours" name="total_hours" class="form-control" step="0.25" min="0.25" max="16" readonly 
                         placeholder="Will calculate automatically..." required>
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
              <div id="overtime-help" class="mt-2">
                  <small class="text-muted">
                      <i class="bi bi-lightbulb"></i> <strong>How it works:</strong> 
                      Enter your actual work times. If you work more than 8 hours, the excess will be calculated as overtime.
                      <br>
                      <strong>Example:</strong> 08:00 - 18:00 = 10 hours total → 2 hours overtime
                  </small>
              </div>
          </div>

          <div class="mb-3">
              <label for="reason" class="form-label">Reason:</label>
              <textarea id="reason" name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
              <div class="form-text">Maximum 500 characters</div>
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
    margin-bottom: 10px;
  }
  .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 1rem;
  }
  .table {
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
    // Initialize DataTable with responsive features
    const table = $('#leaveTable').DataTable({
        responsive: true,
        lengthMenu: [5, 10, 25, 50, 100],
        pageLength: 10,
        order: [[5, 'desc']],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search overtime requests...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            zeroRecords: "No matching overtime requests found",
        },
        columnDefs: [
            { targets: [6], orderable: false },
            { targets: [4], width: '100px' },
            { targets: [6], width: '120px' }
        ],
        // Add responsive breakpoints
        responsive: {
            breakpoints: [
                { name: 'desktop', width: Infinity },
                { name: 'tablet',  width: 1024 },
                { name: 'phone',   width: 480 }
            ]
        }
    });
    
    // Adjust table when window resizes
    $(window).on('resize', function() {
        table.columns.adjust().responsive.recalc();
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
                        action: 'delete',
                        csrf_token: $('input[name="csrf_token"]').val()
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
        $('#total_hours').removeClass('is-invalid is-valid');

        // Show placeholder and clear value if any field is empty
        if (!date || !startTime || !endTime) {
            $('#total_hours').val('').attr('placeholder', 'Enter date and times first...');
            return;
        }

        // Update placeholder to show calculating
        $('#total_hours').attr('placeholder', 'Calculating...');

        try {
            // Parse time values correctly
            const [startHour, startMin] = startTime.split(':').map(Number);
            const [endHour, endMin] = endTime.split(':').map(Number);
            
            // Convert to minutes for easier calculation
            let startTotalMin = startHour * 60 + startMin;
            let endTotalMin = endHour * 60 + endMin;
            
            // Handle overnight shifts (end time is next day)
            if (endTotalMin <= startTotalMin) {
                endTotalMin += 24 * 60; // Add 24 hours worth of minutes
            }
            
            // Calculate total worked minutes
            const totalWorkedMin = endTotalMin - startTotalMin;
            const totalWorkedHours = totalWorkedMin / 60; // Convert back to hours

            // Constants
            const REGULAR_WORK_HOURS = 8;
            const MAX_REASONABLE_HOURS = 24;
            const MIN_OVERTIME_THRESHOLD = 0.25; // 15 minutes

            console.log(`Debug: Start: ${startTime} (${startTotalMin}min), End: ${endTime} (${endTotalMin}min), Total: ${totalWorkedHours.toFixed(2)}h`);

            // Validate reasonable working hours
            if (totalWorkedHours > MAX_REASONABLE_HOURS) {
                $('#total_hours').val('').attr('placeholder', 'Invalid: Exceeds 24 hours');
                showToast('warning', `${totalWorkedHours.toFixed(1)}h exceeds maximum - please verify times`);
                return;
            }

            // Validate minimum working time (15 minutes)
            if (totalWorkedHours < 0.25) {
                $('#total_hours').val('').attr('placeholder', 'Minimum 15 minutes required');
                return;
            }

            // Calculate overtime hours
            if (totalWorkedHours > REGULAR_WORK_HOURS) {
                const exactOvertimeHours = totalWorkedHours - REGULAR_WORK_HOURS;
                
                // Round to nearest quarter hour (0.25)
                const roundedOvertimeHours = Math.round(exactOvertimeHours * 4) / 4;
                
                if (roundedOvertimeHours >= MIN_OVERTIME_THRESHOLD) {
                    $('#total_hours').val(roundedOvertimeHours.toFixed(2));
                    
                    // Show calculation breakdown
                    const breakdownText = `${totalWorkedHours.toFixed(2)}h total - ${REGULAR_WORK_HOURS}h regular = ${roundedOvertimeHours.toFixed(2)}h overtime`;
                    $('#breakdown-text').text(breakdownText);
                    $('#calculation-breakdown').show();
                    
                    // Visual feedback
                    $('#total_hours').addClass('is-valid');
                    setTimeout(() => $('#total_hours').removeClass('is-valid'), 2000);
                } else {
                    $('#total_hours').val('');
                    $('#total_hours').attr('placeholder', `${exactOvertimeHours.toFixed(2)}h overtime (rounded to 0 - minimum 15min)`);
                }
                
            } else if (totalWorkedHours > 0) {
                $('#total_hours').val('');
                
                // Show why no overtime
                const hoursNeeded = REGULAR_WORK_HOURS - totalWorkedHours;
                $('#total_hours').attr('placeholder', `${totalWorkedHours.toFixed(2)}h total (need ${hoursNeeded.toFixed(2)}h more for overtime)`);
                
            } else {
                $('#total_hours').val('').attr('placeholder', 'Invalid time range');
                $('#total_hours').addClass('is-invalid');
                setTimeout(() => $('#total_hours').removeClass('is-invalid'), 3000);
            }

        } catch (error) {
            console.error('Error calculating overtime:', error);
            $('#total_hours').val('').attr('placeholder', 'Error calculating - check your inputs');
            $('#total_hours').addClass('is-invalid');
            setTimeout(() => $('#total_hours').removeClass('is-invalid'), 3000);
        }
    }

    // Helper function for toast notifications
    function showToast(icon, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: icon,
            title: message
        });
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
        const totalHoursVal = $('#total_hours').val();
        const totalHours = parseFloat(totalHoursVal);
        const reason = $('#reason').val().trim();
        const date = $('#date').val();
        const startTime = $('#modal_start_time').val();
        const endTime = $('#modal_end_time').val();
        
        // Validate that all required fields are filled
        if (!date || !startTime || !endTime || !reason) {
            e.preventDefault();
            Swal.fire({
                title: 'Missing Information',
                text: 'Please fill in all required fields.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Validate that overtime hours are calculated and greater than 0
        if (!totalHoursVal || isNaN(totalHours) || totalHours <= 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Overtime Hours',
                text: 'Please ensure you have valid start and end times that result in overtime hours (more than 8 hours total work). The total overtime hours must be greater than 0.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            
            // Highlight the total hours field
            $('#total_hours').addClass('is-invalid');
            setTimeout(() => $('#total_hours').removeClass('is-invalid'), 3000);
            
            return false;
        }
        
        // Validate reasonable overtime hours (max 16 hours)
        if (totalHours > 16) {
            e.preventDefault();
            Swal.fire({
                title: 'Excessive Overtime Hours',
                text: 'Overtime hours cannot exceed 16 hours. Please verify your start and end times.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Validate reason length
        if (reason.length > 500) {
            e.preventDefault();
            Swal.fire({
                title: 'Reason Too Long',
                text: 'Please limit your reason to 500 characters or less.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Validate date is not too far in the future
        const selectedDate = new Date(date);
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 30);
        
        if (selectedDate > maxDate) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Date',
                text: 'Overtime requests cannot be submitted more than 30 days in advance.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Validate date is not too far in the past (more than 7 days)
        const minDate = new Date();
        minDate.setDate(minDate.getDate() - 7);
        
        if (selectedDate < minDate) {
            e.preventDefault();
            Swal.fire({
                title: 'Date Too Old',
                text: 'Overtime requests cannot be submitted for dates more than 7 days in the past.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Show loading message
        Swal.fire({
            title: 'Submitting...',
            text: 'Please wait while we process your overtime request.',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            timer: 1000,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        return true;
    });
    
    // Character counter for reason field
    $('#reason').on('input', function() {
        const maxLength = 500;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        
        let counterText = `${remaining} characters remaining`;
        if (remaining < 0) {
            counterText = `${Math.abs(remaining)} characters over limit`;
            $(this).addClass('is-invalid');
        } else if (remaining < 50) {
            $(this).removeClass('is-invalid').addClass('is-warning');
        } else {
            $(this).removeClass('is-invalid is-warning');
        }
        
        // Update or create counter display
        let counter = $(this).siblings('.character-counter');
        if (counter.length === 0) {
            counter = $('<div class="character-counter form-text"></div>');
            $(this).after(counter);
        }
        counter.text(counterText);
        
        if (remaining < 0) {
            counter.addClass('text-danger');
        } else if (remaining < 50) {
            counter.removeClass('text-danger').addClass('text-warning');
        } else {
            counter.removeClass('text-danger text-warning').addClass('text-muted');
        }
    });
});

// Add time formatting function
function formatTimeDisplay(input, displayId) {
    if (input.value) {
        try {
            const time = new Date(`2000-01-01T${input.value}`);
            const formatted = time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
            const displayElement = document.getElementById(displayId);
            displayElement.textContent = formatted;
            displayElement.classList.add('time-display');
        } catch (error) {
            console.error('Error formatting time:', error);
            const displayElement = document.getElementById(displayId);
            displayElement.textContent = 'Invalid time';
            displayElement.classList.remove('time-display');
        }
    } else {
        const displayElement = document.getElementById(displayId);
        displayElement.textContent = '12-hour format';
        displayElement.classList.remove('time-display');
    }
}

// Update modal reset handlers
$('#leaveModal').on('hidden.bs.modal', function () {
    $('#start_time_display').text('12-hour format').removeClass('time-display');
    $('#end_time_display').text('12-hour format').removeClass('time-display');
    $('.character-counter').remove();
    $('.is-invalid, .is-warning').removeClass('is-invalid is-warning');
});

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    $('.alert').fadeOut('slow');
}, 5000);
</script>
