<?php
session_start();
include 'connection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<div class='alert alert-danger m-4'>No employee ID provided.</div>";
  exit;
}

$employee_id = $_GET['id'];
$employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE employee_id = '$employee_id'"));

if (!$employee) {
  echo "<div class='alert alert-danger m-4'>Employee not found.</div>";
  exit;
}
// UPDATE EMPLOYEE
if (isset($_POST['update_employee'])) {
    $employee_id = $_POST['employee_id'];
    $employee_number = $_POST['employee_number'];
    $biometric_id = $_POST['biometric_id'];
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $middle = $_POST['middle_name'];
    $birthdate = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $civil = $_POST['civil_status'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $hire_date = $_POST['hire_date'];
    $date_regular = $_POST['date_regular'];
    $date_ended = $_POST['date_ended'];
    $total_years_service = $_POST['total_years_service'];
    $status = $_POST['status'];
    $department_id = $_POST['department_id'];
    $shift_id = $_POST['shift_id'];
    $designation_id = $_POST['designation_id'];
    $employmenttype_id = $_POST['employmenttype_id'];

    // New fields
    $salary_type = $_POST['salary_type']; // expected 'Monthly', 'Daily', 'Hourly'
    $employee_rates = $_POST['employee_rates']; // decimal value
    $payroll_group_id = !empty($_POST['payroll_group_id']) ? $_POST['payroll_group_id'] : "NULL"; // allow NULL
    $tax_status = $_POST['tax_status']; // expected 'Single', 'Married', 'Head of Family'
    $is_tax_deducted = isset($_POST['is_tax_deducted']) ? 1 : 0; // checkbox boolean

    // Fetch existing photo path
    $current = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT photo_path FROM employees WHERE employee_id = '$employee_id'")
    )['photo_path'];
    $photo_path = $current;

    // Handle new photo upload (if provided)
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_name = basename($_FILES['photo']['name']);
        $target_dir = "uploads/documents/";
        $target_path = $target_dir . time() . '_' . $photo_name;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_path = $target_path;
            // Optionally delete old file:
            if ($current && file_exists($current)) {
                @unlink($current);
            }
        }
    }

    // If payroll_group_id is NULL, don't quote it in SQL
    $payroll_group_id_sql = ($payroll_group_id === "NULL") ? "NULL" : "'$payroll_group_id'";

    $sql = "UPDATE employees SET
        employee_number     = '$employee_number',
        biometric_id        = '$biometric_id',
        first_name          = '$first',
        last_name           = '$last',
        middle_name         = '$middle',
        birth_date          = '$birthdate',
        gender              = '$gender',
        civil_status        = '$civil',
        email               = '$email',
        phone               = '$phone',
        address             = '$address',
        hire_date           = '$hire_date',
        date_regular        = '$date_regular',
        date_ended          = '$date_ended',
        total_years_service = '$total_years_service',
        photo_path          = '$photo_path',
        status              = '$status',
        department_id       = '$department_id',
        shift_id            = '$shift_id',
        designation_id      = '$designation_id',
        employmenttype_id   = '$employmenttype_id',
        salary_type         = '$salary_type',
        employee_rates      = '$employee_rates',
        payroll_group_id    = $payroll_group_id_sql,
        tax_status          = '$tax_status',
        is_tax_deducted     = '$is_tax_deducted'
    WHERE employee_id = '$employee_id'";

    $update = mysqli_query($conn, $sql);

    if ($update) {
        $_SESSION['updated'] = 'Employee updated successfully!';
        header("Location: employees.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Failed to update employee: " . mysqli_error($conn) . "</div>";
    }
}

// Handle form submission
if (isset($_POST['save_deduction'])) {
  $deduction_id = $_POST['deduction_id'] ?? '';
  $deduction_type_id = $_POST['deduction_type_id'];
  $amount = $_POST['amount'];
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $is_recurring = $_POST['is_recurring'];
  $remarks = $_POST['remarks'];

  if ($_POST['action'] ?? '' === 'delete') {
    $id = mysqli_real_escape_string($conn, $_POST['deduction_id']);
    mysqli_query($conn, "DELETE FROM EmployeeDeductions WHERE deduction_id = '$id'");
    $_SESSION['toast'] = 'Deduction deleted successfully!';
  } elseif (!empty($deduction_id)) {
    // Update
    mysqli_query($conn, "
      UPDATE EmployeeDeductions SET 
        deduction_type_id = '$deduction_type_id',
        amount = '$amount',
        start_date = '$start_date',
        end_date = '$end_date',
        is_recurring = '$is_recurring',
        remarks = '$remarks'
      WHERE deduction_id = '$deduction_id'
    ");
    $_SESSION['toast'] = 'Deduction updated successfully!';
  } else {
    // Insert
    mysqli_query($conn, "
      INSERT INTO EmployeeDeductions (employee_id, deduction_type_id, amount, start_date, end_date, is_recurring, remarks)
      VALUES ('$employee_id', '$deduction_type_id', '$amount', '$start_date', '$end_date', '$is_recurring', '$remarks')
    ");
    $_SESSION['toast'] = 'Deduction added successfully!';
  }

  // Redirect to prevent form resubmission
  header("Location: " . $_SERVER['REQUEST_URI']);
  exit;
}

if (isset($_POST['save_gov_id'])) {
    $gov_id = $_POST['gov_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = mysqli_real_escape_string($conn, $gov_id);
        mysqli_query($conn, "DELETE FROM EmployeeGovernmentIDs WHERE id='$id'");
        $_SESSION['toast'] = 'Government ID record deleted!';
    } else {
        // Escape string values
        $sss = mysqli_real_escape_string($conn, $_POST['sss_number']);
        $philhealth = mysqli_real_escape_string($conn, $_POST['philhealth_number']);
        $pagibig = mysqli_real_escape_string($conn, $_POST['pagibig_number']);
        $gsis = mysqli_real_escape_string($conn, $_POST['gsis_number']);
        $tin = mysqli_real_escape_string($conn, $_POST['tin_number']);

        // Deduction flags (checkboxes)
        $is_sss_deducted = isset($_POST['is_sss_deducted']) ? 1 : 0;
        $is_philhealth_deducted = isset($_POST['is_philhealth_deducted']) ? 1 : 0;
        $is_pagibig_deducted = isset($_POST['is_pagibig_deducted']) ? 1 : 0;
        $is_gsis_deducted = isset($_POST['is_gsis_deducted']) ? 1 : 0;

        if ($gov_id == '') {
            // INSERT
            mysqli_query($conn, "
                INSERT INTO EmployeeGovernmentIDs 
                (employee_id, sss_number, philhealth_number, pagibig_number, gsis_number, tin_number, 
                 is_sss_deducted, is_philhealth_deducted, is_pagibig_deducted, is_gsis_deducted)
                VALUES 
                ('$employee_id', '$sss', '$philhealth', '$pagibig', '$gsis', '$tin',
                 '$is_sss_deducted', '$is_philhealth_deducted', '$is_pagibig_deducted', '$is_gsis_deducted')
            ");
            $_SESSION['toast'] = 'Government ID added!';
        } else {
            // UPDATE
            $id = mysqli_real_escape_string($conn, $gov_id);
            mysqli_query($conn, "
                UPDATE EmployeeGovernmentIDs 
                SET sss_number='$sss',
                    philhealth_number='$philhealth',
                    pagibig_number='$pagibig',
                    gsis_number='$gsis',
                    tin_number='$tin',
                    is_sss_deducted='$is_sss_deducted',
                    is_philhealth_deducted='$is_philhealth_deducted',
                    is_pagibig_deducted='$is_pagibig_deducted',
                    is_gsis_deducted='$is_gsis_deducted'
                WHERE id='$id'
            ");
            $_SESSION['toast'] = 'Government ID updated!';
        }
    }

    header("Location: edit_employee.php?id=$employee_id");
    exit;
}

if (isset($_POST['save_emergency'])) {
  $emergency_id = $_POST['emergency_id'] ?? '';
  $action = $_POST['action'] ?? '';

  if ($action === 'delete') {
    $id = mysqli_real_escape_string($conn, $emergency_id);
    mysqli_query($conn, "DELETE FROM EmployeeEmergencyContacts WHERE id='$id'");
    $_SESSION['toast'] = 'Emergency contact deleted successfully!';
  } else {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $relationship = mysqli_real_escape_string($conn, $_POST['relationship']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    if ($emergency_id == '') {
      // INSERT new emergency contact
      mysqli_query($conn, "INSERT INTO EmployeeEmergencyContacts (employee_id, name, relationship, contact_number, address) 
          VALUES ('$employee_id', '$name', '$relationship', '$contact_number', '$address')");
      $_SESSION['toast'] = 'Emergency contact added successfully!';
    } else {
      // UPDATE existing emergency contact
      $id = mysqli_real_escape_string($conn, $emergency_id);
      mysqli_query($conn, "UPDATE EmployeeEmergencyContacts SET 
          name='$name', 
          relationship='$relationship', 
          contact_number='$contact_number', 
          address='$address' 
          WHERE id='$id'");
      $_SESSION['toast'] = 'Emergency contact updated successfully!';
    }
  }

  header("Location: edit_employee.php?id=$employee_id");
  exit;
}

if (isset($_POST['save_violation'])) {
  $violation_id = $_POST['violation_id'] ?? '';
  $action = $_POST['action'] ?? '';
  $employee_id = $_GET['id'] ?? '';

  if (empty($employee_id)) {
    $_SESSION['toast'] = 'No employee ID provided.';
    header("Location: employee_list.php");
    exit;
  }

  if ($action === 'delete') {
    $id = mysqli_real_escape_string($conn, $violation_id);
    $delete_query = "DELETE FROM EmployeeViolations WHERE violation_id = '$id'";
    if (mysqli_query($conn, $delete_query)) {
      $_SESSION['toast'] = 'Violation deleted successfully!';
    } else {
      $_SESSION['toast'] = 'Failed to delete violation.';
    }

  } else {
    // Sanitize form inputs
    $violation_type_id = mysqli_real_escape_string($conn, $_POST['violation_type_id']);
    $violation_date = mysqli_real_escape_string($conn, $_POST['violation_date']);
    $sanction_type_id = mysqli_real_escape_string($conn, $_POST['sanction_type_id']);
    $sanction_start_date = mysqli_real_escape_string($conn, $_POST['sanction_start_date']);
    $sanction_end_date = mysqli_real_escape_string($conn, $_POST['sanction_end_date']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $reported_by = mysqli_real_escape_string($conn, $_POST['reported_by']);

    if (empty($violation_id)) {
      // INSERT
      $insert_query = "INSERT INTO EmployeeViolations (
          employee_id, violation_type_id, violation_date,
          sanction_type_id, sanction_start_date, sanction_end_date,
          remarks, reported_by
        ) VALUES (
          '$employee_id', '$violation_type_id', '$violation_date',
          '$sanction_type_id', '$sanction_start_date', '$sanction_end_date',
          '$remarks', '$reported_by'
        )";

      if (mysqli_query($conn, $insert_query)) {
        $_SESSION['toast'] = 'Violation added successfully!';
      } else {
        $_SESSION['toast'] = 'Failed to add violation.';
      }

    } else {
      // UPDATE
      $id = mysqli_real_escape_string($conn, $violation_id);
      $update_query = "UPDATE EmployeeViolations SET 
          violation_type_id = '$violation_type_id',
          violation_date = '$violation_date',
          sanction_type_id = '$sanction_type_id',
          sanction_start_date = '$sanction_start_date',
          sanction_end_date = '$sanction_end_date',
          remarks = '$remarks',
          reported_by = '$reported_by'
          WHERE violation_id = '$id'";

      if (mysqli_query($conn, $update_query)) {
        $_SESSION['toast'] = 'Violation updated successfully!';
      } else {
        $_SESSION['toast'] = 'Failed to update violation.';
      }
    }
  }

  header("Location: edit_employee.php?id=$employee_id");
  exit;
}


if (isset($_POST['save_training'])) {
  $training_id = $_POST['training_id'] ?? '';
  $action = $_POST['action'] ?? '';
  $employee_id = $_GET['id'] ?? '';

  if ($action === 'delete') {
    $id = mysqli_real_escape_string($conn, $training_id);
    mysqli_query($conn, "DELETE FROM EmployeeTrainings WHERE training_id='$id'");
    $_SESSION['toast'] = 'Training deleted successfully!';
  } else {
    $category_id = mysqli_real_escape_string($conn, $_POST['training_category_id']);
    $title = mysqli_real_escape_string($conn, $_POST['training_title']);
    $provider = mysqli_real_escape_string($conn, $_POST['training_provider']);
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    if ($training_id == '') {
      mysqli_query($conn, "INSERT INTO EmployeeTrainings (
        employee_id, training_category_id, training_title, provider, start_date, end_date, remarks
      ) VALUES (
        '$employee_id', '$category_id', '$title', '$provider', '$start', '$end', '$remarks'
      )");
      $_SESSION['toast'] = 'Training added successfully!';
    } else {
      $id = mysqli_real_escape_string($conn, $training_id);
      mysqli_query($conn, "UPDATE EmployeeTrainings SET 
        training_category_id = '$category_id',
        training_title = '$title',
        provider = '$provider',
        start_date = '$start',
        end_date = '$end',
        remarks = '$remarks'
        WHERE training_id = '$id'");
      $_SESSION['toast'] = 'Training updated successfully!';
    }
  }

  header("Location: edit_employee.php?id=$employee_id");
  exit;
}
if (isset($_POST['save_login'])) {
  $login_id = $_POST['login_id'] ?? '';
  $action = $_POST['action'] ?? '';
  $username = mysqli_real_escape_string($conn, $_POST['username']);
  $password = $_POST['password'];
  $role = mysqli_real_escape_string($conn, $_POST['role']);
  $is_active = $_POST['is_active'] ?? 1;

  // Handle image upload
  $image = '';
  if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $image = uniqid('profile_', true) . "." . strtolower($ext);
    move_uploaded_file($_FILES['image']['tmp_name'], "uploads/$image");
  }

  if ($action === 'delete') {
    mysqli_query($conn, "DELETE FROM EmployeeLogins WHERE login_id='$login_id'");
    $_SESSION['toast'] = 'Login deleted successfully!';
  } else {
    $password_sql = '';
    if (!empty($password)) {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      $password_sql = ", password_hash='$password_hash'";
    }

    $image_sql = '';
    if (!empty($image)) {
      $image_sql = ", image='$image'";
    }

    if (empty($login_id)) {
      mysqli_query($conn, "
        INSERT INTO EmployeeLogins (employee_id, username, password_hash, role, image, is_active)
        VALUES ('$employee_id', '$username', '$password_hash', '$role', '$image', '$is_active')
      ");
      $_SESSION['toast'] = 'Login created successfully!';
    } else {
      $update_sql = "
        UPDATE EmployeeLogins 
        SET username='$username', role='$role', is_active='$is_active'
        $password_sql
        $image_sql
        WHERE login_id='$login_id'
      ";
      mysqli_query($conn, $update_sql);
      $_SESSION['toast'] = 'Login updated successfully!';
    }
  }

  header("Location: edit_employee.php?id=$employee_id");
  exit;
}


// Document form handler
if (isset($_POST['save_document'])) {
  $doc_id = $_POST['document_id'] ?? '';
  $doc_name = mysqli_real_escape_string($conn, $_POST['document_name'] ?? '');
  $doc_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');
  $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
  $employee_id = mysqli_real_escape_string($conn, $employee_id); // assuming this is set earlier

  // Upload file
  $upload_dir = "uploads/documents/";
  if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
  }

  $file_uploaded = false;
  $file_path = '';

  if (!empty($_FILES['document_file']['name'])) {
    $filename = basename($_FILES['document_file']['name']);
    $target_file = $upload_dir . time() . '_' . $filename;
    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_file)) {
      $file_uploaded = true;
      $file_path = $target_file;
    }
  }

  if (!empty($_POST['action']) && $_POST['action'] === 'delete') {
    $id = mysqli_real_escape_string($conn, $_POST['document_id']);
    // Remove file if exists
    $res = mysqli_query($conn, "SELECT file_path FROM EmployeeDocuments WHERE document_id = '$id'");
    if ($res && $doc = mysqli_fetch_assoc($res)) {
      if (file_exists($doc['file_path']))
        unlink($doc['file_path']);
    }

    mysqli_query($conn, "DELETE FROM EmployeeDocuments WHERE document_id = '$id'");
    $_SESSION['toast'] = 'Document deleted successfully!';
  } else {
    if ($doc_id) {
      // Update
      $query = "UPDATE EmployeeDocuments SET 
                        document_name = '$doc_name',
                        document_type = '$doc_type',
                        remarks = '$remarks'";

      if ($file_uploaded) {
        $query .= ", file_path = '$file_path'";
      }

      $query .= " WHERE document_id = '$doc_id'";
      mysqli_query($conn, $query);
      $_SESSION['toast'] = 'Document updated successfully!';
    } else {
      // Insert
      if (!$file_uploaded) {
        $_SESSION['toast'] = 'Please upload a document.';
      } else {
        mysqli_query($conn, "
                    INSERT INTO EmployeeDocuments (employee_id, document_name, document_type, file_path, remarks)
                    VALUES ('$employee_id', '$doc_name', '$doc_type', '$file_path', '$remarks')
                ");
        $_SESSION['toast'] = 'Document added successfully!';
      }
    }
  }

  // Redirect to avoid resubmission
  echo "<script>location.href=location.href</script>";
  exit;
}


if (isset($_POST['save_loan'])) {
    $loan_id = $_POST['loan_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $loan_type_id = mysqli_real_escape_string($conn, $_POST['loan_type_id']);
    $principal_amount = floatval($_POST['principal_amount']);
    $monthly_amortization = floatval($_POST['monthly_amortization']);
    $remaining_balance = floatval($_POST['remaining_balance']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');

    if ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM employee_loans WHERE loan_id='$loan_id'");
        $_SESSION['toast'] = 'Loan deleted successfully!';
    } else {
        if (empty($loan_id)) {
            mysqli_query($conn, "
                INSERT INTO employee_loans 
                (employee_id, loan_type_id, principal_amount, monthly_amortization, remaining_balance, start_date, status)
                VALUES 
                ('$employee_id', '$loan_type_id', '$principal_amount', '$monthly_amortization', '$remaining_balance', '$start_date', '$status')
            ");
            $_SESSION['toast'] = 'Loan added successfully!';
        } else {
            mysqli_query($conn, "
                UPDATE employee_loans 
                SET loan_type_id='$loan_type_id',
                    principal_amount='$principal_amount',
                    monthly_amortization='$monthly_amortization',
                    remaining_balance='$remaining_balance',
                    start_date='$start_date',
                    status='$status'
                WHERE loan_id='$loan_id'
            ");
            $_SESSION['toast'] = 'Loan updated successfully!';
        }
    }

    header("Location: edit_employee.php?id=$employee_id#employeeLoansSection");
    exit;
}
if (isset($_POST['save_dependent'])) {
    $id = $_POST['id'] ?? '';
    $action = $_POST['action'] ?? '';

    $employee_id = intval($_POST['employee_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $relationship = mysqli_real_escape_string($conn, $_POST['relationship']);
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date']);
    $is_qualified_dependent = isset($_POST['is_qualified_dependent']) ? 1 : 0;

    if ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM employee_dependents WHERE id='$id'");
        $_SESSION['toast'] = 'Dependent deleted successfully!';
    } else {
        if (empty($id)) {
            mysqli_query($conn, "
                INSERT INTO employee_dependents (employee_id, name, relationship, birth_date, is_qualified_dependent)
                VALUES ('$employee_id', '$name', '$relationship', '$birth_date', '$is_qualified_dependent')
            ");
            $_SESSION['toast'] = 'Dependent added successfully!';
        } else {
            mysqli_query($conn, "
                UPDATE employee_dependents
                SET employee_id='$employee_id',
                    name='$name',
                    relationship='$relationship',
                    birth_date='$birth_date',
                    is_qualified_dependent='$is_qualified_dependent'
                WHERE id='$id'
            ");
            $_SESSION['toast'] = 'Dependent updated successfully!';
        }
    }

     header("Location: edit_employee.php?id=$employee_id#dependentsSection");
    exit;
}

?>





<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>
<style>
  .custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #aaa transparent;
  }

  .custom-scroll::-webkit-scrollbar {
    height: 6px;
  }

  .custom-scroll::-webkit-scrollbar-track {
    background: transparent;
  }

  .custom-scroll::-webkit-scrollbar-thumb {
    background-color: #aaa;
    border-radius: 10px;
  }
  .fixed-button-container {
  position: sticky;           /* Sticks when scrolling */
  top: 60px;                  /* Adjust based on your header height */
  z-index: 1000;              /* Stays above other content */
  background-color: #fff;     /* Prevents background overlap */
  padding-top: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid #dee2e6;
}
</style>

<div class="pc-container">
  <div class="pc-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Edit Employee</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
              <li class="breadcrumb-item">Edit</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <!-- Sections Container -->
    <div class="container mt-5 fixed-button-container" >
      <div class="d-flex flex-row flex-nowrap overflow-auto gap-2 pb-2 px-1 custom-scroll">
        <button class="btn btn-info px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#govIDSection">
          <i class="fas fa-id-card me-1"></i> IDs
        </button>
        <button class="btn btn-primary px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#deductionSection">
          <i class="fas fa-minus-circle me-1"></i> Deductions
        </button>
        <button class="btn btn-warning px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#emergencySection">
          <i class="fas fa-phone-alt me-1"></i> Contacts
        </button>
        <button class="btn btn-success px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#benefitsSection">
          <i class="fas fa-gift me-1"></i> Benefits
        </button>
        <button class="btn btn-danger px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#violationSection">
          <i class="fas fa-exclamation-triangle me-1"></i> Violations
        </button>
        <button class="btn btn-secondary px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#trainingSection">
          <i class="fas fa-chalkboard-teacher me-1"></i> Trainings
        </button>
        <button class="btn btn-dark px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#loginSection">
          <i class="fas fa-user-lock me-1"></i> Logins
        </button>
        <button class="btn btn-light border px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#leaveRequestSection">
          <i class="fas fa-calendar-alt me-1"></i> Leave Requests
        </button>
        <button class="btn btn-outline-dark px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#leaveDaysSection">
          <i class="fas fa-calendar-day me-1"></i> Leave Days
        </button>
        <button class="btn btn-outline-danger px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#missingTimeLogSection">
          <i class="fas fa-clock me-1"></i> Missing Time Logs
        </button>
        <button class="btn btn-outline-primary px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#officialBusinessSection">
          <i class="fas fa-briefcase me-1"></i> Official Business
        </button>
        <button class="btn btn-outline-success px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#leaveCreditsSection">
          <i class="fas fa-leaf me-1"></i> Leave Credits
        </button>
        <button class="btn btn-outline-info px-4 py-2" style="min-width: 160px; height: 50px;" data-bs-toggle="collapse"
          data-bs-target="#leaveCreditLogsSection">
          <i class="fas fa-history me-1"></i> Credit Logs
        </button>
        <button class="btn btn-outline-secondary px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#documentsSection">
          <i class="fas fa-file-alt me-1"></i> Documents
        </button>
        <button class="btn btn-outline-secondary px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#employeeMovementsSection">
          <i class="fas fa-exchange-alt me-1"></i> Movements
        </button>

        <!-- ✅ New Button: Employee Separations -->
        <button class="btn btn-outline-danger px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#employeeSeparationsSection">
          <i class="fas fa-user-slash me-1"></i> Separations
        </button>
        <!-- ✅ New Button: Employee Loans -->
        <button class="btn btn-outline-warning px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#employeeLoansSection">
          <i class="fas fa-hand-holding-usd me-1"></i> Loans
        </button>
        <!-- ✅ Employee Dependents Table Button -->
        <button class="btn btn-outline-primary px-4 py-2" style="min-width: 160px; height: 50px;"
          data-bs-toggle="collapse" data-bs-target="#employeeDependentsSection">
          <i class="fas fa-user-friends me-1"></i> Dependents
        </button>

      </div>
    </div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('button[data-bs-toggle="collapse"]');

    buttons.forEach(button => {
      const targetId = button.getAttribute('data-bs-target');
      const targetEl = document.querySelector(targetId);

      button.addEventListener('click', function () {
        // Hide all other collapse sections first
        document.querySelectorAll('.collapse.show').forEach(openSection => {
          if (openSection !== targetEl) {
            const bsCollapse = bootstrap.Collapse.getInstance(openSection) || new bootstrap.Collapse(openSection, { toggle: false });
            bsCollapse.hide();
          }
        });

        // After showing this section, scroll it into view
        if (targetEl) {
          targetEl.addEventListener('shown.bs.collapse', function onShown() {
            targetEl.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
            targetEl.removeEventListener('shown.bs.collapse', onShown); // cleanup
          });
        }
      });
    });
  });
</script>
 <!-- Edit Employee Form -->
    <form method="POST" enctype="multipart/form-data" class="card p-4 shadow mb-4">
      <input type="hidden" name="employee_id" value="<?= $employee['employee_id']; ?>">

      <div class="row">
        <!-- Profile Photo -->
        <div class="col-md-3 mb-4 d-flex flex-column align-items-center justify-content-start">
          <label class="form-label w-100 text-center">Photo</label>
          <input type="file" name="photo" class="form-control d-none" id="photoInput">
          <div class="border rounded" style="width: 200px; height: 200px; cursor: pointer; overflow: hidden;"
            onclick="document.getElementById('photoInput').click();">
            <?php if (!empty($employee['photo_path'])): ?>
              <img src="<?= htmlspecialchars($employee['photo_path']); ?>" alt="Photo"
                style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
              <div class="d-flex justify-content-center align-items-center h-100 text-muted">
                Click to Upload
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Employee Identifiers -->
        <div class="col-md-9">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Employee No.</label>
              <input type="text" name="employee_number" class="form-control"
                value="<?= htmlspecialchars($employee['employee_number']); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Biometric ID</label>
              <input type="text" name="biometric_id" class="form-control"
                value="<?= htmlspecialchars($employee['biometric_id']); ?>">
            </div>

            <!-- Name -->
            <div class="col-md-4 mb-3">
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control"
                value="<?= htmlspecialchars($employee['first_name']); ?>" required>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Middle Name</label>
              <input type="text" name="middle_name" class="form-control"
                value="<?= htmlspecialchars($employee['middle_name']); ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control"
                value="<?= htmlspecialchars($employee['last_name']); ?>" required>
            </div>
          </div>
        </div>
      </div>

      <!-- Personal Info -->
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Birth Date</label>
          <input type="date" name="birth_date" class="form-control"
            value="<?= htmlspecialchars($employee['birth_date']); ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="Male" <?= $employee['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?= $employee['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?= $employee['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Civil Status</label>
          <select name="civil_status" class="form-select">
            <option value="Single" <?= $employee['civil_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
            <option value="Married" <?= $employee['civil_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
            <option value="Divorced" <?= $employee['civil_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
            <option value="Widowed" <?= $employee['civil_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
          </select>
        </div>
      </div>

      <!-- Contact Info -->
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email']); ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($employee['phone']); ?>">
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control"><?= htmlspecialchars($employee['address']); ?></textarea>
        </div>
      </div>

      <!-- Employment Dates -->
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Date Hired</label>
          <input type="date" name="hire_date" class="form-control"
            value="<?= htmlspecialchars($employee['hire_date']); ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Date Regular</label>
          <input type="date" name="date_regular" class="form-control"
            value="<?= htmlspecialchars($employee['date_regular']); ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Date Ended</label>
          <input type="date" name="date_ended" class="form-control"
            value="<?= htmlspecialchars($employee['date_ended']); ?>">
        </div>
      </div>

      <!-- Department, Shift, Designation, Employment Type -->
      <div class="row">
        <!-- Department -->
        <div class="col-md-6 mb-3">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select" required>
            <option value="">Select Department</option>
            <?php
            $departments = mysqli_query($conn, "SELECT department_id, name FROM departments ORDER BY name ASC");
            while ($dept = mysqli_fetch_assoc($departments)):
              ?>
              <option value="<?= $dept['department_id']; ?>" <?= ($employee['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($dept['name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Shift -->
        <div class="col-md-6 mb-3">
          <label class="form-label">Shift</label>
          <select name="shift_id" class="form-select" required>
            <option value="">Select Shift</option>
            <?php
            $shifts = mysqli_query($conn, "SELECT shift_id, shift_name FROM shifts ORDER BY shift_name ASC");
            while ($shift = mysqli_fetch_assoc($shifts)):
              ?>
              <option value="<?= $shift['shift_id']; ?>" <?= ($employee['shift_id'] == $shift['shift_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($shift['shift_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Designation -->
        <div class="col-md-6 mb-3">
          <label class="form-label">Designation</label>
          <select name="designation_id" class="form-select" required>
            <option value="">Select Designation</option>
            <?php
            $designations = mysqli_query($conn, "SELECT designation_id, title FROM designations ORDER BY title ASC");
            while ($desig = mysqli_fetch_assoc($designations)):
              ?>
              <option value="<?= $desig['designation_id']; ?>" <?= ($employee['designation_id'] == $desig['designation_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($desig['title']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Employment Type -->
        <div class="col-md-6 mb-3">
          <label class="form-label">Employment Type</label>
          <select name="employmenttype_id" class="form-select" required>
            <option value="">Select Employment Type</option>
            <?php
            $types = mysqli_query($conn, "SELECT type_id, name FROM employmenttypes ORDER BY name ASC");
            while ($type = mysqli_fetch_assoc($types)):
              ?>
              <option value="<?= $type['type_id']; ?>" <?= ($employee['employmenttype_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($type['name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <!-- Service Info -->
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Total Years of Service</label>
          <input type="number" step="0.01" name="total_years_service" class="form-control"
            value="<?= htmlspecialchars($employee['total_years_service']); ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="Active" <?= $employee['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Inactive" <?= $employee['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>
      </div>

      <!-- Payroll Information Section -->
      <div class="row">
        <div class="col-12 mb-3">
          <hr class="my-3">
          <h6 class="text-primary mb-3">
            <i class="ti ti-currency-dollar me-1"></i>Payroll Information
          </h6>
        </div>
      </div>

      <div class="row">
        <!-- Salary Type -->
        <div class="col-md-4 mb-3">
          <label class="form-label">Salary Type <span class="text-danger">*</span></label>
         <select name="salary_type" class="form-select" required>
            <option value="">Select Type</option>
            <option value="Monthly" <?= ($employee['salary_type'] ?? 'Monthly') == 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
            <option value="Semi-Monthly" <?= ($employee['salary_type'] ?? '') == 'Semi-Monthly' ? 'selected' : ''; ?>>Semi-Monthly</option>
            <option value="Daily" <?= ($employee['salary_type'] ?? '') == 'Daily' ? 'selected' : ''; ?>>Daily</option>
            <option value="Hourly" <?= ($employee['salary_type'] ?? '') == 'Hourly' ? 'selected' : ''; ?>>Hourly</option>
          </select>
        </div>

        <!-- Employee Rate -->
        <div class="col-md-4 mb-3">
          <label class="form-label">Employee Rate <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">₱</span>
            <input type="number" name="employee_rates" class="form-control" step="0.01" min="0" 
              value="<?= htmlspecialchars($employee['employee_rates'] ?? '0.00'); ?>" required>
          </div>
          <small class="text-muted" id="rateHelpText">Enter the rate based on salary type</small>
        </div>

        <!-- Tax Status -->
        <div class="col-md-4 mb-3">
          <label class="form-label">Tax Status <span class="text-danger">*</span></label>
          <select name="tax_status" class="form-select" required>
            <option value="">Select Status</option>
            <option value="Single" <?= ($employee['tax_status'] ?? 'Single') == 'Single' ? 'selected' : ''; ?>>Single</option>
            <option value="Married" <?= ($employee['tax_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
            <option value="Head of Family" <?= ($employee['tax_status'] ?? '') == 'Head of Family' ? 'selected' : ''; ?>>Head of Family</option>
          </select>
        </div>
      </div>

      <div class="row">
        <!-- Payroll Group -->
        <div class="col-md-8 mb-3">
          <label class="form-label">Payroll Group</label>
          <select name="payroll_group_id" class="form-select">
            <option value="">Select Group (Optional)</option>
            <?php
            // Check if payroll_groups table exists and get data
            $payroll_check = mysqli_query($conn, "SHOW TABLES LIKE 'payroll_groups'");
            if ($payroll_check && mysqli_num_rows($payroll_check) > 0) {
                $payroll_result = mysqli_query($conn, "SELECT * FROM payroll_groups WHERE is_active = 1 ORDER BY group_name");
                if ($payroll_result && mysqli_num_rows($payroll_result) > 0) {
                    while ($payroll = mysqli_fetch_assoc($payroll_result)) {
                        $selected = ($employee['payroll_group_id'] ?? '') == $payroll['id'] ? 'selected' : '';
                        echo "<option value='" . $payroll['id'] . "' $selected>" . htmlspecialchars($payroll['group_name']) . "</option>";
                    }
                }
            } else {
                // If table doesn't exist, show default options
                $current_group = $employee['payroll_group_id'] ?? '';
                echo "<option value='1' " . ($current_group == '1' ? 'selected' : '') . ">Regular Employees</option>";
                echo "<option value='2' " . ($current_group == '2' ? 'selected' : '') . ">Contractual</option>";
                echo "<option value='3' " . ($current_group == '3' ? 'selected' : '') . ">Part-time</option>";
            }
            ?>
          </select>
          <small class="text-muted">Optional grouping for payroll processing</small>
        </div>

        <!-- Tax Deduction -->
        <div class="col-md-4 mb-3 d-flex align-items-end">
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_tax_deducted" id="is_tax_deducted" 
              <?= ($employee['is_tax_deducted'] ?? 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_tax_deducted">
              <strong>Subject to Tax Deduction</strong>
            </label>
            <small class="d-block text-muted">Check if employee is subject to income tax deductions</small>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex justify-content-end">
        <a href="employees.php" class="btn btn-secondary me-2">Cancel</a>
        <button type="submit" name="update_employee" class="btn btn-success">Update Employee</button>
      </div>
    </form>

<script>
// Add salary type change handler and form validation for edit form
document.addEventListener('DOMContentLoaded', function() {
    const salaryTypeSelect = document.querySelector('select[name="salary_type"]');
    const employeeRateInput = document.querySelector('input[name="employee_rates"]');
    const rateHelpText = document.getElementById('rateHelpText');
    
    if (salaryTypeSelect && employeeRateInput) {
        function updateRateHelpText() {
            const salaryType = salaryTypeSelect.value;
            
            switch(salaryType) {
                case 'Monthly':
                    if (rateHelpText) rateHelpText.textContent = 'Enter monthly salary amount (e.g. 25000.00)';
                    employeeRateInput.placeholder = 'Monthly salary';
                    break;
                case 'Daily':
                    if (rateHelpText) rateHelpText.textContent = 'Enter daily wage rate (e.g. 500.00)';
                    employeeRateInput.placeholder = 'Daily rate';
                    break;
                case 'Hourly':
                    if (rateHelpText) rateHelpText.textContent = 'Enter hourly wage rate (e.g. 62.50)';
                    employeeRateInput.placeholder = 'Hourly rate';
                    break;
                default:
                    if (rateHelpText) rateHelpText.textContent = 'Enter the rate based on salary type';
                    employeeRateInput.placeholder = '';
            }
        }
        
        salaryTypeSelect.addEventListener('change', updateRateHelpText);
        
        // Trigger change event on page load
        updateRateHelpText();
    }
    
    // Form validation
    const editEmployeeForm = document.querySelector('form[method="POST"]');
    if (editEmployeeForm) {
        editEmployeeForm.addEventListener('submit', function(e) {
            const requiredFields = editEmployeeForm.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate employee rate is greater than 0
            const rateField = document.querySelector('input[name="employee_rates"]');
            if (rateField && parseFloat(rateField.value) <= 0) {
                rateField.classList.add('is-invalid');
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = rateField;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Show SweetAlert if available, otherwise use regular alert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fill in all required fields correctly.',
                    });
                } else {
                    alert('Please fill in all required fields correctly.');
                }
            }
        });
        
        // Remove invalid class when user starts typing
        const inputs = editEmployeeForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

    <!-- Deduction Section -->
    <div class="collapse" id="deductionSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Deductions</h5>
            <small class="text-muted">Manage employee deductions</small>
          </div>
          <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#deductionModal">
            <i class="bi bi-plus-circle me-1"></i> Add Deduction
          </button>
        </div>

        <!-- Modal Form -->
        <div class="modal fade" id="deductionModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" id="deductionForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="formTitle">Add Deduction</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="deduction_id" id="deduction_id">

                  <div class="col-md-6">
                    <label class="form-label">Deduction Type</label>
                    <select name="deduction_type_id" id="deduction_type_id" class="form-select" required>
                      <option value="">Select Type</option>
                      <?php
                      $types_q = mysqli_query($conn, "SELECT * FROM DeductionTypes");
                      while ($type = mysqli_fetch_assoc($types_q)):
                        ?>
                        <option value="<?= $type['deduction_id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                      <?php endwhile; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Amount</label>
                    <input type="number" name="amount" id="amount" step="0.01" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Recurring</label>
                    <select name="is_recurring" id="is_recurring" class="form-select">
                      <option value="0">No</option>
                      <option value="1">Yes</option>
                    </select>
                  </div>

                  <div class="col-md-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="submit" name="save_deduction" class="btn btn-success">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Deduction Table -->
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
                <th style="width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $deductions = mysqli_query($conn, "
            SELECT ed.*, dt.name AS deduction_type_name
            FROM EmployeeDeductions ed
            LEFT JOIN DeductionTypes dt ON ed.deduction_type_id = dt.deduction_id
            WHERE ed.employee_id = '$employee_id'
          ");
              while ($row = mysqli_fetch_assoc($deductions)):
                ?>
                <tr>
                  <td><?= htmlspecialchars($row['deduction_type_name']) ?></td>
                  <td>₱<?= number_format($row['amount'], 2) ?></td>
                  <td><?= htmlspecialchars($row['start_date']) ?></td>
                  <td><?= htmlspecialchars($row['end_date']) ?></td>
                  <td><?= $row['is_recurring'] ? 'Yes' : 'No' ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-deduction me-1" data-bs-toggle="modal"
                      data-bs-target="#deductionModal"
                      data-deduction='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="deduction_id" value="<?= $row['deduction_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_deduction" value="1">
                      <button type="button" class="btn btn-sm btn-danger btn-delete">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
<!-- Government IDs Section -->
<div class="collapse" id="govIDSection">
  <div class="card card-body shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Government IDs</h5>
        <small class="text-muted">Manage government ID records</small>
      </div>
      <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#govIdModal">
        <i class="bi bi-plus-circle me-1"></i> Add Government ID
      </button>
    </div>

    <!-- Government ID Modal -->
    <div class="modal fade" id="govIdModal" tabindex="-1" aria-labelledby="govIdModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST" id="govIdForm">
            <div class="modal-header">
              <h5 class="modal-title" id="govFormTitle">Add Government ID</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
              <input type="hidden" name="gov_id" id="gov_id">

              <!-- Numbers -->
              <div class="col-md-6">
                <label class="form-label">SSS Number</label>
                <input type="text" name="sss_number" id="sss_number" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">PhilHealth Number</label>
                <input type="text" name="philhealth_number" id="philhealth_number" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Pag-IBIG Number</label>
                <input type="text" name="pagibig_number" id="pagibig_number" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">GSIS Number</label>
                <input type="text" name="gsis_number" id="gsis_number" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">TIN Number</label>
                <input type="text" name="tin_number" id="tin_number" class="form-control" required>
              </div>

              <!-- Deduction Checkboxes -->
              <div class="col-md-6">
                <label class="form-label d-block">Deductions</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_sss_deducted" id="is_sss_deducted" checked>
                  <label class="form-check-label" for="is_sss_deducted">SSS Deducted</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_philhealth_deducted" id="is_philhealth_deducted" checked>
                  <label class="form-check-label" for="is_philhealth_deducted">PhilHealth Deducted</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_pagibig_deducted" id="is_pagibig_deducted" checked>
                  <label class="form-check-label" for="is_pagibig_deducted">Pag-IBIG Deducted</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_gsis_deducted" id="is_gsis_deducted">
                  <label class="form-check-label" for="is_gsis_deducted">GSIS Deducted</label>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="save_gov_id" class="btn btn-success">Save</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Government IDs Table -->
    <div class="table-responsive mt-3">
      <table id="govIdTable" class="table table-bordered table-striped align-middle">
        <thead class="table-dark text-center">
          <tr>
            <th>SSS</th>
            <th>PhilHealth</th>
            <th>Pag-IBIG</th>
            <th>GSIS</th>
            <th>TIN</th>
            <th>Deductions</th>
            <th style="width: 100px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $gov_ids_q = mysqli_query($conn, "SELECT * FROM EmployeeGovernmentIDs WHERE employee_id = '$employee_id'");
          while ($row = mysqli_fetch_assoc($gov_ids_q)):
            ?>
            <tr>
              <td><?= htmlspecialchars($row['sss_number']) ?></td>
              <td><?= htmlspecialchars($row['philhealth_number']) ?></td>
              <td><?= htmlspecialchars($row['pagibig_number']) ?></td>
              <td><?= htmlspecialchars($row['gsis_number']) ?></td>
              <td><?= htmlspecialchars($row['tin_number']) ?></td>
              <td>
                <?= $row['is_sss_deducted'] ? '<span class="badge bg-success">SSS</span> ' : '' ?>
                <?= $row['is_philhealth_deducted'] ? '<span class="badge bg-primary">PhilHealth</span> ' : '' ?>
                <?= $row['is_pagibig_deducted'] ? '<span class="badge bg-info text-dark">Pag-IBIG</span> ' : '' ?>
                <?= $row['is_gsis_deducted'] ? '<span class="badge bg-warning text-dark">GSIS</span>' : '' ?>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-primary btn-edit-govid me-1" data-bs-toggle="modal"
                  data-bs-target="#govIdModal"
                  data-govid='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                  <i class="bi bi-pencil-square"></i>
                </button>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="gov_id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="save_gov_id" value="1">
                  <button type="button" class="btn btn-sm btn-danger btn-gov-delete">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

    <!-- Emergency Contacts Section -->
    <div class="collapse" id="emergencySection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Emergency Contacts</h5>
            <small class="text-muted">Manage employee emergency contacts</small>
          </div>
          <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyModal">
            <i class="bi bi-plus-circle me-1"></i> Add Contact
          </button>
        </div>

        <!-- Emergency Contact Modal -->
        <div class="modal fade" id="emergencyModal" tabindex="-1" aria-labelledby="emergencyModalLabel"
          aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST" id="emergencyForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="emergencyFormTitle">Add Emergency Contact</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="emergency_id" id="emergency_id">

                  <div class="col-md-12">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" id="emergency_name" class="form-control" required>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Relationship</label>
                    <input type="text" name="relationship" id="emergency_relationship" class="form-control" required>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" id="emergency_contact_number" class="form-control"
                      required>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="emergency_address" class="form-control" required>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" name="save_emergency" class="btn btn-success">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Emergency Table -->
        <div class="table-responsive mt-3">
          <table id="emergencyTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Name</th>
                <th>Relationship</th>
                <th>Contact Number</th>
                <th>Address</th>
                <th style="width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $emergency_q = mysqli_query($conn, "SELECT * FROM EmployeeEmergencyContacts WHERE employee_id = '$employee_id'");
              while ($row = mysqli_fetch_assoc($emergency_q)):
                ?>
                <tr>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['relationship']) ?></td>
                  <td><?= htmlspecialchars($row['contact_number']) ?></td>
                  <td><?= htmlspecialchars($row['address']) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-emergency me-1" data-bs-toggle="modal"
                      data-bs-target="#emergencyModal"
                      data-emergency='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="emergency_id" value="<?= $row['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_emergency" value="1">
                      <button type="button" class="btn btn-sm btn-danger btn-emergency-delete">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Employee Benefits Section -->
    <div class="collapse" id="benefitsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Benefits</h5>
            <small class="text-muted">Manage employee Benefits</small>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBenefitModal"><i
              class="bi bi-plus-circle me-1"></i> Add Contact</button>
        </div>
        <br>
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="benefitsTable">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Benefit Type</th>
                <th>Amount</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $employee_id = $_GET['id'] ?? 0;
              $query = mysqli_query($conn, "
            SELECT eb.*, bt.name AS benefit_name 
            FROM EmployeeBenefits eb 
            LEFT JOIN BenefitTypes bt ON eb.benefit_id = bt.benefit_id 
            WHERE eb.employee_id = '$employee_id'
          ");
              $i = 1;
              while ($row = mysqli_fetch_assoc($query)) {
                echo "<tr>
              <td>{$i}</td>
              <td>{$row['benefit_name']}</td>
              <td>{$row['amount']}</td>
              <td>{$row['start_date']}</td>
              <td>{$row['end_date']}</td>
              <td>{$row['remarks']}</td>
             <td>
            <button class='btn btn-sm btn-primary btn-edit-benefit' data-benefit='" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . "'>
              <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger btn-delete-benefit' data-id='{$row['employee_benefit_id']}' data-employee='{$employee_id}'>
              <i class='fas fa-trash'></i>
            </button>
          </td>

            </tr>";
                $i++;
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADD Benefit Modal -->
    <div class="modal fade" id="addBenefitModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="save_benefit.php">
            <div class="modal-header">
              <h5 class="modal-title">Add Benefit</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="employee_id" value="<?= $employee_id ?>">

              <div class="mb-3">
                <label class="form-label">Benefit Type</label>
                <select class="form-select" name="benefit_type" required>
                  <option value="">Select Type</option>
                  <?php
                  $btypes = mysqli_query($conn, "SELECT * FROM BenefitTypes");
                  while ($b = mysqli_fetch_assoc($btypes)) {
                    echo "<option value='{$b['benefit_id']}'>{$b['name']}</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" class="form-control" name="amount" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" required>
              </div>
              <div class="mb-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="save_benefit" class="btn btn-success">Save</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- EDIT Benefit Modal -->
    <div class="modal fade" id="editBenefitModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="save_benefit.php">
            <div class="modal-header">
              <h5 class="modal-title">Edit Benefit</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="benefit_id" id="edit_benefit_id">
              <input type="hidden" name="employee_id" value="<?= $employee_id ?>">

              <div class="mb-3">
                <label class="form-label">Benefit Type</label>
                <select class="form-select" name="benefit_type" id="edit_benefit_type" required>
                  <option value="">Select Type</option>
                  <?php
                  $btypes = mysqli_query($conn, "SELECT * FROM BenefitTypes");
                  while ($b = mysqli_fetch_assoc($btypes)) {
                    echo "<option value='{$b['benefit_id']}'>{$b['name']}</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" class="form-control" name="amount" id="edit_amount" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
              </div>
              <div class="mb-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks" id="edit_remarks"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="save_benefit" class="btn btn-success">Update</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php
    $violation_q = mysqli_query($conn, "
  SELECT ev.*, vt.name AS violation_name, st.name AS sanction_name
  FROM EmployeeViolations ev
  LEFT JOIN ViolationTypes vt ON ev.violation_type_id = vt.violation_id
  LEFT JOIN SanctionTypes st ON ev.sanction_type_id = st.sanction_id
  WHERE ev.employee_id = '$employee_id'
");

    if (!$violation_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>
    <!-- Employee Violations Section -->
    <div class="collapse" id="violationSection">
      <div class="card card-body shadow mb-4">

        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Violations</h5>
            <small class="text-muted">Manage your Violations</small>
          </div>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#violationModal">
            <i class="bi bi-plus-circle me-1"></i> Add Violation
          </button>
        </div>
        <br>
        <!-- Violation Modal -->
        <div class="modal fade" id="violationModal" tabindex="-1" aria-labelledby="violationModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST">
                <div class="modal-header">
                  <h5 class="modal-title" id="violationModalLabel">Add Violation</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="violation_id" id="violation_id">

                  <div class="col-md-6">
                    <label class="form-label">Violation Type</label>
                    <select name="violation_type_id" id="violation_type_id" class="form-select" required>
                      <option value="">-- Select --</option>
                      <?php
                      $types = mysqli_query($conn, "SELECT * FROM ViolationTypes");
                      while ($type = mysqli_fetch_assoc($types)) {
                        echo "<option value='{$type['violation_id']}'>{$type['name']}</option>";
                      }
                      ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Sanction Type</label>
                    <select name="sanction_type_id" id="sanction_type_id" class="form-select" required>
                      <option value="">-- Select --</option>
                      <?php
                      $sanctions = mysqli_query($conn, "SELECT * FROM SanctionTypes");
                      while ($s = mysqli_fetch_assoc($sanctions)) {
                        echo "<option value='{$s['sanction_id']}'>{$s['name']}</option>";
                      }
                      ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Violation Date</label>
                    <input type="date" name="violation_date" id="violation_date" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Sanction Start Date</label>
                    <input type="date" name="sanction_start_date" id="sanction_start_date" class="form-control"
                      required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Sanction End Date</label>
                    <input type="date" name="sanction_end_date" id="sanction_end_date" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Reported By</label>
                    <input type="text" name="reported_by" id="reported_by" class="form-control" required>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="submit" name="save_violation" class="btn btn-success">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- DataTable at the Bottom -->
        <div class="table-responsive">
          <table id="violationsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-danger text-center">
              <tr>
                <th>Violation</th>
                <th>Sanction</th>
                <th>Violation Date</th>
                <th>Sanction Start</th>
                <th>Sanction End</th>
                <th>Remarks</th>
                <th>Reported By</th>
                <th style="width: 100px;">Action</th>

              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($violation_q)): ?>
                <tr onclick='populateViolationForm(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                  <td><?= htmlspecialchars($row['violation_name']) ?></td>
                  <td><?= htmlspecialchars($row['sanction_name']) ?></td>
                  <td><?= date('F d, Y', strtotime($row['violation_date'])) ?></td>
                  <td><?= date('F d, Y', strtotime($row['sanction_start_date'])) ?></td>
                  <td><?= date('F d, Y', strtotime($row['sanction_end_date'])) ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?></td>
                  <td><?= htmlspecialchars($row['reported_by']) ?></td>
                  <td class="text-center">
                    <!-- Edit Icon Button -->
                    <button type="button" class="btn btn-sm btn-primary me-1 btn-edit-violation" data-bs-toggle="modal"
                      data-bs-target="#violationModal"
                      data-violation='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>' title="Edit">
                      <i class="bi bi-pencil-square"></i>
                    </button>

                    <!-- Delete Icon Button -->
                    <form method="POST" class="delete-violation-form d-inline">
                      <input type="hidden" name="violation_id" value="<?= $row['violation_id']; ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_violation" value="1">
                      <button type="button" class="btn btn-sm btn-danger btn-violation-delete" title="Delete">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>


                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
    $login_q = mysqli_query($conn, "
  SELECT * FROM EmployeeLogins 
  WHERE employee_id = '$employee_id'
");

    if (!$login_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Logins Section -->
    <div class="collapse" id="loginSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Logins</h5>
            <small class="text-muted">Manage login credentials</small>
          </div>
          <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-plus-circle me-1"></i> Add Login
          </button>
        </div>

        <!-- Login Modal -->
        <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" id="loginForm" enctype="multipart/form-data">
                <div class="modal-header">
                  <h5 class="modal-title" id="loginFormTitle">Add Login</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="login_id" id="login_id">

                  <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control">
                    <small class="text-muted">Leave blank to keep existing password</small>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" id="role" class="form-select" required>
                      <option value="Employee">Employee</option>
                      <option value="Manager">Manager</option>
                    </select>
                  </div>

                  

                  <div class="col-md-6">
                    <label class="form-label">Is Active</label>
                    <select name="is_active" id="is_active" class="form-select" required>
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="submit" name="save_login" class="btn btn-success">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Login Table -->
        <div class="table-responsive mt-3">
          <table id="loginsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
              
                <th>Username</th>
                <th>Role</th>
                <th>Last Login</th>
                <th>Status</th>
                <th style="width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($login_q)): ?>
                <tr>
                  
                  <td><?= htmlspecialchars($row['username']) ?></td>
                  <td><span class="badge bg-info"><?= htmlspecialchars($row['role']) ?></span></td>
                  <td><?= $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never' ?></td>
                  <td>
                    <?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-login me-1" data-bs-toggle="modal"
                      data-bs-target="#loginModal"
                      data-login='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="login_id" value="<?= $row['login_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_login" value="1">
                      <button type="button" class="btn btn-sm btn-danger btn-login-delete">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      function populateLoginForm(data) {
        $('#login_id').val(data.login_id || '');
        $('#username').val(data.username || '');
        $('#password').val('');
        $('#role').val(data.role || 'Employee');
        $('#is_active').val(data.is_active || 1);
        $('#loginModalLabel').text("Edit Login");
      }

      $('.btn-edit-login').on('click', function () {
        const data = $(this).data('login');
        populateLoginForm(data);
      });

      $('.btn-login-delete').on('click', function () {
        const form = $(this).closest('form');
        Swal.fire({
          title: 'Are you sure?',
          text: 'This login will be deleted.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      });

      $(document).ready(function () {
        $('#loginsTable').DataTable();
      });
    </script>

    <?php
    $leave_q = mysqli_query($conn, "
  SELECT lr.*, lt.name AS leave_type
  FROM EmployeeLeaveRequests lr
  LEFT JOIN LeaveTypes lt ON lr.leave_type_id = lt.leave_type_id
  WHERE lr.employee_id = '$employee_id'
");

    if (!$leave_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Leave Requests Section -->
    <div class="collapse" id="leaveRequestSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Leave Requests</h5>
            <small class="text-muted">View leave request history</small>
          </div>
          <!-- Optional Add Button (commented out since read-only) -->
          <!--
      <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal">
        <i class="bi bi-plus-circle me-1"></i> Add Request
      </button>
      -->
        </div>

        <!-- Optional Leave Modal (Disabled for read-only) -->
        <!--
    <div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST" id="leaveForm">
            <div class="modal-header">
              <h5 class="modal-title" id="leaveFormTitle">Add Leave Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
              Add form fields here...
            </div>
            <div class="modal-footer">
              <button type="submit" name="save_leave" class="btn btn-success">Save</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    -->

        <!-- Leave Requests Table -->
        <div class="table-responsive mt-3">
          <table id="leaveTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Leave Type</th>
                <th>Date Range</th>
                <th>Total Days</th>
                <th>Actual Days</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Approved At</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($leave_q)): ?>
                <tr>
                  <td><?= htmlspecialchars($row['leave_type']) ?></td>
                  <td><?= date('M d, Y', strtotime($row['start_date'])) ?> to
                    <?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                  <td class="text-center"><?= $row['total_days'] ?></td>
                  <td class="text-center"><?= $row['actual_leave_days'] ?></td>
                  <td class="text-center">
                    <?php
                    $badge = [
                      'Pending' => 'warning',
                      'Approved' => 'success',
                      'Rejected' => 'danger'
                    ][$row['status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                    ?>
                  </td>
                  <td><?= date('M d, Y H:i', strtotime($row['requested_at'])) ?></td>
                  <td><?= $row['approved_at'] ? date('M d, Y H:i', strtotime($row['approved_at'])) : '—' ?></td>
                  <td><?= htmlspecialchars($row['approval_remarks'] ?? '') ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <script>
      $(document).ready(function () {
        $('#leaveTable').DataTable();
      });
    </script>

    <?php
    $leave_days_q = mysqli_query($conn, "
  SELECT lrd.*, elr.start_date, elr.end_date
  FROM LeaveRequestDays lrd
  LEFT JOIN EmployeeLeaveRequests elr ON lrd.leave_request_id = elr.leave_request_id
  WHERE elr.employee_id = '$employee_id'
");

    if (!$leave_days_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Leave Request Days Section -->
    <div class="collapse" id="leaveDaysSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Leave Request Days</h5>
            <small class="text-muted">Detailed leave day entries</small>
          </div>
          <!-- Optional Add Button (disabled for read-only) -->
          <!--
      <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#leaveDayModal">
        <i class="bi bi-plus-circle me-1"></i> Add Day
      </button>
      -->
        </div>

        <!-- Table -->
        <div class="table-responsive mt-3">
          <table id="leaveDaysTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Leave Date</th>
                <th>Is Working Day</th>
                <th>Is Holiday</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($leave_days_q)): ?>
                <tr>
                  <td><?= date('M d, Y', strtotime($row['leave_date'])) ?></td>
                  <td class="text-center">
                    <?= $row['is_working_day'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                  </td>
                  <td class="text-center">
                    <?= $row['is_holiday'] ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                  </td>
                  <td><?= htmlspecialchars($row['remarks']) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      $(document).ready(function () {
        $('#leaveDaysTable').DataTable();
      });
    </script>

    <?php
    $missing_logs_q = mysqli_query($conn, "
  SELECT * FROM MissingTimeLogRequests
  WHERE employee_id = '$employee_id'
");

    if (!$missing_logs_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Missing Time Log Requests Section -->
    <div class="collapse" id="missingTimeLogSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Missing Time Log Requests</h5>
            <small class="text-muted">Filed requests for missing logs</small>
          </div>
        </div>

        <div class="table-responsive mt-3">
          <table id="missingLogsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Date</th>
                <th>Missing Field</th>
                <th>Requested Time</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Approved At</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($missing_logs_q)): ?>
                <tr>
                  <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                  <td class="text-center"><?= strtoupper(htmlspecialchars($row['missing_field'])) ?></td>
                  <td class="text-center">
                    <?= $row['requested_time'] ? date('M d, Y h:i A', strtotime($row['requested_time'])) : '—' ?>
                  </td>
                  <td><?= htmlspecialchars($row['reason']) ?></td>
                  <td class="text-center">
                    <?php
                    $badge = [
                      'Pending' => 'warning',
                      'Approved' => 'success',
                      'Rejected' => 'danger'
                    ][$row['status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                    ?>
                  </td>
                  <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                  <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                  <td><?= htmlspecialchars($row['approval_remarks']) ?: '—' ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      $(document).ready(function () {
        $('#missingLogsTable').DataTable();
      });
    </script>
    <?php

    $separations_q = mysqli_query($conn, "
  SELECT es.*, 
         CONCAT(e.last_name, ', ', e.first_name, ' ', LEFT(e.middle_name, 1), '.') AS employee_name,
         u.username AS cleared_by_name
  FROM EmployeeSeparations es
  LEFT JOIN Employees e ON es.employee_id = e.employee_id
  LEFT JOIN Users u ON es.cleared_by = u.user_id
  WHERE es.employee_id = '$employee_id'
");

    if (!$separations_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Separations Section -->
    <div class="collapse" id="employeeSeparationsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Separations</h5>
            <small class="text-muted">View separation details</small>
          </div>
        </div>

        <div class="table-responsive mt-3">
          <table id="employeeSeparationsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Employee</th>
                <th>Separation Type</th>
                <th>Reason</th>
                <th>Final Working Day</th>
                <th>Clearance Status</th>
                <th>Cleared By</th>
                <th>Clearance Date</th>
                <th>Exit Interview Notes</th>
                <th>Remarks</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($separations_q)): ?>
                <tr>
                  <td><?= htmlspecialchars($row['employee_name']) ?></td>
                  <td class="text-center"><?= htmlspecialchars($row['separation_type']) ?></td>
                  <td><?= htmlspecialchars($row['reason']) ?></td>
                  <td class="text-center"><?= date('M d, Y', strtotime($row['final_working_day'])) ?></td>
                  <td class="text-center">
                    <?php
                    $badge = [
                      'Pending' => 'warning',
                      'Cleared' => 'success',
                      'Not Cleared' => 'danger'
                    ][$row['clearance_status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['clearance_status']}</span>";
                    ?>
                  </td>
                  <td class="text-center"><?= $row['cleared_by_name'] ?? '—' ?></td>
                  <td class="text-center">
                    <?= $row['clearance_date'] ? date('M d, Y', strtotime($row['clearance_date'])) : '—' ?></td>
                  <td><?= htmlspecialchars($row['exit_interview_notes']) ?: '—' ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                  <td class="text-center"><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- DataTables Initialization -->
    <script>
      $(document).ready(function () {
        $('#employeeSeparationsTable').DataTable({
          pageLength: 5
        });
      });
    </script>


    <?php
    $logs_q = mysqli_query($conn, "
  SELECT lcl.*, lt.name AS leave_type
  FROM LeaveCreditLogs lcl
  LEFT JOIN LeaveTypes lt ON lcl.leave_type_id = lt.leave_type_id
  WHERE lcl.employee_id = '$employee_id'
  ORDER BY lcl.created_at DESC
");

    if (!$logs_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Leave Credit Logs Section -->
    <div class="collapse" id="leaveCreditLogsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Leave Credit Logs</h5>
            <small class="text-muted">History of credit accruals, deductions, and adjustments</small>
          </div>
        </div>

        <div class="table-responsive mt-3">
          <table id="leaveCreditLogsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Date</th>
                <th>Leave Type</th>
                <th>Change</th>
                <th>Previous</th>
                <th>New</th>
                <th>Type</th>
                <th>Reference ID</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($logs_q)): ?>
                <tr>
                  <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                  <td><?= htmlspecialchars($row['leave_type']) ?></td>
                  <td class="text-center"><?= number_format($row['change_amount'], 2) ?></td>
                  <td class="text-center"><?= number_format($row['previous_balance'], 2) ?></td>
                  <td class="text-center"><?= number_format($row['new_balance'], 2) ?></td>
                  <td class="text-center">
                    <?php
                    $badge = match (strtolower($row['change_type'])) {
                      'accrual' => 'success',
                      'deduction' => 'danger',
                      'adjustment' => 'warning',
                      default => 'secondary'
                    };
                    echo "<span class='badge bg-$badge'>" . ucfirst($row['change_type']) . "</span>";
                    ?>
                  </td>
                  <td class="text-center"><?= $row['reference_id'] ?: '—' ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      $(document).ready(function () {
        $('#leaveCreditLogsTable').DataTable();
      });
    </script>
    <?php
    $credits_q = mysqli_query($conn, "
  SELECT elc.*, lt.name AS leave_type
  FROM EmployeeLeaveCredits elc
  LEFT JOIN LeaveTypes lt ON elc.leave_type_id = lt.leave_type_id
  WHERE elc.employee_id = '$employee_id'
");

    if (!$credits_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Leave Credits Section -->
    <div class="collapse" id="leaveCreditsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Leave Credits</h5>
            <small class="text-muted">Current available leave credits per type</small>
          </div>
        </div>

        <div class="table-responsive mt-3">
          <table id="leaveCreditsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Leave Type</th>
                <th>Balance (Days)</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($credits_q)): ?>
                <tr>
                  <td><?= htmlspecialchars($row['leave_type']) ?></td>
                  <td class="text-center"><?= number_format($row['balance'], 2) ?></td>
                  <td><?= date('M d, Y', strtotime($row['last_updated'])) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      $(document).ready(function () {
        $('#leaveCreditsTable').DataTable();
      });
    </script>

    <?php
    $ob_q = mysqli_query($conn, "
  SELECT * FROM EmployeeOfficialBusiness
  WHERE employee_id = '$employee_id'
");

    if (!$ob_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Official Business Section -->
    <div class="collapse" id="officialBusinessSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Official Business Requests</h5>
            <small class="text-muted">Filed OB applications and their statuses</small>
          </div>
          <!-- Optional Add Button (disabled for read-only) -->
          <!--
      <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#obModal">
        <i class="bi bi-plus-circle me-1"></i> Add OB
      </button>
      -->
        </div>

        <div class="table-responsive mt-3">
          <table id="obTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Date</th>
                <th>Time From</th>
                <th>Time To</th>
                <th>Purpose</th>
                <th>Location</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Approved At</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($ob_q)): ?>
                <tr>
                  <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                  <td class="text-center"><?= date('h:i A', strtotime($row['time_from'])) ?></td>
                  <td class="text-center"><?= date('h:i A', strtotime($row['time_to'])) ?></td>
                  <td><?= htmlspecialchars($row['purpose']) ?></td>
                  <td><?= htmlspecialchars($row['location']) ?></td>
                  <td class="text-center">
                    <?php
                    $badge = [
                      'Pending' => 'warning',
                      'Approved' => 'success',
                      'Rejected' => 'danger'
                    ][$row['status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                    ?>
                  </td>
                  <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                  <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                  <td><?= htmlspecialchars($row['approval_remarks']) ?: '—' ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      $(document).ready(function () {
        $('#obTable').DataTable();
      });
    </script>


    <?php
    $training_q = mysqli_query($conn, "
SELECT et.*, tc.name AS name
FROM EmployeeTrainings et
LEFT JOIN TrainingCategories tc ON et.training_category_id = tc.training_category_id
WHERE et.employee_id = '$employee_id'
");

    if (!$training_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }

    ?>
    <!-- Employee Trainings Section -->
    <div class="collapse" id="trainingSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Trainings</h5>
            <small class="text-muted">Manage your trainings</small>
          </div>
          <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#trainingModal">
            <i class="bi bi-plus-circle me-1"></i> Add Training
          </button>
        </div>
        <!-- Training Modal -->
        <div class="modal fade" id="trainingModal" tabindex="-1" aria-labelledby="trainingModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" id="trainingForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="trainingFormTitle">Add Training</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="training_id" id="training_id">

                  <div class="col-md-6">
                    <label class="form-label">Training Category</label>
                    <select name="training_category_id" id="training_category_id" class="form-select" required>
                      <option value="">-- Select Category --</option>
                      <?php
                      $cats = mysqli_query($conn, "SELECT * FROM TrainingCategories");
                      while ($cat = mysqli_fetch_assoc($cats)) {
                        echo "<option value='{$cat['training_category_id']}'>{$cat['name']}</option>";
                      }
                      ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Training Title</label>
                    <input type="text" name="training_title" id="training_title" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Provider</label>
                    <input type="text" name="training_provider" id="training_provider" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="training_start" class="form-control" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" id="training_end" class="form-control" required>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" id="training_remarks" class="form-control" rows="2"></textarea>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="submit" name="save_training" class="btn btn-success">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Trainings Table -->
        <div class="table-responsive mt-3">
          <table id="trainingsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-info text-center">
              <tr>
                <th>Category</th>
                <th>Title</th>
                <th>Provider</th>
                <th>Start</th>
                <th>End</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($training_q)): ?>
                <tr>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['training_title']) ?></td>
                  <td><?= htmlspecialchars($row['provider']) ?></td>
                  <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                  <td><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-training" data-bs-toggle="modal"
                      data-bs-target="#trainingModal"
                      data-training='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="training_id" value="<?= $row['training_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_training" value="1">
                      <button type="button" class="btn btn-sm btn-danger btn-training-delete">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
    // Fetch employee documents
    $documents_q = mysqli_query($conn, "
  SELECT * FROM EmployeeDocuments 
  WHERE employee_id = '$employee_id'
  ORDER BY uploaded_at DESC
");

    if (!$documents_q) {
      echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
      exit;
    }
    ?>

    <!-- Employee Documents Section -->
    <div class="collapse" id="documentsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Employee Documents</h5>
            <small class="text-muted">Upload and manage files</small>
          </div>
          <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#documentModal">
            <i class="bi bi-plus-circle me-1"></i> Add Document
          </button>
        </div>

        <!-- Document Modal -->
        <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="POST" enctype="multipart/form-data" id="documentForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="documentFormTitle">Add Document</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                  <input type="hidden" name="document_id" id="document_id">
                  <div class="col-md-6">
                    <label class="form-label">Document Name</label>
                    <input type="text" name="document_name" id="document_name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Document Type</label>
                    <input type="text" name="document_type" id="document_type" class="form-control">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">File Upload</label>
                    <input type="file" name="document_file" id="document_file" class="form-control" required>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" id="remarks" class="form-control"></textarea>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" name="save_document" class="btn btn-primary">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Documents Table -->
        <div class="table-responsive mt-3">
          <table id="documentsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>File Name</th>
                <th>Type</th>
                <th>Remarks</th>
                <th>Uploaded At</th>
                <th style="width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($documents_q)): ?>
                <tr>
                  <td><a href="<?= $row['file_path'] ?>"
                      target="_blank"><?= htmlspecialchars($row['document_name']) ?></a></td>
                  <td><?= htmlspecialchars($row['document_type']) ?: '—' ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                  <td><?= date('M d, Y h:i A', strtotime($row['uploaded_at'])) ?></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-document me-1" data-bs-toggle="modal"
                      data-bs-target="#documentModal"
                      data-document='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="document_id" value="<?= $row['document_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="save_document" value="1">
                      <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this document?')">
                        <i class="bi bi-trash3-fill"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>


    <?php
    $movements_q = mysqli_query($conn, "
  SELECT em.*, 
         fd.name AS from_dept, td.name AS to_dept,
         fdes.designation_id AS from_desig, tdes.designation_id AS to_desig,
         fl.name AS from_loc, tl.name AS to_loc,
         fet.name AS from_type, tet.name AS to_type
  FROM EmployeeMovements em
  LEFT JOIN Departments fd ON em.from_department_id = fd.department_id
  LEFT JOIN Departments td ON em.to_department_id = td.department_id
  LEFT JOIN Designations fdes ON em.from_designation_id = fdes.designation_id
  LEFT JOIN Designations tdes ON em.to_designation_id = tdes.designation_id
  LEFT JOIN OfficeLocations fl ON em.from_location_id = fl.location_id
  LEFT JOIN OfficeLocations tl ON em.to_location_id = tl.location_id
  LEFT JOIN EmploymentTypes fet ON em.from_employment_type_id = fet.type_id
  LEFT JOIN EmploymentTypes tet ON em.to_employment_type_id = tet.type_id
  WHERE em.employee_id = '$employee_id'
  ORDER BY em.effective_date DESC
");

    if (!$movements_q) {
      echo "<div class='alert alert-danger'>Query Error (EmployeeMovements): " . mysqli_error($conn) . "</div>";
      return;
    }
    ?>
    <!-- Employee Movements Section -->
    <div class="collapse" id="employeeMovementsSection">
      <div class="card card-body shadow mb-4">
        <div class="card-header">
          <h5 class="mb-0">Employee Movements</h5>
          <small class="text-muted">Track promotions, transfers, and changes</small>
        </div>

        <div class="table-responsive mt-3">
          <table id="employeeMovementsTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>Type</th>
                <th>From Dept</th>
                <th>To Dept</th>
                <th>From Desig</th>
                <th>To Desig</th>
                <th>From Loc</th>
                <th>To Loc</th>
                <th>From Type</th>
                <th>To Type</th>
                <th>From Salary</th>
                <th>To Salary</th>
                <th>Effective Date</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($movements_q)): ?>
                <tr>
                  <td class="text-center">
                    <span class="badge bg-primary"><?= htmlspecialchars($row['movement_type']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($row['from_dept'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['to_dept'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['from_desig'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['to_desig'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['from_loc'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['to_loc'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['from_type'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['to_type'] ?? '—') ?></td>
                  <td class="text-end">
                    <?= is_numeric($row['from_salary']) ? number_format($row['from_salary'], 2) : '—' ?></td>
                  <td class="text-end"><?= is_numeric($row['to_salary']) ? number_format($row['to_salary'], 2) : '—' ?>
                  </td>
                  <td class="text-center"><?= date('M d, Y', strtotime($row['effective_date'])) ?></td>
                  <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
<?php
$loans_q = mysqli_query($conn, "
    SELECT el.*, lt.name AS loan_type
    FROM employee_loans el
    LEFT JOIN loantypes lt ON el.loan_type_id = lt.loan_type_id
    WHERE el.employee_id = '$employee_id'
    ORDER BY el.start_date DESC
");
?>

<div class="collapse" id="employeeLoansSection">
  <div class="card card-body shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Employee Loans</h5>
        <small class="text-muted">Track employee loan balances and payments</small>
      </div>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#loanModal">
        <i class="ti ti-plus"></i> Add Loan
      </button>
    </div>

    <div class="table-responsive mt-3">
      <table id="employeeLoansTable" class="table table-bordered table-striped align-middle">
        <thead class="table-dark text-center">
          <tr>
            <th>Loan Type</th>
            <th>Principal</th>
            <th>Monthly Amortization</th>
            <th>Remaining Balance</th>
            <th>Start Date</th>
            <th>Status</th>
            <th style="width:120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = mysqli_fetch_assoc($loans_q)): ?>
            <tr>
              <td><?= htmlspecialchars($row['loan_type']) ?></td>
              <td class="text-end"><?= number_format($row['principal_amount'], 2) ?></td>
              <td class="text-end"><?= number_format($row['monthly_amortization'], 2) ?></td>
              <td class="text-end"><?= number_format($row['remaining_balance'], 2) ?></td>
              <td class="text-center"><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
              <td class="text-center">
                <span class="badge bg-<?= $row['status']=='Active'?'success':($row['status']=='Paid'?'secondary':'danger') ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-warning btn-edit-loan"
                        data-loan='<?= json_encode($row) ?>'
                        data-bs-toggle="modal" data-bs-target="#loanModal">
                  <i class="ti ti-edit"></i>
                </button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="loan_id" value="<?= $row['loan_id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="save_loan" value="1">
                  <button type="button" class="btn btn-sm btn-danger btn-loan-delete">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Loan Modal -->
<div class="modal fade" id="loanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="loanFormTitle">Add Loan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="loan_id" id="loan_id">
        <input type="hidden" name="save_loan" value="1">

        <div class="mb-3">
          <label>Loan Type</label>
          <select name="loan_type_id" id="loan_type_id" class="form-select" required>
            <option value="">Select Loan Type</option>
            <?php
            $loan_types = mysqli_query($conn, "SELECT * FROM loantypes ORDER BY name");
            while ($lt = mysqli_fetch_assoc($loan_types)) {
              echo "<option value='{$lt['loan_type_id']}'>" . htmlspecialchars($lt['name']) . "</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label>Principal Amount</label>
          <input type="number" step="0.01" name="principal_amount" id="principal_amount" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Monthly Amortization</label>
          <input type="number" step="0.01" name="monthly_amortization" id="monthly_amortization" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Remaining Balance</label>
          <input type="number" step="0.01" name="remaining_balance" id="remaining_balance" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Status</label>
          <select name="status" id="status" class="form-select">
            <option value="Active">Active</option>
            <option value="Paid">Paid</option>
            <option value="Canceled">Canceled</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>



<?php
// Fetch Employee Dependents
$dependents_q = mysqli_query($conn, "
    SELECT * 
    FROM employee_dependents
    WHERE employee_id = '$employee_id'
    ORDER BY name ASC
") or die(mysqli_error($conn));
?>

<div class="collapse" id="employeeDependentsSection">
  <div class="card card-body shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Employee Dependents</h5>
        <small class="text-muted">Manage employee dependent information</small>
      </div>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#dependentsModal">
        <i class="ti ti-plus"></i> Add Dependent
      </button>
    </div>

    <div class="table-responsive mt-3">
      <table id="dependentsTable" class="table table-bordered table-striped align-middle">
        <thead class="table-dark text-center">
          <tr>
            <th>Name</th>
            <th>Relationship</th>
            <th>Birth Date</th>
            <th>Qualified</th>
            <th style="width:120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = mysqli_fetch_assoc($dependents_q)): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['relationship']) ?></td>
              <td class="text-center"><?= date('M d, Y', strtotime($row['birth_date'])) ?></td>
              <td class="text-center">
                <span class="badge bg-<?= $row['is_qualified_dependent'] ? 'success' : 'secondary' ?>">
                  <?= $row['is_qualified_dependent'] ? 'Yes' : 'No' ?>
                </span>
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-warning btn-edit-dependent"
                        data-dependent='<?= json_encode($row) ?>'
                        data-bs-toggle="modal" data-bs-target="#dependentsModal">
                  <i class="ti ti-edit"></i>
                </button>
                <form method="post" class="d-inline dependent-delete-form">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="button" class="btn btn-sm btn-danger btn-dependent-delete">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="dependentsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dependentsFormTitle">Add Dependent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="dependent_id">
        <input type="hidden" name="save_dependent" value="1">
        <input type="hidden" name="employee_id" value="<?= $employee_id ?>">

        <div class="mb-3">
          <label>Name</label>
          <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Relationship</label>
          <input type="text" name="relationship" id="relationship" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Birth Date</label>
          <input type="date" name="birth_date" id="birth_date" class="form-control" required>
        </div>

        <div class="mb-3">
          <label>Qualified Dependent</label>
          <select name="is_qualified_dependent" id="is_qualified_dependent" class="form-select">
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>


  </div>
</div>
</div>

<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_SESSION['toast'])): ?>
  <script>
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: '<?= $_SESSION['toast'] ?>',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  </script>
  <?php unset($_SESSION['toast']); ?>
<?php endif; ?>
<script>
  $(document).ready(function () {
    $('#deductionTable').DataTable({
      pageLength: 5,
      columnDefs: [{ orderable: false, targets: 6 }]
    });

    $('.btn-edit-deduction').on('click', function () {
      const d = JSON.parse($(this).attr('data-deduction'));
      $('#formTitle').text('Edit Deduction');
      $('#deduction_id').val(d.deduction_id);
      $('#deduction_type_id').val(d.deduction_type_id);
      $('#amount').val(d.amount);
      $('#start_date').val(d.start_date);
      $('#end_date').val(d.end_date);
      $('#is_recurring').val(d.is_recurring);
      $('#remarks').val(d.remarks);
    });

    $('#deductionModal').on('hidden.bs.modal', function () {
      clearDeductionForm();
    });

    $('.btn-delete').on('click', function () {
      const form = $(this).closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "This deduction will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then(result => {
        if (result.isConfirmed) form.submit();
      });
    });
  });

  function clearDeductionForm() {
    $('#formTitle').text('Add Deduction');
    $('#deduction_id, #deduction_type_id, #amount, #start_date, #end_date, #remarks').val('');
    $('#is_recurring').val('0');
  }
</script>
<script>
  $(document).ready(function () {
    // Initialize DataTable only once
    if (!$.fn.DataTable.isDataTable('#benefitsTable')) {
      $('#benefitsTable').DataTable({
        pageLength: 5,
        columnDefs: [{ orderable: false, targets: 6 }]
      });
    }

    // Handle Edit Button Click
    $(document).on('click', '.btn-edit-benefit', function () {
      try {
        const b = JSON.parse($(this).attr('data-benefit'));

        // Fill Edit Modal fields
        $('#edit_benefit_id').val(b.employee_benefit_id);
        $('#edit_benefit_type').val(b.benefit_id);
        $('#edit_amount').val(b.amount);
        $('#edit_start_date').val(b.start_date);
        $('#edit_end_date').val(b.end_date);
        $('#edit_remarks').val(b.remarks);

        // Show the Edit Modal
        $('#editBenefitModal').modal('show');
      } catch (e) {
        console.error('Failed to parse benefit data:', e);
        alert('Error loading benefit details. Please try again.');
      }
    });
  });

  // Clear the Add Benefit form when opening
  function resetBenefitForm() {
    $('#addBenefitModal select[name="benefit_type"]').val('');
    $('#addBenefitModal input[name="amount"]').val('');
    $('#addBenefitModal input[name="start_date"]').val('');
    $('#addBenefitModal input[name="end_date"]').val('');
    $('#addBenefitModal textarea[name="remarks"]').val('');
  }

  $(document).on('click', '.btn-delete-benefit', function () {
    const id = $(this).data('id');
    const employeeId = $(this).data('employee');

    Swal.fire({
      title: 'Are you sure?',
      text: "This benefit will be permanently deleted.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = `delete_benefit.php?id=${id}&employee_id=${employeeId}`;
      }
    });
  });


</script>
<script>
  $(function () {
    // Only initialize if not already a DataTable
    if (!$.fn.DataTable.isDataTable('#employeeMovementsTable')) {
      $('#employeeMovementsTable').DataTable({
        responsive: true,
        autoWidth: false
      });
    }
  });
</script>

<script>
  $(document).ready(function () {
    // Initialize DataTable
    $('#govIdTable').DataTable({
      lengthChange: true,
      ordering: true,
      searching: true,
      columnDefs: [
        { orderable: false, targets: 6 } // Disable sorting on the Action column
      ]
    });

    // SweetAlert delete for Gov IDs
    $('.btn-gov-delete').on('click', function () {
      const form = $(this).closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "This government ID will be deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });

    // Auto-populate form when clicking edit button
    $('.btn-edit-govid').on('click', function () {
      const data = JSON.parse($(this).attr('data-govid'));

      // Text fields
      $('#gov_id').val(data.id);
      $('#sss_number').val(data.sss_number);
      $('#philhealth_number').val(data.philhealth_number);
      $('#pagibig_number').val(data.pagibig_number);
      $('#gsis_number').val(data.gsis_number);
      $('#tin_number').val(data.tin_number);

      // Checkbox fields
      $('#is_sss_deducted').prop('checked', data.is_sss_deducted == 1);
      $('#is_philhealth_deducted').prop('checked', data.is_philhealth_deducted == 1);
      $('#is_pagibig_deducted').prop('checked', data.is_pagibig_deducted == 1);
      $('#is_gsis_deducted').prop('checked', data.is_gsis_deducted == 1);

      $('#govFormTitle').text('Edit Government ID');
    });

    // Clear form when modal closes
    $('#govIdModal').on('hidden.bs.modal', function () {
      clearGovIDForm();
    });
  });

  // JS function to clear the form
  function clearGovIDForm() {
    $('#gov_id').val('');
    $('#sss_number').val('');
    $('#philhealth_number').val('');
    $('#pagibig_number').val('');
    $('#gsis_number').val('');
    $('#tin_number').val('');

    $('#is_sss_deducted').prop('checked', true);
    $('#is_philhealth_deducted').prop('checked', true);
    $('#is_pagibig_deducted').prop('checked', true);
    $('#is_gsis_deducted').prop('checked', false);

    $('#govFormTitle').text('Add Government ID');
  }
</script>

<script>
  $(document).ready(function () {
    $('#emergencyTable').DataTable({
      pageLength: 5,
      columnDefs: [{ orderable: false, targets: 4 }]
    });

    $('.btn-edit-emergency').on('click', function () {
      const data = JSON.parse($(this).attr('data-emergency'));
      $('#emergencyFormTitle').text('Edit Emergency Contact');
      $('#emergency_id').val(data.id);
      $('#emergency_name').val(data.name);
      $('#emergency_relationship').val(data.relationship);
      $('#emergency_contact_number').val(data.contact_number);
      $('#emergency_address').val(data.address);
    });

    $('#emergencyModal').on('hidden.bs.modal', function () {
      clearEmergencyForm();
    });

    $('.btn-emergency-delete').on('click', function () {
      const form = $(this).closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "This contact will be deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then(result => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  function clearEmergencyForm() {
    $('#emergencyFormTitle').text('Add Emergency Contact');
    $('#emergency_id').val('');
    $('#emergency_name').val('');
    $('#emergency_relationship').val('');
    $('#emergency_contact_number').val('');
    $('#emergency_address').val('');
  }
</script>


<script>
  // Populate violation form with row data
  function populateViolationForm(data) {
    document.getElementById('violation_id').value = data.violation_id || '';
    document.getElementById('violation_type_id').value = data.violation_type_id || '';
    document.getElementById('sanction_type_id').value = data.sanction_type_id || '';
    document.getElementById('violation_date').value = data.violation_date || '';
    document.getElementById('sanction_start_date').value = data.sanction_start_date || '';
    document.getElementById('sanction_end_date').value = data.sanction_end_date || '';
    document.getElementById('remarks').value = data.remarks || '';
    document.getElementById('reported_by').value = data.reported_by || '';
    document.getElementById('violationFormTitle').innerText = "Edit Violation";
  }

  // Clear violation form
  function clearViolationForm() {
    document.getElementById('violation_id').value = '';
    document.getElementById('violation_type_id').value = '';
    document.getElementById('sanction_type_id').value = '';
    document.getElementById('violation_date').value = '';
    document.getElementById('sanction_start_date').value = '';
    document.getElementById('sanction_end_date').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('reported_by').value = '';
    document.getElementById('violationFormTitle').innerText = "Add Violation";
  }

  // SweetAlert for deleting violation
  $(document).ready(function () {
    $('#violationsTable').DataTable({
      lengthChange: true,
      ordering: true,
      searching: true,
      columnDefs: [
        { orderable: false, targets: 7 } // Disable sorting on the "Action" column
      ]
    });

    $('.btn-violation-delete').on('click', function () {
      const form = $(this).closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "This violation record will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
</script>
<script>
  function populateTrainingForm(data) {
    document.getElementById('training_id').value = data.training_id || '';
    document.getElementById('training_category_id').value = data.training_category_id || '';
    document.getElementById('training_title').value = data.training_title || '';
    document.getElementById('training_provider').value = data.provider || '';
    document.getElementById('training_start').value = data.start_date || '';
    document.getElementById('training_end').value = data.end_date || '';
    document.getElementById('training_remarks').value = data.remarks || '';
    document.getElementById('trainingFormTitle').innerText = "Edit Training";
  }

  $(document).ready(function () {
    $('#trainingsTable').DataTable();

    $('.btn-edit-training').on('click', function () {
      const data = $(this).data('training');
      populateTrainingForm(data);
    });

    $('.btn-training-delete').on('click', function () {
      const form = $(this).closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "This training will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
</script>

<script>
  $(document).ready(function () {
    $('#documentsTable').DataTable();

    $('.btn-edit-document').click(function () {
      const data = $(this).data('document');
      $('#documentFormTitle').text('Edit Document');
      $('#document_id').val(data.document_id);
      $('#document_name').val(data.document_name);
      $('#document_type').val(data.document_type);
      $('#remarks').val(data.remarks);
      $('#document_file').prop('required', false); // Don't force upload on edit
    });

    $('#documentModal').on('hidden.bs.modal', function () {
      $('#documentForm')[0].reset();
      $('#documentFormTitle').text('Add Document');
      $('#document_id').val('');
      $('#document_file').prop('required', true);
    });
  });
</script>
<script>
$(document).ready(function () {
    $('#employeeLoansTable').DataTable({
        lengthChange: true,
        ordering: true,
        searching: true,
        columnDefs: [{ orderable: false, targets: 6 }]
    });

    // Edit loan
    $('.btn-edit-loan').on('click', function () {
        const data = JSON.parse($(this).attr('data-loan'));
        $('#loan_id').val(data.loan_id);
        $('#loan_type_id').val(data.loan_type_id);
        $('#principal_amount').val(data.principal_amount);
        $('#monthly_amortization').val(data.monthly_amortization);
        $('#remaining_balance').val(data.remaining_balance);
        $('#start_date').val(data.start_date);
        $('#status').val(data.status);
        $('#loanFormTitle').text('Edit Loan');
    });

    // Delete loan
    $('.btn-loan-delete').on('click', function () {
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure?',
            text: "This loan record will be deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Reset form on modal close
    $('#loanModal').on('hidden.bs.modal', function () {
        $('#loan_id').val('');
        $('#loan_type_id').val('');
        $('#principal_amount').val('');
        $('#monthly_amortization').val('');
        $('#remaining_balance').val('');
        $('#start_date').val('');
        $('#status').val('Active');
        $('#loanFormTitle').text('Add Loan');
    });
});
</script>

<!-- JavaScript -->
<script>
$(document).ready(function () {
    // DataTable
    $('#dependentsTable').DataTable({
        lengthChange: true,
        ordering: true,
        searching: true,
        columnDefs: [{ orderable: false, targets: 4 }]
    });

    // Edit Dependent
    $('.btn-edit-dependent').on('click', function () {
        const data = JSON.parse($(this).attr('data-dependent'));
        $('#dependent_id').val(data.id);
        $('#name').val(data.name);
        $('#relationship').val(data.relationship);
        $('#birth_date').val(data.birth_date);
        $('#is_qualified_dependent').val(data.is_qualified_dependent);
        $('#dependentsFormTitle').text('Edit Dependent');
    });

    // Delete Dependent with SweetAlert
    $('.btn-dependent-delete').on('click', function () {
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure?',
            text: "This dependent will be deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Reset Modal
    $('#dependentsModal').on('hidden.bs.modal', function () {
        $('#dependent_id').val('');
        $('#name').val('');
        $('#relationship').val('');
        $('#birth_date').val('');
        $('#is_qualified_dependent').val('1');
        $('#dependentsFormTitle').text('Add Dependent');
    });
});
</script>