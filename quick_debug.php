<?php
session_start();
include 'connection.php';

echo "<h2>Quick Notifications Debug</h2>";

// Check session
echo "<h3>Session Information:</h3>";
if (isset($_SESSION['login_id'])) {
    $login_id = (int) $_SESSION['login_id'];
    echo "<p><strong>Login ID:</strong> $login_id</p>";
    
    // Get employee_id
    $emp_stmt = mysqli_prepare($conn, "SELECT employee_id FROM employeelogins WHERE login_id = ?");
    mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
    mysqli_stmt_execute($emp_stmt);
    $emp_result = mysqli_stmt_get_result($emp_stmt);
    
    if ($emp_row = mysqli_fetch_assoc($emp_result)) {
        $employee_id = (int) $emp_row['employee_id'];
        echo "<p><strong>Employee ID:</strong> $employee_id</p>";
        
        // Get role
        $role_stmt = mysqli_prepare($conn, "SELECT role FROM employeelogins WHERE login_id = ?");
        mysqli_stmt_bind_param($role_stmt, "i", $login_id);
        mysqli_stmt_execute($role_stmt);
        $role_result = mysqli_stmt_get_result($role_stmt);
        $role_row = mysqli_fetch_assoc($role_result);
        $user_role = $role_row ? $role_row['role'] : 'employee';
        echo "<p><strong>Role:</strong> $user_role</p>";
        
        // Get employee details
        $emp_details = mysqli_query($conn, "SELECT first_name, last_name, department, department_id FROM employees WHERE employee_id = $employee_id");
        $emp_data = mysqli_fetch_assoc($emp_details);
        if ($emp_data) {
            echo "<p><strong>Name:</strong> " . $emp_data['first_name'] . " " . $emp_data['last_name'] . "</p>";
            echo "<p><strong>Department:</strong> " . ($emp_data['department'] ?? 'NULL') . "</p>";
            echo "<p><strong>Department ID:</strong> " . ($emp_data['department_id'] ?? 'NULL') . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>No employee found for login ID $login_id</p>";
    }
} else {
    echo "<p style='color: red;'>No login session found</p>";
}

// Check notifications
echo "<h3>Notifications in Database:</h3>";
$notif_query = "SELECT id, employee_id, message, is_read, created_at FROM employee_notifications ORDER BY created_at DESC LIMIT 10";
$notif_result = mysqli_query($conn, $notif_query);

if (mysqli_num_rows($notif_result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Employee ID</th><th>Message</th><th>Read</th><th>Created</th></tr>";
    while ($row = mysqli_fetch_assoc($notif_result)) {
        $read_status = $row['is_read'] ? 'Yes' : 'No';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['employee_id']}</td>";
        echo "<td>" . htmlspecialchars(substr($row['message'], 0, 50)) . "...</td>";
        echo "<td>$read_status</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No notifications found</p>";
}

// Test the actual query that would be used
if (isset($employee_id) && isset($user_role)) {
    echo "<h3>Testing Notification Query:</h3>";
    
    if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
        echo "<p><strong>Using admin/manager query (show all notifications)</strong></p>";
        $test_query = "
            SELECT 
                en.*,
                e.first_name,
                e.last_name,
                COALESCE(d.name, e.department) as department
            FROM employee_notifications en
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            LEFT JOIN departments d ON e.department_id = d.department_id
            ORDER BY en.created_at DESC 
            LIMIT 5
        ";
    } else {
        echo "<p><strong>Using employee query (personal + department notifications)</strong></p>";
        if ($emp_data['department_id']) {
            $test_query = "
                SELECT 
                    en.*,
                    e.first_name,
                    e.last_name,
                    COALESCE(d.name, e.department) as department
                FROM employee_notifications en
                LEFT JOIN employees e ON en.employee_id = e.employee_id 
                LEFT JOIN departments d ON e.department_id = d.department_id
                WHERE en.employee_id = $employee_id OR e.department_id = {$emp_data['department_id']}
                ORDER BY en.created_at DESC 
                LIMIT 5
            ";
        } else {
            $test_query = "
                SELECT 
                    en.*,
                    e.first_name,
                    e.last_name,
                    e.department
                FROM employee_notifications en
                LEFT JOIN employees e ON en.employee_id = e.employee_id 
                WHERE en.employee_id = $employee_id OR e.department = '{$emp_data['department']}'
                ORDER BY en.created_at DESC 
                LIMIT 5
            ";
        }
    }
    
    echo "<p><strong>Query:</strong></p>";
    echo "<pre>" . htmlspecialchars($test_query) . "</pre>";
    
    $test_result = mysqli_query($conn, $test_query);
    if ($test_result) {
        echo "<p><strong>Query Results:</strong></p>";
        if (mysqli_num_rows($test_result) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Employee</th><th>Department</th><th>Message</th><th>Read</th></tr>";
            while ($row = mysqli_fetch_assoc($test_result)) {
                $read_status = $row['is_read'] ? 'Yes' : 'No';
                $employee_name = $row['first_name'] . ' ' . $row['last_name'];
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>$employee_name (ID: {$row['employee_id']})</td>";
                echo "<td>" . htmlspecialchars($row['department'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars(substr($row['message'], 0, 50)) . "...</td>";
                echo "<td>$read_status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'><strong>Query returned 0 results!</strong></p>";
            echo "<p>This means the notifications are not matching your employee ID or department.</p>";
            
            // Show what employee IDs have notifications
            echo "<h4>Employee IDs that have notifications:</h4>";
            $emp_ids_query = "SELECT DISTINCT employee_id FROM employee_notifications";
            $emp_ids_result = mysqli_query($conn, $emp_ids_query);
            while ($emp_id_row = mysqli_fetch_assoc($emp_ids_result)) {
                echo "<p>Employee ID: {$emp_id_row['employee_id']}</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>Query failed: " . mysqli_error($conn) . "</p>";
    }
}

echo "<h3>Actions:</h3>";
echo "<p><a href='view_all_notifications.php?debug=1'>View Notifications (Debug Mode)</a></p>";
echo "<p><a href='migrate_database_schema.php'>Run Database Migration</a></p>";
?>