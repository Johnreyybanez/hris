<?php
session_start();
include 'connection.php';

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id'] ?? '');
    $payroll_group_id = mysqli_real_escape_string($conn, $_POST['payroll_group_id'] ?? '');
    $payroll_id = mysqli_real_escape_string($conn, $_POST['payroll_id'] ?? '');

    if (empty($payroll_id)) {
        $_SESSION['error_message'] = "❌ Error: Please select a payroll period.";
    } else {
        // Validate payroll period exists
        $payroll_query = "SELECT cutoff_start_date, cutoff_end_date, payroll_date 
                         FROM payroll WHERE payroll_id = '$payroll_id' AND status IN ('Finalized', 'Processed')";
        $payroll_result = mysqli_query($conn, $payroll_query);
        
        if (!$payroll_result) {
            $_SESSION['error_message'] = "Error fetching payroll data: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($payroll_result) == 0) {
            $_SESSION['error_message'] = "Error: Invalid or inactive payroll period selected.";
        } else {
            // Store form data in session to pass to print page
            $_SESSION['print_form_data'] = array(
                'employee_id' => $employee_id,
                'payroll_group_id' => $payroll_group_id,
                'payroll_id' => $payroll_id
            );
            
            // Redirect to print page
            header("Location: payroll_print_page.php");
            exit();
        }
    }
}

// Include UI components AFTER handling redirects
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch all employees for dropdown
$employees_query = "SELECT employee_id, CONCAT(last_name, ', ', first_name, 
                    CASE WHEN middle_name IS NOT NULL AND middle_name != '' 
                    THEN CONCAT(' ', middle_name) ELSE '' END) AS name 
                    FROM employees WHERE status='active' ORDER BY last_name";
$employees = mysqli_query($conn, $employees_query);

// Fetch all active payroll groups
$payroll_groups_query = "SELECT id, group_name FROM payroll_groups WHERE is_active = 1 ORDER BY group_name";
$payroll_groups = mysqli_query($conn, $payroll_groups_query);

// Fetch all payroll records for cutoff selection
$payroll_periods_query = "SELECT payroll_id, CONCAT(DATE_FORMAT(cutoff_start_date, '%M %d, %Y'), ' - ', DATE_FORMAT(cutoff_end_date, '%M %d, %Y')) AS period, payroll_date, cutoff_start_date, cutoff_end_date 
                         FROM payroll WHERE status IN ('Finalized', 'Processed') ORDER BY cutoff_start_date DESC";
$payroll_periods = mysqli_query($conn, $payroll_periods_query);
$payroll_periods_count = mysqli_num_rows($payroll_periods);

// Check for query errors
if (!$employees || !$payroll_groups || !$payroll_periods) {
    die("Error fetching data: " . mysqli_error($conn));
}

$search_performed = false;
$no_results = false;
$error_message = '';

// Check for error messages from form processing
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Remove the POST processing section since it's now at the top
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Payroll Summary Report</title>
    
    <!-- Include necessary CSS libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .filter-card { 
            margin-bottom: 20px; 
        }
        .alert { 
            margin: 20px auto; 
            max-width: 500px; 
        }
        .no-results-card { 
            max-width: 500px; 
            margin: 50px auto; 
            padding: 30px; 
            text-align: center; 
            border: 1px solid #dee2e6; 
            border-radius: 5px; 
            background-color: #f8f9fa; 
        }
        .no-results-icon { 
            font-size: 50px; 
            color: #6c757d; 
            margin-bottom: 20px; 
        }
        .button-container {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Payroll Summary</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item">Payroll</li>
                            <li class="breadcrumb-item">Payroll Summary</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($payroll_periods_count == 0): ?>
        <center>
            <div class="alert alert-warning">
                <h4 class="alert-heading">No Payroll Periods Available</h4>
                <p>No finalized or processed payroll periods found. Please create or finalize a payroll period to generate a summary.</p>
            </div>
        </center>
        <?php elseif (!empty($error_message)): ?>
        <center>
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error</h4>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        </center>
        <?php endif; ?>

        <?php if ($payroll_periods_count > 0): ?>
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i> Generate Payroll Summary Print
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3" id="payrollFilterForm">
                    <div class="col-md-4">
                        <label for="employee_id" class="form-label">
                            <i class="fas fa-user me-1"></i> Employee
                        </label>
                        <select name="employee_id" id="employee_id" class="form-select">
                            <option value="">-- All Employees --</option>
                            <?php 
                            mysqli_data_seek($employees, 0);
                            while ($emp = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?= $emp['employee_id'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="payroll_group_id" class="form-label">
                            <i class="fas fa-users me-1"></i> Payroll Group
                        </label>
                        <select name="payroll_group_id" id="payroll_group_id" class="form-select">
                            <option value="">-- All Groups --</option>
                            <?php 
                            mysqli_data_seek($payroll_groups, 0);
                            while ($group = mysqli_fetch_assoc($payroll_groups)): ?>
                                <option value="<?= $group['id'] ?>">
                                    <?= htmlspecialchars($group['group_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="payroll_id" class="form-label">
                            <i class="fas fa-calendar-alt me-1"></i> Payroll Period
                        </label>
                        <select name="payroll_id" id="payroll_id" class="form-select" required>
                            <option value="">-- Select Payroll Period --</option>
                            <?php 
                            mysqli_data_seek($payroll_periods, 0);
                            while ($period = mysqli_fetch_assoc($payroll_periods)): ?>
                                <option value="<?= $period['payroll_id'] ?>">
                                    <?= htmlspecialchars($period['period']) ?> (Pay Date: <?= date('M d, Y', strtotime($period['payroll_date'])) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-12 button-container">
                        <button type="submit" class="btn btn-success btn-l">
                            <i class="fas fa-print me-2"></i> Generate Print Format
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary btn-l ms-3">
                            <i class="fas fa-times me-2"></i> Clear Filter
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('#payrollFilterForm').on('submit', function(e) {
        if (!$('#payroll_id').val()) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select a payroll period.',
                confirmButtonText: 'OK'
            });
        }
    });
});
</script>

</body>
</html>