<?php
session_start();
include 'connection.php';

// ADD EMPLOYEE
if (isset($_POST['add_employee'])) {
    $employee_number = $_POST['employee_number'];
    $biometric_id = $_POST['biometric_id'];
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $middle = $_POST['middle_name'];
    $birthdate = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $civil = $_POST['civil_status'];
    $contact = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $hire = $_POST['hire_date'];
    $regular = $_POST['date_regular'];
    $ended = $_POST['date_ended'];
    $total_years_service = $_POST['total_years_service'];
    $status = $_POST['status'];
    $department_id = $_POST['department_id'];
    $shift_id = $_POST['shift_id'];
    $designation_id = $_POST['designation_id'];
    $employmenttype_id = $_POST['employmenttype_id'];
    
    // New payroll fields
    $salary_type = $_POST['salary_type'] ?? 'Monthly';
    $employee_rates = $_POST['employee_rates'] ?? 0.00;
    $payroll_group_id = !empty($_POST['payroll_group_id']) ? $_POST['payroll_group_id'] : null;
    $tax_status = $_POST['tax_status'] ?? 'Single';
    $is_tax_deducted = isset($_POST['is_tax_deducted']) ? 1 : 0;

    $photo_path = '';

    // Handle file upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photo_name = basename($_FILES['photo']['name']);
        $target_dir = "uploads/documents/";
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $target_path = $target_dir . time() . '_' . $photo_name;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_path = $target_path;
        }
    }

    // Date formatting function
    function formatDateForDB($dateString) {
        if (empty($dateString)) {
            return null;
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
        
        return null;
    }

    // Format dates properly
    $hire_formatted = formatDateForDB($hire);
    $regular_formatted = formatDateForDB($regular);
    $ended_formatted = formatDateForDB($ended);
    $birthdate_formatted = formatDateForDB($birthdate);

    // Updated query with new payroll fields
    $stmt = $conn->prepare("INSERT INTO employees (
        employee_number, biometric_id, first_name, last_name, middle_name,
        birth_date, gender, civil_status, phone, email, address,
        hire_date, date_regular, date_ended, total_years_service,
        photo_path, status, department_id, shift_id,
        designation_id, employmenttype_id, salary_type, employee_rates,
        payroll_group_id, tax_status, is_tax_deducted
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sssssssssssssssssiiiisdisi", 
            $employee_number, $biometric_id, $first, $last, $middle,
            $birthdate_formatted, $gender, $civil, $contact, $email, $address,
            $hire_formatted, $regular_formatted, $ended_formatted, $total_years_service,
            $photo_path, $status, $department_id, $shift_id,
            $designation_id, $employmenttype_id, $salary_type, $employee_rates,
            $payroll_group_id, $tax_status, $is_tax_deducted
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Employee added successfully!';
        } else {
            $_SESSION['error'] = 'Failed to add employee: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Database prepare error: ' . $conn->error;
    }

    header("Location: employees.php");
    exit();
}
// DELETE EMPLOYEE - ROBUST VERSION WITH AUTO FK DETECTION
if (isset($_POST['delete_employee'])) {
    $delete_id = $_POST['delete_id'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get photo path before deleting to remove file
        $stmt = $conn->prepare("SELECT photo_path FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();
        
        if (!$employee) {
            throw new Exception("Employee not found.");
        }
        
        // Get database name
        $db_name_result = mysqli_query($conn, "SELECT DATABASE()");
        $db_name = mysqli_fetch_row($db_name_result)[0];
        
        // Find all tables that reference employees table
        $fk_query = "
            SELECT 
                TABLE_NAME,
                COLUMN_NAME
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE 
                REFERENCED_TABLE_SCHEMA = '$db_name'
                AND REFERENCED_TABLE_NAME = 'employees'
                AND REFERENCED_COLUMN_NAME = 'employee_id'
            ORDER BY TABLE_NAME
        ";
        
        $fk_result = mysqli_query($conn, $fk_query);
        $tables_with_fk = [];
        
        while ($row = mysqli_fetch_assoc($fk_result)) {
            $tables_with_fk[$row['TABLE_NAME']] = $row['COLUMN_NAME'];
        }
        
        // Add common tables that might not have FK constraints defined
        $additional_tables = [
            'attendance' => 'employee_id',
            'leaves' => 'employee_id',
            'leave_requests' => 'employee_id',
            'payroll' => 'employee_id',
            'payroll_details' => 'employee_id',
            'overtime' => 'employee_id',
            'overtime_requests' => 'employee_id',
            'schedules' => 'employee_id',
            'time_logs' => 'employee_id',
            'deductions' => 'employee_id',
            'benefits' => 'employee_id',
            'loans' => 'employee_id',
            'advances' => 'employee_id'
        ];
        
        // Merge both lists (FK detected tables take priority)
        $tables_to_clean = array_merge($additional_tables, $tables_with_fk);
        
        $deleted_tables = [];
        $total_records_deleted = 0;
        
        // Delete from child tables first
        foreach ($tables_to_clean as $table => $column) {
            // Check if table exists
            $check_table = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            
            if (mysqli_num_rows($check_table) > 0) {
                // Verify the column exists
                $check_column = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
                
                if (mysqli_num_rows($check_column) > 0) {
                    // Count records first
                    $count_query = "SELECT COUNT(*) as cnt FROM `$table` WHERE `$column` = ?";
                    $count_stmt = $conn->prepare($count_query);
                    $count_stmt->bind_param("i", $delete_id);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $record_count = $count_result->fetch_assoc()['cnt'];
                    $count_stmt->close();
                    
                    if ($record_count > 0) {
                        // Delete records
                        $delete_query = "DELETE FROM `$table` WHERE `$column` = ?";
                        $stmt = $conn->prepare($delete_query);
                        
                        if ($stmt) {
                            $stmt->bind_param("i", $delete_id);
                            if ($stmt->execute()) {
                                $deleted_tables[] = "$table ($record_count records)";
                                $total_records_deleted += $record_count;
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
        
        // Finally, delete the employee record
        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete employee: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected_rows === 0) {
            throw new Exception("Employee not found or already deleted.");
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Delete photo file if exists (after successful DB deletion)
        if (!empty($employee['photo_path']) && file_exists($employee['photo_path'])) {
            @unlink($employee['photo_path']);
        }
        
        // Build detailed success message
        $success_msg = 'Employee deleted successfully!';
        
        if (!empty($deleted_tables)) {
            $success_msg .= '<br><small class="text-muted">Deleted ' . $total_records_deleted . ' related records from: ' . implode(', ', $deleted_tables) . '</small>';
        }
        
        $_SESSION['success'] = $success_msg;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error'] = 'Failed to delete employee: ' . $e->getMessage();
    }
    
    header("Location: employees.php");
    exit();
}
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
              <h5 class="m-b-10">Employee Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">HR</li>
              <li class="breadcrumb-item">Employees</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Employees</h5>
              <small class="text-muted">Manage employee records</small>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-success" onclick="window.open('print_employees.php', '_blank')">
                <i class="ti ti-printer me-1"></i>Print List
              </button>
              <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importRatesModal">
                <i class="ti ti-file-upload me-1"></i>Import Rates
              </button>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="ti ti-plus me-1"></i>Add Employee
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="employeeTable">
                <thead>
                  <tr>
                    <th>Photo</th>
                    <th>Emp. No.</th>
                    <th>Biometric ID</th>
                    <th>Employee Name</th>
                    <th>Hire Date</th>
                    <th>Salary Type</th>
                    <th>Rate</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $result = mysqli_query($conn, "SELECT * FROM employees ORDER BY employee_number ASC");
                  if ($result && mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)): 
                    
                    // Format hire date
                    $hire_display = 'N/A';
                    $hire_raw = $row['hire_date'];
                    
                    if (!empty($hire_raw) && $hire_raw !== '0000-00-00' && $hire_raw !== null) {
                        $timestamp = strtotime($hire_raw);
                        if ($timestamp !== false && $timestamp > 0) {
                            $hire_display = date('M d, Y', $timestamp);
                        } else {
                            $hire_date = DateTime::createFromFormat('Y-m-d', $hire_raw);
                            if ($hire_date !== false) {
                                $hire_display = $hire_date->format('M d, Y');
                            } else {
                                $hire_date = DateTime::createFromFormat('Y-m-d H:i:s', $hire_raw);
                                if ($hire_date !== false) {
                                    $hire_display = $hire_date->format('M d, Y');
                                }
                            }
                        }
                    }
                    
                    // Format salary rate display
                    $rate_display = number_format($row['employee_rates'] ?? 0, 2);
                    $salary_type = $row['salary_type'] ?? 'Monthly';
                    ?>
                  <tr>
                    <td>
                      <?php if (!empty($row['photo_path']) && file_exists($row['photo_path'])): ?>
                        <img src="<?= htmlspecialchars($row['photo_path']) ?>" alt="Photo" style="height:40px; width:40px; object-fit:cover; border-radius:4px;">
                      <?php else: ?>
                    <center>  
                      <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-center" style="height:40px; width:40px;">
                        <i class="ti ti-user text-white" style="line-height: 1; font-size: 20px;"></i>
                      </div>
                    </center>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['employee_number']) ?></td>
                    <td><?= htmlspecialchars($row['biometric_id']) ?></td>
                    <td class="text-start"><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['first_name']) ?> <?= htmlspecialchars($row['middle_name']) ?></td>
                    <td><?= $hire_display ?></td>
                    <td>
                      <span class="badge bg-<?= $salary_type == 'Monthly' ? 'primary' : ($salary_type == 'Daily' ? 'info' : 'warning') ?>">
                        <?= htmlspecialchars($salary_type) ?>
                      </span>
                    </td>
                    <td>₱<?= $rate_display ?></td>
                    <td>
                      <span class="badge bg-<?= $row['status'] == 'Active' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($row['status']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group gap-1">
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['employee_id'] ?>"
                          data-photo="<?= htmlspecialchars($row['photo_path']) ?>"
                          data-number="<?= htmlspecialchars($row['employee_number']) ?>"
                          data-bio="<?= htmlspecialchars($row['biometric_id']) ?>"
                          data-name="<?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_name']) ?>"
                          data-email="<?= htmlspecialchars($row['email']) ?>"
                          data-phone="<?= htmlspecialchars($row['phone']) ?>"
                          data-address="<?= htmlspecialchars($row['address']) ?>"
                          data-birthdate="<?php 
                            $birthdate_raw = $row['birth_date'];
                            if (!empty($birthdate_raw) && $birthdate_raw !== '0000-00-00' && $birthdate_raw !== null) {
                                $timestamp = strtotime($birthdate_raw);
                                if ($timestamp !== false && $timestamp > 0) {
                                    echo date('M d, Y', $timestamp);
                                } else {
                                    $birth_date = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
                                    if ($birth_date !== false) {
                                        echo $birth_date->format('M d, Y');
                                    } else {
                                        $birth_date = DateTime::createFromFormat('Y-m-d H:i:s', $birthdate_raw);
                                        echo ($birth_date !== false) ? $birth_date->format('M d, Y') : 'N/A';
                                    }
                                }
                            } else {
                                echo 'N/A';
                            }
                          ?>"
                          data-gender="<?= htmlspecialchars($row['gender']) ?>"
                          data-civil="<?= htmlspecialchars($row['civil_status']) ?>"
                          data-hire="<?= $hire_display ?>"
                          data-regular="<?php 
                            $regular_raw = $row['date_regular'];
                            if (!empty($regular_raw) && $regular_raw !== '0000-00-00' && $regular_raw !== null) {
                                $timestamp = strtotime($regular_raw);
                                if ($timestamp !== false && $timestamp > 0) {
                                    echo date('M d, Y', $timestamp);
                                } else {
                                    $regular_date = DateTime::createFromFormat('Y-m-d', $regular_raw);
                                    if ($regular_date !== false) {
                                        echo $regular_date->format('M d, Y');
                                    } else {
                                        $regular_date = DateTime::createFromFormat('Y-m-d H:i:s', $regular_raw);
                                        echo ($regular_date !== false) ? $regular_date->format('M d, Y') : 'N/A';
                                    }
                                }
                            } else {
                                echo 'N/A';
                            }
                          ?>"
                          data-ended="<?php 
                            $ended_raw = $row['date_ended'];
                            if (!empty($ended_raw) && $ended_raw !== '0000-00-00' && $ended_raw !== null) {
                                $timestamp = strtotime($ended_raw);
                                if ($timestamp !== false && $timestamp > 0) {
                                    echo date('M d, Y', $timestamp);
                                } else {
                                    $ended_date = DateTime::createFromFormat('Y-m-d', $ended_raw);
                                    if ($ended_date !== false) {
                                        echo $ended_date->format('M d, Y');
                                    } else {
                                        $ended_date = DateTime::createFromFormat('Y-m-d H:i:s', $ended_raw);
                                        echo ($ended_date !== false) ? $ended_date->format('M d, Y') : 'N/A';
                                    }
                                }
                            } else {
                                echo 'N/A';
                            }
                          ?>"
                          data-years="<?= htmlspecialchars($row['total_years_service']) ?>"
                          data-status="<?= htmlspecialchars($row['status']) ?>"
                          data-salary-type="<?= htmlspecialchars($salary_type) ?>"
                          data-rate="<?= $rate_display ?>"
                          data-tax-status="<?= htmlspecialchars($row['tax_status'] ?? 'Single') ?>"
                          data-tax-deducted="<?= ($row['is_tax_deducted'] ?? 1) ? 'Yes' : 'No' ?>">
                          <i class="ti ti-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning editBtn" data-id="<?= $row['employee_id'] ?>">
                          <i class="ti ti-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['employee_id'] ?>">
                          <i class="ti ti-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; 
                  else: ?>
                  <tr>
                    <td colspan="9" class="text-center py-4">
                      <div class="text-muted">
                        <i class="ti ti-users-off mb-2" style="font-size: 2rem;"></i>
                        <p>No employees found</p>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<?php include 'modals/add_modals.php'; ?>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="viewModalLabel">Employee Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-3 text-center">
            <img id="viewPhoto" src="assets/img/default.png" alt="Photo" class="img-thumbnail rounded" style="max-height: 150px; max-width: 150px; object-fit: cover;">
          </div>
          <div class="col-md-9">
            <div class="row">
              <div class="col-md-4">
                <p><strong>Employee No:</strong> <span id="viewEmpNo"></span></p>
                <p><strong>Biometric ID:</strong> <span id="viewBioID"></span></p>
                <p><strong>Name:</strong> <span id="viewName"></span></p>
                <p><strong>Email:</strong> <span id="viewEmail"></span></p>
                <p><strong>Phone:</strong> <span id="viewPhone"></span></p>
                <p><strong>Address:</strong> <span id="viewAddress"></span></p>
                <p><strong>Birth Date:</strong> <span id="viewBirthdate"></span></p>
              </div>
              <div class="col-md-4">
                <p><strong>Gender:</strong> <span id="viewGender"></span></p>
                <p><strong>Civil Status:</strong> <span id="viewCivil"></span></p>
                <p><strong>Hire Date:</strong> <span id="viewHire" class="text-primary fw-bold"></span></p>
                <p><strong>Date Regular:</strong> <span id="viewRegular"></span></p>
                <p><strong>Date Ended:</strong> <span id="viewEnded"></span></p>
                <p><strong>Total Years of Service:</strong> <span id="viewYears"></span></p>
                <p><strong>Status:</strong> <span id="viewStatus" class="badge"></span></p>
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0"><i class="ti ti-currency-dollar me-1"></i>Payroll Information</h6>
                  </div>
                  <div class="card-body p-3">
                    <p><strong>Salary Type:</strong> <span id="viewSalaryType" class="badge"></span></p>
                    <p><strong>Rate:</strong> ₱<span id="viewRate"></span></p>
                    <p><strong>Tax Status:</strong> <span id="viewTaxStatus"></span></p>
                    <p><strong>Tax Deducted:</strong> <span id="viewTaxDeducted" class="badge"></span></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Import Rates Modal -->
<div class="modal fade" id="importRatesModal" tabindex="-1" aria-labelledby="importRatesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="importRatesModalLabel">
          <i class="ti ti-file-upload me-2"></i>Import Employee Rates from Excel/CSV
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="import_rates.php" enctype="multipart/form-data" id="importRatesForm">
        <div class="modal-body">
          <!-- Instructions -->
          <div class="alert alert-info border-info">
            <h6 class="alert-heading"><i class="ti ti-info-circle me-1"></i>How to Import Rates</h6>
            <ol class="mb-0 ps-3">
              <li>Download the blank template OR export current employee rates</li>
              <li>Fill in the employee names (format: "Last, First") and new rates</li>
              <li>Optionally update the salary type (Monthly, Daily, or Hourly)</li>
              <li>Save the file and upload it below</li>
            </ol>
          </div>

          <!-- Download Options -->
          <div class="row mb-4">
            <div class="col-md-6">
              <a href="import_rates.php?download_template=1" class="btn btn-outline-success w-100">
                <i class="ti ti-file-text me-2"></i>Download Blank Template
              </a>
            </div>
            <div class="col-md-6">
              <a href="import_rates.php?export_current=1" class="btn btn-outline-primary w-100">
                <i class="ti ti-database-export me-2"></i>Export Current Rates
              </a>
            </div>
          </div>

          <!-- File Format Info -->
          <div class="card bg-light mb-4">
            <div class="card-body">
              <h6 class="card-title"><i class="ti ti-table me-1"></i>CSV Format Example</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead class="table-primary">
                    <tr>
                      <th>Employee Name</th>
                      <th>New Rate</th>
                      <th>Salary Type (Optional)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Dela Cruz, Juan</td>
                      <td>25000.00</td>
                      <td>Monthly</td>
                    </tr>
                    <tr>
                      <td>Santos, Maria</td>
                      <td>500.00</td>
                      <td>Daily</td>
                    </tr>
                    <tr>
                      <td>Reyes, Pedro</td>
                      <td>150.00</td>
                      <td>Hourly</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <small class="text-muted mt-2 d-block">
                <strong>Note:</strong> Use format "Last Name, First Name" or "Last Name, First Name Middle Name". If Salary Type is empty, only the rate will be updated.
              </small>
            </div>
          </div>

          <!-- File Upload -->
          <div class="mb-3">
            <label for="excel_file" class="form-label fw-bold">
              <i class="ti ti-file-upload me-1"></i>Select CSV File to Upload
            </label>
            <input type="file" class="form-control form-control-lg" id="excel_file" name="excel_file" 
                   accept=".csv" required>
            <div class="form-text">
              <i class="ti ti-info-circle me-1"></i>
              Accepted format: <strong>.csv</strong> only. Open your Excel file and "Save As" → "CSV (Comma delimited)".
            </div>
          </div>

          <!-- Warning -->
          <div class="alert alert-warning border-warning mb-0">
            <i class="ti ti-alert-triangle me-1"></i>
            <strong>Warning:</strong> This will update employee rates in the database immediately. Make sure to review your file before uploading!
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="ti ti-x me-1"></i>Cancel
          </button>
          <button type="submit" name="import_rates" class="btn btn-info" id="submitImportBtn">
            <i class="ti ti-upload me-1"></i>Upload & Import Rates
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hidden Delete Form -->
<form method="POST" id="deleteForm">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_employee" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#employeeTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search employees...",
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, className: 'text-center align-middle', width: '80px' },
      { targets: -1, orderable: false, className: 'text-center align-middle' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActions();
    }
  });

  function bindActions() {
    $('.editBtn').off('click').on('click', function () {
      const id = $(this).data('id');
      window.open('edit_employee.php?id=' + id, '_blank');
    });

    $('.deleteBtn').off('click').on('click', function () {
      const id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?',
        html: '<p>This will permanently delete the employee and <strong>all related records</strong>:</p>' +
              '<ul class="text-start">' +
              '<li>Attendance records</li>' +
              '<li>Leave requests</li>' +
              '<li>Payroll history</li>' +
              '<li>Overtime records</li>' +
              '<li>And more...</li>' +
              '</ul>' +
              '<p class="text-danger fw-bold">This action cannot be undone!</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete everything!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading
          Swal.fire({
            title: 'Deleting...',
            text: 'Please wait while we delete the employee and related records.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
          
          $('#delete_id').val(id);
          $('#deleteForm').submit();
        }
      });
    });

    $('.viewBtn').off('click').on('click', function () {
      const photoPath = $(this).data('photo');
      if (photoPath && photoPath !== '') {
        $('#viewPhoto').show().attr('src', photoPath);
        $('#noPhotoPlaceholder').hide();
      } else {
        $('#viewPhoto').hide();
        $('#noPhotoPlaceholder').show();
      }
      
      $('#viewEmpNo').text($(this).data('number'));
      $('#viewBioID').text($(this).data('bio'));
      $('#viewName').text($(this).data('name'));
      $('#viewEmail').text($(this).data('email'));
      $('#viewPhone').text($(this).data('phone'));
      $('#viewAddress').text($(this).data('address'));
      $('#viewBirthdate').text($(this).data('birthdate'));
      $('#viewGender').text($(this).data('gender'));
      $('#viewCivil').text($(this).data('civil'));
      $('#viewHire').text($(this).data('hire'));
      $('#viewRegular').text($(this).data('regular'));
      $('#viewEnded').text($(this).data('ended'));
      $('#viewYears').text($(this).data('years'));
      
      // Status badge
      const status = $(this).data('status');
      const statusBadge = $('#viewStatus');
      statusBadge.text(status);
      statusBadge.removeClass('bg-success bg-danger bg-warning');
      statusBadge.addClass(status === 'Active' ? 'bg-success' : 'bg-danger');
      
      // Payroll information
      const salaryType = $(this).data('salary-type');
      const salaryTypeBadge = $('#viewSalaryType');
      salaryTypeBadge.text(salaryType);
      salaryTypeBadge.removeClass('bg-primary bg-info bg-warning');
      salaryTypeBadge.addClass(salaryType === 'Monthly' ? 'bg-primary' : (salaryType === 'Daily' ? 'bg-info' : 'bg-warning'));
      
      $('#viewRate').text($(this).data('rate'));
      $('#viewTaxStatus').text($(this).data('tax-status'));
      
      const taxDeducted = $(this).data('tax-deducted');
      const taxDeductedBadge = $('#viewTaxDeducted');
      taxDeductedBadge.text(taxDeducted);
      taxDeductedBadge.removeClass('bg-success bg-danger');
      taxDeductedBadge.addClass(taxDeducted === 'Yes' ? 'bg-success' : 'bg-danger');
      
      $('#viewModal').modal('show');
    });
  }

  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }
  
  // Import form validation and loading
  $('#importRatesForm').on('submit', function(e) {
    const fileInput = $('#excel_file')[0];
    
    if (!fileInput.files || fileInput.files.length === 0) {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'No File Selected',
        text: 'Please select a CSV file to upload.'
      });
      return false;
    }
    
    const file = fileInput.files[0];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (fileExtension !== 'csv') {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Invalid File Type',
        text: 'Please upload a CSV file only.'
      });
      return false;
    }
    
    // Show loading
    Swal.fire({
      title: 'Importing Rates...',
      text: 'Please wait while we process your file.',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
  });
  
  // Success messages
  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success']; ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
  <?php unset($_SESSION['success']); endif; ?>

  // Error messages
  <?php if (isset($_SESSION['error'])): ?>
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    html: '<?= addslashes($_SESSION['error']); ?>',
    confirmButtonText: 'OK',
    width: '600px'
  });
  <?php unset($_SESSION['error']); endif; ?>

  // Warning messages
  <?php if (isset($_SESSION['warning'])): ?>
  Swal.fire({
    icon: 'warning',
    title: 'Import Completed with Warnings',
    html: '<?= addslashes($_SESSION['warning']); ?>',
    confirmButtonText: 'OK',
    width: '600px'
  });
  <?php unset($_SESSION['warning']); endif; ?>

  // Updated messages
  <?php if (isset($_SESSION['updated'])): ?>
  Swal.fire({
    icon: 'success',
    title: '<?= $_SESSION['updated']; ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
  <?php unset($_SESSION['updated']); endif; ?>
});
</script>
