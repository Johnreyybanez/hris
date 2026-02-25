<?php
session_start();
include 'connection.php';
include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';

$employee_id = $_SESSION['user_id'];

$query = mysqli_query($conn, "
    SELECT eb.*, bt.name AS benefit_name 
    FROM EmployeeBenefits eb 
    LEFT JOIN BenefitTypes bt ON eb.benefit_id = bt.benefit_id 
    WHERE eb.employee_id = '$employee_id'
    ORDER BY eb.start_date DESC
");
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
        Benefits
    </li>
</ul>
    <div class="container-fluid py-4">
      <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">My Employee Benefits</h5>
            <small class="text-muted">List of all your active and historical benefits</small>
          </div>
        </div>
      <div class="card-body px-3 py-2 bg-white text-dark">
          <div class="table-responsive">
            <table id="benefitsTable" class="table table-bordered " >
            <thead class="table-danger text-center">
                <tr>
                  <th class="text-white text-center">#</th>
                  <th class="text-white text-center">Benefit Type</th>
                  <th class="text-white text-center">Amount</th>
                  <th class="text-white text-center">Start Date</th>
                  <th class="text-white text-center">End Date</th>
                  <th class="text-white text-center">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                if (mysqli_num_rows($query) > 0) {
                  while ($row = mysqli_fetch_assoc($query)) {
                    echo "<tr class='text-center'  style='background-color: white'> 
                      <td>{$i}</td>
                      <td>" . htmlspecialchars($row['benefit_name']) . "</td>
                      <td>₱" . number_format($row['amount'], 2) . "</td>
                      <td>" . htmlspecialchars($row['start_date']) . "</td>
                      <td>" . htmlspecialchars($row['end_date']) . "</td>
                      <td>" . htmlspecialchars($row['remarks'] ?? '—') . "</td>
                    </tr>";
                    $i++;
                  }
                } else {
                 
                }
                ?>
              </tbody>
            </table>
          </div>
           </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
  const table = $('#benefitsTable').DataTable({
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
                    <p class="mt-2 mb-20 text-muted">No benefits data</p>
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
                    placeholder="Search benefits..." 
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
<style>
  #dt-search-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: #ced4da !important; /* default border */
  }
  #benefitsTable

{ background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}

</style>
