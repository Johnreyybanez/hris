<?php
// get_notifications.php - Fetch notifications for the current user

$user_image = 'assets/images/admin/img-add-user.png'; // fallback image
$user_name = 'Guest';
$role = 'Manager';
$notifications = [];
$unread_count = 0;
$employee_id = null;

if (isset($_SESSION['login_id'])) {
    $login_id = (int) $_SESSION['login_id'];

    // Get employee info from login_id
    $emp_query = mysqli_query($conn, "
        SELECT el.employee_id, CONCAT(e.first_name, ' ', e.last_name) as full_name, 
               el.role, e.photo_path, el.image as profile_image
        FROM employeelogins el 
        LEFT JOIN employees e ON el.employee_id = e.employee_id 
        WHERE el.login_id = $login_id 
        LIMIT 1
    ");
    
    if ($emp_query && $emp_row = mysqli_fetch_assoc($emp_query)) {
        $employee_id = $emp_row['employee_id'];
        $user_name = $emp_row['full_name'] ?: 'Manager';
        $role = $emp_row['role'] ?: 'Manager';
        
        // Set profile image
        if (!empty($emp_row['profile_image'])) {
            $user_image = 'uploads/profile/' . $emp_row['profile_image'];
        } elseif (!empty($emp_row['photo_path'])) {
            $user_image = $emp_row['photo_path'];
        }
        
        // Verify image exists
        if (!file_exists($user_image)) {
            $user_image = 'assets/images/admin/img-add-user.png';
        }
        
        // Check if employee_notifications table exists, if not create it
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'employee_notifications'");
        if (mysqli_num_rows($table_check) == 0) {
            // Create the table if it doesn't exist
            $create_table = "
                CREATE TABLE `employee_notifications` (
                  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
                  `employee_id` int(11) NOT NULL,
                  `message` text NOT NULL,
                  `link` varchar(255) DEFAULT NULL,
                  `is_read` enum('yes','no') NOT NULL DEFAULT 'no',
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`notification_id`),
                  KEY `idx_employee_id` (`employee_id`),
                  KEY `idx_is_read` (`is_read`),
                  KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            mysqli_query($conn, $create_table);
        }
        
        // Fetch notifications for this employee with better query
        $notif_query = mysqli_query($conn, "
            SELECT notification_id, message, link, is_read, created_at 
            FROM employee_notifications 
            WHERE employee_id = $employee_id 
            ORDER BY 
                CASE WHEN is_read = 'no' THEN 0 ELSE 1 END,
                created_at DESC 
            LIMIT 15
        ");
        
        // Count unread notifications
        $unread_query = mysqli_query($conn, "
            SELECT COUNT(*) as unread 
            FROM employee_notifications 
            WHERE employee_id = $employee_id 
            AND is_read = 'no'
        ");
        
        if ($unread_query && $unread_row = mysqli_fetch_assoc($unread_query)) {
            $unread_count = (int) $unread_row['unread'];
        }
        
        // Store notifications in array
        if ($notif_query) {
            while ($notif = mysqli_fetch_assoc($notif_query)) {
                $notifications[] = $notif;
            }
        }
        
        // If no notifications found, create some sample ones for testing
        if (empty($notifications)) {
            $sample_notifications = [
                ['message' => 'Your leave request has been approved', 'link' => 'leave_requests.php', 'is_read' => 'no'],
                ['message' => 'New company policy update available', 'link' => 'policies.php', 'is_read' => 'no'],
                ['message' => 'Monthly performance review scheduled', 'link' => 'performance.php', 'is_read' => 'no'],
                ['message' => 'Timesheet submission reminder', 'link' => 'timesheet.php', 'is_read' => 'yes'],
                ['message' => 'Welcome to the HRIS system!', 'link' => 'dashboard.php', 'is_read' => 'yes']
            ];
            
            foreach ($sample_notifications as $index => $notif) {
                $message = mysqli_real_escape_string($conn, $notif['message']);
                $link = mysqli_real_escape_string($conn, $notif['link']);
                $is_read = $notif['is_read'];
                $created_at = date('Y-m-d H:i:s', strtotime("-" . ($index + 1) . " hours"));
                
                mysqli_query($conn, "
                    INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at) 
                    VALUES ($employee_id, '$message', '$link', '$is_read', '$created_at')
                ");
            }
            
            // Re-fetch notifications after inserting samples
            $notif_query = mysqli_query($conn, "
                SELECT notification_id, message, link, is_read, created_at 
                FROM employee_notifications 
                WHERE employee_id = $employee_id 
                ORDER BY 
                    CASE WHEN is_read = 'no' THEN 0 ELSE 1 END,
                    created_at DESC 
                LIMIT 15
            ");
            
            $notifications = [];
            if ($notif_query) {
                while ($notif = mysqli_fetch_assoc($notif_query)) {
                    $notifications[] = $notif;
                }
            }
            
            // Recount unread notifications
            $unread_query = mysqli_query($conn, "
                SELECT COUNT(*) as unread 
                FROM employee_notifications 
                WHERE employee_id = $employee_id 
                AND is_read = 'no'
            ");
            
            if ($unread_query && $unread_row = mysqli_fetch_assoc($unread_query)) {
                $unread_count = (int) $unread_row['unread'];
            }
        }
    }
}
?>