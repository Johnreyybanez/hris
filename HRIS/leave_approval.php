<?php
session_start();
include_once 'connection.php';

// Fetch logged-in manager's full name
$manager_fullname = '';
if (isset($_SESSION['login_id'])) {
    $login_id = $_SESSION['login_id'];
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS fullname FROM employees WHERE employee_id = (SELECT employee_id FROM employeelogins WHERE login_id = ?)");
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $stmt->bind_result($manager_fullname);
    $stmt->fetch();
    $stmt->close();
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave'])) {
    $leave_request_id = intval($_POST['leave_request_id']);
    $status = $_POST['status'];
    $approval_remarks = trim($_POST['approval_remarks']);
    $approved_by = $_SESSION['login_id'] ?? null;
    $approved_at = $_POST['approved_at'] ?? date('Y-m-d H:i:s');
    $requested_at = $_POST['requested_at'] ?? null;

    // Get employee and department info
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employeeleaverequests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        WHERE lr.leave_request_id = ?
    ");
    $stmt->bind_param("i", $leave_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Update the leave request
    $stmt = $conn->prepare("UPDATE employeeleaverequests SET status=?, approval_remarks=?, approved_by=?, approved_at=?, requested_at=? WHERE leave_request_id=?");
    $stmt->bind_param("ssissi", $status, $approval_remarks, $approved_by, $approved_at, $requested_at, $leave_request_id);
    $update_success = $stmt->execute();
    $stmt->close();

    if ($update_success) {
        // 1. Notify the employee who made the request
        $notification_message = "Your leave request (#$leave_request_id) has been $status";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // 2. Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department = ? AND employee_id != ?
        ");
        $dept_stmt->bind_param("si", $department, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's leave request (#$leave_request_id) has been $status";
        while ($dept_employee = $dept_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $dept_employee['employee_id'], $dept_notification);
            $stmt->execute();
            $stmt->close();
        }
        $dept_stmt->close();

        // 3. Notify HR and managers
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

        $hr_notification = "Leave request (#$leave_request_id) for $employee_name from $department department has been $status";
        while ($hr_mgr = $hr_mgr_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $hr_mgr['employee_id'], $hr_notification);
            $stmt->execute();
            $stmt->close();
        }
        $hr_mgr_stmt->close();
    }

    header("Location: leave_approval.php");
    exit;
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_request_id']) && !isset($_POST['update_leave'])) {
    $leave_request_id = intval($_POST['leave_request_id']);

    // Get employee and department info before deletion
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.department, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employeeleaverequests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        WHERE lr.leave_request_id = ?
    ");
    $stmt->bind_param("i", $leave_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $employee_id = $employee_data['employee_id'];
    $department = $employee_data['department'];
    $employee_name = $employee_data['employee_name'];
    $stmt->close();

    // Delete the leave request
    $stmt = $conn->prepare("DELETE FROM employeeleaverequests WHERE leave_request_id = ?");
    $stmt->bind_param("i", $leave_request_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 1. Notify the employee
        $notification_message = "Your leave request (#$leave_request_id) has been deleted";
        $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->bind_param("is", $employee_id, $notification_message);
        $stmt->execute();
        $stmt->close();

        // 2. Notify department members
        $dept_stmt = $conn->prepare("
            SELECT employee_id 
            FROM employees 
            WHERE department = ? AND employee_id != ?
        ");
        $dept_stmt->bind_param("si", $department, $employee_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        $dept_notification = "$employee_name's leave request (#$leave_request_id) has been deleted";
        while ($dept_employee = $dept_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->bind_param("is", $dept_employee['employee_id'], $dept_notification);
            $stmt->execute();
            $stmt->close();
        }
        $dept_stmt->close();

        // 3. Notify HR and managers
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

        $hr_notification = "Leave request (#$leave_request_id) for $employee_name from $department department has been deleted";
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

// Fetch current user's username and role ONCE before the loop
$current_username = '';
$current_role = '';
if (isset($_SESSION['login_id'])) {
  $current_login_id = $_SESSION['login_id'];
  $stmt = $conn->prepare("SELECT username, role FROM employeelogins WHERE login_id = ?");
  $stmt->bind_param("i", $current_login_id);
  $stmt->execute();
  $stmt->bind_result($current_username, $current_role);
  $stmt->fetch();
  $stmt->close();
}

// Fetch all departments for the filter dropdown
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($dept_row = $departments_result->fetch_assoc()) {
    $departments[] = $dept_row;
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
LEFT JOIN departments d ON e.department = d.name
LEFT JOIN employeelogins ml ON lr.approved_by = ml.login_id
LEFT JOIN employees mgr ON ml.employee_id = mgr.employee_id
GROUP BY lr.leave_request_id
ORDER BY lr.leave_request_id DESC";
$result = $conn->query($sql);
?>

<div class="pc-container">
  <div class="pc-content">
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
                <td><?= date('F j, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= htmlspecialchars($row['mgr_first_name'] . ' ' . $row['mgr_last_name']) ?></td>
                <td><?= $row['approved_at'] ? date('F j, Y h:i A', strtotime($row['approved_at'])) : '' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks']) ?></td>
                <td>
                  <!-- Edit Icon Button -->
                  <button 
                    class="btn btn-sm btn-primary btn-edit-leave" 
                    data-leave='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
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
  }
  .table-responsive {
    overflow-x: auto;
  }
</style>

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
    // Show all rows
    table.column(2).search('').draw();
  } else {
    // Filter by selected department (column index 2 is the Department column)
    table.column(2).search('^' + selectedDepartment + '$', true, false).draw();
  }
});

// Status filter functionality
$('#statusFilter').on('change', function() {
  var selectedStatus = $(this).val();
  
  // Clear any existing custom search functions for status
  $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
    return fn.toString().indexOf('leaveStatusFilter') === -1;
  });
  
  if (selectedStatus === '') {
    // Show all rows
    table.draw();
  } else {
    // Add custom search function using data attributes
    $.fn.dataTable.ext.search.push(
      function leaveStatusFilter(settings, data, dataIndex) {
        if (settings.nTable.id !== 'leaveTable') {
          return true;
        }
        var row = $(settings.nTable).find('tbody tr').eq(dataIndex);
        var rowStatus = row.attr('data-status');
        return rowStatus === selectedStatus;
      }
    );
    table.draw();
  }
});

function populateLeaveModal(data) {
  // Set all modal fields with data from the row
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
  // Always show current manager full name if status is Approved
  <?php
    // $manager_fullname already fetched above
  ?>
  if (data.status === 'Approved') {
    $('#modal_approved_by').val('<?= addslashes($manager_fullname) ?>');
  } else {
    $('#modal_approved_by').val('');
  }
  if (data.approved_at) {
    // Support both "YYYY-MM-DD HH:MM:SS" and "YYYY-MM-DDTHH:MM"
    var approvedAt = data.approved_at.replace(' ', 'T').slice(0, 16);
    $('#modal_approved_at').val(approvedAt);
  } else {
    $('#modal_approved_at').val('');
  }
}

// Edit button: populate modal with row data
$(document).on('click', '.btn-edit-leave', function () {
  var data = $(this).data('leave');
  // If data is a string, parse it
  if (typeof data === 'string') {
    data = JSON.parse(data);
  }
  populateLeaveModal(data);
  $('#leaveModal').modal('show');
});

// Auto-update Approved By and Approved At if status is set to Approved or Rejected
$('#modal_status').on('change', function () {
  if ($(this).val() === 'Approved' || $(this).val() === 'Rejected') {
    $('#modal_approved_by').val('<?= addslashes($manager_fullname) ?>');
    
    // Get current date/time in Asia/Manila timezone
    var now = new Date();
    // Format for Philippines/Manila timezone
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
    // Convert to datetime-local format (YYYY-MM-DDTHH:mm)
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

// Update form submission handling
$('#updateLeaveForm').on('submit', function() {
    Swal.fire({
        title: 'Success',
        text: 'Leave request updated successfully! Employee will be notified.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
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
                            text: 'Leave request has been deleted and employee has been notified.',
                            icon: 'success',
                            timer: 2000,
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
