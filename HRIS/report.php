
<?php
session_start();
include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch all employees for the dropdown
$employees_query = "SELECT employee_id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS name FROM employees ORDER BY name";
$employees = mysqli_query($conn, $employees_query);

if (!$employees) {
    die("Error fetching employees: " . mysqli_error($conn));
}
?>

<!-- Include same CSS as Summary -->
<style>
    .filter-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: white;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }
    
</style>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Daily Time Record Generator </h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Report</li>
              <li class="breadcrumb-item">Daily Time Record Generator</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
                <!-- Filter Card -->
                <div class="card filter-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-3"></i>
                            Daily Time Record Generator
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="generate_dtr.php" method="POST" class="row g-3" target="_blank">
                            <div class="col-md-6">
                                <label for="employee_id" class="form-label">
                                    <i class="fas fa-user me-1"></i> Select Employee
                                </label>
                                <select name="employee_id" id="employee_id" class="form-select" required>
                                    <option value="">-- Select Employee --</option>
                                    <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                                        <option value="<?= $emp['employee_id'] ?>">
                                            <?= htmlspecialchars($emp['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> Start Date
                                </label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> End Date
                                </label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-alt me-2"></i> Generate DTR Form
                                </button>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i> Clear Form
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Information Card -->
                <div class="card info-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i> Instructions
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>How to generate your Daily Time Record:</strong></p>
                        <ul>
                            <li>Select an employee from the dropdown list</li>
                            <li>Choose the start and end dates for the period you want</li>
                            <li>Click "Generate DTR Form" – it will open in a new tab</li>
                            <li>You can print directly or save as PDF from that tab</li>
                        </ul>
                        <p><strong>Note:</strong> The form will include all attendance records for the selected employee within the specified date range.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set default date range (current month)
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    if (!startDate.value) {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        startDate.value = firstDay.toISOString().split('T')[0];
        endDate.value = lastDay.toISOString().split('T')[0];
    }
});
</script>
