<?php
include 'connection.php';

// Get counts
$leave_sql = "SELECT COUNT(*) AS total FROM employeeleaverequests WHERE status = 'Pending'";
$leave_result = mysqli_query($conn, $leave_sql);
$leave_count = ($leave_result && $row = mysqli_fetch_assoc($leave_result)) ? $row['total'] : 0;

$overtime_sql = "SELECT COUNT(*) AS total FROM overtime WHERE approval_status = 'Pending'";
$overtime_result = mysqli_query($conn, $overtime_sql);
$overtime_count = ($overtime_result && $row = mysqli_fetch_assoc($overtime_result)) ? $row['total'] : 0;

$missing_sql = "SELECT COUNT(*) AS total FROM missingtimelogrequests WHERE status = 'Pending'";
$missing_result = mysqli_query($conn, $missing_sql);
$missing_count = ($missing_result && $row = mysqli_fetch_assoc($missing_result)) ? $row['total'] : 0;

$ob_sql = "SELECT COUNT(*) AS total FROM employeeofficialbusiness WHERE status = 'Pending'";
$ob_result = mysqli_query($conn, $ob_sql);
$ob_count = ($ob_result && $row = mysqli_fetch_assoc($ob_result)) ? $row['total'] : 0;

// Detect new request (session)
session_start();
$new_request = false;
if (!isset($_SESSION['prev_counts'])) {
    $_SESSION['prev_counts'] = [
        'leave' => $leave_count,
        'overtime' => $overtime_count,
        'missing' => $missing_count,
        'ob' => $ob_count
    ];
} else {
    if (
        $leave_count > $_SESSION['prev_counts']['leave'] ||
        $overtime_count > $_SESSION['prev_counts']['overtime'] ||
        $missing_count > $_SESSION['prev_counts']['missing'] ||
        $ob_count > $_SESSION['prev_counts']['ob']
    ) {
        $new_request = true;
    }
    $_SESSION['prev_counts'] = [
        'leave' => $leave_count,
        'overtime' => $overtime_count,
        'missing' => $missing_count,
        'ob' => $ob_count
    ];
}

// Return JSON
echo json_encode([
    'leave_count' => $leave_count,
    'overtime_count' => $overtime_count,
    'missing_count' => $missing_count,
    'ob_count' => $ob_count,
    'new_request' => $new_request
]);
?>
