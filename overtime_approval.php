<?php
session_start();
date_default_timezone_set('Asia/Manila');
include_once 'connection.php';

// Get current manager's info
$manager_info = null;
if (isset($_SESSION['login_id'])) {
    $manager_query = "SELECT e.first_name, e.last_name, el.username, el.employee_id 
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_overtime'])) {
    $overtime_id = intval($_POST['overtime_id']);
    $status = $_POST['status'];
    $remarks = trim($_POST['remarks']);
    $approved_by = $manager_info['employee_id'] ?? null;
    $approved_at = date('Y-m-d H:i:s');

    // Get employee and department info
    $stmt = $conn->prepare("
        SELECT e.employee_id, d.name as department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM overtime o
        JOIN employees e ON o.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE o.overtime_id = ?
    ");
    $stmt->bind_param("i", $overtime_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Update the overtime request
    $stmt = $conn->prepare("UPDATE overtime SET approval_status=?, remarks=?, approved_by=?, updated_at=? WHERE overtime_id=?");
    $stmt->bind_param("ssisi", $status, $remarks, $approved_by, $approved_at, $overtime_id);
    $update_success = $stmt->execute();
    $stmt->close();

    if ($update_success) {
        // Notify the employee
        $notification_message = "Your overtime request (#$overtime_id) has been $status";
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
        
        $dept_notification = "$employee_name's overtime request (#$overtime_id) has been $status";
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

        $hr_notification = "Overtime request (#$overtime_id) for $employee_name from $department department has been $status";
        while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $hr_mgr['employee_id'], $hr_notification);
            $stmt->execute();
            $stmt->close();
        }
        $hr_mgr_stmt->close();
    }

    header("Location: overtime_approval.php");
    exit;
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['overtime_id']) && !isset($_POST['update_overtime'])) {
    $overtime_id = intval($_POST['overtime_id']);

    // Get employee and department info before deletion
    $stmt = $conn->prepare("
        SELECT e.employee_id, d.name as department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM overtime o
        JOIN employees e ON o.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE o.overtime_id = ?
    ");
    $stmt->bind_param("i", $overtime_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Delete the overtime request
    $stmt = $conn->prepare("DELETE FROM overtime WHERE overtime_id = ?");
    $stmt->bind_param("i", $overtime_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Notify the employee
        $notification_message = "Your overtime request (#$overtime_id) has been deleted";
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
        
        $dept_notification = "$employee_name's overtime request (#$overtime_id) has been deleted";
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

        $hr_notification = "Overtime request (#$overtime_id) for $employee_name from $department department has been deleted";
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

// Fetch all departments for the filter dropdown
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($dept_row = $departments_result->fetch_assoc()) {
    $departments[] = $dept_row;
}

// Fetch overtime requests with department information
$sql = "SELECT 
    o.overtime_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    o.date,
    o.start_time,
    o.end_time,
    o.total_hours,
    o.reason,
    o.approval_status,
    o.created_at,
    o.updated_at,
    o.approved_by,
    o.remarks,
    CONCAT(m.first_name, ' ', m.last_name) AS approved_by_name,
    d.name AS department_name,
    d.department_id
FROM overtime o
JOIN employees e ON o.employee_id = e.employee_id
JOIN departments d ON e.department_id = d.department_id
LEFT JOIN employees m ON o.approved_by = m.employee_id
ORDER BY o.created_at DESC";

$result = $conn->query($sql);
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Overtime Approval Request</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
              <li class="breadcrumb-item" aria-current="page">Overtime Approval</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Overtime Request Section -->
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Overtime Requests</h5>
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
  <table id="overtimeTable" class="table table-bordered table-hover">
    <thead class="table-success">
      <tr>
        <th>ID</th>
        <th>Employee Name</th>
        <th>Department</th>
        <th>Date</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Total Hours</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Created At</th>
        <th>Approved By</th>
        <th>Updated At</th>
        <th>Remarks</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr data-status="<?= htmlspecialchars($row['approval_status']) ?>" data-department="<?= htmlspecialchars($row['department_name']) ?>">
            <td><?= htmlspecialchars($row['overtime_id']) ?></td>
            <td><?= htmlspecialchars($row['employee_name']) ?></td>
            <td><?= htmlspecialchars($row['department_name']) ?></td>
            <td><?= htmlspecialchars($row['date']) ?></td>
            <td><?= date('h:i A', strtotime($row['start_time'])) ?></td>
            <td><?= date('h:i A', strtotime($row['end_time'])) ?></td>
            <td><?= number_format($row['total_hours'], 1) ?> hrs</td>
            <td><?= htmlspecialchars($row['reason']) ?></td>
            <td>
              <span class="badge bg-<?= $row['approval_status'] == 'Approved' ? 'success' : 
                                     ($row['approval_status'] == 'Rejected' ? 'danger' : 'warning') ?>">
                <?= htmlspecialchars($row['approval_status']) ?>
              </span>
            </td>
            <td><?= date('F j, Y h:i A', strtotime($row['created_at'])) ?></td>
            <td><?= htmlspecialchars($row['approved_by_name']) ?></td>
            <td><?= $row['updated_at'] ? date('F j, Y h:i A', strtotime($row['updated_at'])) : 'N/A' ?></td>
            <td><?= htmlspecialchars($row['remarks']) ?></td>
            <td>
             <!-- Edit Icon Button -->
      <button 
        class="btn btn-sm btn-primary btn-edit-overtime" 
        data-overtime='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
        title="Edit">
        <i class="bi bi-pencil-square"></i>
      </button>

      <button 
        class="btn btn-sm btn-danger btn-delete-overtime" 
        data-overtime-id="<?= $row['overtime_id'] ?>"
        title="Delete"
        <?= ($row['approval_status'] === 'Approved') ? 'disabled' : '' ?>>
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
              No overtime requests found in the database.
              <br><small>Please add some overtime requests to see data here.</small>
            </div>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

      <!-- Overtime Modal -->
      <div class="modal fade" id="overtimeModal" tabindex="-1" aria-labelledby="overtimeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form method="POST" id="updateOvertimeForm" class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="overtimeModalLabel">Update Overtime Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="overtime_id" id="modal_overtime_id">
              <div class="mb-2">
                <label class="form-label">Overtime ID</label>
                <input type="text" id="modal_overtime_id_display" class="form-control" readonly>
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
                <label class="form-label">Date</label>
                <input type="date" id="modal_date" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Start Time</label>
                <input type="time" id="modal_start_time" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">End Time</label>
                <input type="time" id="modal_end_time" class="form-control" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Total Hours</label>
                <input type="number" id="modal_total_hours" class="form-control" step="0.1" readonly>
              </div>
              <div class="mb-2">
                <label class="form-label">Reason</label>
                <textarea id="modal_reason" class="form-control" readonly></textarea>
              </div>
              <div class="mb-2">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" id="modal_remarks" class="form-control"></textarea>
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
                <label for="modal_created_at" class="form-label">Created At</label>
                <input type="text" name="created_at" id="modal_created_at" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label for="modal_approved_by" class="form-label">Approved By</label>
                <input type="text" name="approved_by" id="modal_approved_by" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label for="modal_updated_at" class="form-label">Updated At</label>
                <input type="datetime-local" name="updated_at" id="modal_updated_at" class="form-control" readonly>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="update_overtime" class="btn btn-success">Save</button>
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


<style>
  .dataTables_wrapper .dataTables_filter {
    float: right;
  }
  .table-responsive {
    overflow-x: auto;
  }
</style>

<script>
// Initialize DataTable
var table = $('#overtimeTable').DataTable({
  scrollX: true,
  autoWidth: false,
  lengthMenu: [5, 10, 25, 50, 100],
  pageLength: 10,
  language: {
    search: "_INPUT_",
    searchPlaceholder: "Search overtime request...",
    lengthMenu: "Show _MENU_ entries",
    info: "Showing _START_ to _END_ of _TOTAL_ entries",
    infoEmpty: "No records available",
    zeroRecords: "No matching overtime request found"
  }
});

// Department filter functionality
$('#departmentFilter').on('change', function() {
  var selectedDepartment = $(this).val();
  
  if (selectedDepartment === '') {
    // Show all rows
    table.column(2).search('').draw();
  } else {
    // Filter by selected department (column index 2 is the Department column)
    table.column(2).search('^' + selectedDepartment + '$', true, false).draw();
  }
});

// Status filter functionality - FIXED VERSION using data attributes
$('#statusFilter').on('change', function() {
  var selectedStatus = $(this).val();
  
  // Clear any existing custom search functions for status
  $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
    return fn.toString().indexOf('overtimeStatusFilter') === -1;
  });
  
  if (selectedStatus === '') {
    // Show all rows
    table.draw();
  } else {
    // Add custom search function using data attributes
    $.fn.dataTable.ext.search.push(
      function overtimeStatusFilter(settings, data, dataIndex) {
        if (settings.nTable.id !== 'overtimeTable') {
          return true;
        }
        
        // Get the actual row element and check its data-status attribute
        var row = $(settings.nTable).find('tbody tr').eq(dataIndex);
        var rowStatus = row.attr('data-status');
        
        return rowStatus === selectedStatus;
      }
    );
    
    table.draw();
  }
});

// Add current manager info
var currentManager = <?php echo json_encode([
    'name' => $manager_info ? trim($manager_info['first_name'] . ' ' . $manager_info['last_name']) : '',
    'username' => $manager_info['username'] ?? '',
    'employee_id' => $manager_info['employee_id'] ?? ''
]); ?>;

function populateOvertimeModal(data) {
    $('#modal_overtime_id').val(data.overtime_id);
    $('#modal_overtime_id_display').val(data.overtime_id);
    $('#modal_employee_name').val(data.employee_name);
    $('#modal_department_name').val(data.department_name);
    $('#modal_date').val(data.date);
    $('#modal_start_time').val(data.start_time);
    $('#modal_end_time').val(data.end_time);
    $('#modal_total_hours').val(data.total_hours);
    $('#modal_reason').val(data.reason);
    $('#modal_remarks').val(data.remarks);
    $('#modal_status').val(data.approval_status);
    $('#modal_created_at').val(data.created_at || '');

    // Show approved by name based on status
    if (data.approval_status === 'Approved' || data.approval_status === 'Rejected') {
        $('#modal_approved_by').val(data.approved_by_name || currentManager.name);
        if (data.updated_at) {
            var updatedAt = data.updated_at.replace(' ', 'T').slice(0, 16);
            $('#modal_updated_at').val(updatedAt);
        }
    } else {
        $('#modal_approved_by').val('');
        $('#modal_updated_at').val('');
    }
}

// Edit button: populate modal with row data
$(document).on('click', '.btn-edit-overtime', function () {
  var data = $(this).data('overtime');
  // If data is a string, parse it
  if (typeof data === 'string') {
    data = JSON.parse(data);
  }
  populateOvertimeModal(data);
  $('#overtimeModal').modal('show');
});

// Update status change handler
$('#modal_status').on('change', function() {
    if ($(this).val() === 'Approved' || $(this).val() === 'Rejected') {
        // Always use current manager's name
        $('#modal_approved_by').val(currentManager.name);
        
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
        $('#modal_updated_at').val(formattedDate);
    } else {
        $('#modal_approved_by').val('');
        $('#modal_updated_at').val('');
    }
});

// Delete button: AJAX delete with SweetAlert confirmation
$(document).on('click', '.btn-delete-overtime', function () {
  var overtimeId = $(this).data('overtime-id');
  var row = $(this).closest('tr');
  Swal.fire({
    title: 'Are you sure?',
    text: "This will delete overtime request ID: " + overtimeId,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: '', // same page
        type: 'POST',
        data: { overtime_id: overtimeId },
        success: function(response) {
          if (response.trim() === 'success') {
            Swal.fire('Deleted!', 'Overtime request has been deleted. The employee has been notified.', 'success');
            // Remove row from DataTable
            table.row(row).remove().draw();
          } else {
            Swal.fire('Error', 'Failed to delete overtime request.', 'error');
          }
        },
        error: function() {
          Swal.fire('Error', 'Failed to delete overtime request.', 'error');
        }
      });
    }
  });
});
</script>
    </div>
  </div>
</div>