<?php
// get_payroll_deductions.php
header('Content-Type: application/json');

include 'connection.php';

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$payroll_detail_id = isset($_GET['payroll_detail_id']) ? (int)$_GET['payroll_detail_id'] : 0;

if ($payroll_detail_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid payroll detail ID']);
    exit;
}

$sql = "SELECT 
            pd.deduction_id,
            pd.deduction_type_id,
            COALESCE(dt.deduction_name, 'General Deduction') as deduction_type,
            pd.amount,
            pd.description
        FROM payroll_deductions pd
        LEFT JOIN deduction_types dt ON pd.deduction_type_id = dt.deduction_type_id
        WHERE pd.payroll_detail_id = ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query preparation failed: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $payroll_detail_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$deductions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $deductions[] = [
        'deduction_id' => $row['deduction_id'],
        'deduction_type' => $row['deduction_type'],
        'amount' => $row['amount'],
        'description' => $row['description']
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode([
    'success' => true,
    'deductions' => $deductions
]);
?>