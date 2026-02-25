<?php
session_start();
include 'connection.php';
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Payroll Deductions View</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="enhanced_payroll.php">Payroll</a></li>
              <li class="breadcrumb-item active">Payroll Deductions</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header">
            <div>
              <h5 class="mb-0">All Payroll Deductions Records</h5>
              <small class="text-muted">View all payroll deductions across all payroll periods</small>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="deductionsTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Payroll Period</th>
                    <th>Employee</th>
                    <th>Deduction Type</th>
                    <th class="text-end">Amount</th>
                    <th>Description</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // First, let's get all deduction types into an array for quick lookup
                  $deduction_types = [];
                  $types_query = "SELECT deduction_id, name FROM deductiontypes";
                  $types_result = mysqli_query($conn, $types_query);
                  if ($types_result) {
                    while ($type = mysqli_fetch_assoc($types_result)) {
                      $deduction_types[$type['deduction_id']] = $type['name'];
                    }
                  }
                  
                  // Now query payroll deductions
                  $query = "
                    SELECT 
                      pd.payroll_deduction_id,
                      pd.payroll_detail_id,
                      pd.deduction_type_id,
                      pd.amount,
                      pd.description,
                      e.first_name,
                      e.last_name,
                      e.employee_id,
                      p.cutoff_start_date,
                      p.cutoff_end_date,
                      p.payroll_date,
                      p.status as payroll_status
                    FROM payroll_deductions pd
                    LEFT JOIN payroll_details pdet ON pd.payroll_detail_id = pdet.payroll_detail_id
                    LEFT JOIN payroll p ON pdet.payroll_id = p.payroll_id
                    LEFT JOIN employees e ON pdet.employee_id = e.employee_id
                    ORDER BY pd.payroll_deduction_id DESC
                  ";
                  
                  $result = mysqli_query($conn, $query);
                  
                  if (!$result) {
                    echo "<tr><td colspan='7' class='text-center text-danger'>Database Error: " . mysqli_error($conn) . "</td></tr>";
                  } else if (mysqli_num_rows($result) == 0) {
                    echo "<tr><td colspan='7' class='text-center text-muted'>No payroll deductions found.</td></tr>";
                  } else {
                    while ($row = mysqli_fetch_assoc($result)):
                      $employee_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                      
                      // Get deduction type name from our array
                      $deduction_type_id = $row['deduction_type_id'];
                      if (isset($deduction_types[$deduction_type_id])) {
                        $deduction_type = htmlspecialchars($deduction_types[$deduction_type_id]);
                      } else {
                        $deduction_type = 'Unknown Deduction';
                      }
                      
                      $payroll_period = date('M d', strtotime($row['cutoff_start_date'])) . " - " . date('M d, Y', strtotime($row['cutoff_end_date']));
                      $payroll_date = date('M d, Y', strtotime($row['payroll_date']));
                      $status = $row['payroll_status'];
                  ?>
                  <tr>
                    <td><span class="badge bg-secondary"><?= $row['payroll_deduction_id']; ?></span></td>
                    <td>
                      <div><small class="text-muted">Period:</small> <?= $payroll_period; ?></div>
                      <div><small class="text-muted">Payroll Date:</small> <?= $payroll_date; ?></div>
                      <div><small class="text-muted">Detail ID:</small> <?= $row['payroll_detail_id']; ?></div>
                    </td>
                    <td>
                      <div><?= $employee_name; ?></div>
                      <div><small class="text-muted">ID: <?= $row['employee_id']; ?></small></div>
                    </td>
                    <td>
                      <?= $deduction_type; ?>
                      <br><small class="text-muted">Type ID: <?= $deduction_type_id; ?></small>
                    </td>
                    <td class="text-end">
                      <span class="fw-bold text-danger">₱<?= number_format($row['amount'], 2); ?></span>
                    </td>
                    <td>
                      <?= !empty($row['description']) ? htmlspecialchars($row['description']) : '<span class="text-muted">No description</span>'; ?>
                    </td>
                    <td>
                      <span class="badge bg-<?= $status == 'Draft' ? 'warning' : ($status == 'Finalized' ? 'info' : 'success') ?>">
                        <?= $status; ?>
                      </span>
                    </td>
                  </tr>
                  <?php endwhile; } ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <div class="row">
              <div class="col-md-6">
                <small class="text-muted">
                  <i class="ti ti-info-circle me-1"></i>
                  This page shows all payroll deductions across all payroll periods.
                </small>
                <?php if (empty($deduction_types)): ?>
                  <div class="alert alert-warning mt-2 p-2">
                    <small><i class="ti ti-alert-triangle me-1"></i> No deduction types found in database. Add deduction types in the deduction types management page.</small>
                  </div>
                <?php endif; ?>
              </div>
              <div class="col-md-6 text-end">
                <a href="payroll.php" class="btn btn-secondary">
                  <i class="ti ti-arrow-left me-1"></i>Back to Payroll
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
<script>
$(document).ready(function () {
  // Initialize DataTable
  const table = $('#deductionsTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search designations...",
      zeroRecords: "No matching designations found",
      emptyTable: "No designations available",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      paginate: {
        first: "First", last: "Last", next: "Next", previous: "Previous"
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, width: '80px', className: 'text-center' },
      { targets: 3, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    initComplete: function () {
      // Add search icon after DataTable is fully initialized
      addSearchIcon();
      bindActionButtons();
    },
    drawCallback: function () {
      bindActionButtons();
    }
  });

  // Function to add search icon
  function addSearchIcon() {
    const searchBox = $('.dataTables_filter');
    if (searchBox.length && searchBox.find('.ti-search').length === 0) {
      searchBox.addClass('position-relative');
      searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
      searchBox.find('input').addClass('form-control ps-5 dt-search-input');
    }
  }
  
  // Function to bind action buttons (if needed)
  function bindActionButtons() {
    // Your button binding logic here
  }
});
</script>
