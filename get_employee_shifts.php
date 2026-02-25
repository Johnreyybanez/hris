<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

// Check if request is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    
    // Validate employee_id is not empty
    if (empty($employee_id)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Employee ID is required',
            'shifts' => []
        ]);
        exit;
    }
    
    // Get unique shift_ids used by this employee in their DTR records
    $query = "
        SELECT DISTINCT d.shift_id, s.shift_name, s.description,
               TIME_FORMAT(s.time_in, '%H:%i') as time_in,
               TIME_FORMAT(s.time_out, '%H:%i') as time_out
        FROM EmployeeDTR d
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        WHERE d.employee_id = ? 
        AND d.shift_id IS NOT NULL
        ORDER BY s.shift_name
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database prepare failed: ' . mysqli_error($conn),
            'shifts' => []
        ]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Query execution failed: ' . mysqli_stmt_error($stmt),
            'shifts' => []
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to get result: ' . mysqli_error($conn),
            'shifts' => []
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $shifts = [];
    $shift_details = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $shifts[] = intval($row['shift_id']);
        $shift_details[] = [
            'shift_id' => intval($row['shift_id']),
            'shift_name' => $row['shift_name'] ?? 'Unknown',
            'time_in' => $row['time_in'] ?? 'N/A',
            'time_out' => $row['time_out'] ?? 'N/A',
            'description' => $row['description'] ?? ''
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Log for debugging (optional)
    error_log("Employee $employee_id has " . count($shifts) . " shifts: " . implode(', ', $shifts));
    
    echo json_encode([
        'success' => true,
        'shifts' => $shifts,
        'shift_details' => $shift_details,
        'count' => count($shifts),
        'employee_id' => $employee_id
    ]);
    
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method or missing employee_id',
        'shifts' => []
    ]);
}

mysqli_close($conn);
?>