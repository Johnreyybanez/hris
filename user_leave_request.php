<?php
session_start();
include 'connection.php';


$employee_id = $_SESSION['user_id'];

// Handle Add/Edit/Delete Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_leave'])) {
    $action = $_POST['action'] ?? 'add';
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $total_days = $_POST['total_days'];
    $actual_leave_days = $_POST['actual_leave_days'];
    $remarks = $_POST['remarks'];
    $leave_request_id = $_POST['leave_request_id'] ?? null;

    if ($action === 'delete' && $leave_request_id) {
        $del = $conn->prepare("DELETE FROM EmployeeLeaveRequests WHERE leave_request_id = ? AND employee_id = ?");
        $del->bind_param("ii", $leave_request_id, $employee_id);
        $del->execute();
        $del->close();
        $_SESSION['leave_success'] = 'Leave request deleted.';
    } else {
        if ($action === 'edit' && $leave_request_id) {
            $stmt = $conn->prepare("UPDATE EmployeeLeaveRequests SET leave_type_id=?, start_date=?, end_date=?, total_days=?, actual_leave_days=?, approval_remarks=? WHERE leave_request_id=? AND employee_id=? AND status='Pending'");
            $stmt->bind_param("isssdsii", $leave_type_id, $start_date, $end_date, $total_days, $actual_leave_days, $remarks, $leave_request_id, $employee_id);
            $_SESSION['leave_success'] = 'Leave request updated.';
        } else {
            $stmt = $conn->prepare("INSERT INTO EmployeeLeaveRequests (employee_id, leave_type_id, start_date, end_date, total_days, actual_leave_days, status, requested_at, approval_remarks) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?)");
            $stmt->bind_param("iissdds", $employee_id, $leave_type_id, $start_date, $end_date, $total_days, $actual_leave_days, $remarks);
            $_SESSION['leave_success'] = 'Leave request submitted.';
        }
        $stmt->execute();
        
        // Send notification to managers for new leave requests
        if ($action !== 'edit' && $action !== 'delete') {
            $new_leave_id = $conn->insert_id;
            
            // Get leave type name
            $type_query = "SELECT name FROM LeaveTypes WHERE leave_type_id = ?";
            $type_stmt = $conn->prepare($type_query);
            $type_stmt->bind_param("i", $leave_type_id);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            $leave_type_name = $type_result->fetch_assoc()['name'] ?? 'Unknown';
            $type_stmt->close();
            
            // Send notification
           
        }
        
        $stmt->close();
    }

    header("Location: user_leave_request.php");
    exit;
}

$leave_q = mysqli_query($conn, "
    SELECT elr.*, lt.name AS leave_type
    FROM EmployeeLeaveRequests elr
    LEFT JOIN LeaveTypes lt ON elr.leave_type_id = lt.leave_type_id
    WHERE elr.employee_id = '$employee_id'
    ORDER BY elr.requested_at DESC
");

include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';
?>

<!-- Leave Request Section -->
<div class="pc-container">
    <div class="pc-content">
       <div style="
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        opacity: 0.04;
        z-index: 0;
        pointer-events: none;
    ">
        <img src="asset/images/logo.webp" alt="Company Logo" style="max-width: 600px;">
    </div>
    
<!-- Custom Breadcrumb Style -->
<style>
    .breadcrumb {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        margin-left: 15px;
         margin-right: 15px;
    }
    .breadcrumb-item a {
        color:  #6c757d;
        text-decoration: none;
        font-weight: 500;
    }
    .breadcrumb-item a:hover {
        text-decoration: underline;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        content: "\f285"; /* Bootstrap chevron-right */
        font-family: "bootstrap-icons";
        color: #6c757d;
        font-size: 0.8rem;
    }
    .breadcrumb-item.active {
    color: #007bff; /* Bootstrap blue */
    
}
</style>

<!-- Breadcrumb -->
<ul class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="user_dashboard.php"><i class="bi bi-house-door-fill"></i> Home</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        Leave Requests
    </li>
</ul>
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
        <h5 class="mb-0">Leave Requests</h5>
         <small class="text-muted">Below is a list of your recorded leave request</small>
          </div>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal">
          <i class="bi bi-plus-circle"></i> Add Leave
        </button>
      </div>

     <div class="card-body px-3 py-2 bg-white text-dark">
  <div class="table-responsive mt-3">
    <table id="leaveTable" class="table table-bordered " >
            <thead class="table-danger text-center">
        <tr>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Leave Type</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Date Range</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Total Days</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Actual Days</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Status</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Requested At</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Approved At</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Remarks</th>
          <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Action</th>
        </tr>
      </thead>
      <tbody>
            <?php while ($row = mysqli_fetch_assoc($leave_q)) : ?>
             <tr style="background-color: white;"> <!-- ✅ Force white background -->
                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                <td><?= date('M d, Y', strtotime($row['start_date'])) ?> to <?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                <td><?= $row['total_days'] ?></td>
                <td><?= $row['actual_leave_days'] ?></td>
                <td><span class="badge bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= $row['status'] ?></span></td>
                <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['status'] === 'Pending') : ?>
                    <button class="btn btn-sm btn-primary btn-edit-leave" 
                            data-bs-toggle="modal" 
                            data-bs-target="#leaveModal" 
                            data-leave='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="leave_request_id" value="<?= $row['leave_request_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" name="save_leave" class="btn btn-sm btn-danger" onclick="return confirm('Delete this leave request?')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php elseif ($row['status'] === 'Rejected') : ?>
                    <span class="text-muted">—</span>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="leave_request_id" value="<?= $row['leave_request_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" name="save_leave" class="btn btn-sm btn-danger" onclick="return confirm('Delete this rejected request?')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php else : ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="leave_request_id" id="leave_request_id">
      <input type="hidden" name="action" value="add" id="form_action">
      <div class="modal-header">
        <h5 class="modal-title">Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Leave Type</label>
          <select name="leave_type_id" id="leave_type_id" class="form-select" required>
            <option value="">Select Type</option>
            <?php
            $types = mysqli_query($conn, "SELECT * FROM LeaveTypes");
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
          <input type="number" name="total_days" id="total_days" step="0.5" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Actual Days</label>
          <input type="number" name="actual_leave_days" id="actual_leave_days" step="0.5" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" id="remarks" class="form-control"></textarea>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
    const table = $('#leaveTable').DataTable({
          lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search deduction...",
            lengthMenu: "",
            info: "Showing <strong>_START_</strong> to <strong>_END_</strong> of <strong>_TOTAL_</strong> documents",
            infoEmpty: "No documents available",
            zeroRecords: `
                <div style="text-align:center; padding:10px ;">
                    <div id="no-results-animation" style="width:120px; height:120px; margin:0 auto;"></div>
                    <p class="mt-2 mb-20 text-muted">No leave request data</p>
                </div>
            `,
            paginate: {
                previous: `<i class="bi bi-chevron-left"></i>`,
                next: `<i class="bi bi-chevron-right"></i>`
            }
        },
        dom:
            "<'row mb-3 align-items-center'<'col-md-6'<'custom-length-wrapper'>>" +
            "<'col-md-6 text-end'<'search-wrapper'>>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3 align-items-center'<'col-sm-6'i><'col-sm-6 text-end'p>>"
    });

    // Show Lottie animation only when searching
    table.on('draw', function () {
        const searchValue = table.search().trim(); // Current search text
        if (searchValue.length > 0) { 
            setTimeout(() => {
                const container = document.getElementById('no-results-animation');
                if (container) {
                    lottie.loadAnimation({
                        container: container,
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: 'asset/images/nodatafound.json' // ✅ correct path to your JSON file
                    });
                }
            }, 50);
        }
    });

    setTimeout(() => {
        // Custom entries dropdown
        const customLength = `
            <div class="d-flex align-items-center gap-2">
                <label for="customLengthSelect" class="mb-0 text-dark">Show</label>
                <select id="customLengthSelect" class="form-select form-select-sm w-auto" style="border: none; box-shadow: none;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="text-dark">entries</span>
            </div>
        `;
        $('.custom-length-wrapper').html(customLength);

        $('#customLengthSelect').on('change', function () {
            table.page.len($(this).val()).draw();
        });

        // Custom search bar
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 200px; height: 36px;">
                <span class="input-group-text bg-white border-end-0 rounded-start px-2 py-1" style="height: 100%;">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input 
                    type="search" 
                    class="form-control border-start-0 rounded-end py-1" 
                    placeholder="Search leave request..." 
                    id="dt-search-input" 
                    style="height: 100%;"
                >
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
        });

        // Cleanup pagination buttons
        $('body').on('draw.dt', function () {
            $('.dataTables_paginate .paginate_button')
                .removeClass()
                .addClass('text-dark')
                .css({
                    'border': 'none',
                    'background': 'none',
                    'border-radius': '0',
                    'padding': '4px',
                    'margin': '0 2px',
                    'box-shadow': 'none'
                });

            $('.dataTables_info')
                .removeClass()
                .addClass('text-muted small')
                .css({
                    'border': 'none',
                    'background': 'none',
                    'border-radius': '0',
                    'padding': '0',
                    'margin': '0',
                    'font-size': '13px'
                });
        });
    }, 0);
});
</script>
<script>
  document.querySelectorAll('.btn-edit-leave').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const data = JSON.parse(btn.getAttribute('data-leave'));
      if (data.status !== 'Pending') {
        Swal.fire({
          icon: 'info',
          title: 'Not Editable',
          text: 'Only pending leave requests can be edited.',
        });
        e.preventDefault();
        return;
      }
      document.getElementById('form_action').value = 'edit';
      document.getElementById('leave_request_id').value = data.leave_request_id;
      document.getElementById('leave_type_id').value = data.leave_type_id;
      document.getElementById('start_date').value = data.start_date;
      document.getElementById('end_date').value = data.end_date;
      document.getElementById('total_days').value = data.total_days;
      document.getElementById('actual_leave_days').value = data.actual_leave_days;
      document.getElementById('remarks').value = data.approval_remarks;
    });
  });
</script>

<?php if (isset($_SESSION['leave_success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $_SESSION['leave_success'] ?>',
  toast: true,
  position: 'top-end',
  timer: 3000,
  showConfirmButton: false
});
</script>
<style>
  #leaveTable
  { background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}
</style>
<?php unset($_SESSION['leave_success']); endif; ?>
