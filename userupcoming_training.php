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
        Upcoming Trainings
    </li>
</ul>
    <div class="container py-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">My Upcoming Trainings</h5>
        </div>

        <div class="card-body table-responsive">
          <table id="upcomingtrainingTable" class="table table-bordered ">
            <thead class="table-danger text-center">
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
                  <tr style="background-color: white;"> <!-- ✅ Force white background -->
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
                  <td colspan="6" class=" text-center">No upcoming trainings found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  #upcomingtrainingTable
  { background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}
</style>
