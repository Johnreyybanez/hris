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

// Function to send OB request notifications
function sendOBRequestNotifications($conn, $action, $ob_id, $manager_id, $department_id, $date = '', $time_from = '', $time_to = '', $purpose = '', $location = '') {
    $manager_details = getManagerDetails($conn, $manager_id);
    $manager_name = $manager_details['full_name'];
    $department_name = $manager_details['department'];
    
    // Format date and time for display
    $formatted_date = $date ? date('M d, Y', strtotime($date)) : '';
    $formatted_time_from = $time_from ? date('h:i A', strtotime($time_from)) : '';
    $formatted_time_to = $time_to ? date('h:i A', strtotime($time_to)) : '';
    
    $time_range = '';
    if ($formatted_time_from && $formatted_time_to) {
        $time_range = " from $formatted_time_from to $formatted_time_to";
    }
    
    $date_info = $formatted_date ? " on $formatted_date" : '';
    $purpose_info = $purpose ? " - $purpose" : '';
    $location_info = $location ? " at $location" : '';
    
    switch ($action) {
        case 'add':
            // Notify the manager (self)
            $manager_message = "Your official business request (#$ob_id) has been submitted and is Pending approval";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Official business request (#$ob_id) for $manager_name from department $department_name$date_info$time_range$purpose_info$location_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'update':
            // Notify the manager (self)
            $manager_message = "Your official business request (#$ob_id) has been updated";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Official business request (#$ob_id) for $manager_name from department $department_name has been updated$date_info$time_range$purpose_info$location_info";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
            
        case 'delete':
            // Notify the manager (self)
            $manager_message = "Your official business request (#$ob_id) has been deleted";
            insertNotification($conn, $manager_id, $manager_message);
            
            // Notify department heads and admins
            $recipients = getNotificationRecipients($conn, $department_id, $manager_id);
            $admin_message = "Official business request (#$ob_id) for $manager_name from department $department_name has been deleted";
            
            foreach ($recipients as $recipient_id) {
                insertNotification($conn, $recipient_id, $admin_message);
            }
            break;
    }
}

// Handle add OB request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ob'])) {
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $date = $_POST['date'] ?? '';
    $time_from = $_POST['time_from'] ?? '';
    $time_to = $_POST['time_to'] ?? '';
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $status = $_POST['status'] ?? 'Pending';
    $requested_at = date('Y-m-d H:i:s');

    // Validate time range only if both times are provided
    if (!empty($time_from) && !empty($time_to) && strtotime($time_from) >= strtotime($time_to)) {
        $error_message = "Time From must be earlier than Time To.";
    } else {
        // Only check if employee exists
        $check_emp = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $check_emp->bind_param("i", $employee_id);
        $check_emp->execute();
        $emp_result = $check_emp->get_result();

        if ($emp_result->num_rows == 0) {
            $error_message = "Selected employee does not exist.";
            $check_emp->close();
        } else {
            $check_emp->close();

            // Insert the OB request
            $stmt = $conn->prepare("INSERT INTO employeeofficialbusiness (employee_id, date, time_from, time_to, purpose, location, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $employee_id, $date, $time_from, $time_to, $purpose, $location, $status, $requested_at);

            if ($stmt->execute()) {
                $new_ob_id = mysqli_insert_id($conn);
                $stmt->close();
                
                // Send add notifications
                sendOBRequestNotifications(
                    $conn, 
                    'add', 
                    $new_ob_id, 
                    $manager_id, 
                    $department_id,
                    $date,
                    $time_from,
                    $time_to,
                    $purpose,
                    $location
                );
                
                header("Location: manager_ob.php?success=added");
                exit;
            } else {
                $stmt->close();
                $error_message = "Error adding OB request: " . mysqli_error($conn);
            }
        }
    }
}

// Handle update action for OB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ob'])) {
    $ob_id = intval($_POST['ob_id']);
    $status = $_POST['status'] ?? 'Pending';
    $requested_at = $_POST['requested_at'] ?? date('Y-m-d H:i:s');
    $employee_name = $_POST['employee_name'] ?? '';
    $date = $_POST['date'] ?? null;
    $time_from = $_POST['time_from'] ?? null;
    $time_to = $_POST['time_to'] ?? null;
    $purpose = $_POST['purpose'] ?? '';
    $location = $_POST['location'] ?? '';

    // Get employee_id from name
    $employee_id = null;
    if (!empty($employee_name)) {
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
        $stmt->bind_param("s", $employee_name);
        $stmt->execute();
        $stmt->bind_result($employee_id);
        $stmt->fetch();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE employeeofficialbusiness SET employee_id=?, date=?, time_from=?, time_to=?, purpose=?, location=?, status=?, requested_at=? WHERE ob_id=? AND employee_id=? LIMIT 1");
    $stmt->bind_param(
        "isssssssii",
        $employee_id,
        $date,
        $time_from,
        $time_to,
        $purpose,
        $location,
        $status,
        $requested_at,
        $ob_id,
        $manager_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Send update notifications
        sendOBRequestNotifications(
            $conn, 
            'update', 
            $ob_id, 
            $manager_id, 
            $department_id,
            $date,
            $time_from,
            $time_to,
            $purpose,
            $location
        );
        
        header("Location: manager_ob.php?success=updated");
        exit;
    } else {
        $stmt->close();
        $error_message = "Error updating OB request: " . mysqli_error($conn);
    }
}

// Handle AJAX delete request for OB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ob_id']) && !isset($_POST['update_ob']) && !isset($_POST['add_ob'])) {
    $ob_id = intval($_POST['ob_id']);
    
    // Get OB request details before deletion for notifications
    $ob_details_query = mysqli_query($conn, "
        SELECT * FROM employeeofficialbusiness 
        WHERE ob_id = $ob_id AND employee_id = $manager_id
    ");
    
    if ($ob_details_query && mysqli_num_rows($ob_details_query) > 0) {
        $ob_details = mysqli_fetch_assoc($ob_details_query);
        
        $stmt = $conn->prepare("DELETE FROM employeeofficialbusiness WHERE ob_id = ? AND employee_id = ?");
        $stmt->bind_param("ii", $ob_id, $manager_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            // Send delete notifications
            sendOBRequestNotifications(
                $conn, 
                'delete', 
                $ob_id, 
                $manager_id, 
                $department_id,
                $ob_details['date'],
                $ob_details['time_from'],
                $ob_details['time_to'],
                $ob_details['purpose'],
                $ob_details['location']
            );
        }
        
        echo $success ? 'success' : 'error';
        exit;
    } else {
        echo 'error';
        exit;
    }
}

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

// Modified SQL query to show only manager's OB requests - Updated query
$sql = "SELECT 
    ob.ob_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    ob.date,
    ob.time_from,
    ob.time_to,
    ob.purpose,
    ob.location,
    ob.status,
    ob.requested_at,
    d.name AS department_name
FROM employeeofficialbusiness ob
LEFT JOIN employees e ON ob.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
WHERE ob.employee_id = $manager_id
ORDER BY ob.ob_id DESC";
$result = $conn->query($sql);
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
    <h5 class="m-b-10">Official Business Request</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">OB Request</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php if ($_GET['success'] == 'added'): ?>
          OB request has been successfully added!
        <?php elseif ($_GET['success'] == 'updated'): ?>
          OB request has been successfully updated!
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- OB Request Section -->
    <div class="row">
      <div class="col-sm-12">
        <div class="card card-body shadow mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Official Business Requests</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOBModal">
              <i class="bi bi-plus-circle"></i> Add OB Request
            </button>
          </div>
          <div class="table-responsive mt-3">
            <table id="obTable" class="table table-striped table-hover table-bordered" width="100%">
              <thead class="table-dark">
                <tr>
                  <th>OB ID</th>
                  <th>Employee Name</th>
                  <th>Date</th>
                  <th>Time From</th>
                  <th>Time To</th>
                  <th>Purpose</th>
                  <th>Location</th>
                  <th>Status</th>
                  <th>Requested At</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['ob_id'] ?></td>
                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['time_from'] ? date('h:i A', strtotime($row['time_from'])) : '' ?></td>
                        <td><?= $row['time_to'] ? date('h:i A', strtotime($row['time_to'])) : '' ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $row['status'] === 'Approved' ? 'success' : 
                                ($row['status'] === 'Rejected' ? 'danger' : 'warning'
                            ) ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td><?= date("F j, Y h:i A", strtotime($row['requested_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary btn-edit-ob"
                                    data-ob='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-ob" data-ob-id="<?= $row['ob_id'] ?>">
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

    <!-- OB Modal -->
    <div class="modal fade" id="obModal" tabindex="-1" aria-labelledby="obModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" id="updateOBForm" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="obModalLabel">Update Official Business Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="ob_id" id="modal_ob_id">
            <div class="mb-2">
              <label class="form-label">OB ID</label>
              <input type="text" id="modal_obid" class="form-control" readonly>
            </div>
            <div class="mb-2">
              <label class="form-label">Employee Name</label>
              <input type="text" name="employee_name" id="modal_employee_name" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Date</label>
              <input type="date" name="date" id="modal_date" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Time From</label>
              <div class="input-group">
                <input type="time" name="time_from" id="modal_time_from" class="form-control" 
                       required onchange="formatTimeDisplay(this, 'time_from_display')">
                <span class="input-group-text">
                  <span id="time_from_display" class="text-muted small">12-hour format</span>
                </span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Time To</label>
              <div class="input-group">
                <input type="time" name="time_to" id="modal_time_to" class="form-control" 
                       required onchange="formatTimeDisplay(this, 'time_to_display')">
                <span class="input-group-text">
                  <span id="time_to_display" class="text-muted small">12-hour format</span>
                </span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Purpose</label>
              <textarea name="purpose" id="modal_purpose" class="form-control" required></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">Location</label>
              <input type="text" name="location" id="modal_location" class="form-control" required>
            </div>
             <div class="mb-2">
              <label class="form-label">Status</label>
              <input type="text" class="form-control" value="Pending" readonly>
            </div>
            <div class="mb-3">
              <label for="modal_requested_at" class="form-label">Requested At</label>
              <input type="text" name="requested_at" id="modal_requested_at" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="update_ob" class="btn btn-success">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add OB Modal -->
    <div class="modal fade" id="addOBModal" tabindex="-1" aria-labelledby="addOBModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" id="addOBForm" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addOBModalLabel">Add Official Business Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <label class="form-label">OB ID</label>
              <input type="text" class="form-control" value="Auto" readonly>
            </div>
            <div class="mb-2">
              <label class="form-label">Employee Name</label>
              <select name="employee_id" class="form-select" required>
                  <option value="">Select Employee</option>
                  <?php
                  $manager_query = mysqli_query($conn, "
                      SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS name,
                             d.name as department_name 
                      FROM employees e
                      JOIN departments d ON e.department_id = d.department_id
                      WHERE e.employee_id = $manager_id
                  ");
                  if ($manager = mysqli_fetch_assoc($manager_query)) {
                      echo '<option value="' . $manager['employee_id'] . '" selected>' . htmlspecialchars($manager['name']) . '</option>';
                  }
                  ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Time From</label>
              <div class="input-group">
                <input type="time" name="time_from" id="add_time_from" class="form-control" onchange="formatTimeDisplay(this, 'add_time_from_display')">
                <span class="input-group-text">
                  <span id="add_time_from_display" class="text-muted small">12-hour format</span>
                </span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Time To</label>
              <div class="input-group">
                <input type="time" name="time_to" id="add_time_to" class="form-control" onchange="formatTimeDisplay(this, 'add_time_to_display')">
                <span class="input-group-text">
                  <span id="add_time_to_display" class="text-muted small">12-hour format</span>
                </span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Purpose</label>
              <textarea name="purpose" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control">
            </div>
            <input type="hidden" name="status" value="Pending">
            <div class="mb-2">
              <label class="form-label">Status</label>
              <input type="text" class="form-control" value="Pending" readonly>
            </div>
            <div class="mb-2">
              <label class="form-label">Requested At</label>
              <?php 
              $manila_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
              ?>
              <input type="text" class="form-control" value="<?= $manila_time->format('Y-m-d H:i:s') ?>" readonly>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="add_ob" class="btn btn-success">Add OB Request</button>
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
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
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <!-- Bootstrap 5 JS (required for modal and header buttons) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

    <script>
// Global function for formatting time display
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

$(document).ready(function () {
    // Initialize DataTable with enhanced responsive features
    const table = $('#obTable').DataTable({
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal({
                    header: function (row) {
                        return 'OB Request Details';
                    }
                }),
                renderer: $.fn.dataTable.Responsive.renderer.tableAll()
            }
        },
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        pageLength: 10,
        order: [[8, 'desc']], // Order by requested_at column
        columnDefs: [
            {
                targets: [9], // Action column
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
            searchPlaceholder: "Search OB requests...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            zeroRecords: "No matching OB requests found"
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

    // Improved getCurrentManilaTime function
    function getCurrentManilaTime() {
        const now = new Date();
        const options = {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        return now.toLocaleString('en-US', options).replace(',', '');
    }

    // Improved modal population with validation
    function populateOBModal(data) {
        try {
            // Ensure data is an object
            const obData = typeof data === 'string' ? JSON.parse(data) : data;
            
            // Clear previous validation states
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();
            
            // Populate fields with null checking
            const fields = {
                'modal_ob_id': 'ob_id',
                'modal_obid': 'ob_id',
                'modal_employee_name': 'employee_name',
                'modal_date': 'date',
                'modal_time_from': 'time_from',
                'modal_time_to': 'time_to',
                'modal_purpose': 'purpose',
                'modal_location': 'location',
                'modal_requested_at': 'requested_at'
            };

            Object.entries(fields).forEach(([elementId, dataKey]) => {
                const value = obData[dataKey] || '';
                $(`#${elementId}`).val(value);
            });

            // Handle date and time fields specially
            if (obData.date) {
                $('#modal_date').val(obData.date.split(' ')[0]);
            }

            // Set current time if empty
            if (!obData.requested_at) {
                $('#modal_requested_at').val(getCurrentManilaTime());
            }

            // Format times for display with AM/PM
            if (obData.time_from) {
                $('#modal_time_from').val(obData.time_from);
                formatTimeDisplay($('#modal_time_from')[0], 'time_from_display');
            }
            
            if (obData.time_to) {
                $('#modal_time_to').val(obData.time_to);
                formatTimeDisplay($('#modal_time_to')[0], 'time_to_display');
            }
            
        } catch (error) {
            console.error('Error populating modal:', error);
            Swal.fire('Error', 'Failed to load OB request details', 'error');
        }
    }

    // Improved edit button handler
    $(document).on('click', '.btn-edit-ob', function() {
        try {
            const data = $(this).data('ob');
            populateOBModal(data);
            const modal = new bootstrap.Modal(document.getElementById('obModal'));
            modal.show();
        } catch (error) {
            console.error('Error handling edit:', error);
            Swal.fire('Error', 'Failed to open edit form', 'error');
        }
    });

    // Form validation before submit
    $('#updateOBForm').on('submit', function(e) {
        let isValid = true;
        const requiredFields = ['date', 'time_from', 'time_to', 'purpose', 'location'];
        
        requiredFields.forEach(field => {
            const input = $(`#modal_${field}`);
            if (!input.val().trim()) {
                isValid = false;
                input.addClass('is-invalid');
                input.after(`<div class="invalid-feedback">This field is required</div>`);
            }
        });

        // Validate time range
        if ($('#modal_time_from').val() && $('#modal_time_to').val()) {
            if ($('#modal_time_from').val() >= $('#modal_time_to').val()) {
                isValid = false;
                $('#modal_time_to').addClass('is-invalid');
                $('#modal_time_to').after('<div class="invalid-feedback">End time must be after start time</div>');
            }
        }

        if (!isValid) {
            e.preventDefault();
            return false;
        }
    });

    // Reset form when modal is closed
    $('#addOBModal').on('hidden.bs.modal', function () {
        $('#addOBForm')[0].reset();
        // Clear any validation errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        // Reset time displays
        $('#add_time_from_display').text('12-hour format').removeClass('time-display');
        $('#add_time_to_display').text('12-hour format').removeClass('time-display');
    });

    // Reset update modal time displays when closed
    $('#obModal').on('hidden.bs.modal', function () {
        $('#time_from_display').text('12-hour format').removeClass('time-display');
        $('#time_to_display').text('12-hour format').removeClass('time-display');
    });

    // Set minimum date to today for date input
    var today = new Date().toISOString().split('T')[0];
    $('input[name="date"]').attr('min', today);

    // Delete button: AJAX delete with SweetAlert confirmation
    $(document).on('click', '.btn-delete-ob', function () {
        var obId = $(this).data('ob-id');
        var row = $(this).closest('tr');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete OB request ID: " + obId,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '', // same page
                    type: 'POST',
                    data: { ob_id: obId },
                    success: function(response) {
                        if (response.trim() === 'success') {
                            Swal.fire('Deleted!', 'OB request has been deleted.', 'success');
                            table.row(row).remove().draw();
                        } else {
                            Swal.fire('Error', 'Failed to delete OB request.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to delete OB request.', 'error');
                    }
                });
            }
        });
    });
});
</script>

