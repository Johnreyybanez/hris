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
    <div class="container-fluid py-4">
      <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">My Employee Benefits</h5>
            <small class="text-muted">List of all your active and historical benefits</small>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-striped" id="benefitsTable">
              <thead class="table-dark text-center">
                <tr>
                  <th>#</th>
                  <th>Benefit Type</th>
                  <th>Amount</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                if (mysqli_num_rows($query) > 0) {
                  while ($row = mysqli_fetch_assoc($query)) {
                    echo "<tr class='text-center'>
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
                  echo "<tr><td colspan='6' class='text-center text-muted'>No benefits found.</td></tr>";
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

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
  $(document).ready(function () {
  const table = $  ('#benefitsTable').DataTable({
     lengthMenu: [5, 10, 25, 50],
    pageLength: 10,
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search violations...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      zeroRecords: "No matching benefits found",
      infoEmpty: "No benefits records available",
    },
 dom:
            "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>"+
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
    });

    // Build modern Bootstrap search input group with icon
    setTimeout(() => {
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0 rounded-start">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search benefits..." id="dt-search-input">
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        // Link search input to DataTable search
        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
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
</style>
