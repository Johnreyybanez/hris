<?php
session_start();
include 'connection.php';

// Single notification mark as read (via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['created_at'])) {
    $employee_id = (int) $_POST['employee_id'];
    $created_at = mysqli_real_escape_string($conn, $_POST['created_at']);
    
    $query = "UPDATE employee_notifications 
              SET is_read = 'yes' 
              WHERE employee_id = $employee_id 
              AND created_at = '$created_at'";
              
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// Mark all notifications as read (via GET)
if (isset($_GET['employee_id'])) {
    $employee_id = (int) $_GET['employee_id'];
    
    $query = "UPDATE employee_notifications 
              SET is_read = 'yes' 
              WHERE employee_id = $employee_id 
              AND is_read = 'no'";
              
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $_SESSION['success_message'] = 'All notifications marked as read';
    } else {
        $_SESSION['error_message'] = 'Failed to mark notifications as read';
    }
    
    // Redirect back to the previous page
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: $redirect");
    exit;
}

// If no valid parameters, redirect to dashboard
header("Location: manager_dashboard.php");
exit;
?>