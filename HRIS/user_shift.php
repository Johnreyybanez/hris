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

<!-- Main content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            
          </div>
        </div>
      </div>
    </div>

    <!-- Shift Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="m-b-10">My Shift Schedule</h5>
            <p class="text-muted">View assigned shifts and details</p>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="shiftTable" class="table table-striped table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Shift Name</th>
                <th>Time In</th>
                <th>Break Out</th>
                <th>Break In</th>
                <th>Time Out</th>
                <th>Total Hours</th>
                <th>Has Break</th>
                <th>Flexible</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $result = mysqli_query($conn, "SELECT * FROM shifts ORDER BY shift_id ASC");
              while ($row = mysqli_fetch_assoc($result)):
              ?>
              <tr>
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
      search: "_INPUT_",
      searchPlaceholder: "Search violations...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      zeroRecords: "No matching shift found",
      infoEmpty: "No shift records available",
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
                <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search shift..." id="dt-search-input">
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
