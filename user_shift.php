<?php
session_start();
include 'connection.php'; // Database connection

// Optional: redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
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
        Shift
    </li>
</ul>
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                       <h5 class="m-b-10">My Shift Schedule</h5>
            <p class="text-muted">View assigned shifts and details</p>
                    </div>
                    
                </div>
<div class="card-body px-3 py-2 bg-white text-dark"> 
      <div class="table-responsive">
  <table id="shiftTable" class="table table-bordered " >
            <thead class="table-danger text-center">
      <tr>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">#</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Shift Name</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Time In</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Break Out</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Break In</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Time Out</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Total Hours</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Has Break</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Flexible</th>
        <th class="text-white" style="border-bottom: 1px solid #dee2e6;">Description</th>
      </tr>
    </thead>
    <tbody>

              <?php
              $result = mysqli_query($conn, "SELECT * FROM shifts ORDER BY shift_id ASC");
              while ($row = mysqli_fetch_assoc($result)):
              ?>
             <tr style="background-color: white;"> <!-- ✅ Force white background -->
                <td><?= $row['shift_id'] ?></td>
                <td><?= htmlspecialchars($row['shift_name']) ?></td>
                <td><?= $row['time_in'] ?></td>
                <td><?= $row['break_out'] ?? '-' ?></td>
                <td><?= $row['break_in'] ?? '-' ?></td>
                <td><?= $row['time_out'] ?></td>
                <td><?= $row['total_hours'] ?></td>
                <td><?= $row['has_break'] ? 'Yes' : 'No' ?></td>
                <td><?= $row['is_flexible'] ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
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
<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- Optional: Include DataTables -->
<script>
$(document).ready(function () {
    const table = $('#shiftTable').DataTable({
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search shift...",
            lengthMenu: "",
            info: "Showing <strong>_START_</strong> to <strong>_END_</strong> of <strong>_TOTAL_</strong> shifts",
            infoEmpty: "No shift records available",
            zeroRecords: "No matching shift found",
            paginate: {
                previous: `<i class="bi bi-chevron-left text-dark"></i>`,
                next: `<i class="bi bi-chevron-right text-dark"></i>`
            }
        },
        dom:
            "<'row mb-3 align-items-center'<'col-md-6'<'custom-length-wrapper'>>" +
            "<'col-md-6 text-end'<'search-wrapper'>>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3 align-items-center'<'col-sm-6'i><'col-sm-6 text-end'p>>"
    });

    setTimeout(() => {
        // Custom entries dropdown
        const customLength = `
            <div class="d-flex align-items-center gap-2">
                <label for="customLengthSelectShift" class="mb-0 text-dark">Show</label>
                <select id="customLengthSelectShift" class="form-select form-select-sm w-auto" style="border: none; box-shadow: none;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="text-dark">entries</span>
            </div>
        `;
        $('.custom-length-wrapper').html(customLength);

        $('#customLengthSelectShift').on('change', function () {
            table.page.len($(this).val()).draw();
        });

        // Custom search input group
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 200px; height: 36px;">
                <span class="input-group-text bg-white border-end-0 rounded-start px-2 py-1" style="height: 100%;">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input 
                    type="search" 
                    class="form-control border-start-0 rounded-end py-1" 
                    placeholder="Search shift..." 
                    id="dt-search-shift" 
                    style="height: 100%;"
                >
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        $('#dt-search-shift').on('keyup', function () {
            table.search(this.value).draw();
        });

        // Remove pagination button styling
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

            // Clean up info text style
            $('.dataTables_info')
                .removeClass()
                .addClass('text-muted small')
                .css({
                    'border': 'none',
                    'background': 'none',
                    'padding': '0',
                    'margin': '0',
                    'font-size': '13px'
                });
        });
    }, 0);
});
</script>

<style>
  #dt-search-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: #ced4da !important; /* default border */
  }
#shiftTable

{ background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}

</style>
