<?php
session_start();
include 'connection.php';

$benefit_id = $_GET['id'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';

if (!empty($benefit_id)) {
  $query = "DELETE FROM EmployeeBenefits WHERE employee_benefit_id = '$benefit_id'";
  if (mysqli_query($conn, $query)) {
    $_SESSION['toast'] = "Benefit deleted successfully!";
  } else {
    $_SESSION['toast'] = "Failed to delete benefit: " . mysqli_error($conn);
  }
}

// Redirect back to employee page
header("Location: edit_employee.php?id=$employee_id");
exit;
?>
