<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id']) || !isset($_POST['employee_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

$employee_id = (int)$_POST['employee_id'];

// Get user role and department
$user_query = mysqli_prepare($conn, "
    SELECT e.department, el.role 
    FROM employeelogins el
    JOIN employees e ON el.employee_id = e.employee_id
    WHERE e.employee_id = ?
");
mysqli_stmt_bind_param($user_query, "i", $employee_id);
mysqli_stmt_execute($user_query);
$user_result = mysqli_stmt_get_result($user_query);
$user_data = mysqli_fetch_assoc($user_result);

if ($user_data) {
    // Prepare update query based on role
    if ($user_data['role'] == 'admin' || $user_data['role'] == 'hr') {
        // Admin/HR can mark all notifications as read
        $update_query = "UPDATE employee_notifications SET is_read = 1 WHERE is_read = 0";
        $stmt = mysqli_prepare($conn, $update_query);
    } else {
        // Regular users can only mark their department's and personal notifications
        $update_query = "
            UPDATE employee_notifications en
            JOIN employees e ON en.employee_id = e.employee_id
            SET en.is_read = 1
            WHERE (en.employee_id = ? OR e.department = ?) AND en.is_read = 0
        ";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "is", $employee_id, $user_data['department']);
    }

    $success = mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    echo json_encode([
        'success' => $success,
        'affected_rows' => $affected_rows,
        'message' => $success ? 'Notifications marked as read' : 'Error updating notifications'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
