   <?php
include 'connection.php';

echo "<h2>Database Schema Migration</h2>";
echo "<p>This script will update your database to match the new schema with proper foreign keys.</p>";

$errors = [];
$success = [];

// Step 1: Update employees table to use department_id instead of department
echo "<h3>Step 1: Updating employees table</h3>";

// Check if employees table has department_id column
$emp_structure = mysqli_query($conn, "DESCRIBE employees");
$emp_columns = [];
while ($row = mysqli_fetch_assoc($emp_structure)) {
    $emp_columns[] = $row['Field'];
}

if (!in_array('department_id', $emp_columns)) {
    echo "<p>Adding department_id column to employees table...</p>";
    $add_dept_id = "ALTER TABLE employees ADD COLUMN department_id INT AFTER department";
    if (mysqli_query($conn, $add_dept_id)) {
        $success[] = "Added department_id column to employees table";
    } else {
        $errors[] = "Failed to add department_id column: " . mysqli_error($conn);
    }
} else {
    $success[] = "department_id column already exists in employees table";
}

// Step 2: Migrate department data to department_id
echo "<h3>Step 2: Migrating department data</h3>";

// Get all unique departments from employees table
$dept_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''";
$dept_result = mysqli_query($conn, $dept_query);

if ($dept_result && mysqli_num_rows($dept_result) > 0) {
    while ($dept_row = mysqli_fetch_assoc($dept_result)) {
        $dept_name = $dept_row['department'];
        
        // Check if department exists in departments table
        $check_dept = mysqli_prepare($conn, "SELECT department_id FROM departments WHERE name = ?");
        mysqli_stmt_bind_param($check_dept, "s", $dept_name);
        mysqli_stmt_execute($check_dept);
        $check_result = mysqli_stmt_get_result($check_dept);
        
        if ($check_row = mysqli_fetch_assoc($check_result)) {
            $dept_id = $check_row['department_id'];
            $success[] = "Department '$dept_name' already exists with ID $dept_id";
        } else {
            // Insert new department
            $insert_dept = mysqli_prepare($conn, "INSERT INTO departments (name, description) VALUES (?, ?)");
            $description = "Migrated from employees table";
            mysqli_stmt_bind_param($insert_dept, "ss", $dept_name, $description);
            
            if (mysqli_stmt_execute($insert_dept)) {
                $dept_id = mysqli_insert_id($conn);
                $success[] = "Created new department '$dept_name' with ID $dept_id";
            } else {
                $errors[] = "Failed to create department '$dept_name': " . mysqli_error($conn);
                continue;
            }
            mysqli_stmt_close($insert_dept);
        }
        mysqli_stmt_close($check_dept);
        
        // Update employees with department_id
        $update_emp = mysqli_prepare($conn, "UPDATE employees SET department_id = ? WHERE department = ? AND (department_id IS NULL OR department_id = 0)");
        mysqli_stmt_bind_param($update_emp, "is", $dept_id, $dept_name);
        
        if (mysqli_stmt_execute($update_emp)) {
            $affected = mysqli_stmt_affected_rows($update_emp);
            if ($affected > 0) {
                $success[] = "Updated $affected employees with department_id $dept_id for department '$dept_name'";
            }
        } else {
            $errors[] = "Failed to update employees for department '$dept_name': " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_emp);
    }
} else {
    $success[] = "No departments found to migrate";
}

// Step 3: Update employee_notifications table
echo "<h3>Step 3: Updating employee_notifications table</h3>";

// Check if employee_notifications table exists
$notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'employee_notifications'");
if (mysqli_num_rows($notif_check) > 0) {
    // Check table structure
    $notif_structure = mysqli_query($conn, "DESCRIBE employee_notifications");
    $notif_columns = [];
    while ($row = mysqli_fetch_assoc($notif_structure)) {
        $notif_columns[$row['Field']] = $row;
    }
    
    // Update is_read column to BOOLEAN if it's TINYINT
    if (isset($notif_columns['is_read']) && strpos($notif_columns['is_read']['Type'], 'tinyint') !== false) {
        echo "<p>Converting is_read column to BOOLEAN...</p>";
        $alter_read = "ALTER TABLE employee_notifications MODIFY COLUMN is_read BOOLEAN DEFAULT FALSE";
        if (mysqli_query($conn, $alter_read)) {
            $success[] = "Updated is_read column to BOOLEAN";
        } else {
            $errors[] = "Failed to update is_read column: " . mysqli_error($conn);
        }
    } else {
        $success[] = "is_read column is already BOOLEAN or doesn't exist";
    }
    
    // Update created_at column to DATETIME if it's TIMESTAMP
    if (isset($notif_columns['created_at']) && strpos($notif_columns['created_at']['Type'], 'timestamp') !== false) {
        echo "<p>Converting created_at column to DATETIME...</p>";
        $alter_created = "ALTER TABLE employee_notifications MODIFY COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
        if (mysqli_query($conn, $alter_created)) {
            $success[] = "Updated created_at column to DATETIME";
        } else {
            $errors[] = "Failed to update created_at column: " . mysqli_error($conn);
        }
    } else {
        $success[] = "created_at column is already DATETIME or doesn't exist";
    }
    
    // Add foreign key constraint if it doesn't exist
    $fk_check = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'employee_notifications' AND CONSTRAINT_NAME LIKE '%employee_id%' AND TABLE_SCHEMA = DATABASE()");
    if (mysqli_num_rows($fk_check) == 0) {
        echo "<p>Adding foreign key constraint...</p>";
        $add_fk = "ALTER TABLE employee_notifications ADD CONSTRAINT fk_employee_notifications_employee_id FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE";
        if (mysqli_query($conn, $add_fk)) {
            $success[] = "Added foreign key constraint for employee_id";
        } else {
            $errors[] = "Failed to add foreign key constraint: " . mysqli_error($conn);
        }
    } else {
        $success[] = "Foreign key constraint already exists";
    }
    
} else {
    echo "<p>Creating employee_notifications table...</p>";
    $create_notif = "
        CREATE TABLE `employee_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `message` TEXT NOT NULL,
            `link` VARCHAR(255),
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $create_notif)) {
        $success[] = "Created employee_notifications table";
    } else {
        $errors[] = "Failed to create employee_notifications table: " . mysqli_error($conn);
    }
}

// Step 4: Add foreign key constraints to employees table
echo "<h3>Step 4: Adding foreign key constraints to employees table</h3>";

$foreign_keys = [
    'department_id' => 'departments(department_id)',
    'shift_id' => 'shifts(shift_id)',
    'designation_id' => 'designations(designation_id)',
    'employmenttype_id' => 'employmenttypes(type_id)'
];

foreach ($foreign_keys as $column => $reference) {
    // Check if foreign key already exists
    $fk_name = "fk_employees_$column";
    $fk_check = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'employees' AND CONSTRAINT_NAME = '$fk_name' AND TABLE_SCHEMA = DATABASE()");
    
    if (mysqli_num_rows($fk_check) == 0) {
        // Check if referenced table exists
        $ref_table = explode('(', $reference)[0];
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE '$ref_table'");
        
        if (mysqli_num_rows($table_check) > 0) {
            echo "<p>Adding foreign key constraint for $column...</p>";
            $add_fk = "ALTER TABLE employees ADD CONSTRAINT $fk_name FOREIGN KEY ($column) REFERENCES $reference";
            if (mysqli_query($conn, $add_fk)) {
                $success[] = "Added foreign key constraint for $column";
            } else {
                $errors[] = "Failed to add foreign key constraint for $column: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Referenced table $ref_table does not exist, skipping foreign key for $column";
        }
    } else {
        $success[] = "Foreign key constraint for $column already exists";
    }
}

// Display results
echo "<h3>Migration Results</h3>";

if (!empty($success)) {
    echo "<h4 style='color: green;'>Successful Operations:</h4>";
    echo "<ul>";
    foreach ($success as $msg) {
        echo "<li style='color: green;'>✓ $msg</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h4 style='color: red;'>Errors:</h4>";
    echo "<ul>";
    foreach ($errors as $msg) {
        echo "<li style='color: red;'>✗ $msg</li>";
    }
    echo "</ul>";
}

echo "<h3>Migration Complete!</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li><a href='test_notifications_debug.php'>Run Notifications Debug</a></li>";
echo "<li><a href='view_all_notifications.php?debug=1'>View Notifications (Debug Mode)</a></li>";
echo "<li><a href='test_add_sample_notifications.php'>Add Sample Notifications</a></li>";
echo "</ul>";
?>