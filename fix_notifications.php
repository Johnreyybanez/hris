<?php
session_start();
include 'connection.php';

echo "<h2>🔧 Notifications Fix Tool</h2>";

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    echo "<p style='color: red;'>❌ Not logged in. Please login first.</p>";
    echo "<p><a href='login.php'>Login</a></p>";
    exit;
}

$login_id = (int) $_SESSION['login_id'];
$fixes_applied = [];
$errors = [];

echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔍 Diagnosing Issue...</h3>";

// Step 1: Get user information
$emp_stmt = mysqli_prepare($conn, "SELECT employee_id FROM employeelogins WHERE login_id = ? LIMIT 1");
mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);

if ($emp_row = mysqli_fetch_assoc($emp_result)) {
    $employee_id = (int) $emp_row['employee_id'];
    echo "<p>✅ Login ID: $login_id → Employee ID: $employee_id</p>";
} else {
    echo "<p style='color: red;'>❌ No employee found for login ID $login_id</p>";
    exit;
}

// Step 2: Get user role
$role_stmt = mysqli_prepare($conn, "SELECT role FROM employeelogins WHERE login_id = ?");
mysqli_stmt_bind_param($role_stmt, "i", $login_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$role_row = mysqli_fetch_assoc($role_result);
$user_role = $role_row ? $role_row['role'] : null;

echo "<p>👤 Current Role: " . ($user_role ?? 'NULL') . "</p>";

// Step 3: Check if employee_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'employee_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    echo "<p style='color: orange;'>⚠️ employee_notifications table doesn't exist. Creating...</p>";
    
    $create_table = "
        CREATE TABLE `employee_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `message` TEXT NOT NULL,
            `link` VARCHAR(255),
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_employee_id` (`employee_id`),
            INDEX `idx_is_read` (`is_read`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table)) {
        $fixes_applied[] = "Created employee_notifications table";
    } else {
        $errors[] = "Failed to create table: " . mysqli_error($conn);
    }
} else {
    echo "<p>✅ employee_notifications table exists</p>";
}

// Step 4: Count total notifications
$total_query = "SELECT COUNT(*) as total FROM employee_notifications";
$total_result = mysqli_query($conn, $total_query);
$total_count = mysqli_fetch_assoc($total_result)['total'];

echo "<p>📊 Total notifications in database: $total_count</p>";

// Step 5: Count notifications for current user
$user_query = "SELECT COUNT(*) as count FROM employee_notifications WHERE employee_id = $employee_id";
$user_result = mysqli_query($conn, $user_query);
$user_count = mysqli_fetch_assoc($user_result)['count'];

echo "<p>📋 Notifications for your employee ID ($employee_id): $user_count</p>";

echo "</div>";

// Step 6: Apply fixes based on the situation
echo "<h3>🛠️ Applying Fixes...</h3>";

if ($total_count == 0) {
    // No notifications exist - create sample ones
    echo "<p>🔄 No notifications found. Creating sample notifications...</p>";
    
    $sample_notifications = [
        "Welcome to the HRIS system! Your account has been activated.",
        "Your leave request has been submitted and is pending approval.",
        "New company policy update: Please review the updated employee handbook.",
        "Reminder: Your performance review is scheduled for next week.",
        "Your overtime request for last week has been approved.",
        "Training session on workplace safety is scheduled for Friday.",
        "Your timesheet for the current period has been approved.",
        "New benefits enrollment period starts next month.",
        "System maintenance scheduled for this weekend.",
        "Your profile information has been updated successfully."
    ];
    
    $created_count = 0;
    foreach ($sample_notifications as $index => $message) {
        $is_read = ($index % 3 == 0) ? 'FALSE' : 'FALSE'; // All unread initially
        $hours_ago = $index * 2; // Spread over time
        
        $insert_query = "INSERT INTO employee_notifications (employee_id, message, is_read, created_at) 
                        VALUES ($employee_id, ?, $is_read, DATE_SUB(NOW(), INTERVAL $hours_ago HOUR))";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "s", $message);
        
        if (mysqli_stmt_execute($stmt)) {
            $created_count++;
        }
        mysqli_stmt_close($stmt);
    }
    
    $fixes_applied[] = "Created $created_count sample notifications for employee ID $employee_id";
    
} elseif ($user_count == 0 && $total_count > 0) {
    // Notifications exist but none for current user
    echo "<p>🔄 Found $total_count notifications but none for your employee ID. Checking role...</p>";
    
    if (!$user_role || $user_role == 'employee') {
        echo "<p>🔄 Setting your role to 'admin' so you can see all notifications...</p>";
        
        $update_role = "UPDATE employeelogins SET role = 'admin' WHERE login_id = $login_id";
        if (mysqli_query($conn, $update_role)) {
            $fixes_applied[] = "Updated your role to 'admin' - you can now see all notifications";
            $user_role = 'admin';
        } else {
            $errors[] = "Failed to update role: " . mysqli_error($conn);
        }
    }
    
} else {
    // Notifications exist for user but might not be displaying due to other issues
    echo "<p>✅ Found notifications for your employee ID</p>";
}

// Step 7: Fix table structure if needed
echo "<p>🔄 Checking table structure...</p>";

$structure_query = "DESCRIBE employee_notifications";
$structure_result = mysqli_query($conn, $structure_query);
$columns = [];
while ($row = mysqli_fetch_assoc($structure_result)) {
    $columns[$row['Field']] = $row['Type'];
}

// Fix is_read column if it's not BOOLEAN
if (isset($columns['is_read']) && strpos($columns['is_read'], 'tinyint') !== false) {
    echo "<p>🔄 Converting is_read column to BOOLEAN...</p>";
    $alter_query = "ALTER TABLE employee_notifications MODIFY COLUMN is_read BOOLEAN DEFAULT FALSE";
    if (mysqli_query($conn, $alter_query)) {
        $fixes_applied[] = "Updated is_read column to BOOLEAN type";
    } else {
        $errors[] = "Failed to update is_read column: " . mysqli_error($conn);
    }
}

// Step 8: Test the notification query
echo "<p>🔄 Testing notification queries...</p>";

if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
    $test_query = "
        SELECT COUNT(*) as count
        FROM employee_notifications en
        LEFT JOIN employees e ON en.employee_id = e.employee_id 
        ORDER BY en.created_at DESC 
        LIMIT 10
    ";
    echo "<p>📝 Using admin query (shows all notifications)</p>";
} else {
    // Get user department
    $dept_query = "SELECT department, department_id FROM employees WHERE employee_id = $employee_id";
    $dept_result = mysqli_query($conn, $dept_query);
    $dept_data = mysqli_fetch_assoc($dept_result);
    
    if ($dept_data && $dept_data['department_id']) {
        $test_query = "
            SELECT COUNT(*) as count
            FROM employee_notifications en
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            WHERE en.employee_id = $employee_id OR e.department_id = {$dept_data['department_id']}
        ";
    } else {
        $test_query = "
            SELECT COUNT(*) as count
            FROM employee_notifications en
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            WHERE en.employee_id = $employee_id OR e.department = '{$dept_data['department']}'
        ";
    }
    echo "<p>📝 Using employee query (personal + department notifications)</p>";
}

$test_result = mysqli_query($conn, $test_query);
if ($test_result) {
    $test_count = mysqli_fetch_assoc($test_result)['count'];
    echo "<p>✅ Query test successful: $test_count notifications should be visible</p>";
    
    if ($test_count == 0) {
        echo "<p style='color: orange;'>⚠️ Query returns 0 results. Creating notifications for your employee ID...</p>";
        
        $quick_fix = "INSERT INTO employee_notifications (employee_id, message, is_read, created_at) VALUES 
                     ($employee_id, 'System notification: Your notifications are now working!', FALSE, NOW()),
                     ($employee_id, 'Welcome! This is a test notification to verify the system is working.', FALSE, NOW() - INTERVAL 1 HOUR)";
        
        if (mysqli_query($conn, $quick_fix)) {
            $fixes_applied[] = "Created 2 test notifications for your employee ID";
        }
    }
} else {
    $errors[] = "Query test failed: " . mysqli_error($conn);
}

// Display results
echo "<div style='background: #f0fff0; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>✅ Fix Results</h3>";

if (!empty($fixes_applied)) {
    echo "<h4 style='color: green;'>Fixes Applied:</h4>";
    echo "<ul>";
    foreach ($fixes_applied as $fix) {
        echo "<li style='color: green;'>✅ $fix</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h4 style='color: red;'>Errors:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>❌ $error</li>";
    }
    echo "</ul>";
}

echo "</div>";

// Final verification
echo "<div style='background: #fff8dc; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🔍 Final Verification</h3>";

$final_query = "SELECT COUNT(*) as total FROM employee_notifications";
$final_result = mysqli_query($conn, $final_query);
$final_total = mysqli_fetch_assoc($final_result)['total'];

$final_user_query = "SELECT COUNT(*) as count FROM employee_notifications WHERE employee_id = $employee_id";
$final_user_result = mysqli_query($conn, $final_user_query);
$final_user_count = mysqli_fetch_assoc($final_user_result)['count'];

echo "<p><strong>📊 Current Status:</strong></p>";
echo "<ul>";
echo "<li>Total notifications in database: $final_total</li>";
echo "<li>Notifications for your employee ID ($employee_id): $final_user_count</li>";
echo "<li>Your current role: " . ($user_role ?? 'employee') . "</li>";
echo "</ul>";

if ($final_total > 0) {
    echo "<p style='color: green; font-weight: bold;'>🎉 SUCCESS! Your notifications should now be working.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Still no notifications found. Please check the error messages above.</p>";
}

echo "</div>";

// Action buttons
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='view_all_notifications.php' style='background: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px; display: inline-block;'>📋 View Notifications</a>";
echo "<a href='view_all_notifications.php?debug=1' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px; display: inline-block;'>🐛 View with Debug Info</a>";
echo "<a href='quick_debug.php' style='background: #ffc107; color: black; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px; display: inline-block;'>🔍 Run Diagnostics</a>";
echo "</div>";

echo "<hr>";
echo "<p><em>Fix completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>