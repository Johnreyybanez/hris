<?php
session_start();
date_default_timezone_set('Asia/Manila');
include_once 'connection.php';

$manager_info = null;
if (isset($_SESSION['login_id'])) {
    $manager_query = "SELECT e.first_name, e.last_name, el.username 
                     FROM employeelogins el 
                     JOIN employees e ON el.employee_id = e.employee_id 
                     WHERE el.login_id = ?";
    $stmt = $conn->prepare($manager_query);
    $stmt->bind_param('i', $_SESSION['login_id']);
    $stmt->execute();
    $manager_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ob'])) {
    $ob_id = $_POST['ob_id'];
    $status = $_POST['status'];
    $approval_remarks = trim($_POST['approval_remarks']);
    $approved_by = $_SESSION['login_id'] ?? null;
    
    // Get employee and department info
    $stmt = $conn->prepare("
        SELECT e.employee_id, d.name as department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employeeofficialbusiness ob
        JOIN employees e ON ob.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE ob.ob_id = ?
    ");
    $stmt->bind_param("i", $ob_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();
    
    // Use current Manila time for approved_at
    date_default_timezone_set('Asia/Manila');
    $approved_at = date('Y-m-d H:i:s');

    // Update the OB request
    $stmt = $conn->prepare("UPDATE employeeofficialbusiness SET status=?, approval_remarks=?, approved_by=?, approved_at=? WHERE ob_id=?");
    $stmt->bind_param("ssisi", $status, $approval_remarks, $approved_by, $approved_at, $ob_id);
    $update_success = $stmt->execute();
    $stmt->close();

    if ($update_success) {
        // Insert notification for the employee
        $manager_name = trim($manager_info['first_name'] . ' ' . $manager_info['last_name']);
        $notification_message = "Your Official Business request (#$ob_id) has been $status by $manager_name";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department_id = (
                SELECT department_id 
                FROM employees 
                WHERE employee_id = ?
            ) AND employee_id != ?
        ");
        $dept_stmt->bind_param("ii", $employee_id, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's Official Business request (#$ob_id) has been $status";
        while ($dept_employee = $dept_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $dept_employee['employee_id'], $dept_notification);
            $stmt->execute();
            $stmt->close();
        }
        $dept_stmt->close();

        // Notify HR and managers
        $hr_mgr_stmt = $conn->prepare("
            SELECT DISTINCT e.employee_id 
            FROM employees e 
            JOIN employeelogins el ON e.employee_id = el.employee_id 
            WHERE el.role IN ('admin', 'hr', 'manager') 
            AND e.employee_id NOT IN (?, ?)
        ");
        $hr_mgr_stmt->bind_param("ii", $employee_id, $_SESSION['login_id']);
        $hr_mgr_stmt->execute();
        $hr_mgr_result = $hr_mgr_stmt->get_result();

        $hr_notification = "Official Business request (#$ob_id) for $employee_name from $department department has been $status";
        while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $hr_mgr['employee_id'], $hr_notification);
            $stmt->execute();
            $stmt->close();
        }
        $hr_mgr_stmt->close();
    }

    header("Location: ob_approval.php");
    exit;
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ob_id'])) {
    $delete_id = intval($_POST['delete_ob_id']);

    // Get employee and department info before deletion
    $stmt = $conn->prepare("
        SELECT e.employee_id, d.name as department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employeeofficialbusiness ob
        JOIN employees e ON ob.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE ob.ob_id = ?
    ");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Delete the OB request
    $stmt = $conn->prepare("DELETE FROM employeeofficialbusiness WHERE ob_id = ?");
    $stmt->bind_param("i", $delete_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Notify the employee
        $manager_name = trim($manager_info['first_name'] . ' ' . $manager_info['last_name']);
        $notification_message = "Your Official Business request (#$delete_id) has been deleted by $manager_name";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department_id = (
                SELECT department_id 
                FROM employees 
                WHERE employee_id = ?
            ) AND employee_id != ?
        ");
        $dept_stmt->bind_param("ii", $employee_id, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's Official Business request (#$delete_id) has been deleted";
        while ($dept_employee = $dept_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $dept_employee['employee_id'], $dept_notification);
            $stmt->execute();
            $stmt->close();
        }
        $dept_stmt->close();

        // Notify HR and managers
        $hr_mgr_stmt = $conn->prepare("
            SELECT DISTINCT e.employee_id 
            FROM employees e 
            JOIN employeelogins el ON e.employee_id = el.employee_id 
            WHERE el.role IN ('admin', 'hr', 'manager') 
            AND e.employee_id NOT IN (?, ?)
        ");
        $hr_mgr_stmt->bind_param("ii", $employee_id, $_SESSION['login_id']);
        $hr_mgr_stmt->execute();
        $hr_mgr_result = $hr_mgr_stmt->get_result();

        $hr_notification = "Official Business request (#$delete_id) for $employee_name from $department department has been deleted";
        while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $hr_mgr['employee_id'], $hr_notification);
            $stmt->execute();
            $stmt->close();
        }
        $hr_mgr_stmt->close();
    }

    echo $success ? 'success' : 'error';
    exit;
}

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

// Modified SQL query to include department information
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
    ob.approved_by,
    ob.approved_at,
    ob.approval_remarks,
    ml.login_id AS manager_login_id,
    ml.username AS manager_username,
    mgr.first_name AS mgr_first_name,
    mgr.last_name AS mgr_last_name,
    d.name AS department_name,
    d.department_id
FROM employeeofficialbusiness ob
LEFT JOIN employees e ON ob.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN employeelogins ml ON ob.approved_by = ml.login_id
LEFT JOIN employees mgr ON ml.employee_id = mgr.employee_id
ORDER BY ob.requested_at DESC";

// Check database connection
if (!$conn) {
    error_log("Database connection failed");
    die("Database connection error. Please try again later.");
}

// Initialize variables
$result = null;
$departments = [];

try {
    // Verify that $sql is defined
    if (!isset($sql) || empty($sql)) {
        throw new Exception("SQL query is not defined");
    }
    
    // Execute the main query
    $result = $conn->query($sql);
    if (!$result) {
        $error_message = $conn ? $conn->error : "Database connection error";
        throw new Exception("Error fetching OB requests: " . $error_message);
    }
    
    // Fetch departments for filter dropdown
    $departments_query = "SELECT department_id, name FROM departments ORDER BY name";
    $departments_result = $conn->query($departments_query);
    
    if ($departments_result === false) {
        error_log("Error fetching departments: " . $conn->error);
        // Continue with empty departments array - this won't break the page
    } else {
        while ($dept_row = $departments_result->fetch_assoc()) {
            $departments[] = $dept_row;
        }
        $departments_result->free();
    }
    
} catch (Exception $e) {
    error_log("Database error in ob_approval.php: " . $e->getMessage());
    die("An error occurred while loading the page. Please try again later.");
}

?>

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
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Official Business</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card card-body shadow mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">OB Requests</h5>
          </div>
          
          <!-- Department and Status Filters -->
          <div class="row mb-3">
            <div class="col-md-3">
              <label for="departmentFilter" class="form-label">Filter by Department:</label>
              <select id="departmentFilter" class="form-select">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                  <option value="<?= htmlspecialchars($dept['name']) ?>">
                    <?= htmlspecialchars($dept['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="statusFilter" class="form-label">Filter by Status:</label>
              <select id="statusFilter" class="form-select">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>
          </div>
          
        <div class="table-responsive">
  <table id="obTable" class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>OB ID</th>
        <th>Employee</th>
        <th>Department</th>
        <th>Date</th>
        <th>Time From</th>
        <th>Time To</th>
        <th>Purpose</th>
        <th>Location</th>
        <th>Status</th>
        <th>Requested At</th>
        <th>Approved By</th>
        <th>Approved At</th>
        <th>Remarks</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <tr data-status="<?= htmlspecialchars($row['status']) ?>" 
              data-department="<?= htmlspecialchars($row['department_name']) ?>">
            <td><?= htmlspecialchars($row['ob_id']) ?></td>
            <td><?= htmlspecialchars($row['employee_name']) ?></td>
            <td><?= htmlspecialchars($row['department_name']) ?></td>
            <td><?= htmlspecialchars($row['date']) ?></td>
            <td>
              <?= date('g:i A', strtotime($row['time_from'])) ?>
            </td>
            <td>
              <?= date('g:i A', strtotime($row['time_to'])) ?>
            </td>
            <td><?= htmlspecialchars($row['purpose']) ?></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td>
              <span class="badge bg-<?= $row['status'] == 'Approved' ? 'success' : ($row['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>
            <td>
              <?= date('F j, Y g:i A', strtotime($row['requested_at'])) ?>
            </td>
            <td><?= htmlspecialchars($row['mgr_first_name'] . ' ' . $row['mgr_last_name']) ?></td>
            <td>
              <?= $row['approved_at'] ? date('F j, Y g:i A', strtotime($row['approved_at'])) : 'N/A' ?>
            </td>
            <td><?= htmlspecialchars($row['approval_remarks']) ?></td>
            <td>
              <!-- Edit Icon Button -->
              <button 
                class="btn btn-sm btn-primary btn-edit-ob" 
                data-bs-toggle="modal" 
                data-bs-target="#updateModal"
                data-ob='<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'
                title="Edit">
                <i class="bi bi-pencil-square"></i>
              </button>

              <!-- Delete Icon Button -->
              <button 
                class="btn btn-sm btn-danger btn-delete-ob" 
                data-ob-id="<?= $row['ob_id'] ?>"
                title="Delete"
                <?= ($row['status'] === 'Approved') ? 'disabled' : '' ?>>
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="14" class="text-center">
            <div class="alert alert-info">
              <i class="bi bi-info-circle"></i>
              No OB requests found in the database.
              <br><small>Please add some OB requests to see data here.</small>
            </div>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="updateObForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="updateModalLabel">Update Official Business</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Read-only fields -->
          <input type="hidden" name="ob_id" id="modal_ob_id">
          <div class="mb-2">
            <label class="form-label">OB ID</label>
            <input type="text" id="modal_obid_display" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Employee Name</label>
            <input type="text" id="modal_empname" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Department</label>
            <input type="text" id="modal_department" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Date</label>
            <input type="text" id="modal_date" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Time From</label>
            <input type="text" id="modal_timefrom" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Time To</label>
            <input type="text" id="modal_timeto" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Purpose</label>
            <input type="text" id="modal_purpose" class="form-control" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Location</label>
            <input type="text" id="modal_location" class="form-control" readonly>
          </div>
          <!-- Editable fields -->
          <div class="mb-3">
            <label for="modal_status" class="form-label">Status</label>
            <select name="status" id="modal_status" class="form-select" required>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="modal_requestedat" class="form-label">Requested At</label>
            <input type="text" name="requested_at" id="modal_requestedat" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label for="modal_approvedby" class="form-label">Approved By</label>
            <input type="text" name="approved_by" id="modal_approvedby" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label for="modal_approvedat" class="form-label">Approved At</label>
            <input type="datetime-local" name="approved_at" id="modal_approvedat" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label for="modal_remarks" class="form-label">Approval Remarks</label>
            <input type="text" name="approval_remarks" id="modal_remarks" class="form-control" placeholder="Remarks">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_ob" class="btn btn-primary">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- DataTables JS & CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 JS & CSS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

/* Enhanced responsive styles for mobile devices */
@media screen and (max-width: 991px) {
    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    
    .table {
        font-size: 0.875rem;
        min-width: 1200px; /* Ensure horizontal scroll on smaller screens */
    }
    
    .table th,
    .table td {
        white-space: nowrap;
        padding: 0.5rem 0.25rem;
        vertical-align: middle;
    }
    
    /* Make action buttons smaller on mobile */
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .btn-sm i {
        font-size: 0.75rem;
    }
}

@media screen and (max-width: 767px) {
    /* DataTables controls responsive layout */
    div.dataTables_wrapper div.dataTables_length,
    div.dataTables_wrapper div.dataTables_filter,
    div.dataTables_wrapper div.dataTables_info,
    div.dataTables_wrapper div.dataTables_paginate {
        text-align: center;
        margin-top: 8px;
        margin-bottom: 8px;
        float: none !important;
    }
    
    div.dataTables_wrapper div.dataTables_filter {
        margin-bottom: 15px;
    }
    
    div.dataTables_wrapper div.dataTables_filter input {
        width: 100% !important;
        max-width: 300px;
        margin: 0 auto;
        display: block;
    }
    
    div.dataTables_wrapper div.dataTables_length select {
        width: auto;
        margin: 0 auto;
        display: block;
    }
    
    div.dataTables_wrapper div.dataTables_paginate ul.pagination {
        justify-content: center !important;
        flex-wrap: wrap;
    }
    
    div.dataTables_wrapper div.dataTables_paginate .paginate_button {
        padding: 0.25rem 0.5rem;
        margin: 0.125rem;
    }
    
    /* Table specific mobile styles */
    .table {
        font-size: 0.75rem;
        min-width: 1400px; /* Increased for better mobile scroll */
    }
    
    .table th,
    .table td {
        padding: 0.375rem 0.25rem;
        font-size: 0.75rem;
        line-height: 1.2;
    }
    
    /* Filter controls responsive */
    .row.mb-3 .col-md-3 {
        margin-bottom: 1rem;
    }
    
    .form-select,
    .form-control {
        font-size: 0.875rem;
    }
    
    /* Modal responsive adjustments */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-body {
        padding: 1rem 0.75rem;
    }
    
    .modal-body .form-control,
    .modal-body .form-select {
        font-size: 0.875rem;
    }
}

@media screen and (max-width: 576px) {
    /* Extra small devices */
    .table {
        font-size: 0.7rem;
        min-width: 1500px; /* Even more scroll space for very small screens */
    }
    
    .table th,
    .table td {
        padding: 0.25rem 0.125rem;
        font-size: 0.7rem;
    }
    
    .btn-sm {
        padding: 0.125rem 0.25rem;
        font-size: 0.7rem;
    }
    
    .btn-sm i {
        font-size: 0.7rem;
    }
    
    /* Card header responsive */
    .card-header h5 {
        font-size: 1rem;
    }
    
    /* Page header responsive */
    .page-header-title h5 {
        font-size: 1.1rem;
    }
    
    /* Badge responsive */
    .badge {
        font-size: 0.65rem;
        padding: 0.25em 0.4em;
    }
    
    /* DataTables info text smaller */
    div.dataTables_wrapper div.dataTables_info {
        font-size: 0.75rem;
        padding-top: 0.5rem;
    }
}

/* Action buttons styling */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 0.25rem;
    flex-wrap: nowrap;
}

.time-display {
    font-weight: bold;
    color: #0d6efd;
}

/* Ensure table stays within container */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Status badge responsive */
.badge {
    white-space: nowrap;
}

/* Ensure long text doesn't break layout */
.table td {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Specific column width adjustments for better mobile experience */
.table th:nth-child(1), .table td:nth-child(1) { min-width: 60px; } /* OB ID */
.table th:nth-child(2), .table td:nth-child(2) { min-width: 120px; } /* Employee */
.table th:nth-child(3), .table td:nth-child(3) { min-width: 100px; } /* Department */
.table th:nth-child(4), .table td:nth-child(4) { min-width: 100px; } /* Date */
.table th:nth-child(5), .table td:nth-child(5) { min-width: 90px; } /* Time From */
.table th:nth-child(6), .table td:nth-child(6) { min-width: 90px; } /* Time To */
.table th:nth-child(7), .table td:nth-child(7) { min-width: 150px; } /* Purpose */
.table th:nth-child(8), .table td:nth-child(8) { min-width: 120px; } /* Location */
.table th:nth-child(9), .table td:nth-child(9) { min-width: 80px; } /* Status */
.table th:nth-child(10), .table td:nth-child(10) { min-width: 130px; } /* Requested At */
.table th:nth-child(11), .table td:nth-child(11) { min-width: 120px; } /* Approved By */
.table th:nth-child(12), .table td:nth-child(12) { min-width: 130px; } /* Approved At */
.table th:nth-child(13), .table td:nth-child(13) { min-width: 150px; } /* Remarks */
.table th:nth-child(14), .table td:nth-child(14) { min-width: 100px; } /* Actions */

/* Alert responsive styling */
.alert {
    font-size: 0.875rem;
}

@media screen and (max-width: 576px) {
    .alert {
        font-size: 0.8rem;
        padding: 0.5rem;
    }
    
    .alert small {
        font-size: 0.75rem;
    }
}
</style>

<script>
// Initialize DataTable with custom ordering
var table = $('#obTable').DataTable({
  scrollX: true,
  autoWidth: false,
  lengthMenu: [5, 10, 25, 50, 100],
  pageLength: 10,
  language: {
    search: "_INPUT_",
    searchPlaceholder: "Search OB request...",
    lengthMenu: "Show _MENU_ entries",
    info: "Showing _START_ to _END_ of _TOTAL_ entries",
    infoEmpty: "No records available",
    zeroRecords: "No matching OB request found"
  }
});

// Department filter functionality - Fixed to search by department name
$('#departmentFilter').on('change', function() {
    var selectedDepartment = $(this).val();
    table
        .columns(2) // Department column index
        .search(selectedDepartment ? '^' + selectedDepartment + '$' : '', true, false)
        .draw();
});

// Status filter functionality
$('#statusFilter').on('change', function() {
    var selectedStatus = $(this).val();
    if (selectedStatus === '') {
        // Show all rows
        table.column(8).search('').draw();
    } else {
        // Search for the status text within the badge
        table.column(8).search(selectedStatus).draw();
    }
});

// Edit button: populate modal with row data
$(document).on('click', '.btn-edit-ob', function () {
    var data = $(this).data('ob');
    if (typeof data === 'string') data = JSON.parse(data);

    $('#modal_ob_id').val(data.ob_id || '');
    $('#modal_obid_display').val(data.ob_id || '');
    $('#modal_empname').val(data.employee_name || '');
    $('#modal_department').val(data.department_name || '');
    $('#modal_date').val(data.date || '');
    $('#modal_timefrom').val(data.time_from || '');
    $('#modal_timeto').val(data.time_to || '');
    $('#modal_purpose').val(data.purpose || '');
    $('#modal_location').val(data.location || '');
    $('#modal_status').val(data.status || '');
    $('#modal_requestedat').val(data.requested_at || '');
    $('#modal_approvedby').val(data.manager_username || data.approved_by || '');
    
    if (data.approved_at) {
      var approvedAt = data.approved_at.replace(' ', 'T').slice(0, 16);
      $('#modal_approvedat').val(approvedAt);
    } else {
      $('#modal_approvedat').val('');
    }
    
    $('#modal_remarks').val(data.approval_remarks || '');
});

// Add current manager info
var currentManager = <?php echo json_encode([
    'name' => $manager_info ? trim($manager_info['first_name'] . ' ' . $manager_info['last_name']) : '',
    'username' => $manager_info['username'] ?? '',
    'login_id' => $_SESSION['login_id'] ?? ''
]); ?>;

// Handle status change to automatically set current Manila time
$('#modal_status').on('change', function() {
    if ($(this).val() === 'Approved' || $(this).val() === 'Rejected') {
      // Get current date/time in Asia/Manila timezone
      var now = new Date();
      var options = {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      };
      var manilaTime = now.toLocaleString('en-US', options);
      var parts = manilaTime.split(',');
      var datePart = parts[0].trim().split('/');
      var timePart = parts[1].trim();
      var formattedDate = `${datePart[2]}-${datePart[0].padStart(2, '0')}-${datePart[1].padStart(2, '0')}T${timePart}`;
      $('#modal_approvedat').val(formattedDate);

      // Set the approver name to current manager
      var approverName = currentManager.name || currentManager.username || 'Unknown Manager';
      $('#modal_approvedby').val(approverName);
    } else {
      $('#modal_approvedat').val('');
      $('#modal_approvedby').val('');
    }
});

// Reset modal on close
$('#updateModal').on('hidden.bs.modal', function () {
    $('#updateObForm')[0].reset();
});

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
          data: { delete_ob_id: obId },
          success: function(response) {
            if (response.trim() === 'success') {
              Swal.fire('Deleted!', 'OB request has been deleted. The employee has been notified.', 'success');
              // Remove row from DataTable
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

</script>

<!-- Bootstrap 5 JS (required for modal and header buttons) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- If you have custom JS for header/sidebar toggles, include it here -->
<script src="vendor/header.js"></script>
<script src="vendor/sidebar.js"></script>

        </div>
      </div>
    </div>
  </div>
</div>