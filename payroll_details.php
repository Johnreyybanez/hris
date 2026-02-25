<?php
session_start();
include 'connection.php';

// UPDATE PAYROLL DETAIL
if (isset($_POST['update_detail'])) {
    $payroll_detail_id = intval($_POST['payroll_detail_id']);
    $payroll_id = $_POST['payroll_id'];
    $employee_id = $_POST['employee_id'];
    $gross_pay = $_POST['gross_pay'];
    $total_deductions = $_POST['total_deductions'];
    $net_pay = $_POST['net_pay'];
    $remarks = $_POST['remarks'];

    $update = mysqli_query($conn, "UPDATE payroll_details SET payroll_id='$payroll_id', employee_id='$employee_id', gross_pay='$gross_pay', total_deductions='$total_deductions', net_pay='$net_pay', remarks='$remarks' WHERE payroll_detail_id=$payroll_detail_id");
    if ($update) {
        $_SESSION['success'] = 'Payroll detail updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update payroll detail.';
    }
    header("Location: payroll_details.php");
    exit();
}

// DELETE PAYROLL DETAIL
if (isset($_POST['delete_detail'])) {
    $payroll_detail_id = intval($_POST['delete_id']);
    $delete = mysqli_query($conn, "DELETE FROM payroll_details WHERE payroll_detail_id=$payroll_detail_id");
    if ($delete) {
        $_SESSION['success'] = 'Payroll detail deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete payroll detail.';
    }
    header("Location: payroll_details.php");
    exit();
}
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<!-- Main Content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Payroll Details Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Payroll</li>
              <li class="breadcrumb-item active">Payroll Details</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Payroll Details</h5>
            <small class="text-muted">Manage individual employee payroll details</small>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="detailTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Payroll</th>
                    <th>Employee</th>
                    <th>Gross Pay</th>
                    <th>Total Deductions</th>
                    <th>Net Pay</th>
                    <th>Remarks</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $query = "SELECT pd.*, p.payroll_date, e.first_name, e.last_name, e.employee_number 
                           FROM payroll_details pd 
                           LEFT JOIN payroll p ON pd.payroll_id = p.payroll_id 
                           LEFT JOIN employees e ON pd.employee_id = e.employee_id 
                           ORDER BY pd.payroll_detail_id DESC";
                  
                  $result = mysqli_query($conn, $query);
                  
                  if (!$result) {
                      echo "<tr><td colspan='8' class='text-center text-danger'>Error: " . mysqli_error($conn) . "</td></tr>";
                  } elseif (mysqli_num_rows($result) == 0) {
                      echo "<tr><td colspan='8' class='text-center text-muted'>No payroll details found</td></tr>";
                  } else {
                      while ($row = mysqli_fetch_assoc($result)): 
                  ?>
                  <tr>
                    <td><span class="badge bg-dark"><?= $row['payroll_detail_id']; ?></span></td>
                    <td><?= $row['payroll_date'] ? date('M d, Y', strtotime($row['payroll_date'])) : 'N/A'; ?></td>
                    <td>
                      <?php if ($row['first_name']): ?>
                        <div>
                          <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                          <br><small class="text-muted"><?= htmlspecialchars($row['employee_number']); ?></small>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">N/A</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">₱<?= number_format($row['gross_pay'], 2); ?></td>
                    <td class="text-end text-danger">₱<?= number_format($row['total_deductions'], 2); ?></td>
                    <td class="text-end text-success"><strong>₱<?= number_format($row['net_pay'], 2); ?></strong></td>
                    <td><?= htmlspecialchars($row['remarks'] ?? ''); ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['payroll_detail_id']; ?>"
                          data-payroll-id="<?= $row['payroll_id']; ?>"
                          data-employee-id="<?= $row['employee_id']; ?>"
                          data-gross-pay="<?= $row['gross_pay']; ?>"
                          data-total-deductions="<?= $row['total_deductions']; ?>"
                          data-net-pay="<?= $row['net_pay']; ?>"
                          data-remarks="<?= htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES); ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['payroll_detail_id']; ?>"
                          title="Delete"><i class="ti ti-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                  <?php 
                      endwhile; 
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Payroll Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="payroll_detail_id" id="edit_id">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Payroll</label>
            <select name="payroll_id" id="edit_payroll_id" class="form-select" required>
              <option value="">Select Payroll</option>
              <?php
              $payrolls_query = "SELECT p.*, pg.name as group_name 
                                FROM payroll p 
                                LEFT JOIN payroll_groups pg ON p.payroll_group_id = pg.payroll_group_id 
                                ORDER BY p.payroll_date DESC";
              $payrolls = mysqli_query($conn, $payrolls_query);
              if ($payrolls && mysqli_num_rows($payrolls) > 0) {
                  while ($payroll = mysqli_fetch_assoc($payrolls)): ?>
                    <option value="<?= $payroll['payroll_id']; ?>">
                      <?= htmlspecialchars($payroll['group_name'] ?? 'No Group'); ?> - <?= date('M d, Y', strtotime($payroll['payroll_date'])); ?>
                    </option>
                  <?php endwhile; 
              }
              ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Employee</label>
            <select name="employee_id" id="edit_employee_id" class="form-select" required>
              <option value="">Select Employee</option>
              <?php
              $employees_query = "SELECT * FROM employees ORDER BY first_name ASC";
              $employees = mysqli_query($conn, $employees_query);
              if ($employees && mysqli_num_rows($employees) > 0) {
                  while ($employee = mysqli_fetch_assoc($employees)): ?>
                    <option value="<?= $employee['employee_id']; ?>">
                      <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> (<?= htmlspecialchars($employee['employee_number']); ?>)
                    </option>
                  <?php endwhile; 
              }
              ?>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Gross Pay</label>
            <input type="number" name="gross_pay" id="edit_gross_pay" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Total Deductions</label>
            <input type="number" name="total_deductions" id="edit_total_deductions" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Net Pay</label>
            <input type="number" name="net_pay" id="edit_net_pay" class="form-control" step="0.01" min="0" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea name="remarks" id="edit_remarks" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_detail" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_detail" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#detailTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search payroll details...",
      zeroRecords: "No matching records found",
      emptyTable: "No payroll details available",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      paginate: { first: "First", last: "Last", next: "Next", previous: "Previous" }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, className: 'text-center', width: '60px' },
      { targets: [3, 4, 5], className: 'text-end' },
      { targets: 7, orderable: false, searchable: false, className: 'text-center' } // Disable ordering and searching for Actions
    ],
    order: [[0, 'desc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_payroll_id').val($(this).data('payroll-id'));
      $('#edit_employee_id').val($(this).data('employee-id'));
      $('#edit_gross_pay').val($(this).data('gross-pay'));
      $('#edit_total_deductions').val($(this).data('total-deductions'));
      $('#edit_net_pay').val($(this).data('net-pay'));
      $('#edit_remarks').val($(this).data('remarks'));
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          $('#delete_id').val(id);
          $('#deleteForm').submit();
        }
      });
    });
  }

  // Bind buttons on first load
  bindActionButtons();
});
</script>