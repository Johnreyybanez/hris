<?php
session_start();
include 'connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if notification_id is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = (int) $_POST['notification_id'];
$login_id = (int) $_SESSION['login_id'];

try {
    // Get employee_id from login
    $emp_stmt = mysqli_prepare($conn, "SELECT employee_id FROM employeelogins WHERE login_id = ?");
    if (!$emp_stmt) {
        throw new Exception("Failed to prepare employee query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
    mysqli_stmt_execute($emp_stmt);
    $emp_result = mysqli_stmt_get_result($emp_stmt);
    
    if (!$emp_row = mysqli_fetch_assoc($emp_result)) {
        mysqli_stmt_close($emp_stmt);
        throw new Exception("Employee not found for login ID");
    }
    
    $employee_id = (int) $emp_row['employee_id'];
    mysqli_stmt_close($emp_stmt);
    
    // Get user role to determine permissions
    $role_stmt = mysqli_prepare($conn, "SELECT role FROM employeelogins WHERE login_id = ?");
    mysqli_stmt_bind_param($role_stmt, "i", $login_id);
    mysqli_stmt_execute($role_stmt);
    $role_result = mysqli_stmt_get_result($role_stmt);
    $user_role = mysqli_fetch_assoc($role_result)['role'];
    mysqli_stmt_close($role_stmt);
    
    // Update notification as read
    if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
        // Admin/HR/Manager can mark any notification as read
        $update_stmt = mysqli_prepare($conn, "UPDATE employee_notifications SET is_read = TRUE WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "i", $notification_id);
    } else {
        // Regular employees can only mark their own notifications as read
        $update_stmt = mysqli_prepare($conn, "UPDATE employee_notifications SET is_read = TRUE WHERE id = ? AND employee_id = ?");
        mysqli_stmt_bind_param($update_stmt, "ii", $notification_id, $employee_id);
    }
    
    if (!$update_stmt) {
        throw new Exception("Failed to prepare update query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_execute($update_stmt);
    
    if (mysqli_stmt_affected_rows($update_stmt) > 0) {
        mysqli_stmt_close($update_stmt);
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        mysqli_stmt_close($update_stmt);
        echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
    }
    
} catch (Exception $e) {
    error_log("Error in mark_single_notification_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>