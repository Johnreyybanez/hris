<?php
session_start();
include 'connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if notification_id is provided
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID not provided']);
    exit;
}

$notification_id = intval($_POST['notification_id']);
$employee_id = $_SESSION['user_id'];

try {
    // Verify that the notification belongs to the current user and update it
    $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE id = ? AND employee_id = ?");
    $stmt->bind_param("ii", $notification_id, $employee_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>