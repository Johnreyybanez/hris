<?php
session_start();
include_once 'connection.php';

// Security check - ensure user is logged in
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit;
}

class LeaveApprovalManager {
    private $conn;
    private $login_id;
    private $manager_fullname;
    
    public function __construct($connection, $login_id) {
        $this->conn = $connection;
        $this->login_id = $login_id;
        $this->manager_fullname = $this->getManagerFullname();
    }
    
    private function getManagerFullname() {
        $stmt = $this->conn->prepare("
            SELECT CONCAT(e.first_name, ' ', e.last_name) AS fullname 
            FROM employees e 
            JOIN employeelogins el ON e.employee_id = el.employee_id 
            WHERE el.login_id = ?
        ");
        
        if (!$stmt) {
            error_log("Prepare failed: " . $this->conn->error);
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $stmt->bind_param("i", $this->login_id);
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row['fullname'] : '';
    }
    
    public function getManagerName() {
        return $this->manager_fullname;
    }
    
    private function getEmployeeData($leave_request_id) {
        $stmt = $this->conn->prepare("
            SELECT e.employee_id, d.name as department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
            FROM employeeleaverequests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            JOIN departments d ON e.department_id = d.department_id
            WHERE lr.leave_request_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $stmt->bind_param("i", $leave_request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    private function sendNotifications($employee_id, $leave_request_id, $action, $employee_name, $department) {
        try {
            // 1. Notify the employee who made the request
            $notification_message = "Your leave request (#$leave_request_id) has been $action by {$this->manager_fullname}";
            $this->insertNotification($employee_id, $notification_message);

            // 2. Notify department members
            $dept_stmt = $this->conn->prepare("
                SELECT employee_id 
                FROM employees 
                WHERE department_id = (
                    SELECT department_id 
                    FROM employees 
                    WHERE employee_id = ?
                ) AND employee_id != ?
            ");
            
            if ($dept_stmt) {
                $dept_stmt->bind_param("ii", $employee_id, $employee_id);
                $dept_stmt->execute();
                $dept_result = $dept_stmt->get_result();
                
                $dept_notification = "$employee_name's leave request (#$leave_request_id) has been $action";
                while ($dept_employee = $dept_result->fetch_assoc()) {
                    $this->insertNotification($dept_employee['employee_id'], $dept_notification);
                }
                $dept_stmt->close();
            }

            // 3. Notify HR and managers
            $hr_mgr_stmt = $this->conn->prepare("
                SELECT DISTINCT e.employee_id 
                FROM employees e 
                JOIN employeelogins el ON e.employee_id = el.employee_id 
                WHERE el.role IN ('admin', 'hr', 'manager') 
                AND e.employee_id NOT IN (?, ?)
            ");
            
            if ($hr_mgr_stmt) {
                $hr_mgr_stmt->bind_param("ii", $employee_id, $this->login_id);
                $hr_mgr_stmt->execute();
                $hr_mgr_result = $hr_mgr_stmt->get_result();

                $hr_notification = "Leave request (#$leave_request_id) for $employee_name from $department department has been $action";
                while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
                    $this->insertNotification($hr_mgr['employee_id'], $hr_notification);
                }
                $hr_mgr_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            // Don't throw exception here as notifications are secondary to main operation
        }
    }
    
    private function insertNotification($employee_id, $message) {
        $stmt = $this->conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        if ($stmt) {
            $stmt->bind_param("is", $employee_id, $message);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    public function updateLeaveRequest($leave_request_id, $status, $approval_remarks, $approved_at, $requested_at) {
        // Validate inputs
        $leave_request_id = intval($leave_request_id);
        $status = trim($status);
        $approval_remarks = trim($approval_remarks);
        
        if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
            throw new Exception("Invalid status provided.");
        }
        
        // Get employee data before update
        $employee_data = $this->getEmployeeData($leave_request_id);
        if (!$employee_data) {
            throw new Exception("Leave request not found.");
        }
        
        // Update the leave request
        $stmt = $this->conn->prepare("
            UPDATE employeeleaverequests 
            SET status=?, approval_remarks=?, approved_by=?, approved_at=?, requested_at=? 
            WHERE leave_request_id=?
        ");
        
        if (!$stmt) {
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $stmt->bind_param("ssissi", $status, $approval_remarks, $this->login_id, $approved_at, $requested_at, $leave_request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update leave request.");
        }
        
        $stmt->close();
        
        // Send notifications
        $this->sendNotifications(
            $employee_data['employee_id'],
            $leave_request_id,
            $status,
            $employee_data['employee_name'],
            $employee_data['department']
        );
        
        return true;
    }
    
    public function deleteLeaveRequest($leave_request_id) {
        $leave_request_id = intval($leave_request_id);
        
        // Get employee data before deletion
        $employee_data = $this->getEmployeeData($leave_request_id);
        if (!$employee_data) {
            throw new Exception("Leave request not found.");
        }
        
        // Delete the leave request
        $stmt = $this->conn->prepare("DELETE FROM employeeleaverequests WHERE leave_request_id = ?");
        
        if (!$stmt) {
            throw new Exception("Database error occurred. Please try again.");
        }
        
        $stmt->bind_param("i", $leave_request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete leave request.");
        }
        
        $stmt->close();
        
        // Send notifications
        $this->sendNotifications(
            $employee_data['employee_id'],
            $leave_request_id,
            'deleted',
            $employee_data['employee_name'],
            $employee_data['department']
        );
        
        return true;
    }
}

// Initialize the manager
try {
    $leaveManager = new LeaveApprovalManager($conn, $_SESSION['login_id']);
    $manager_fullname = $leaveManager->getManagerName();
} catch (Exception $e) {
    error_log($e->getMessage());
    die("System error occurred. Please try again later.");
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave'])) {
    try {
        $leave_request_id = $_POST['leave_request_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $approval_remarks = $_POST['approval_remarks'] ?? '';
        $approved_at = $_POST['approved_at'] ?? date('Y-m-d H:i:s');
        $requested_at = $_POST['requested_at'] ?? null;
        
        $leaveManager->updateLeaveRequest($leave_request_id, $status, $approval_remarks, $approved_at, $requested_at);
        
        $_SESSION['success_message'] = 'Leave request updated successfully!';
        header("Location: leave_approval.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: leave_approval.php");
        exit;
    }
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_request_id']) && !isset($_POST['update_leave'])) {
    try {
        $leave_request_id = $_POST['leave_request_id'] ?? 0;
        $leaveManager->deleteLeaveRequest($leave_request_id);
        echo 'success';
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo 'error';
    }
    exit;
}

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

// Fetch current user's username and role
$current_username = '';
$current_role = '';
if (isset($_SESSION['login_id'])) {
    $stmt = $conn->prepare("SELECT username, role FROM employeelogins WHERE login_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['login_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $current_username = $row['username'];
            $current_role = $row['role'];
        }
        $stmt->close();
    }
}

// Fetch all departments for the filter dropdown
$departments = [];
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments_result = $conn->query($departments_query);
if ($departments_result) {
    while ($dept_row = $departments_result->fetch_assoc()) {
        $departments[] = $dept_row;
    }
}

// Fetch leave requests with employee and leave type info, manager info, and department info
$sql = "SELECT 
    lr.leave_request_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    lt.name AS leave_type,
    lr.start_date,
    lr.end_date,
    lr.total_days,
    lr.actual_leave_days,
    lr.reason,
    lr.status,
    lr.requested_at,
    ml.login_id AS manager_login_id,
    ml.username AS manager_username,
    ml.employee_id AS manager_empid,
    mgr.first_name AS mgr_first_name,
    mgr.last_name AS mgr_last_name,
    lr.approved_at,
    lr.approval_remarks,
    d.name AS department_name,
    d.department_id
FROM employeeleaverequests lr
LEFT JOIN employees e ON lr.employee_id = e.employee_id
LEFT JOIN leavetypes lt ON lr.leave_type_id = lt.leave_type_id
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN employeelogins ml ON lr.approved_by = ml.login_id
LEFT JOIN employees mgr ON ml.employee_id = mgr.employee_id
GROUP BY lr.leave_request_id
ORDER BY lr.leave_request_id DESC";

$result = $conn->query($sql);
if (!$result) {
    echo "Error fetching leave requests: " . (is_object($conn) ? $conn->error : 'Unknown database error');
    exit;
}
?>

<div class="pc-container">
  <div class="pc-content">
    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Leave Approval Request</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Leave Approval</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Leave Request Section -->
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Leave Requests</h5>
      </div>
      
      <!-- Department Filter -->
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
        <table id="leaveTable" class="table table-bordered table-hover">
          <thead class="table-success">
            <tr>
              <th>ID</th>
              <th>Employee Name</th>
              <th>Department</th>
              <th>Leave Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Total Days</th>
              <th>Actual Days</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Requested At</th>
              <th>Approved By</th>
              <th>Approved At</th>
              <th>Remarks</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr data-status="<?= htmlspecialchars($row['status']) ?>" 
                  data-department="<?= htmlspecialchars($row['department_name']) ?>">
                <td><?= htmlspecialchars($row['leave_request_id']) ?></td>
                <td><?= htmlspecialchars($row['employee_name']) ?></td>
                <td><?= htmlspecialchars($row['department_name']) ?></td>
                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                <td><?= htmlspecialchars($row['start_date']) ?></td>
                <td><?= htmlspecialchars($row['end_date']) ?></td>
                <td>
                  <?= number_format((float)$row['total_days'], 1) ?> 
                  <?= ((float)$row['total_days'] == 1.0) ? 'day' : 'days' ?>
                </td>
                <td>
                  <?= number_format((float)$row['actual_leave_days'], 1) ?> 
                  <?= ((float)$row['actual_leave_days'] == 1.0) ? 'day' : 'days' ?>
                </td>
                <td><?= htmlspecialchars($row['reason']) ?></td>
                <td>
                  <span class="badge bg-<?= $row['status'] == 'Approved' ? 'success' : ($row['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td><?= $row['requested_at'] ? date('F j, Y h:i A', strtotime($row['requested_at'])) : '' ?></td>
                <td><?= htmlspecialchars(trim($row['mgr_first_name'] . ' ' . $row['mgr_last_name'])) ?></td>
                <td><?= $row['approved_at'] ? date('F j, Y h:i A', strtotime($row['approved_at'])) : '' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks']) ?></td>
                <td>
                  <!-- Edit Icon Button -->
                  <button 
                    class="btn btn-sm btn-primary btn-edit-leave" 
                    data-leave='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'
                    title="Edit">
                    <i class="bi bi-pencil-square"></i>
                  </button>

                  <button 
                    class="btn btn-sm btn-danger btn-delete-leave" 
                    data-leave-id="<?= $row['leave_request_id'] ?>"
                    title="Delete"
                    <?= ($row['status'] === 'Approved') ? 'disabled' : '' ?>>
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Leave Modal -->
      <div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form method="POST" id="updateLeaveForm" class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="leaveModalLabel">Update Leave Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="leave_request_id" id="modal_leave_request_id">
              <div class="mb-2">
                <label class="form-label">Leave ID</label>
                <input type="text" id="modal_leave_id" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Employee Name</label>
                <input type="text" id="modal_employee_name" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Department</label>
                <input type="text" id="modal_department_name" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Leave Type</label>
                <input type="text" id="modal_leave_type" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Start Date</label>
                <input type="date" id="modal_start_date" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">End Date</label>
                <input type="date" id="modal_end_date" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Total Days</label>
                <input type="number" id="modal_total_days" class="form-control" min="0.5" step="0.5" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Actual Days</label>
                <input type="number" id="modal_actual_leave_days" class="form-control" min="0.5" step="0.5" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Reason</label>
                <textarea id="modal_reason" class="form-control" readonly></textarea>
              </div>
              <div class="mb-2">
                <label class="form-label">Approval Remarks</label>
                <textarea name="approval_remarks" id="modal_remarks" class="form-control"></textarea>
              </div>
              <div class="mb-3">
                <label for="modal_status" class="form-label">Status</label>
                <select name="status" id="modal_status" class="form-select" required>
                  <option value="">Select Status</option>
                  <option value="Pending">Pending</option>
                  <option value="Approved">Approved</option>
                  <option value="Rejected">Rejected</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="modal_requested_at" class="form-label">Requested At</label>
                <input type="text" name="requested_at" id="modal_requested_at" class="form-control">
              </div>
              <div class="mb-3">
                <label for="modal_approved_by" class="form-label">Approved By</label>
                <input type="text" name="approved_by" id="modal_approved_by" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label for="modal_approved_at" class="form-label">Approved At</label>
                <input type="datetime-local" name="approved_at" id="modal_approved_at" class="form-control" readonly>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="update_leave" class="btn btn-success">Save</button>
            </div>
          </form>
        </div>
      </div>

      <!-- DataTables + Bootstrap Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
.table th:nth-child(1), .table td:nth-child(1) { min-width: 50px; } /* ID */
.table th:nth-child(2), .table td:nth-child(2) { min-width: 120px; } /* Employee Name */
.table th:nth-child(3), .table td:nth-child(3) { min-width: 100px; } /* Department */
.table th:nth-child(4), .table td:nth-child(4) { min-width: 100px; } /* Leave Type */
.table th:nth-child(5), .table td:nth-child(5) { min-width: 100px; } /* Start Date */
.table th:nth-child(6), .table td:nth-child(6) { min-width: 100px; } /* End Date */
.table th:nth-child(7), .table td:nth-child(7) { min-width: 80px; } /* Total Days */
.table th:nth-child(8), .table td:nth-child(8) { min-width: 80px; } /* Actual Days */
.table th:nth-child(9), .table td:nth-child(9) { min-width: 150px; } /* Reason */
.table th:nth-child(10), .table td:nth-child(10) { min-width: 80px; } /* Status */
.table th:nth-child(11), .table td:nth-child(11) { min-width: 120px; } /* Requested At */
.table th:nth-child(12), .table td:nth-child(12) { min-width: 120px; } /* Approved By */
.table th:nth-child(13), .table td:nth-child(13) { min-width: 120px; } /* Approved At */
.table th:nth-child(14), .table td:nth-child(14) { min-width: 150px; } /* Remarks */
.table th:nth-child(15), .table td:nth-child(15) { min-width: 100px; } /* Actions */
</style>

<!-- Add responsive DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>



<script>
// Initialize DataTable
var table = $('#leaveTable').DataTable({
  scrollX: true,
  autoWidth: false,
  lengthMenu: [5, 10, 25, 50, 100],
  pageLength: 10,
  language: {
    search: "_INPUT_",
    searchPlaceholder: "Search leave request...",
    lengthMenu: "Show _MENU_ entries",
    info: "Showing _START_ to _END_ of _TOTAL_ entries",
    infoEmpty: "No records available",
    zeroRecords: "No matching leave request found"
  }
});

// Department filter functionality
$('#departmentFilter').on('change', function() {
  var selectedDepartment = $(this).val();
  
  if (selectedDepartment === '') {
    table.column(2).search('').draw();
  } else {
    table.column(2).search(selectedDepartment, true, false).draw();
  }
});

// Status filter functionality
$('#statusFilter').on('change', function() {
  var selectedStatus = $(this).val();
  
  if (selectedStatus === '') {
    // Show all records
    table.column(9).search('').draw(); // Column 9 is the status column
  } else {
    // Search in the status column
    table.column(9).search(selectedStatus, false, false).draw();
  }
});

function populateLeaveModal(data) {
  $('#modal_leave_request_id').val(data.leave_request_id || '');
  $('#modal_leave_id').val(data.leave_request_id || '');
  $('#modal_employee_name').val(data.employee_name || '');
  $('#modal_department_name').val(data.department_name || '');
  $('#modal_leave_type').val(data.leave_type || '');
  $('#modal_start_date').val(data.start_date || '');
  $('#modal_end_date').val(data.end_date || '');
  $('#modal_total_days').val(data.total_days || '');
  $('#modal_actual_leave_days').val(data.actual_leave_days || '');
  $('#modal_reason').val(data.reason || '');
  $('#modal_remarks').val(data.approval_remarks || '');
  $('#modal_status').val(data.status || '');
  $('#modal_requested_at').val(data.requested_at || '');
  
  if (data.status === 'Approved') {
    $('#modal_approved_by').val('<?= addslashes($manager_fullname) ?>');
  } else {
    $('#modal_approved_by').val('');
  }
  
  if (data.approved_at) {
    var approvedAt = data.approved_at.replace(' ', 'T').slice(0, 16);
    $('#modal_approved_at').val(approvedAt);
  } else {
    $('#modal_approved_at').val('');
  }
}

// Edit button: populate modal with row data
$(document).on('click', '.btn-edit-leave', function () {
  var data = $(this).data('leave');
  if (typeof data === 'string') {
    try {
      data = JSON.parse(data);
    } catch (e) {
      console.error('Error parsing JSON:', e);
      return;
    }
  }
  populateLeaveModal(data);
  $('#leaveModal').modal('show');
});

// Auto-update Approved By and Approved At if status is set to Approved or Rejected
$('#modal_status').on('change', function () {
  if ($(this).val() === 'Approved' || $(this).val() === 'Rejected') {
    $('#modal_approved_by').val('<?= addslashes($manager_fullname) ?>');
    
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
    $('#modal_approved_at').val(formattedDate);
  } else {
    $('#modal_approved_by').val('');
    $('#modal_approved_at').val('');
  }
});

// Delete button: AJAX delete with SweetAlert confirmation
$(document).on('click', '.btn-delete-leave', function () {
  var leaveId = $(this).data('leave-id');
  var row = $(this).closest('tr');
  
  Swal.fire({
    title: 'Are you sure?',
    text: "This will delete leave request ID: " + leaveId,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: '',
        type: 'POST',
        data: { leave_request_id: leaveId },
        success: function(response) {
          if (response.trim() === 'success') {
            Swal.fire({
              title: 'Deleted!',
              text: 'Leave request has been deleted. The employee has been notified.',
              icon: 'success',
              timer: 3000,
              showConfirmButton: false
            });
            table.row(row).remove().draw();
          } else {
            Swal.fire('Error', 'Failed to delete leave request.', 'error');
          }
        },
        error: function() {
          Swal.fire('Error', 'Failed to delete leave request.', 'error');
        }
      });
    }
  });
});
</script>

    </div>
  </div>
</div>
