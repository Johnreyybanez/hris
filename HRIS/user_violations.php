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
    <div class="container-fluid py-4">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Violation Records</h5>
          <small class="text-muted">Below is a list of your recorded violations.</small>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-bordered table-striped align-middle" id="violationsTable">
            <thead class="table-danger text-center">
              <tr>
                <th>Violation</th>
                <th>Sanction</th>
                <th>Violation Date</th>
                <th>Sanction Start</th>
                <th>Sanction End</th>
                <th>Remarks</th>
                <th>Reported By</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($violation_q)) : ?>
              <tr>
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
      searchPlaceholder: "Search violations...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      zeroRecords: "No matching violations found",
      infoEmpty: "No violation records available",
    },
    dom:
      "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>"+
      "<'row'<'col-sm-12'tr>>"+
      "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
  });

  // Replace default search input
  setTimeout(() => {
    const customSearch = `
      <div class="input-group w-auto ms-auto" style="max-width: 300px;">
        <span class="input-group-text bg-white border-end-0 rounded-start">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search violations..." id="dt-search-input">
      </div>
    `;
    $('.search-wrapper').html(customSearch);

    $('#dt-search-input').on('keyup', function () {
      table.search(this.value).draw();
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
</style>
