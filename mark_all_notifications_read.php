<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id']) || !isset($_POST['employee_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

$login_id = (int)$_SESSION['login_id'];
$employee_id = (int)$_POST['employee_id'];

try {
    // Get user role and department_id
    $user_query = mysqli_prepare($conn, "
        SELECT e.department_id, el.role 
        FROM employeelogins el
        JOIN employees e ON el.employee_id = e.employee_id
        WHERE el.login_id = ? AND e.employee_id = ?
    ");
    
    if (!$user_query) {
        throw new Exception("Failed to prepare user query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($user_query, "ii", $login_id, $employee_id);
    mysqli_stmt_execute($user_query);
    $user_result = mysqli_stmt_get_result($user_query);
    $user_data = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_query);

    if ($user_data) {
        $user_role = $user_data['role'];
        $department_id = $user_data['department_id'];
        
        // Prepare update query based on role (matching the logic from manager_header.php)
        if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
            // Admin/HR/Manager can mark all notifications as read
            $update_query = "UPDATE employee_notifications SET is_read = 1 WHERE is_read = 0";
            $stmt = mysqli_prepare($conn, $update_query);
        } else {
            // Regular users can only mark their department's and personal notifications
            $update_query = "
                UPDATE employee_notifications en
                LEFT JOIN employees e ON en.employee_id = e.employee_id
                SET en.is_read = 1
                WHERE (en.employee_id = ? OR e.department_id = ?) AND en.is_read = 0
            ";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ii", $employee_id, $department_id);
        }

        if (!$stmt) {
            throw new Exception("Failed to prepare update query: " . mysqli_error($conn));
        }

        $success = mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => $success,
            'affected_rows' => $affected_rows,
            'message' => $success ? "Successfully marked {$affected_rows} notifications as read" : 'Error updating notifications'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or unauthorized']);
    }
    
} catch (Exception $e) {
    error_log("Error in mark_all_notifications_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
