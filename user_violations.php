<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'];

$violation_q = mysqli_query($conn, "
    SELECT ev.*, vt.name AS violation_name, st.name AS sanction_name
    FROM EmployeeViolations ev
    LEFT JOIN ViolationTypes vt ON ev.violation_type_id = vt.violation_id
    LEFT JOIN SanctionTypes st ON ev.sanction_type_id = st.sanction_id
    WHERE ev.employee_id = '$employee_id'
    ORDER BY ev.violation_date DESC
");

if (!$violation_q) {
    echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
    exit;
}
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
     <!-- Bootstrap Icons -->


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
        Violations & Sanctions
    </li>
</ul>

        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                       <h5 class="mb-0">Violations & Sanctions Records</h5>
          <small class="text-muted">Below is a list of your recorded violations & sanctions.</small>
                    </div>
                    
                </div>
<div class="card-body px-3 py-2 bg-white text-dark"> 
      <div class="table-responsive">
    <table id="violationsTable" class="table table-bordered ">
        
            <thead class="table-danger text-center">
            <tr>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Violation</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Sanction</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Violation Date</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Sanction Start</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Sanction End</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Remarks</th>
                <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Reported By</th>
                <th class="text-white text-center" style="border-bottom: 1px solid #dee2e6;">Action</th>
            </tr>
        </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($violation_q)) : ?>
               <tr style="background-color: white;"> <!-- ✅ Force white background -->
                <td><?= htmlspecialchars($row['violation_name']) ?></td>
                <td><?= htmlspecialchars($row['sanction_name']) ?></td>
                <td><?= date('F d, Y', strtotime($row['violation_date'])) ?></td>
                <td><?= date('F d, Y', strtotime($row['sanction_start_date'])) ?></td>
                <td><?= date('F d, Y', strtotime($row['sanction_end_date'])) ?></td>
                <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                <td><?= htmlspecialchars($row['reported_by']) ?: '—' ?></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-primary" onclick='viewViolation(<?= json_encode($row) ?>)'>
                    <i class="bi bi-eye"></i>
                  </button>
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
</div>

<!-- View Modal -->
<div class="modal fade" id="viewViolationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Violation Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>Violation:</strong> <span id="view_violation"></span></p>
        <p><strong>Sanction:</strong> <span id="view_sanction"></span></p>
        <p><strong>Violation Date:</strong> <span id="view_violation_date"></span></p>
        <p><strong>Sanction Start:</strong> <span id="view_start"></span></p>
        <p><strong>Sanction End:</strong> <span id="view_end"></span></p>
        <p><strong>Remarks:</strong> <span id="view_remarks"></span></p>
        <p><strong>Reported By:</strong> <span id="view_reported_by"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    const table = $('#violationsTable').DataTable({
      
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search documents...",
            lengthMenu: "",
            info: "Showing <strong>_START_</strong> to <strong>_END_</strong> of <strong>_TOTAL_</strong> documents",
            infoEmpty: "No documents available",
            zeroRecords: `
                <div style="text-align:center; padding:10px ;">
                    <div id="no-results-animation" style="width:120px; height:120px; margin:0 auto;"></div>
                    <p class="mt-2 mb-20 text-muted">No violation & sanctions data</p>
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
            <div class="input-group w-auto ms-auto" style="max-width: 240px; height: 36px;">
                <span class="input-group-text bg-white border-end-0 rounded-start px-2 py-1" style="height: 100%;">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input 
                    type="search" 
                    class="form-control border-start-0 rounded-end py-1" 
                    placeholder="Search violations & sanctions..." 
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


// View Violation Function
function viewViolation(data) {
    $('#view_violation').text(data.violation_name);
    $('#view_sanction').text(data.sanction_name);
    $('#view_violation_date').text(new Date(data.violation_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    $('#view_start').text(new Date(data.sanction_start_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    $('#view_end').text(new Date(data.sanction_end_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    $('#view_remarks').text(data.remarks || '—');
    $('#view_reported_by').text(data.reported_by || '—');

    new bootstrap.Modal(document.getElementById('viewViolationModal')).show();
}
</script>


<style>
#dt-search-input:focus {
  outline: none !important;
  box-shadow: none !important;
  border-color: #ced4da !important;
}
#violationsTable

{
    background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}
</style>
