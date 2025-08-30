<?php
session_start();
include 'connection.php';



$employee_id = $_SESSION['user_id'];

// Fetch deductions
$deductions = mysqli_query($conn, "
    SELECT ed.*, dt.name AS deduction_type_name
    FROM EmployeeDeductions ed
    LEFT JOIN DeductionTypes dt ON ed.deduction_type_id = dt.deduction_id
    WHERE ed.employee_id = '$employee_id'
    ORDER BY ed.start_date DESC
");
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container-fluid py-4">
      <div class="card card-body shadow mb-4">
        <div class="card-header">
          <h5 class="mb-0">My Deductions</h5>
          <small class="text-muted">List of deductions applied to your payroll</small>
        </div>

        <div class="table-responsive mt-3">
          <table id="deductionTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>Start</th>
                <th>End</th>
                <th>Recurring</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($deductions) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($deductions)): ?>
                  <tr class="text-center">
                    <td><?= htmlspecialchars($row['deduction_type_name']) ?></td>
                    <td>₱<?= number_format($row['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?: '—' ?></td>
                    <td><?= $row['is_recurring'] ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No deductions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
