<?php
/**
 * Notification Helper Functions
 * This file contains functions to send notifications to managers when employees submit requests
 */

/**
 * Send notification to managers when an employee submits a request
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee who submitted the request
 * @param string $request_type Type of request (Leave, OB, Overtime, Missing Time Log)
 * @param int $request_id ID of the submitted request
 * @param string $additional_info Additional information about the request
 */
function sendManagerNotification($conn, $employee_id, $request_type, $request_id, $additional_info = '') {
    try {
        // Get employee information - removed department field as it might not exist
        $emp_query = "SELECT first_name, last_name FROM employees WHERE employee_id = ?";
        $emp_stmt = $conn->prepare($emp_query);
        
        // Check if prepare() was successful
        if ($emp_stmt === false) {
            error_log("Failed to prepare employee query: " . $conn->error);
            return;
        }
        
        $emp_stmt->bind_param("i", $employee_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        
        if ($emp_row = $emp_result->fetch_assoc()) {
            $employee_name = $emp_row['first_name'] . ' ' . $emp_row['last_name'];
            
            // Create notification message
            $message = "New {$request_type} request from {$employee_name} (ID: #{$request_id})";
            if (!empty($additional_info)) {
                $message .= " - {$additional_info}";
            }
            
            // Get all managers, HR, and admin users
            $manager_query = "SELECT DISTINCT e.employee_id 
                             FROM employees e 
                             INNER JOIN employeelogins el ON e.employee_id = el.employee_id 
                             WHERE el.role IN ('manager', 'hr', 'admin')";
            
            $manager_stmt = $conn->prepare($manager_query);
            
            // Check if prepare() was successful
            if ($manager_stmt === false) {
                error_log("Failed to prepare manager query: " . $conn->error);
                $emp_stmt->close();
                return;
            }
            
            $manager_stmt->execute();
            $manager_result = $manager_stmt->get_result();
            
            // Insert notification for each manager/HR/admin
            $insert_query = "INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)";
            $insert_stmt = $conn->prepare($insert_query);
            
            // Check if prepare() was successful
            if ($insert_stmt === false) {
                error_log("Failed to prepare insert notification query: " . $conn->error);
                $manager_stmt->close();
                $emp_stmt->close();
                return;
            }
            
            while ($manager_row = $manager_result->fetch_assoc()) {
                $insert_stmt->bind_param("is", $manager_row['employee_id'], $message);
                $insert_stmt->execute();
            }
            
            $insert_stmt->close();
            $manager_stmt->close();
        }
        
        $emp_stmt->close();
        
    } catch (Exception $e) {
        error_log("Error sending manager notification: " . $e->getMessage());
    }
}

/**
 * Send notification for leave request
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee ID
 * @param int $leave_request_id Leave request ID
 * @param string $start_date Start date
 * @param string $end_date End date
 * @param string $leave_type Leave type name
 */
function sendLeaveRequestNotification($conn, $employee_id, $leave_request_id, $start_date, $end_date, $leave_type) {
    $date_info = date('M j', strtotime($start_date));
    if ($start_date != $end_date) {
        $date_info .= ' - ' . date('M j, Y', strtotime($end_date));
    } else {
        $date_info .= ', ' . date('Y', strtotime($start_date));
    }
    
    $additional_info = "{$leave_type} ({$date_info})";
    sendManagerNotification($conn, $employee_id, 'Leave', $leave_request_id, $additional_info);
}

/**
 * Send notification for official business request
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee ID
 * @param int $ob_id OB request ID
 * @param string $date Date
 * @param string $purpose Purpose
 */
function sendOBRequestNotification($conn, $employee_id, $ob_id, $date, $purpose) {
    $date_info = date('M j, Y', strtotime($date));
    $additional_info = "on {$date_info} - " . substr($purpose, 0, 50) . (strlen($purpose) > 50 ? '...' : '');
    sendManagerNotification($conn, $employee_id, 'Official Business', $ob_id, $additional_info);
}

/**
 * Send notification for overtime request
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee ID
 * @param int $overtime_id Overtime request ID
 * @param string $date Date
 * @param float $total_hours Total hours
 */
function sendOvertimeRequestNotification($conn, $employee_id, $overtime_id, $date, $total_hours) {
    $date_info = date('M j, Y', strtotime($date));
    $additional_info = "on {$date_info} ({$total_hours} hours)";
    sendManagerNotification($conn, $employee_id, 'Overtime', $overtime_id, $additional_info);
}

/**
 * Send notification for missing time log request
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee ID
 * @param int $request_id Request ID
 * @param string $date Date
 * @param string $missing_field Missing field (time_in/time_out)
 */
function sendMissingTimeLogNotification($conn, $employee_id, $request_id, $date, $missing_field) {
    $date_info = date('M j, Y', strtotime($date));
    $field_name = ($missing_field == 'time_in') ? 'Time In' : 'Time Out';
    $additional_info = "{$field_name} for {$date_info}";
    sendManagerNotification($conn, $employee_id, 'Missing Time Log', $request_id, $additional_info);
}

/**
 * Send notification to employee when their request is deleted by manager
 * @param mysqli $conn Database connection
 * @param int $employee_id Employee who submitted the request
 * @param string $request_type Type of request (Leave, OB, Overtime, Missing Time Log)
 * @param int $request_id ID of the deleted request
 * @param string $additional_info Additional information about the request
 */
function sendEmployeeDeletionNotification($conn, $employee_id, $request_type, $request_id, $additional_info = '') {
    try {
        // Create notification message
        $message = "Your {$request_type} request (ID: #{$request_id}) has been deleted by management";
        if (!empty($additional_info)) {
            $message .= " - {$additional_info}";
        }
        
        // Insert notification for the employee
        $insert_query = "INSERT INTO employee_notifications (employee_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)";
        $insert_stmt = $conn->prepare($insert_query);
        
        // Check if prepare() was successful
        if ($insert_stmt === false) {
            error_log("Failed to prepare employee deletion notification query: " . $conn->error);
            return;
        }
        
        $insert_stmt->bind_param("is", $employee_id, $message);
        $insert_stmt->execute();
        $insert_stmt->close();
        
    } catch (Exception $e) {
        error_log("Error sending employee deletion notification: " . $e->getMessage());
    }
}
?>