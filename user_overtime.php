<?php
session_start();
include 'connection.php';


$employee_id = $_SESSION['user_id'] ?? 0;
// --- DELETE Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $log_id = $_POST['log_id'] ?? null;

    if ($log_id && $employee_id) {
        $stmt = $conn->prepare("DELETE FROM overtime WHERE overtime_id = ? AND employee_id = ?");
        $stmt->bind_param("ii", $log_id, $employee_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Overtime request deleted successfully.";
            } else {
                $_SESSION['error'] = "No matching overtime request found or not authorized.";
            }
        } else {
            $_SESSION['error'] = "Failed to delete overtime request: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid request data.";
    }
    header("Location: user_overtime.php");
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    $action = $_POST['action'] ?? 'add';
    $log_id = $_POST['log_id'] ?? null;
    $date = $_POST['date'];
    $start_time = $_POST['start_time'] . ":00";
    $end_time = $_POST['end_time'] . ":00";
    $reason = $_POST['reason'];

    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $interval = $start->diff($end);
    $total_hours = $interval->h + ($interval->i / 60);

    
    if ($action === 'edit' && $log_id) {
        $stmt = $conn->prepare("UPDATE overtime SET date=?, start_time=?, end_time=?, total_hours=?, reason=? WHERE overtime_id=? AND employee_id=?");
        $stmt->bind_param("sssdsii", $date, $start_time, $end_time, $total_hours, $reason, $log_id, $employee_id);
        $stmt->execute();
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Overtime request updated successfully." : "No changes made.";
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO overtime (employee_id, date, start_time, end_time, total_hours, reason, approval_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isssds", $employee_id, $date, $start_time, $end_time, $total_hours, $reason);
        $stmt->execute();
        
        // Send notification to managers for new overtime requests
        $new_overtime_id = $conn->insert_id;
       
        
        $_SESSION['success'] = $stmt->affected_rows > 0 ? "Overtime request filed successfully." : "Failed to file request.";
        $stmt->close();
    }

    header("Location: user_overtime.php");
    exit;
}

// Fetch records
$logs_q = mysqli_query($conn, "SELECT * FROM overtime WHERE employee_id = '$employee_id' ORDER BY created_at DESC");
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

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
       Overtime Requests
    </li>
</ul>
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
       <div>
        <h5 class="mb-0">Overtime Requests</h5>
        <small class="text-muted">Below is a list of your recorded overtime request.</small>
          </div>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#logModal">
          <i class="bi bi-plus-circle me-1"></i> File Request
        </button>
      </div>
<div class="card-body px-3 py-2 bg-white text-dark">
      <div class="table-responsive">
        <table id="overtimeTable" class="table table-bordered " >
            <thead class="table-danger text-center">
            <tr>
              <th>Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Total Hours</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($logs_q)) : ?>
              <tr style="background-color: white;"> <!-- ✅ Force white background -->
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td><?= date('h:i A', strtotime($row['start_time'])) ?></td>
                <td><?= date('h:i A', strtotime($row['end_time'])) ?></td>
                <td class="text-center"><?= number_format($row['total_hours'], 2) ?> hrs</td>
                <td><?= htmlspecialchars($row['reason']) ?></td>
                <td class="text-center">
                  <?php
                  $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'][$row['approval_status']] ?? 'secondary';
                  echo "<span class='badge bg-$badge'>{$row['approval_status']}</span>";
                  ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                <td><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['approval_status'] == 'Pending') : ?>
                    <button class="btn btn-sm btn-primary" onclick='editLog(<?= json_encode($row) ?>)'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                   <form method="POST" class="d-inline">
  <input type="hidden" name="log_id" value="<?= $row['overtime_id'] ?>">
  <input type="hidden" name="action" value="delete">
  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this request?')">
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
</div>

<!-- Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="log_id" id="log_id">
      <input type="hidden" name="action" id="form_action" value="add">
      <input type="hidden" name="save_log" value="1">
      <div class="modal-header">
        <h5 class="modal-title">Overtime Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Date</label>
          <input type="date" name="date" id="date" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Start Time</label>
          <input type="time" name="start_time" id="start_time" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">End Time</label>
          <input type="time" name="end_time" id="end_time" class="form-control" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Reason</label>
          <textarea name="reason" id="reason" class="form-control" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
 $(document).ready(function () {
  const table = $('#overtimeTable').DataTable({
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
                    <p class="mt-2 mb-20 text-muted">No overtime data</p>
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
                    placeholder="Search overtime..." 
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


  function editLog(data) {
    document.getElementById('form_action').value = 'edit';
    document.getElementById('log_id').value = data.overtime_id;
    document.getElementById('date').value = data.date;
    document.getElementById('start_time').value = data.start_time.slice(0,5);
    document.getElementById('end_time').value = data.end_time.slice(0,5);
    document.getElementById('reason').value = data.reason;
    new bootstrap.Modal(document.getElementById('logModal')).show();
  }
</script>

<?php if (isset($_SESSION['success'])) : ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success'] ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])) : ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?= $_SESSION['error'] ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
</script>
<?php unset($_SESSION['error']); endif; ?>

<style>
  #dt-search-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: #ced4da !important;
  }
  #overtimeTable
  { background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}
</style>
