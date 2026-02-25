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

// Get manager's department - Updated query
$dept_query = mysqli_query($conn, "
    SELECT e.department_id, d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = $manager_id
") or die(mysqli_error($conn));
$manager_dept = mysqli_fetch_assoc($dept_query);
$department_id = $manager_dept['department_id'];

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to insert notification
function insertNotification($conn, $employee_id, $message, $link = null) {
    $current_time = date('Y-m-d H:i:s');
    $message = mysqli_real_escape_string($conn, $message);
    $link = $link ? "'" . mysqli_real_escape_string($conn, $link) . "'" : 'NULL';
    
    $query = "INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at) 
              VALUES ($employee_id, '$message', $link, 0, '$current_time')";
    
    return mysqli_query($conn, $query);
}

// Function to get manager details - Updated query
function getManagerDetails($conn, $manager_id) {
    $query = mysqli_query($conn, "
        SELECT CONCAT(e.first_name, ' ', e.last_name) AS full_name, 
               d.name as department
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

// Function to send timelog request notifications
function sendTimelogRequestNotifications($conn, $action, $request_id, $manager_id, $department_id, $date = '', $missing_field = '', $requested_time = '', $reason = '') {
    $manager_details = getManagerDetails($conn, $manager_id);
    $manager_name = $manager_details['full_name'];
    $department_name = $manager_details['department'];
    
    // Format date and time for display
    $formatted_date = $date ? date('M d, Y', strtotime($date)) : '';
    $formatted_time = $requested_time ? date('h:i A', strtotime($requested_time)) : '';
    $field_display = ucwords(str_replace('_', ' ', $missing_field));
    
    $date_info = $formatted_date ? " for $formatted_date" : '';
    $time_info = $formatted_time ? " at $formatted_time" : '';
    $field_info = $missing_field ? " ($field_display)" : '';
    $reason_info = $reason ? " - $reason" : '';
    
    switch ($action) {
        case 'add':
            // Notify the manager (self)
            $manager_message = "Your missing timelog request (#$request_id) has been submitted and is Pending approval";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Missing timelog request (#$request_id) for $manager_name from department $department_name$date_info$field_info$time_info$reason_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'update':
            // Notify the manager (self)
            $manager_message = "Your missing timelog request (#$request_id) has been updated";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Missing timelog request (#$request_id) for $manager_name from department $department_name has been updated$date_info$field_info$time_info$reason_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'delete':
            // Notify the manager (self)
            $manager_message = "Your missing timelog request (#$request_id) has been deleted";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Missing timelog request (#$request_id) for $manager_name from department $department_name has been deleted";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
    }
}

// --- UPDATE LOGIC FIRST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timelog'])) {
    try {
        // Get required fields
        $request_id = intval($_POST['request_id']);
        $employee_id = intval($_POST['employee_id']);
        $date = $_POST['date'];
        $missing_field = $_POST['missing_field'];
        $requested_time = $_POST['requested_time'];
        $reason = trim($_POST['reason']);
        $status = 'Pending'; // Always set to Pending for manager's own requests
        $approved_by = null; // Clear approval info
        $approved_at = null;

        // Format requested time properly
        if (strpos($requested_time, 'T') !== false) {
            $requested_time = str_replace('T', ' ', $requested_time) . ':00';
        }

        // Update the request with minimal validation
        $stmt = $conn->prepare("UPDATE missingtimelogrequests SET date=?, missing_field=?, requested_time=?, reason=? WHERE request_id=? AND employee_id=?");
        $stmt->bind_param("ssssii", $date, $missing_field, $requested_time, $reason, $request_id, $employee_id);

        if ($stmt->execute()) {
            $stmt->close();
            
            // Send update notifications
            sendTimelogRequestNotifications(
                $conn, 
                'update', 
                $request_id, 
                $manager_id, 
                $department_id,
                $date,
                $missing_field,
                $requested_time,
                $reason
            );
            
            header("Location: manager_timelog.php?success=1");
            exit;
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Update timelog error: " . $e->getMessage());
        header("Location: manager_timelog.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_timelog_id'])) {
    $delete_id = intval($_POST['delete_timelog_id']);
    
    try {
        // Get timelog request details before deletion for notifications
        $timelog_details_query = mysqli_query($conn, "
            SELECT * FROM missingtimelogrequests 
            WHERE request_id = $delete_id AND employee_id = $manager_id
        ");
        
        if ($timelog_details_query && mysqli_num_rows($timelog_details_query) > 0) {
            $timelog_details = mysqli_fetch_assoc($timelog_details_query);
            
            $stmt = $conn->prepare("DELETE FROM missingtimelogrequests WHERE request_id = ? AND employee_id = ?");
            $stmt->bind_param("ii", $delete_id, $manager_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Send delete notifications
                sendTimelogRequestNotifications(
                    $conn, 
                    'delete', 
                    $delete_id, 
                    $manager_id, 
                    $department_id,
                    $timelog_details['date'],
                    $timelog_details['missing_field'],
                    $timelog_details['requested_time'],
                    $timelog_details['reason']
                );
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? 'Timelog request deleted successfully' : 'Failed to delete timelog request'
            ]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Timelog request not found or access denied'
            ]);
            exit;
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle add timelog action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_timelog'])) {
    try {
        // Validate required fields
        if (empty($_POST['employee_id']) || empty($_POST['date']) || empty($_POST['missing_field']) || 
            empty($_POST['requested_time']) || empty($_POST['reason'])) {
            throw new Exception("All fields are required");
        }

        $employee_id = intval($_POST['employee_id']);
        $date = $_POST['date'];
        $missing_field = $_POST['missing_field'];
        $requested_time = $_POST['requested_time'];
        $reason = trim($_POST['reason']);
        $requested_at = date('Y-m-d H:i:s');
        $status = 'Pending';
        
        // Validate employee exists
        $check_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $check_stmt->bind_param("i", $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Invalid employee selected");
        }
        $check_stmt->close();
        
        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $date)) {
            throw new Exception("Invalid date format");
        }
        
        // Validate missing field
        $valid_fields = ['time_in', 'break_out', 'break_in', 'time_out'];
        if (!in_array($missing_field, $valid_fields)) {
            throw new Exception("Invalid missing field");
        }
        
        // Format requested time properly
        if (strpos($requested_time, 'T') !== false) {
            $requested_time = str_replace('T', ' ', $requested_time) . ':00';
        }
        
        // Validate datetime format
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $requested_time)) {
            throw new Exception("Invalid requested time format");
        }
        
        // Check for duplicate request
        $duplicate_check = $conn->prepare("SELECT request_id FROM missingtimelogrequests WHERE employee_id = ? AND date = ? AND missing_field = ? AND status = 'Pending'");
        $duplicate_check->bind_param("iss", $employee_id, $date, $missing_field);
        $duplicate_check->execute();
        $duplicate_result = $duplicate_check->get_result();
        
        if ($duplicate_result->num_rows > 0) {
            throw new Exception("A pending request already exists for this employee, date, and field");
        }
        $duplicate_check->close();
        
        // Insert the new request
        $stmt = $conn->prepare("INSERT INTO missingtimelogrequests (employee_id, date, missing_field, requested_time, reason, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $employee_id, $date, $missing_field, $requested_time, $reason, $status, $requested_at);
        
        if ($stmt->execute()) {
            if ($conn instanceof mysqli) {
                $new_request_id = $conn->insert_id;
                $stmt->close();
            } else {
                throw new Exception("Invalid database connection");
            }
            
            // Send add notifications
            sendTimelogRequestNotifications(
                $conn, 
                'add', 
                $new_request_id, 
                $manager_id, 
                $department_id,
                $date,
                $missing_field,
                $requested_time,
                $reason
            );
            
            // Handle AJAX response
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Timelog request added successfully']);
                exit;
            } else {
                header("Location: manager_timelog.php?success=1");
                exit;
            }
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Add timelog error: " . $e->getMessage());
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttrequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        } else {
            header("Location: manager_timelog.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Get employees for dropdown
$employees = [];
$emp_result = $conn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS name FROM employees ORDER BY first_name, last_name");
if ($emp_result) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}


include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';


// Get manager details - Updated query
$manager_query = mysqli_query($conn, "
    SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS name,
           d.name as department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = $manager_id
") or die(mysqli_error($conn));
$manager_info = mysqli_fetch_assoc($manager_query);

// Get manager's timelogs - Updated query
$sql = "SELECT 
    mtr.request_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    mtr.employee_id,
    d.name AS department_name,
    mtr.date,
    mtr.missing_field,
    mtr.requested_time,
    mtr.reason,
    mtr.status,
    mtr.requested_at,
    mtr.approved_by,
    mtr.approved_at
FROM missingtimelogrequests mtr
LEFT JOIN employees e ON mtr.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
WHERE mtr.employee_id = $manager_id
ORDER BY mtr.requested_at DESC";
$result = $conn->query($sql);
?>

<div class="pc-container">
  <div class="pc-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Success!</strong> Timelog request added successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error!</strong> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Missing Timelog Requests</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Missing Timelog</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card card-body shadow mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">TIMELOG REQUEST</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTimelogModal">
              <i class="bi bi-plus-circle"></i> Add Timelog
            </button>
          </div>
        <div class="table-responsive mt-3">
  <table class="table table-striped table-hover table-bordered" id="timelogTable" width="100%">
    <thead class="table-dark">
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
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['request_id']) ?></td>
          <td><?= htmlspecialchars($row['employee_name']) ?></td>
          <td><?= htmlspecialchars($row['date']) ?></td>
          <td><?= ucwords(str_replace('_', ' ', $row['missing_field'])) ?></td>
          <td><?= date("h:i A", strtotime($row['requested_time'])) ?></td>
          <td><?= htmlspecialchars($row['reason']) ?></td>
          <td>
            <?php
              $badge = match ($row['status']) {
                'Approved' => 'success',
                'Rejected' => 'danger',
                default     => 'warning',
              };
            ?>
            <span class="badge bg-<?= $badge ?>"><?= $row['status'] ?></span>
          </td>
          <td><?= date('Y-m-d h:i A', strtotime($row['requested_at'])) ?></td>
          <td>
           <button class="btn btn-sm btn-outline-primary btn-edit-timelog"
              data-request='<?= json_encode($row) ?>'>
              <i class="bi bi-pencil-square"></i>
            </button>
           <button class="btn btn-sm btn-outline-danger btn-delete-timelog"
        data-timelog-id="<?= $row['request_id'] ?>"
        title="<?= $row['status'] === 'Approved' ? 'Cannot delete approved request' : 'Delete request' ?>"
        <?= $row['status'] === 'Approved' ? 'disabled' : '' ?>>
  <i class="bi bi-trash"></i>
</button>

          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Add Timelog Modal -->
<div class="modal fade" id="addTimelogModal" tabindex="-1" aria-labelledby="addTimelogModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="addTimelogForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addTimelogModalLabel">Add Missing Timelog</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Employee</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($manager_info['name']) ?>" readonly>
            <input type="hidden" name="employee_id" value="<?= $manager_info['employee_id'] ?>">
          </div>
          <div class="mb-2">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="date" id="add_date" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Missing Field <span class="text-danger">*</span></label>
            <select name="missing_field" id="add_missing_field" class="form-select" required>
              <option value="">Select Field</option>
              <option value="time_in">Time In</option>
              <option value="break_out">Break Out</option>
              <option value="break_in">Break In</option>
              <option value="time_out">Time Out</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Requested Time <span class="text-danger">*</span></label>
            <input type="datetime-local" name="requested_time" id="add_requested_time" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <input type="text" name="reason" id="add_reason" class="form-control" required placeholder="Enter reason for missing timelog">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_timelog" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            Add Request
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="updateTimelogForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateModalLabel">Update Missing Timelog</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="request_id" id="modal_request_id">
        <div class="mb-2">
          <label class="form-label">Request ID</label>
          <input type="text" id="modal_requestid_display" class="form-control" readonly>
        </div>
        <div class="mb-2">
          <label class="form-label">Employee</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($manager_info['name']) ?>" readonly>
          <input type="hidden" name="employee_id" value="<?= $manager_info['employee_id'] ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Date <span class="text-danger">*</span></label>
          <input type="date" name="date" id="modal_date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Missing Field <span class="text-danger">*</span></label>
          <select name="missing_field" id="modal_missing_field" class="form-select" required>
            <option value="">Select Field</option>
            <option value="time_in">Time In</option>
            <option value="break_out">Break Out</option>
            <option value="break_in">Break In</option>
            <option value="time_out">Time Out</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Requested Time <span class="text-danger">*</span></label>
          <input type="datetime-local" name="requested_time" id="modal_requested_time" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Reason <span class="text-danger">*</span></label>
          <input type="text" name="reason" id="modal_reason" class="form-control" required placeholder="Enter reason for missing timelog">
        </div>
        <div class="mb-2">
                <label class="form-label">Status</label>
                <input type="text" class="form-control" value="Pending" readonly>
              </div>
        <div class="mb-3">
          <label for="modal_requestedat" class="form-label">Requested At</label>
          <input type="text" name="requested_at" id="modal_requestedat" class="form-control" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_timelog" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- DataTables JS & CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Bootstrap 5 JS (required for modal and header buttons) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
  .dataTables_wrapper .dataTables_filter {
    float: right;
  }
  .table-responsive {
    overflow-x: auto;
  }
</style>

<script>
$(document).ready(function () {
  // Initialize DataTable first
  var timelogTable = $('#timelogTable').DataTable({
    lengthMenu: [5, 10, 25, 50, 100],
    pageLength: 10,
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search timelog request...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "No records available",
      zeroRecords: "No matching timelog request found",
    },
    order: [[4, 'desc']], // Sort by Request Info column descending
    columnDefs: [
      {
        targets: [5], // Action column
        orderable: false,
        searchable: false
      }
    ]
  });

  // Function to get current Manila time in datetime-local format
  function getCurrentManilaTime() {
    const now = new Date();
    const manilaTime = new Date(now.getTime() + (8 * 60 * 60 * 1000));
    return manilaTime.toISOString().slice(0, 16);
  }

  // Set current time when Add Timelog modal is shown
  $('#addTimelogModal').on('show.bs.modal', function () {
    $('#add_requested_time').val(getCurrentManilaTime());
  });

  // Add Timelog form submit (AJAX)
  $('#addTimelogForm').on('submit', function(e) {
    e.preventDefault();

    // Show loading spinner
    const submitBtn = $(this).find('button[type="submit"]');
    const spinner = submitBtn.find('.spinner-border');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true);
    spinner.removeClass('d-none');
    submitBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Adding...');

    const formData = $(this).serialize() + '&add_timelog=1';

    $.ajax({
      url: window.location.href,
      type: 'POST',
      data: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      dataType: 'json',
      success: function(response) {
        $('#addTimelogModal').modal('hide');
        // Always reload the page after closing the modal, regardless of success or error
        setTimeout(function() {
          location.reload();
        }, 400);
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error:', error);
        console.error('Response:', xhr.responseText);
        $('#addTimelogModal').modal('hide');
        setTimeout(function() {
          location.reload();
        }, 400);
      },
      complete: function() {
        // Reset loading state
        submitBtn.prop('disabled', false);
        submitBtn.html(originalText);
      }
    });
  });

  // Reset form when modal is closed
  $('#addTimelogModal').on('hidden.bs.modal', function () {
    $('#addTimelogForm')[0].reset();
    // Reset button state
    const submitBtn = $(this).find('button[type="submit"]');
    submitBtn.prop('disabled', false);
    submitBtn.html('<span class="spinner-border spinner-border-sm d-none" role="status"></span> Add Request');
  });

  // Enhanced edit button handler
  $(document).on('click', '.btn-edit-timelog', function () {
    var data = $(this).data('request');
    if (typeof data === 'string') {
        data = JSON.parse(data);
    }

    // Auto-populate form fields
    $('#modal_request_id').val(data.request_id);
    $('#modal_requestid_display').val(data.request_id);
    
    // Set employee (manager) automatically
    $('select[name="employee_id"]').val(data.employee_id);
    
    // Format date properly
    const formattedDate = data.date ? data.date.split(' ')[0] : '';
    $('#modal_date').val(formattedDate);
    
    // Set missing field
    $('#modal_missing_field').val(data.missing_field);
    
    // Format requested time for datetime-local input
    const reqTime = data.requested_time ? 
        data.requested_time.replace(' ', 'T').slice(0, 16) : '';
    $('#modal_requested_time').val(reqTime);
    
    // Set other fields
    $('#modal_reason').val(data.reason);
    $('#modal_status').val(data.status);
    $('#modal_requestedat').val(data.requested_at);

    // Disable employee selection since it's manager only
    $('select[name="employee_id"]').prop('disabled', true);

    // Show the modal
    $('#updateModal').modal('show');
  });

  // Add form validation
  $('#updateTimelogForm').on('submit', function(e) {
    // No validation needed since all fields are pre-populated
    // Just prevent submission if date is in future
    const date = $('#modal_date').val();
    const selectedDate = new Date(date);
    const today = new Date();
    
    if (selectedDate > today) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Date cannot be in the future',
            icon: 'error'
        });
        return false;
    }
  });

  // Delete button: AJAX delete with SweetAlert confirmation
  $(document).on('click', '.btn-delete-timelog', function () {
    // Check if button is disabled
    if ($(this).prop('disabled')) {
      return;
    }

    var timelogId = $(this).data('timelog-id');
    var row = $(this).closest('tr');

    Swal.fire({
      title: 'Are you sure?',
      text: "This will permanently delete timelog request ID: " + timelogId,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, Delete',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loading state
        Swal.fire({
          title: 'Deleting...',
          text: 'Please wait while we delete the timelog request.',
          icon: 'info',
          allowOutsideClick: false,
          showConfirmButton: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        $.ajax({
          url: window.location.href,
          type: 'POST',
          data: { delete_timelog_id: timelogId },
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          dataType: 'json',
          success: function(response) {
            if (response.status === 'success') {
              // Remove row from DataTable
              timelogTable.row(row).remove().draw();
              
              Swal.fire({
                title: 'Deleted!',
                text: response.message || 'Timelog request has been deleted successfully.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
            } else {
              Swal.fire({
                title: 'Error!',
                text: response.message || 'Failed to delete timelog request.',
                icon: 'error',
                confirmButtonText: 'OK'
              });
            }
          },
          error: function(xhr, status, error) {
            console.error('Delete Error:', error);
            console.error('Response:', xhr.responseText);
            Swal.fire({
              title: 'Error!',
              text: 'An error occurred while deleting the timelog request. Please try again.',
              icon: 'error',
              confirmButtonText: 'OK'
            });
          }
        });
      }
    });
  });
  // Set max date for date input to today
  var today = new Date().toISOString().split('T')[0];
  $('#add_date').attr('max', today);
  $('#modal_date').attr('max', today);
});
</script>
</div>
</div>
</div>
