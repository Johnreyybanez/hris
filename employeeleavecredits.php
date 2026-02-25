<?php
// Start session and enable output buffering to avoid "headers already sent"
ob_start();
session_start();

include 'connection.php';

// Handle UPDATE LEAVE CREDITS before any HTML or includes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave_credit'])) {
    $leave_credit_id = $_POST['leave_credit_id'];
    $employee_id = $_POST['employee_id'];
    $leave_type_id = $_POST['leave_type_id'];
    $balance = floatval($_POST['balance']);

    $update_sql = "
        UPDATE EmployeeLeaveCredits SET
            employee_id = ?,
            leave_type_id = ?,
            balance = ?,
            last_updated = CURDATE()
        WHERE leave_credit_id = ?
    ";

    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "iidi", $employee_id, $leave_type_id, $balance, $leave_credit_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Leave credit updated successfully!";
        
        // Preserve search parameters for auto-refresh
        $redirect_params = [];
        if (isset($_POST['preserve_employee_id'])) {
            $redirect_params['employee_id'] = $_POST['preserve_employee_id'];
        }
        if (isset($_POST['preserve_leave_type_id'])) {
            $redirect_params['leave_type_id'] = $_POST['preserve_leave_type_id'];
        }
        $redirect_params['filter_credits'] = '1'; // Auto-trigger filter
        
        $redirect_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($redirect_params);
        header("Location: " . $redirect_url);
        exit;
    } else {
        $_SESSION['error'] = "Failed to update leave credit: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Handle ADD NEW LEAVE CREDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave_credit'])) {
    $employee_id = $_POST['new_employee_id'];
    $leave_type_id = $_POST['new_leave_type_id'];
    $balance = floatval($_POST['new_balance']);

    // Check if record already exists
    $check_sql = "SELECT leave_credit_id FROM EmployeeLeaveCredits WHERE employee_id = ? AND leave_type_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $employee_id, $leave_type_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Leave credit already exists for this employee and leave type!";
    } else {
        $insert_sql = "
            INSERT INTO EmployeeLeaveCredits (employee_id, leave_type_id, balance, last_updated)
            VALUES (?, ?, ?, CURDATE())
        ";
        
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "iid", $employee_id, $leave_type_id, $balance);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Leave credit added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add leave credit: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($check_stmt);
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle DELETE LEAVE CREDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_leave_credit'])) {
    $leave_credit_id = $_POST['delete_leave_credit_id'];
    
    $delete_sql = "DELETE FROM EmployeeLeaveCredits WHERE leave_credit_id = ?";
    $stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt, "i", $leave_credit_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Leave credit deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete leave credit: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch only ACTIVE employees
$employees_query = "SELECT employee_id, last_name, middle_name, first_name 
                   FROM employees 
                   WHERE status = 'active' 
                   ORDER BY last_name";
$employees = mysqli_query($conn, $employees_query);
if (!$employees) {
    die("Error fetching employees: " . mysqli_error($conn));
}

// Fetch all leave types
$leave_types_query = "SELECT leave_type_id, name FROM LeaveTypes ORDER BY name";
$leave_types = mysqli_query($conn, $leave_types_query);
if (!$leave_types) {
    die("Error fetching leave types: " . mysqli_error($conn));
}

$display_rows = [];
$search_performed = false;
$no_results = false;

// Handle both POST and GET requests for filtering
$should_filter = false;
$employee_id = '';
$leave_type_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_credits'])) {
    // Manual filter submission
    $should_filter = true;
    $employee_id = $_POST['employee_id'] ?? '';
    $leave_type_id = $_POST['leave_type_id'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['filter_credits'])) {
    // Auto-filter after update redirect
    $should_filter = true;
    $employee_id = $_GET['employee_id'] ?? '';
    $leave_type_id = $_GET['leave_type_id'] ?? '';
} else {
    // Show all records by default
    $should_filter = true;
}

if ($should_filter) {
    $search_performed = true;
    
    $query = "
        SELECT 
            lc.leave_credit_id,
            lc.employee_id,
            lc.leave_type_id,
            lc.balance,
            lc.last_updated,
            CONCAT(e.last_name, ', ', e.first_name, ' ', IFNULL(e.middle_name, '')) AS employee_name,
            lt.name AS leave_type_name
        FROM EmployeeLeaveCredits lc
        JOIN Employees e ON lc.employee_id = e.employee_id
        JOIN LeaveTypes lt ON lc.leave_type_id = lt.leave_type_id
        WHERE e.status = 'active'
    ";

    $params = [];
    $types = "";

    if (!empty($employee_id)) {
        $query .= " AND e.employee_id = ?";
        $params[] = $employee_id;
        $types .= "i";
    }

    if (!empty($leave_type_id)) {
        $query .= " AND lt.leave_type_id = ?";
        $params[] = $leave_type_id;
        $types .= "i";
    }

    $query .= " ORDER BY employee_name ASC, lt.name ASC";

    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }

    $no_results = empty($display_rows);
    
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}

// Now include UI
include 'head.php';
include 'sidebar.php';
include 'header.php';
?>

<style>
@media print {
    th:last-child, td:last-child {
        display: none !important;
    }
}

#leaveCreditsTable {
    table-layout: auto !important;
    width: 100% !important;
    border-collapse: collapse;
}

#leaveCreditsTable th {
    font-weight: bold;
    font-size: 12px;
    color: #212529;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
}

#leaveCreditsTable td {
    font-size: 14px;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    white-space: nowrap;
    word-break: normal;
}

#leaveCreditsTable button {
    margin: 2px;
    font-size: 11px;
    padding: 4px 8px;
}

#leaveCreditsTable .btn {
    display: inline-block;
    white-space: nowrap;
}

.balance-low {
    color: #dc3545;
    font-weight: bold;
}

.balance-medium {
    color: #ffc107;
    font-weight: bold;
}

.balance-high {
    color: #28a745;
    font-weight: bold;
}
</style>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Employee Leave Credits Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Leave Management</li>
              <li class="breadcrumb-item">Leave Credits</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Filter Leave Credits</h5>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLeaveCreditsModal">
          <i class="fas fa-plus me-1"></i> Add New
        </button>
      </div>
      <div class="card-body">
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label>Select Employee</label>
            <select name="employee_id" class="form-select">
              <option value="">-- All Active Employees --</option>
              <?php
              mysqli_data_seek($employees, 0);
              while ($emp = mysqli_fetch_assoc($employees)):
              ?>
              <option value="<?= $emp['employee_id'] ?>" <?= ($employee_id == $emp['employee_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Select Leave Type</label>
            <select name="leave_type_id" class="form-select">
              <option value="">-- All Leave Types --</option>
              <?php
              mysqli_data_seek($leave_types, 0);
              while ($lt = mysqli_fetch_assoc($leave_types)):
              ?>
              <option value="<?= $lt['leave_type_id'] ?>" <?= ($leave_type_id == $lt['leave_type_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($lt['name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end gap-2">
            <button type="submit" name="filter_credits" class="btn btn-primary">
              <i class="fas fa-filter me-1"></i> Filter
            </button>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">
              <i class="fas fa-sync-alt me-1"></i> Reset
            </a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($search_performed && $no_results): ?>
    <div class="alert alert-warning text-center">
      <strong>No Records Found.</strong> Try adjusting your filters or add new leave credits.
    </div>
    <?php endif; ?>

    <?php if (!empty($display_rows)): ?>
    <!-- Results Card -->
    <div class="card">
      <div class="card-header">
        <h5>Leave Credits (<?= count($display_rows) ?> found)</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="leaveCreditsTable">
            <thead class="table-light">
              <tr>
                <th>Actions</th>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Balance</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($display_rows as $r): ?>
              <tr data-leave-credit-id="<?= $r['leave_credit_id'] ?>"
                  data-employee-id="<?= $r['employee_id'] ?>"
                  data-leave-type-id="<?= $r['leave_type_id'] ?>"
                  data-balance="<?= $r['balance'] ?>">
                <td>
                  <button type="button" class="btn btn-sm btn-outline-warning editCreditsBtn">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger deleteCreditsBtn">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
                <td><?= htmlspecialchars($r['employee_name']) ?></td>
                <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
                <td class="<?php 
                  $balance = floatval($r['balance']);
                  if ($balance <= 2) echo 'balance-low';
                  elseif ($balance <= 5) echo 'balance-medium';
                  else echo 'balance-high';
                ?>">
                  <?= number_format($r['balance'], 2) ?>
                </td>
                <td><?= date('M d, Y', strtotime($r['last_updated'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCreditsModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5>Edit Leave Credits</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="leave_credit_id" id="edit_leave_credit_id">
        
        <!-- Preserve search parameters -->
        <input type="hidden" name="preserve_employee_id" value="<?= htmlspecialchars($employee_id) ?>">
        <input type="hidden" name="preserve_leave_type_id" value="<?= htmlspecialchars($leave_type_id) ?>">
        
        <div class="row g-3">
          <div class="col-12">
            <label>Employee</label>
            <select name="employee_id" id="edit_employee_id" class="form-select" required>
              <?php
              mysqli_data_seek($employees, 0);
              while ($emp = mysqli_fetch_assoc($employees)):
              ?>
              <option value="<?= $emp['employee_id'] ?>">
                <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12">
            <label>Leave Type</label>
            <select name="leave_type_id" id="edit_leave_type_id" class="form-select" required>
              <?php
              mysqli_data_seek($leave_types, 0);
              while ($lt = mysqli_fetch_assoc($leave_types)):
              ?>
              <option value="<?= $lt['leave_type_id'] ?>">
                <?= htmlspecialchars($lt['name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12">
            <label>Balance</label>
            <input type="number" name="balance" id="edit_balance" class="form-control" 
                   step="0.01" min="0" max="999.99" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_leave_credit" class="btn btn-warning">
          <i class="fas fa-save"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Add New Modal -->
<div class="modal fade" id="addLeaveCreditsModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5>Add Leave Credits</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label>Employee</label>
            <select name="new_employee_id" class="form-select" required>
              <option value="">-- Select Employee --</option>
              <?php
              mysqli_data_seek($employees, 0);
              while ($emp = mysqli_fetch_assoc($employees)):
              ?>
              <option value="<?= $emp['employee_id'] ?>">
                <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12">
            <label>Leave Type</label>
            <select name="new_leave_type_id" class="form-select" required>
              <option value="">-- Select Leave Type --</option>
              <?php
              mysqli_data_seek($leave_types, 0);
              while ($lt = mysqli_fetch_assoc($leave_types)):
              ?>
              <option value="<?= $lt['leave_type_id'] ?>">
                <?= htmlspecialchars($lt['name']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12">
            <label>Initial Balance</label>
            <input type="number" name="new_balance" class="form-control" 
                   step="0.01" min="0" max="999.99" value="0.00" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_leave_credit" class="btn btn-success">
          <i class="fas fa-plus"></i> Add
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCreditsModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5>Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="delete_leave_credit_id" id="delete_leave_credit_id">
        <p>Are you sure you want to delete this leave credit record?</p>
        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="delete_leave_credit" class="btn btn-danger">
          <i class="fas fa-trash"></i> Delete
        </button>
      </div>
    </form>
  </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if (isset($_SESSION['success'])): ?>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: '<?= $_SESSION['success'] ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: '<?= $_SESSION['error'] ?>',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});
<?php unset($_SESSION['error']); endif; ?>
</script>

<!-- DataTables Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(document).ready(function () {
    // Initialize DataTable
    const table = $('#leaveCreditsTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom:
            "<'row mb-3'<'col-md-6 d-flex align-items-center'f><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3'<'col-md-6'i><'col-md-6'p>>",
        buttons: [
            {
                extend: 'copy',
                className: 'btn btn-sm btn-outline-secondary me-2',
                text: '<i class="fas fa-copy"></i> Copy',
                exportOptions: {
                    columns: ':not(:first-child)'
                }
            },
            {
                extend: 'csv',
                className: 'btn btn-sm btn-outline-primary me-2',
                text: '<i class="fas fa-file-csv"></i> CSV',
                exportOptions: {
                    columns: ':not(:first-child)'
                }
            },
            {
                extend: 'excel',
                className: 'btn btn-sm btn-outline-success me-2',
                text: '<i class="fas fa-file-excel"></i> Excel',
                exportOptions: {
                    columns: ':not(:first-child)'
                }
            },
            {
                extend: 'pdf',
                className: 'btn btn-sm btn-outline-danger me-2',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':not(:first-child)'
                }
            },
            {
                extend: 'print',
                className: 'btn btn-sm btn-outline-info',
                text: '<i class="fas fa-print"></i> Print',
                exportOptions: {
                    columns: ':not(:first-child)'
                }
            }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search leave credits...",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            },
            info: "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty: "Showing 0 to 0 of 0 records",
            infoFiltered: "(filtered from _MAX_ total records)",
            lengthMenu: "Show _MENU_ records per page",
            zeroRecords: "No matching records found"
        }
    });

    $('.dataTables_filter input').addClass('form-control ms-2').css('width', '250px');
    $('.dataTables_filter label').addClass('d-flex align-items-center').prepend('<i class="fas fa-search me-2"></i>');

    // Edit button click handler
    $(document).on('click', '.editCreditsBtn', function() {
        const row = $(this).closest('tr');
        
        const leaveCreditId = row.data('leave-credit-id');
        const employeeId = row.data('employee-id');
        const leaveTypeId = row.data('leave-type-id');
        const balance = row.data('balance');
        
        $('#edit_leave_credit_id').val(leaveCreditId);
        $('#edit_employee_id').val(employeeId);
        $('#edit_leave_type_id').val(leaveTypeId);
        $('#edit_balance').val(balance);
        
        $('#editCreditsModal').modal('show');
    });

    // Delete button click handler
    $(document).on('click', '.deleteCreditsBtn', function() {
        const row = $(this).closest('tr');
        const leaveCreditId = row.data('leave-credit-id');
        
        $('#delete_leave_credit_id').val(leaveCreditId);
        $('#deleteCreditsModal').modal('show');
    });

    // Form validation
    $('input[type="number"]').on('input', function() {
        const value = parseFloat($(this).val());
        const min = parseFloat($(this).attr('min'));
        const max = parseFloat($(this).attr('max'));
        
        if (value < min || value > max || isNaN(value)) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    // Prevent form submission if validation fails
    $('form').on('submit', function(e) {
        const invalidFields = $(this).find('.is-invalid');
        if (invalidFields.length > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please correct the highlighted fields before submitting.',
                confirmButtonText: 'OK'
            });
        }
    });

    $(window).on('resize', function () {
        table.columns.adjust();
    });
});
</script>
