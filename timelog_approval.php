<?php
session_start();
date_default_timezone_set('Asia/Manila');
include_once 'connection.php';

// Get current manager's info
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

// --- UPDATE LOGIC FIRST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timelog'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $approval_remarks = trim($_POST['approval_remarks']);
    $approved_by = $_SESSION['login_id'] ?? null;
    $approved_at = date('Y-m-d H:i:s');

    // Get employee and department info
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM missingtimelogrequests mtr
        JOIN employees e ON mtr.employee_id = e.employee_id
        WHERE mtr.request_id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Update the timelog request
    $stmt = $conn->prepare("UPDATE missingtimelogrequests SET status=?, approval_remarks=?, approved_by=?, approved_at=? WHERE request_id=?");
    $stmt->bind_param("ssisi", $status, $approval_remarks, $approved_by, $approved_at, $request_id);
    $update_success = $stmt->execute();
    $stmt->close();

    if ($update_success) {
        // Notify the employee
        $notification_message = "Your missing timelog request (#$request_id) has been $status";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department = ? AND employee_id != ?
        ");
        $dept_stmt->bind_param("si", $department, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's missing timelog request (#$request_id) has been $status";
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

        $hr_notification = "Missing timelog request (#$request_id) for $employee_name from $department department has been $status";
        while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $hr_mgr['employee_id'], $hr_notification);
            $stmt->execute();
            $stmt->close();
        }
        $hr_mgr_stmt->close();
    }

    header("Location: timelog_approval.php");
    exit;
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_timelog_id'])) {
    $delete_id = intval($_POST['delete_timelog_id']);

    // Get employee and department info before deletion
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM missingtimelogrequests mtr
        JOIN employees e ON mtr.employee_id = e.employee_id
        WHERE mtr.request_id = ?
    ");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Delete the timelog request
    $stmt = $conn->prepare("DELETE FROM missingtimelogrequests WHERE request_id = ?");
    $stmt->bind_param("i", $delete_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Notify the employee
        $notification_message = "Your missing timelog request (#$delete_id) has been deleted";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department = ? AND employee_id != ?
        ");
        $dept_stmt->bind_param("si", $department, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's missing timelog request (#$delete_id) has been deleted";
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

        $hr_notification = "Missing timelog request (#$delete_id) for $employee_name from $department department has been deleted";
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

// Fetch departments for filter dropdown
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($dept_row = $departments_result->fetch_assoc()) {
    $departments[] = $dept_row;
}
include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';
// Fetch missing timelog requests with employee and department info
$sql = "SELECT 
    mtr.request_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    d.name AS department_name,
    mtr.date,
    mtr.missing_field,
    mtr.requested_time,
    mtr.reason,
    mtr.status,
    mtr.requested_at,
    ml.login_id AS manager_login_id,
    ml.username AS manager_username,
    mgr.first_name AS mgr_first_name,
    mgr.last_name AS mgr_last_name,
    mtr.approved_at,
    mtr.approval_remarks
FROM missingtimelogrequests mtr
LEFT JOIN employees e ON mtr.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN employeelogins ml ON mtr.approved_by = ml.login_id
LEFT JOIN employees mgr ON ml.employee_id = mgr.employee_id
ORDER BY mtr.requested_at DESC";

if (!$conn) {
    die("Database connection failed");
}

$result = $conn->query($sql);
if (!$result) {
    die("Error in query: " . ($conn->error ?? "Unknown error"));
}
?>
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
.table th:nth-child(1), .table td:nth-child(1) { min-width: 80px; } /* Request ID */
.table th:nth-child(2), .table td:nth-child(2) { min-width: 120px; } /* Employee */
.table th:nth-child(3), .table td:nth-child(3) { min-width: 100px; } /* Department */
.table th:nth-child(4), .table td:nth-child(4) { min-width: 100px; } /* Date */
.table th:nth-child(5), .table td:nth-child(5) { min-width: 100px; } /* Missing Field */
.table th:nth-child(6), .table td:nth-child(6) { min-width: 110px; } /* Requested Time */
.table th:nth-child(7), .table td:nth-child(7) { min-width: 150px; } /* Reason */
.table th:nth-child(8), .table td:nth-child(8) { min-width: 80px; } /* Status */
.table th:nth-child(9), .table td:nth-child(9) { min-width: 130px; } /* Requested At */
.table th:nth-child(10), .table td:nth-child(10) { min-width: 120px; } /* Approved By */
.table th:nth-child(11), .table td:nth-child(11) { min-width: 130px; } /* Approved At */
.table th:nth-child(12), .table td:nth-child(12) { min-width: 150px; } /* Remarks */
.table th:nth-child(13), .table td:nth-child(13) { min-width: 100px; } /* Actions */

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

/* Time display formatting */
.time-display {
    font-weight: 500;
    color: #495057;
}

/* Missing field styling */
.missing-field {
    text-transform: capitalize;
    font-weight: 500;
}
</style>
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Missing Timelog Approval</h5>
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
            <h5 class="mb-0">TIMELOG APPROVAL</h5>
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
  <table id="timelogTable" class="table table-bordered table-hover nowrap" style="width:100%">
    <thead class="table-light">
      <tr>
        <th>Request ID</th>
        <th>Employee</th>
        <th>Department</th>
        <th>Date</th>
        <th>Missing Field</th>
        <th>Requested Time</th>
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
          <td><?= htmlspecialchars($row['request_id']) ?></td>
          <td><?= htmlspecialchars($row['employee_name']) ?></td>
          <td><?= htmlspecialchars($row['department_name']) ?></td>
          <td><?= htmlspecialchars($row['date']) ?></td>
          <td><?= ucfirst(htmlspecialchars($row['missing_field'])) ?></td>
          <td><?= date('h:i A', strtotime($row['requested_time'])) ?></td>
          <td><?= htmlspecialchars($row['reason']) ?></td>
          <td>
            <span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning') ?>">
              <?= htmlspecialchars($row['status']) ?>
            </span>
          </td>
          <td>
  <?= date('Y-m-d h:i A', strtotime($row['requested_at'])) ?>
</td>
          <td>
            <?= htmlspecialchars(trim($row['mgr_first_name'] . ' ' . $row['mgr_last_name'])) ?: htmlspecialchars($row['manager_username']) ?>
          </td>
          <td>
  <?= $row['approved_at'] ? date('Y-m-d h:i A', strtotime($row['approved_at'])) : '' ?>
</td>
          <td><?= htmlspecialchars($row['approval_remarks']) ?></td>
          <td>
            <!-- Edit Icon Button -->
<button class="btn btn-sm btn-primary btn-edit-timelog"
        data-bs-toggle="modal"
        data-bs-target="#updateModal"
        data-request='<?= json_encode($row) ?>'
        title="Edit">
  <i class="bi bi-pencil-square"></i>
</button>

<!-- Delete Icon Button -->
<button class="btn btn-sm btn-danger btn-delete-timelog"
        data-timelog-id="<?= $row['request_id'] ?>"
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
          <label class="form-label">Employee Name</label>
          <input type="text" id="modal_employee_name" class="form-control" readonly>
        </div>
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="text" id="modal_date" class="form-control" readonly>
        </div>
        <div class="mb-2">
          <label class="form-label">Missing Field</label>
          <input type="text" id="modal_missing_field" class="form-control" readonly>
        </div>
        <div class="mb-2">
          <label class="form-label">Requested Time</label>
          <input type="text" id="modal_requested_time" class="form-control" readonly>
        </div>
        <div class="mb-2">
          <label class="form-label">Reason</label>
          <input type="text" id="modal_reason" class="form-control" readonly>
        </div>
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
          <input type="text" name="requested_at" id="modal_requestedat" class="form-control"readonly>
        </div>
        <div class="mb-3">
          <label for="modal_approvedby" class="form-label">Approved By</label>
          <input type="text" name="approved_by" id="modal_approvedby" class="form-control" readonly>
        </div>          <div class="mb-3">
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

<script>
// Add current manager info
var currentManager = <?php echo json_encode([
    'name' => $manager_info ? trim($manager_info['first_name'] . ' ' . $manager_info['last_name']) : '',
    'username' => $manager_info['username'] ?? '',
    'login_id' => $_SESSION['login_id'] ?? ''
]); ?>;

// Initialize DataTable only once
var table = $('#timelogTable').DataTable({
  scrollX: true,
  autoWidth: false,
  lengthMenu: [5, 10, 25, 50, 100],
  pageLength: 10,
  language: {
    search: "_INPUT_",
    searchPlaceholder: "Search timelog request...",
    lengthMenu: "Show _MENU_ entries",
    info: "Showing _START_ to _END_ of _TOTAL_ entries",
    infoEmpty: "No records available",
    zeroRecords: "No matching timelog request found"
  }
});

// Department filter functionality - Fixed
$('#departmentFilter').on('change', function() {
  var selectedDepartment = $(this).val();
  
  if (selectedDepartment === '') {
    // Show all rows - search for empty string in department column (index 2)
    table.column(2).search('').draw();
  } else {
    // Search for exact department name in department column (index 2)
    table.column(2).search('^' + $.fn.dataTable.util.escapeRegex(selectedDepartment) + '$', true, false).draw();
  }
});

// Status filter functionality - Fixed
$('#statusFilter').on('change', function() {
  var selectedStatus = $(this).val();
  
  if (selectedStatus === '') {
    // Show all rows - search for empty string in status column (index 7)
    table.column(7).search('').draw();
  } else {
    // Search for the status text in the status column (index 7)
    table.column(7).search(selectedStatus, false, false).draw();
  }
});

// Edit button: populate modal with row data
$(document).on('click', '.btn-edit-timelog', function () {
  var data = $(this).data('request');
  if (typeof data === 'string') data = JSON.parse(data);
  currentData = data; // Store for use in status change

  $('#modal_request_id').val(data.request_id || '');
  $('#modal_requestid_display').val(data.request_id || '');
  $('#modal_employee_name').val(data.employee_name || '');
  $('#modal_date').val(data.date || '');
  $('#modal_missing_field').val(data.missing_field || '');
  $('#modal_requested_time').val(data.requested_time || '');
  $('#modal_reason').val(data.reason || '');
  $('#modal_status').val(data.status || '');
  $('#modal_requestedat').val(data.requested_at || '');

  // Show manager name
  var mgrFullName = '';
  if ((data.mgr_first_name && data.mgr_first_name.trim()) || (data.mgr_last_name && data.mgr_last_name.trim())) {
    mgrFullName = ((data.mgr_first_name || '') + ' ' + (data.mgr_last_name || '')).trim();
  }
  $('#modal_approvedby').val(mgrFullName || data.manager_username || '');

  // Format approved_at time if exists
  if (data.approved_at) {
    var approvedAt = data.approved_at.replace(' ', 'T').slice(0, 16);
    $('#modal_approvedat').val(approvedAt);
  } else {
    $('#modal_approvedat').val('');
  }
  
  $('#modal_remarks').val(data.approval_remarks || '');
});

// Update the status change handler
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

    // Set the current manager's name
    $('#modal_approvedby').val(currentManager.name || currentManager.username);
  } else {
    $('#modal_approvedat').val('');
    $('#modal_approvedby').val('');
  }
});

// Reset modal on close
$('#updateModal').on('hidden.bs.modal', function () {
  $('#updateTimelogForm')[0].reset();
  currentData = null; // Clear stored data
});

// Delete button: AJAX delete with SweetAlert confirmation
$(document).on('click', '.btn-delete-timelog', function () {
  var timelogId = $(this).data('timelog-id');
  var row = $(this).closest('tr');
  Swal.fire({
    title: 'Are you sure?',
    text: "This will delete timelog request ID: " + timelogId,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: '', // same page
        type: 'POST',
        data: { delete_timelog_id: timelogId },
        success: function(response) {
          if (response.trim() === 'success') {
            Swal.fire('Deleted!', 'Timelog request has been deleted. The employee has been notified.', 'success');
            table.row(row).remove().draw();
          } else {
            Swal.fire('Error', 'Failed to delete timelog request.', 'error');
          }
        },
        error: function() {
          Swal.fire('Error', 'Failed to delete timelog request.', 'error');
        }
      });
    }
  });
});
</script>

        </div>
      </div>
    </div>
  </div>
</div>