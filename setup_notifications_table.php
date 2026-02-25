<?php
include 'connection.php';

echo "<h2>Setting up Employee Notifications Table</h2>";

// Check if table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'employee_notifications'");

if (mysqli_num_rows($table_check) == 0) {
    echo "<p>Creating employee_notifications table...</p>";
    
    $create_table = "
        CREATE TABLE `employee_notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `employee_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `link` varchar(255) DEFAULT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `employee_id` (`employee_id`),
            KEY `is_read` (`is_read`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_table)) {
        echo "<p style='color: green;'>✓ Table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating table: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Table already exists</p>";
    
    // Check if table has correct structure
    $columns = [];
    $structure = mysqli_query($conn, "DESCRIBE employee_notifications");
    while ($row = mysqli_fetch_assoc($structure)) {
        $columns[] = $row['Field'];
    }
    
    // Check for required columns
    $required_columns = ['id', 'employee_id', 'message', 'link', 'is_read', 'created_at'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (!empty($missing_columns)) {
        echo "<p style='color: orange;'>Missing columns: " . implode(', ', $missing_columns) . "</p>";
        
        // Add missing columns
        foreach ($missing_columns as $column) {
            switch ($column) {
                case 'id':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
                    break;
                case 'employee_id':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN employee_id int(11) NOT NULL";
                    break;
                case 'message':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN message text NOT NULL";
                    break;
                case 'link':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN link varchar(255) DEFAULT NULL";
                    break;
                case 'is_read':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN is_read tinyint(1) DEFAULT 0";
                    break;
                case 'created_at':
                    $alter = "ALTER TABLE employee_notifications ADD COLUMN created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP";
                    break;
            }
            
            if (mysqli_query($conn, $alter)) {
                echo "<p style='color: green;'>✓ Added column: {$column}</p>";
            } else {
                echo "<p style='color: red;'>✗ Error adding column {$column}: " . mysqli_error($conn) . "</p>";
            }
        }
    } else {
        echo "<p style='color: green;'>✓ All required columns exist</p>";
    }
}

// Show final table structure
echo "<h3>Final Table Structure:</h3>";
$structure = mysqli_query($conn, "DESCRIBE employee_notifications");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($structure)) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='test_notification_system.php'>Test Notification System</a></p>";
echo "<p><a href='user_dashboard.php'>Back to Dashboard</a></p>";
?>