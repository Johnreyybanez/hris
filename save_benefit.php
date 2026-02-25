<?php

include 'connection.php';

if (isset($_POST['save_benefit'])) {
  $employee_id = $_POST['employee_id'];
  $benefit_id = $_POST['benefit_type'];
  $amount = $_POST['amount'];
  $start = $_POST['start_date'];
  $end = $_POST['end_date'];
  $remarks = $_POST['remarks'];
  $id = $_POST['benefit_id'] ?? '';

  if (!empty($id)) {
    // UPDATE Mode
    $query = "UPDATE EmployeeBenefits 
              SET benefit_id='$benefit_id', 
                  amount='$amount', 
                  start_date='$start', 
                  end_date='$end', 
                  remarks='$remarks' 
              WHERE employee_benefit_id='$id'";
    $message = "Benefit updated successfully!";
  } else {
    // INSERT Mode
    $query = "INSERT INTO EmployeeBenefits (employee_id, benefit_id, amount, start_date, end_date, remarks) 
              VALUES ('$employee_id', '$benefit_id', '$amount', '$start', '$end', '$remarks')";
    $message = "Benefit added successfully!";
  }

  if (mysqli_query($conn, $query)) {
    $_SESSION['toast'] = $message;
    header("Location: edit_employee.php?id=$employee_id");
    exit;
  } else {
    echo "Error: " . mysqli_error($conn);
  }
}
?>
