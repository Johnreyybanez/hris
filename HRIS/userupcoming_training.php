<?php
session_start();
include 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];

// Fetch employee's upcoming trainings
$training_q = mysqli_query($conn, "
    SELECT et.*, tc.name AS category_name
    FROM EmployeeTrainings et
    LEFT JOIN TrainingCategories tc ON et.training_category_id = tc.training_category_id
    WHERE et.employee_id = '$employee_id'
    AND et.end_date >= CURDATE()
    ORDER BY et.start_date ASC
");

if (!$training_q) {
    echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
    exit;
}
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container py-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">My Upcoming Trainings</h5>
        </div>

        <div class="card-body table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-info">
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
                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                    <td><?= htmlspecialchars($row['training_title']) ?></td>
                    <td><?= htmlspecialchars($row['provider']) ?></td>
                    <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                    <td><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">No upcoming trainings found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>


