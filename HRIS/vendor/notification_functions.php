<?php
/**
 * Notification Functions
 * Handles all notification-related database operations
 */

/**
 * Get notifications for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID (employee_id from session)
 * @param int $limit Number of notifications to fetch (default: 5)
 * @return array Array containing notifications and unread count
 */
function getNotifications($conn, $user_id, $limit = 5) {
    $notifications = [];
    $unread_count = 0;
    
    if (!$user_id || !$conn) {
        return ['notifications' => $notifications, 'unread_count' => $unread_count];
    }
    
    // Fetch notifications with proper column names
    $notifications_sql = "SELECT notification_id as id, message, is_read, created_at, link 
                         FROM notifications 
                         WHERE user_id = ? 
                         ORDER BY created_at DESC 
                         LIMIT ?";
    
    if ($stmt = $conn->prepare($notifications_sql)) {
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($notif = $result->fetch_assoc()) {
                $notifications[] = $notif;
                if (!$notif['is_read']) {
                    $unread_count++;
                }
            }
        }
        $stmt->close();
    }
    
    return ['notifications' => $notifications, 'unread_count' => $unread_count];
}

/**
 * Mark all notifications as read for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID (employee_id from session)
 * @return bool Success status
 */
function markAllNotificationsRead($conn, $user_id) {
    if (!$user_id || !$conn) {
        return false;
    }
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    return false;
}

/**
 * Mark a specific notification as read
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function markNotificationRead($conn, $notification_id, $user_id) {
    if (!$notification_id || !$user_id || !$conn) {
        return false;
    }
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    return false;
}

/**
 * Get a specific notification
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return array|null Notification data or null if not found
 */
function getNotification($conn, $notification_id, $user_id) {
    if (!$notification_id || !$user_id || !$conn) {
        return null;
    }
    
    $sql = "SELECT notification_id as id, message, is_read, created_at, link 
            FROM notifications 
            WHERE notification_id = ? AND user_id = ? 
            LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $notification = $result->fetch_assoc();
            $stmt->close();
            return $notification;
        }
        $stmt->close();
    }
    
    return null;
}

/**
 * Create a new notification
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to send notification to
 * @param string $message Notification message
 * @param string|null $link Optional link for the notification
 * @return bool Success status
 */
function createNotification($conn, $user_id, $message, $link = null) {
    if (!$user_id || !$message || !$conn) {
        return false;
    }
    
    $sql = "INSERT INTO notifications (user_id, message, link, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iss", $user_id, $message, $link);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    return false;
}

/**
 * Get unread notification count for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Unread notification count
 */
function getUnreadNotificationCount($conn, $user_id) {
    if (!$user_id || !$conn) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $count = (int)$row['count'];
            $stmt->close();
            return $count;
        }
        $stmt->close();
    }
    
    return 0;
}
?>