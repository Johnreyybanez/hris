<?php
session_start();
include 'connection.php';


$employee_id = $_SESSION['user_id'];

// Handle Add/Edit/Delete Official Business
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_ob'])) {
        $ob_id = $_POST['ob_id'] ?? null;
        $date = $_POST['date'];
        $time_from = $_POST['time_from'];
        $time_to = $_POST['time_to'];
        $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);

        if ($ob_id) {
            $stmt = $conn->prepare("UPDATE EmployeeOfficialBusiness SET date=?, time_from=?, time_to=?, purpose=?, location=? WHERE ob_id=? AND status='Pending'");
            $stmt->bind_param("sssssi", $date, $time_from, $time_to, $purpose, $location, $ob_id);
            $stmt->execute();
            $_SESSION['ob_success'] = 'Official Business updated!';
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO EmployeeOfficialBusiness (employee_id, date, time_from, time_to, purpose, location, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->bind_param("isssss", $employee_id, $date, $time_from, $time_to, $purpose, $location);
            $stmt->execute();
            
            // Send notification to managers for new OB requests
            $new_ob_id = $conn->insert_id;
            
            
            $_SESSION['ob_success'] = 'Official Business request submitted!';
            $stmt->close();
        }
    }

    if (isset($_POST['delete_ob'])) {
        $ob_id = $_POST['delete_ob'];
        $stmt = $conn->prepare("DELETE FROM EmployeeOfficialBusiness WHERE ob_id = ? AND status='Pending'");
        $stmt->bind_param("i", $ob_id);
        $stmt->execute();
        $_SESSION['ob_success'] = $stmt->affected_rows > 0 ? 'Request deleted successfully.' : 'Delete failed — already approved/rejected.';
        $stmt->close();
    }

    header("Location: user_ob_request.php");
    exit;
}

$ob_q = mysqli_query($conn, "SELECT * FROM EmployeeOfficialBusiness WHERE employee_id = '$employee_id' ORDER BY requested_at DESC");

include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';
?>

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
        Official Business Requests
    </li>
</ul>
    <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
        <h5 class="mb-0">Official Bussiness</h5>
          <small class="text-muted">Below is a list of your recorded official business request.</small>
          </div>
        <button class="btn btn-dark btn-sm" onclick="clearOBForm()" data-bs-toggle="modal" data-bs-target="#obModal">
          <i class="bi bi-plus-circle me-1"></i> Add Official Business
        </button>
      </div>
<div class="card-body px-3 py-2 bg-white text-dark">
      <div class="table-responsive">
        <table id="obTable"class="table table-bordered " >
            <thead class="table-danger text-center">
            <tr>
              <th>Date</th>
              <th>Time From</th>
              <th>Time To</th>
              <th>Purpose</th>
              <th>Location</th>
              <th>Status</th>
              <th>Requested At</th>
              <th>Approved At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($ob_q)) : ?>
             <tr style="background-color: white;"> <!-- ✅ Force white background -->
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td class="text-center"><?= date('h:i A', strtotime($row['time_from'])) ?></td>
                <td class="text-center"><?= date('h:i A', strtotime($row['time_to'])) ?></td>
                <td><?= htmlspecialchars($row['purpose']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td class="text-center">
                  <?php
                    $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'][$row['status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                  ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['status'] === 'Pending') : ?>
                    <button class="btn btn-sm btn-primary me-1" onclick='editOB(<?= json_encode($row) ?>)'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="delete_ob" value="<?= $row['ob_id'] ?>">
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
<div class="modal fade" id="obModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="ob_id">
      <div class="modal-header">
        <h5 class="modal-title">Official Business Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Time From</label>
          <input type="time" name="time_from" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Time To</label>
          <input type="time" name="time_to" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Purpose</label>
          <textarea name="purpose" class="form-control" required></textarea>
        </div>
        <div class="mb-3"><label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="submit_ob" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTable Init -->
<script>
$(document).ready(function () {
  const table = $('#obTable').DataTable({
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
                    <p class="mt-2 mb-20 text-muted">No official business data</p>
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
                    placeholder="Search Official Business..." 
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

<!-- OB Functions -->
<script>
function editOB(data) {
  if (data.status !== 'Pending') {
    Swal.fire({
      icon: 'info',
      title: 'Not Editable',
      text: 'Only pending requests can be edited.'
    });
    return;
  }

  const form = document.querySelector('#obModal form');
  form.querySelector('[name="ob_id"]').value = data.ob_id;
  form.querySelector('[name="date"]').value = data.date;
  form.querySelector('[name="time_from"]').value = data.time_from;
  form.querySelector('[name="time_to"]').value = data.time_to;
  form.querySelector('[name="purpose"]').value = data.purpose;
  form.querySelector('[name="location"]').value = data.location;
  new bootstrap.Modal(document.getElementById('obModal')).show();
}

function clearOBForm() {
  const form = document.querySelector('#obModal form');
  form.reset();
  form.querySelector('[name="ob_id"]').value = '';
}

$('#obModal').on('hidden.bs.modal', function () {
  clearOBForm();
});
</script>

<!-- SweetAlert Feedback -->
<?php if (isset($_SESSION['ob_success'])) : ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $_SESSION['ob_success'] ?>',
  toast: true,
  position: 'top-end',
  timer: 3000,
  showConfirmButton: false
});
</script>
<style>
  #obTable
  { background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}
</style>
<?php unset($_SESSION['ob_success']); endif; ?>
