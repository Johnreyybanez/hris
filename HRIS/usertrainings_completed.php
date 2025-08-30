<?php
session_start();
include 'connection.php'; // adjust the path if needed

// Ensure the user is logged in and is an employee


$employee_id = $_SESSION['user_id'];

// Fetch only completed training records (where end_date < today)
$training_q = mysqli_query($conn, "
    SELECT t.*, c.name 
    FROM employeetrainings t
    JOIN trainingcategories c ON c.training_category_id = t.training_category_id
    WHERE t.employee_id = $employee_id
    AND t.end_date < CURDATE()
    ORDER BY t.start_date DESC
");
?>
<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container py-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Trainings Completed</h5>
        </div>

        <div class="card-body table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
              <tr>
                <th>Category</th>
                <th>Title</th>
                <th>Provider</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($training_q) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($training_q)) : ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['training_title']) ?></td>
                    <td><?= htmlspecialchars($row['provider']) ?></td>
                    <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                    <td><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">No completed trainings found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
