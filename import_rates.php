<?php
session_start();
include 'connection.php';

// IMPORT RATES FROM CSV/EXCEL
if (isset($_POST['import_rates'])) {
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
        $_SESSION['error'] = 'Please select a valid file.';
        header("Location: employees.php");
        exit();
    }

    $file = $_FILES['excel_file']['tmp_name'];
    $fileExtension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($fileExtension, ['csv', 'txt'])) {
        $_SESSION['error'] = 'Invalid file type. Please upload a CSV file (.csv).';
        header("Location: employees.php");
        exit();
    }

    try {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new Exception('Unable to open file.');
        }

        // Skip header row
        fgetcsv($handle);

        $updated_count = 0;
        $skipped_count = 0;
        $errors = [];
        $row_number = 1;

        // Start transaction
        mysqli_begin_transaction($conn);

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $row_number++;
            
            // Skip empty rows
            if (empty($data[0]) && empty($data[1])) {
                continue;
            }

            $employee_name = isset($data[0]) ? trim($data[0]) : '';
            $new_rate = isset($data[1]) ? trim($data[1]) : '';
            $salary_type = isset($data[2]) ? trim($data[2]) : null;

            // Validate employee name
            if (empty($employee_name)) {
                $errors[] = "Row $row_number: Employee name is required.";
                $skipped_count++;
                continue;
            }

            // Validate rate
            if (empty($new_rate) || !is_numeric($new_rate) || $new_rate < 0) {
                $errors[] = "Row $row_number: Invalid rate for employee $employee_name.";
                $skipped_count++;
                continue;
            }

            // Parse employee name (support formats: "Last, First" or "Last, First Middle" or "First Last")
            $name_parts = array_map('trim', explode(',', $employee_name));
            
            if (count($name_parts) >= 2) {
                // Format: "Last, First" or "Last, First Middle"
                $last_name = $name_parts[0];
                $first_and_middle = explode(' ', $name_parts[1], 2);
                $first_name = $first_and_middle[0];
                $middle_name = isset($first_and_middle[1]) ? $first_and_middle[1] : '';
                
                // Search for employee
                $check_stmt = $conn->prepare("SELECT employee_id, employee_number, CONCAT(last_name, ', ', first_name, ' ', middle_name) as full_name 
                                              FROM employees 
                                              WHERE LOWER(last_name) = LOWER(?) 
                                              AND LOWER(first_name) = LOWER(?)");
                $check_stmt->bind_param("ss", $last_name, $first_name);
            } else {
                // Format: "First Last" - try to match both ways
                $parts = explode(' ', $employee_name, 2);
                if (count($parts) == 2) {
                    $first_name = $parts[0];
                    $last_name = $parts[1];
                    
                    $check_stmt = $conn->prepare("SELECT employee_id, employee_number, CONCAT(last_name, ', ', first_name, ' ', middle_name) as full_name 
                                                  FROM employees 
                                                  WHERE (LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?))
                                                  OR (LOWER(last_name) = LOWER(?) AND LOWER(first_name) = LOWER(?))");
                    $check_stmt->bind_param("ssss", $first_name, $last_name, $first_name, $last_name);
                } else {
                    // Single name - search in last name or first name
                    $check_stmt = $conn->prepare("SELECT employee_id, employee_number, CONCAT(last_name, ', ', first_name, ' ', middle_name) as full_name 
                                                  FROM employees 
                                                  WHERE LOWER(last_name) = LOWER(?) 
                                                  OR LOWER(first_name) = LOWER(?)");
                    $check_stmt->bind_param("ss", $employee_name, $employee_name);
                }
            }

            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows == 0) {
                $errors[] = "Row $row_number: Employee '$employee_name' not found in database.";
                $skipped_count++;
                $check_stmt->close();
                continue;
            } else if ($result->num_rows > 1) {
                $errors[] = "Row $row_number: Multiple employees found with name '$employee_name'. Please be more specific.";
                $skipped_count++;
                $check_stmt->close();
                continue;
            }

            $employee_data = $result->fetch_assoc();
            $employee_id = $employee_data['employee_id'];
            $check_stmt->close();

            // Update employee rate
            if ($salary_type && in_array(strtolower($salary_type), ['monthly', 'daily', 'hourly'])) {
                // Capitalize first letter
                $salary_type = ucfirst(strtolower($salary_type));
                
                // Update both rate and salary type
                $update_stmt = $conn->prepare("UPDATE employees SET employee_rates = ?, salary_type = ? WHERE employee_id = ?");
                $update_stmt->bind_param("dsi", $new_rate, $salary_type, $employee_id);
            } else {
                // Update only rate
                $update_stmt = $conn->prepare("UPDATE employees SET employee_rates = ? WHERE employee_id = ?");
                $update_stmt->bind_param("di", $new_rate, $employee_id);
            }

            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $updated_count++;
            } else {
                if ($update_stmt->affected_rows == 0) {
                    $errors[] = "Row $row_number: No changes made for '$employee_name' (rate already set to $new_rate).";
                } else {
                    $errors[] = "Row $row_number: Failed to update '$employee_name' - " . $update_stmt->error;
                }
                $skipped_count++;
            }

            $update_stmt->close();
        }

        fclose($handle);

        // Commit transaction
        mysqli_commit($conn);

        // Build success message
        if ($updated_count > 0) {
            $message = "Import completed successfully! Updated: $updated_count employee(s).";
            
            if ($skipped_count > 0) {
                $message .= " Skipped/No changes: $skipped_count row(s).";
            }

            if (count($errors) > 0 && count($errors) <= 10) {
                $_SESSION['warning'] = $message . "<br><br>Issues found:<br>" . implode('<br>', $errors);
            } else if (count($errors) > 10) {
                $_SESSION['warning'] = $message . "<br><br>Issues found in " . count($errors) . " rows. First 10:<br>" . implode('<br>', array_slice($errors, 0, 10));
            } else {
                $_SESSION['success'] = $message;
            }
        } else {
            $_SESSION['error'] = 'No employees were updated. ' . implode('<br>', $errors);
        }

    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
    }

    header("Location: employees.php");
    exit();
}

// DOWNLOAD SAMPLE TEMPLATE
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employee_rates_template_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, ['Employee Name', 'New Rate', 'Salary Type (Optional)']);
    
    // Sample data
    fputcsv($output, ['Dela Cruz, Juan', '25000.00', 'Monthly']);
    fputcsv($output, ['Santos, Maria', '500.00', 'Daily']);
    fputcsv($output, ['Reyes, Pedro', '150.00', 'Hourly']);
    
    fclose($output);
    exit();
}

// EXPORT CURRENT RATES
if (isset($_GET['export_current'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employee_current_rates_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, ['Employee Name', 'Current Rate', 'Salary Type', 'Status']);
    
    // Fetch all employees
    $result = mysqli_query($conn, "SELECT first_name, last_name, middle_name, employee_rates, salary_type, status FROM employees ORDER BY last_name, first_name ASC");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $full_name = $row['last_name'] . ', ' . $row['first_name'];
        if (!empty($row['middle_name'])) {
            $full_name .= ' ' . $row['middle_name'];
        }
        
        fputcsv($output, [
            $full_name,
            number_format($row['employee_rates'] ?? 0, 2, '.', ''),
            $row['salary_type'] ?? 'Monthly',
            $row['status']
        ]);
    }
    
    fclose($output);
    exit();
}
?>