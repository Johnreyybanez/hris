<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'];




// Fetch leave credits for logged-in user
$query = "
    SELECT 
        lc.leave_credit_id,
        lc.balance,
        lc.last_updated,
        lt.name AS leave_type_name
    FROM EmployeeLeaveCredits lc
    JOIN LeaveTypes lt ON lc.leave_type_id = lt.leave_type_id
    JOIN Employees e ON lc.employee_id = e.employee_id
    WHERE e.status = 'active'
      AND e.employee_id = ?
    ORDER BY lt.name ASC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $employee_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$display_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $display_rows[] = $row;
}
mysqli_stmt_close($stmt);

// Layout includes
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
        Leave Balance
    </li>
</ul>
     <?php if (empty($display_rows)): ?>
            <div class="alert alert-warning text-center">
                <strong>No Leave Credits Found.</strong> Please contact HR.
            </div>
        <?php else: ?>
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                       <h5 class="mb-0">Leave Balance</h5>
          <small class="text-muted">View your leave Balance</small>
                    </div>
                    
                </div>
<div class="card-body px-3 py-2 bg-white text-dark"> 
      <div class="table-responsive">
                        <table id="leaveBalanceTable" class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th  class="text-center">Leave Type</th>
                                    <th  class="text-center">Balance</th>
                                    <th  class="text-center">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_rows as $r): ?>
                                    <tr>
                                        <td  class="text-center"><?= htmlspecialchars($r['leave_type_name']) ?></td>
                                      <td class="<?php 
    $balance = floatval($r['balance']);
    if ($balance <= 2) echo 'balance-low';
    elseif ($balance <= 5) echo 'balance-medium';
    else echo 'balance-high';
?> text-center">
    <?php
        // Convert decimal to days (assuming 30 days in a month)
        $days = $balance * 30;
        echo number_format($days, 0) . ' day' . ($days != 1 ? 's' : '');
    ?>
</td>


                                        <td  class="text-center"><?= $r['last_updated'] ? date('M d, Y', strtotime($r['last_updated'])) : '—' ?></td>
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

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
     const table = $('#leaveBalanceTable').DataTable({
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
                    <p class="mt-2 mb-20 text-muted">No leave balance data</p>
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
                    placeholder="Search leave balance..." 
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