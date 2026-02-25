<?php
session_start();

// Enhanced session and role validation
if (!isset($_SESSION['login_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    // Log the error for debugging
    error_log("Access denied to manager dashboard. Session data: " . print_r($_SESSION, true));
    
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Ensure all required session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    error_log("Missing required session variables for manager dashboard");
    header("Location: login.php");
    exit;
}

require_once 'connection.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Check login and role properly
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

$manager_id = (int)$_SESSION['user_id'];

// Get manager info with proper department info using prepared statement
$dept_stmt = mysqli_prepare($conn, "
    SELECT e.department_id, d.name as department, e.first_name, e.last_name, e.photo_path,
           el.username, el.image as profile_image, el.role, el.last_login 
    FROM employees e
    JOIN employeelogins el ON e.employee_id = el.employee_id
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = ? AND el.role = 'Manager'
");

if (!$dept_stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($dept_stmt, "i", $manager_id);
mysqli_stmt_execute($dept_stmt);
$dept_result = mysqli_stmt_get_result($dept_stmt);
$manager_info = mysqli_fetch_assoc($dept_result);
mysqli_stmt_close($dept_stmt);

if (!$manager_info) {
    // Manager info not found, redirect to login
    header("Location: login.php");
    exit;
}

// Set manager details for header with proper image paths
$_SESSION['username'] = $manager_info['username'] ?? 'Manager';
$_SESSION['photo_path'] = '';

// Check and set profile image
if (!empty($manager_info['profile_image'])) {
    $_SESSION['photo_path'] = 'uploads/profile/' . $manager_info['profile_image'];
} elseif (!empty($manager_info['photo_path'])) {
    $_SESSION['photo_path'] = $manager_info['photo_path'];
}

// Verify image exists
if (empty($_SESSION['photo_path']) || !file_exists($_SESSION['photo_path'])) {
    $_SESSION['photo_path'] = 'assets/images/default-user.jpg';
}

$_SESSION['last_login'] = $manager_info['last_login'] ?? '';

// Initialize variables with proper fallbacks
$department_id = $manager_info['department_id'] ?? null;
$department_name = $manager_info['department'] ?? 'Unknown Department';

// Function to insert notifications using prepared statements
function insertNotification($conn, $recipient_id, $title, $message, $type = 'info') {
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (recipient_id, title, message, type, created_at, is_read) VALUES (?, ?, ?, ?, NOW(), 0)");
    if (!$stmt) {
        error_log("Prepare failed for insertNotification: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "isss", $recipient_id, $title, $message, $type);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Function to check if notification already exists today using prepared statements
function notificationExistsToday($conn, $recipient_id, $title) {
    $today = date('Y-m-d');
    $stmt = mysqli_prepare($conn, "SELECT id FROM notifications WHERE recipient_id = ? AND title = ? AND DATE(created_at) = ?");
    if (!$stmt) {
        error_log("Prepare failed for notificationExistsToday: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iss", $recipient_id, $title, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $exists;
}

// Initialize celebration arrays for notifications
$today_celebrations = [];

// Count statistics filtered by manager's department
$user_count = 0;
$employee_count = 0;
$leave_type_count = 0;
$shift_count = 0;
$leave_request_count = 0;
$admin_user_count = 0;
$leave = null;

if ($department_id) {
    // Count employees in manager's department using prepared statement
    $employee_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM employees e WHERE e.department_id = ?");
    if ($employee_stmt) {
        mysqli_stmt_bind_param($employee_stmt, "i", $department_id);
        mysqli_stmt_execute($employee_stmt);
        $employee_result = mysqli_stmt_get_result($employee_stmt);
        $employee_count = $employee_result ? mysqli_fetch_assoc($employee_result)['total'] : 0;
        mysqli_stmt_close($employee_stmt);
    }
    
    // Count user logins for employees in manager's department using prepared statement
    $user_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total 
        FROM employeelogins el 
        JOIN employees e ON el.employee_id = e.employee_id 
        WHERE e.department_id = ?
    ");
    if ($user_stmt) {
        mysqli_stmt_bind_param($user_stmt, "i", $department_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user_count = $user_result ? mysqli_fetch_assoc($user_result)['total'] : 0;
        mysqli_stmt_close($user_stmt);
    }
    
    // Count leave requests from manager's department using prepared statement
    $leave_req_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total 
        FROM employeeleaverequests elr 
        JOIN employees e ON elr.employee_id = e.employee_id 
        WHERE e.department_id = ?
    ");
    if ($leave_req_stmt) {
        mysqli_stmt_bind_param($leave_req_stmt, "i", $department_id);
        mysqli_stmt_execute($leave_req_stmt);
        $leave_req_result = mysqli_stmt_get_result($leave_req_stmt);
        $leave_request_count = $leave_req_result ? mysqli_fetch_assoc($leave_req_result)['total'] : 0;
        mysqli_stmt_close($leave_req_stmt);
    }
}

// Global counts (not department-specific)
$leave_type_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM leavetypes");
$leave_type_count = $leave_type_result ? mysqli_fetch_assoc($leave_type_result)['total'] : 0;

$shift_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM shifts");
$shift_count = $shift_result ? mysqli_fetch_assoc($shift_result)['total'] : 0;

$admin_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM employeelogins WHERE role = 'admin'");
$admin_user_count = $admin_result ? mysqli_fetch_assoc($admin_result)['total'] : 0;

// Latest leave request from manager's department using prepared statement
if ($department_id) {
    $latest_leave_stmt = mysqli_prepare($conn, "
        SELECT elr.*, 
               CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name,
               lt.name AS leave_type_name,
               d.name AS department_name
        FROM employeeleaverequests elr
        JOIN employees e ON elr.employee_id = e.employee_id
        JOIN leavetypes lt ON elr.leave_type_id = lt.leave_type_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE e.department_id = ?
        ORDER BY elr.requested_at DESC
        LIMIT 1
    ");
    
    if ($latest_leave_stmt) {
        mysqli_stmt_bind_param($latest_leave_stmt, "i", $department_id);
        mysqli_stmt_execute($latest_leave_stmt);
        $latest_leave_result = mysqli_stmt_get_result($latest_leave_stmt);
        $leave = $latest_leave_result ? mysqli_fetch_assoc($latest_leave_result) : null;
        mysqli_stmt_close($latest_leave_stmt);
    }
}

// --- Functions for statistics and analytics ---
function get_requests_per_day($conn, $table, $date_field, $days = 7, $department_id = null) {
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        if ($department_id && in_array($table, ['employeeleaverequests', 'employeeofficialbusiness', 'missingtimelogrequests', 'employeeviolations', 'employeetrainings', 'employeedocuments'])) {
            $query = mysqli_query($conn, "
                SELECT COUNT(*) AS total 
                FROM $table t 
                JOIN employees e ON t.employee_id = e.employee_id 
                WHERE e.department_id = $department_id AND DATE(t.$date_field) = '$date'
            ");
        } else {
            $query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM $table WHERE DATE($date_field) = '$date'");
        }
        
        $count = 0;
        if ($query && ($row = mysqli_fetch_assoc($query))) {
            $count = (int)$row['total'];
        }
        $data[] = $count;
    }
    return $data;
}

function get_stats_days($days = 7) {
    $labels = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $labels[] = date('D', strtotime("-$i days"));
    }
    return $labels;
}

// Process today's celebrations
$month = date('m');
$day = date('d');
$today_date = date('Y-m-d');

// Get today's birthdays from all departments and create notifications
$bday_today_q = mysqli_query($conn, "
    SELECT e.employee_id, e.first_name, e.last_name, e.birth_date, d.name as department_name,
           el.employee_id as login_id
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employeelogins el ON e.employee_id = el.employee_id
    WHERE MONTH(birth_date) = $month AND DAY(birth_date) = $day
    ORDER BY e.last_name
");

if ($bday_today_q) {
    while ($row = mysqli_fetch_assoc($bday_today_q)) {
        $employee_name = $row['first_name'] . ' ' . $row['last_name'];
        $dept_name = $row['department_name'] ?? 'Unknown Department';
        
        // Add to today's celebrations for popup
        $today_celebrations[] = [
            'type' => 'birthday',
            'name' => $employee_name,
            'department' => $dept_name,
            'message' => "🎂 It's " . $employee_name . "'s birthday today!"
        ];
        
        // Create notification for the birthday employee (if they have login)
        if ($row['login_id']) {
            $birthday_title = "🎉 Happy Birthday!";
            $birthday_message = "Wishing you a wonderful birthday filled with joy and happiness!";
            
            if (!notificationExistsToday($conn, $row['login_id'], $birthday_title)) {
                insertNotification($conn, $row['login_id'], $birthday_title, $birthday_message, 'celebration');
            }
        }
        
        // Create notification for manager about birthday
        $manager_bday_title = "🎂 Birthday Today - " . $employee_name;
        $manager_bday_message = $employee_name . " from " . $dept_name . " is celebrating their birthday today!";
        
        if (!notificationExistsToday($conn, $manager_id, $manager_bday_title)) {
            insertNotification($conn, $manager_id, $manager_bday_title, $manager_bday_message, 'info');
        }
    }
}

// Get today's work anniversaries and create notifications
$anniv_today_q = mysqli_query($conn, "
    SELECT e.employee_id, e.first_name, e.last_name, e.hire_date, d.name as department_name,
           el.employee_id as login_id,
           YEAR(CURDATE()) - YEAR(e.hire_date) as years_service
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employeelogins el ON e.employee_id = el.employee_id
    WHERE MONTH(hire_date) = $month AND DAY(hire_date) = $day
    AND e.hire_date != '$today_date'
    ORDER BY e.last_name
");

if ($anniv_today_q) {
    while ($row = mysqli_fetch_assoc($anniv_today_q)) {
        $employee_name = $row['first_name'] . ' ' . $row['last_name'];
        $dept_name = $row['department_name'] ?? 'Unknown Department';
        $years = $row['years_service'];
        
        // Add to today's celebrations for popup
        $today_celebrations[] = [
            'type' => 'anniversary',
            'name' => $employee_name,
            'department' => $dept_name,
            'years' => $years,
            'message' => "🏆 " . $employee_name . " celebrates " . $years . " year" . ($years > 1 ? "s" : "") . " of service today!"
        ];
        
        // Create notification for the anniversary employee (if they have login)
        if ($row['login_id']) {
            $anniv_title = "🏆 Work Anniversary!";
            $anniv_message = "Congratulations on your " . $years . " year" . ($years > 1 ? "s" : "") . " of dedicated service with the company!";
            
            if (!notificationExistsToday($conn, $row['login_id'], $anniv_title)) {
                insertNotification($conn, $row['login_id'], $anniv_title, $anniv_message, 'celebration');
            }
        }
        
        // Create notification for manager about anniversary
        $manager_anniv_title = "🎊 Work Anniversary - " . $employee_name;
        $manager_anniv_message = $employee_name . " from " . $dept_name . " is celebrating " . $years . " year" . ($years > 1 ? "s" : "") . " of service today!";
        
        if (!notificationExistsToday($conn, $manager_id, $manager_anniv_title)) {
            insertNotification($conn, $manager_id, $manager_anniv_title, $manager_anniv_message, 'info');
        }
    }
}

include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- SweetAlert2 for beautiful notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Add animate.css for better animations -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
/* Custom styles for celebration popups */
.birthday-popup {
    border-radius: 20px !important;
    box-shadow: 0 20px 60px rgba(245, 158, 11, 0.3) !important;
}

.anniversary-popup {
    border-radius: 20px !important;
    box-shadow: 0 20px 60px rgba(14, 165, 233, 0.3) !important;
}

.birthday-title {
    color: #f59e0b !important;
}

.anniversary-title {
    color: #0ea5e9 !important;
}

/* Real-time clock styling */
.current-time {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #6366f1;
}

/* Add this at the beginning of the style section */
body.dark-mode .fw-semibold.text-dark {
    color: #e0e0e0 !important;
}
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header mb-4">
            <div class="page-block">
                <h4 class="fw-bold text-primary">Manager Dashboard</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-0">
                        <li class="breadcrumb-item"><a href="manager_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                    <div class="text-muted small mt-2">
                        Logged in as: <strong><?= htmlspecialchars($department_name) ?> Department Manager</strong>
                        <br>
                        <small class="current-time"><?= date('F j, Y - g:i:s A (T)') ?></small>
                    </div>
                </nav>
            </div>
        </div>

    <!-- Widgets Row -->
        <div class="row g-4 mb-4">
            <!-- Leave Requests Pending -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(99, 102, 241, 0.1);">
                                    <span class="fs-4" style="color: #6366f1;"><i class="ti ti-calendar-check"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">Leave Requests</span>
                            </div>
                        </div>
                        <?php
                        // Count all pending leave requests from all departments
                        $leave_result = mysqli_query($conn, "
                            SELECT COUNT(*) AS total 
                            FROM employeeleaverequests elr 
                            WHERE elr.status = 'Pending'
                        ");
                        $pending_leave = $leave_result ? mysqli_fetch_assoc($leave_result)['total'] : 0;
                        ?>
                        <h2 class="fw-bold mb-1" style="color: #6366f1; font-size: 2.5rem;"><?= $pending_leave ?></h2>
                        <div class="small text-muted mb-3">Awaiting approval (All Departments)</div>
                    </div>
                </div>
            </div>
            <!-- OB Requests Pending -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(14, 165, 233, 0.1);">
                                    <span class="fs-4" style="color: #0ea5e9;"><i class="ti ti-briefcase"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">OB Requests</span>
                            </div>
                        </div>
                        <?php
                        // Count all pending OB requests from all departments
                        $ob_result = mysqli_query($conn, "
                            SELECT COUNT(*) AS total 
                            FROM employeeofficialbusiness eob 
                            WHERE eob.status = 'Pending'
                        ");
                        $pending_ob = $ob_result ? mysqli_fetch_assoc($ob_result)['total'] : 0;
                        ?>
                        <h2 class="fw-bold mb-1" style="color: #0ea5e9; font-size: 2.5rem;"><?= $pending_ob ?></h2>
                        <div class="small text-muted mb-3">Needing sign-off (All Departments)</div>
                    </div>
                </div>
            </div>
            <!-- Missing Log Requests -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(245, 158, 11, 0.1);">
                                    <span class="fs-4" style="color: #f59e0b;"><i class="ti ti-clock-off"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">Missing Logs</span>
                            </div>
                        </div>
                        <?php
                        // Count all pending missing time log requests from all departments
                        $timelog_result = mysqli_query($conn, "
                            SELECT COUNT(*) AS total 
                            FROM missingtimelogrequests mtr 
                            WHERE mtr.status = 'Pending'
                        ");
                        $pending_timelog = $timelog_result ? mysqli_fetch_assoc($timelog_result)['total'] : 0;
                        ?>
                        <h2 class="fw-bold mb-1" style="color: #f59e0b; font-size: 2.5rem;"><?= $pending_timelog ?></h2>
                        <div class="small text-muted mb-3">Corrections to review (All Departments)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Overtime Requests -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(239, 68, 68, 0.1);">
                                    <span class="fs-4" style="color: #ef4444;"><i class="ti ti-clock"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">Overtime</span>
                            </div>
                        </div>
                        <?php
                        // Count all pending overtime requests from all departments
                        $overtime_result = mysqli_query($conn, "
                            SELECT COUNT(*) AS total 
                            FROM overtime o
                            WHERE o.approval_status = 'Pending'
                        ");
                        $pending_overtime = $overtime_result ? mysqli_fetch_assoc($overtime_result)['total'] : 0;
                        ?>
                        <h2 class="fw-bold mb-1" style="color: #ef4444; font-size: 2.5rem;"><?= $pending_overtime ?></h2>
                        <div class="small text-muted mb-3">Excess hours to validate (All Departments)</div>
                    </div>
                </div>
            </div>
            <!-- Birthdays / Work Anniversaries -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(34, 197, 94, 0.1);">
                                    <span class="fs-4" style="color: #22c55e;"><i class="ti ti-gift"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">Celebrations</span>
                            </div>
                        </div>
                        <?php
                        $birthdays = [];
                        $anniversaries = [];
                        
                        // Get all birthdays for this month to display
                        $bday_q = mysqli_query($conn, "
                            SELECT e.first_name, e.last_name, e.birth_date, d.name as department_name
                            FROM employees e
                            LEFT JOIN departments d ON e.department_id = d.department_id
                            WHERE MONTH(birth_date) = $month
                            ORDER BY DAY(birth_date)
                        ");
                        if ($bday_q) {
                            while ($row = mysqli_fetch_assoc($bday_q)) {
                                $dept = $row['department_name'] ? ' (' . $row['department_name'] . ')' : '';
                                $birthdays[] = $row['first_name'] . ' ' . $row['last_name'] . $dept . ' - ' . date('M d', strtotime($row['birth_date']));
                            }
                        }
                        
                        // Get all work anniversaries for this month to display
                        $anniv_q = mysqli_query($conn, "
                            SELECT e.first_name, e.last_name, e.hire_date, d.name as department_name
                            FROM employees e
                            LEFT JOIN departments d ON e.department_id = d.department_id
                            WHERE MONTH(hire_date) = $month
                            ORDER BY DAY(hire_date)
                        ");
                        if ($anniv_q) {
                            while ($row = mysqli_fetch_assoc($anniv_q)) {
                                $dept = $row['department_name'] ? ' (' . $row['department_name'] . ')' : '';
                                $anniversaries[] = $row['first_name'] . ' ' . $row['last_name'] . $dept . ' - ' . date('M d', strtotime($row['hire_date']));
                            }
                        }
                        ?>
                        <div class="small text-muted mb-2" style="font-weight: 500;">This month (All Departments)</div>
                        <div style="max-height: 120px; overflow-y: auto;">
                            <?php foreach ($birthdays as $b): ?>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="ti ti-cake me-2" style="color: #f59e0b; font-size: 14px;"></i>
                                    <span style="font-size: 13px;"><?= htmlspecialchars($b) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($anniversaries as $a): ?>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="ti ti-medal me-2" style="color: #0ea5e9; font-size: 14px;"></i>
                                    <span style="font-size: 13px;"><?= htmlspecialchars($a) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($birthdays) && empty($anniversaries)): ?>
                                <div class="text-muted" style="font-size: 13px;">No celebrations this month</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Upcoming Leave Schedule -->
            <div class="col-md-4">
                <div class="card border-0 h-100" style="background: #ffffff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); border-radius: 16px; transition: all 0.3s ease;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: rgba(139, 92, 246, 0.1);">
                                    <span class="fs-4" style="color: #8b5cf6;"><i class="ti ti-calendar-event"></i></span>
                                </div>
                                <span class="fw-semibold text-dark">Leave Schedule</span>
                            </div>
                        </div>
                        <?php
                        $leave_sched = [];
                        
                        // Get upcoming leave schedule from all departments
                        $leave_q = mysqli_query($conn, "
                            SELECT e.first_name, e.last_name, elr.start_date, elr.end_date, d.name as department_name
                            FROM employeeleaverequests elr 
                            JOIN employees e ON elr.employee_id = e.employee_id 
                            LEFT JOIN departments d ON e.department_id = d.department_id
                            WHERE elr.status = 'Approved' 
                            AND elr.end_date >= '$today_date' 
                            ORDER BY elr.start_date ASC 
                            LIMIT 5
                        ");
                        if ($leave_q) {
                            while ($row = mysqli_fetch_assoc($leave_q)) {
                                $dept = $row['department_name'] ? ' (' . $row['department_name'] . ')' : '';
                                $leave_sched[] = $row['first_name'] . ' ' . $row['last_name'] . $dept . ' - ' . date('M d', strtotime($row['start_date'])) . ' to ' . date('M d', strtotime($row['end_date']));
                            }
                        }
                        ?>
                        <div class="small text-muted mb-2" style="font-weight: 500;">Upcoming absences (All Departments)</div>
                        <div style="max-height: 120px; overflow-y: auto;">
                            <?php foreach ($leave_sched as $ls): ?>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="ti ti-user me-2" style="color: #8b5cf6; font-size: 14px;"></i>
                                    <span style="font-size: 13px;"><?= htmlspecialchars($ls) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($leave_sched)): ?>
                                <div class="text-muted" style="font-size: 13px;">No upcoming leaves scheduled</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-primary"><i class="ti ti-users"></i></span>
                        <div class="fw-bold"><?= $user_count ?></div>
                        <div class="small text-muted">Dept Users</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-warning"><i class="ti ti-user-check"></i></span>
                        <div class="fw-bold"><?= $employee_count ?></div>
                        <div class="small text-muted">Dept Employees</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-success"><i class="ti ti-calendar-event"></i></span>
                        <div class="fw-bold"><?= $leave_type_count ?></div>
                        <div class="small text-muted">Leave Types</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-info"><i class="ti ti-clock"></i></span>
                        <div class="fw-bold"><?= $shift_count ?></div>
                        <div class="small text-muted">Shifts</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-danger"><i class="ti ti-clipboard-text"></i></span>
                        <div class="fw-bold"><?= $leave_request_count ?></div>
                        <div class="small text-muted">Dept Leave Requests</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card shadow-sm border-0 h-100 text-center">
                    <div class="card-body">
                        <span class="fs-2 text-secondary"><i class="ti ti-building-bank"></i></span>
                        <div class="fw-bold"><?= $admin_user_count ?></div>
                        <div class="small text-muted">Admin Users</div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer mt-5 text-center small text-muted">
            Biometrix System & Trading Corp. &#9829; © 2025
        </footer>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<script>
// Pass celebration data to JavaScript
const todayCelebrations = <?= json_encode($today_celebrations) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Update current time every second
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    
    // Show celebration notifications if there are any
    if (todayCelebrations && todayCelebrations.length > 0) {
        showCelebrationNotifications();
    }

    // Sidebar toggle functionality
    const sidebarHide = document.getElementById('sidebar-hide');
    const mobileCollapse = document.getElementById('mobile-collapse');
    const body = document.querySelector('body');

    function toggleSidebar() {
        if (body.classList.contains('pc-sidebar-collapse')) {
            body.classList.remove('pc-sidebar-collapse');
        } else {
            body.classList.add('pc-sidebar-collapse');
        }
    }

    function toggleMobileSidebar() {
        if (body.classList.contains('mob-sidebar-active')) {
            body.classList.remove('mob-sidebar-active');
        } else {
            body.classList.add('mob-sidebar-active');
        }
    }

    if (sidebarHide) {
        sidebarHide.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (mobileCollapse) {
        mobileCollapse.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMobileSidebar();
        });
    }
});

// Update current time every second
function updateCurrentTime() {
    const now = new Date();
    const options = {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    
    const timeString = now.toLocaleDateString('en-US', options);
    const timeElement = document.querySelector('.current-time');
    if (timeElement) {
        timeElement.textContent = timeString + ' (PHT)';
    }
}

function showCelebrationNotifications() {
    let delay = 1000; // Start with 1 second delay
    
    todayCelebrations.forEach((celebration, index) => {
        setTimeout(() => {
            if (celebration.type === 'birthday') {
                Swal.fire({
                    title: '🎉 Birthday Celebration!',
                    html: `
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="ti ti-cake" style="font-size: 4rem; color: #f59e0b;"></i>
                            </div>
                            <h4 style="color: #6366f1; margin-bottom: 15px;">${celebration.name}</h4>
                            <p style="color: #64748b; font-size: 1.1rem;">is celebrating their birthday today!</p>
                            <p style="color: #94a3b8; font-size: 0.9rem;">Department: ${celebration.department}</p>
                            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 10px;">
                                <p style="color: #92400e; margin: 0; font-weight: 600;">🎂 Let's wish them a wonderful day! 🎂</p>
                            </div>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: '🎉 Hooray!',
                    confirmButtonColor: '#f59e0b',
                    background: '#ffffff',
                    customClass: {
                        popup: 'birthday-popup',
                        title: 'birthday-title'
                    },
                    showClass: {
                        popup: 'animate__animated animate__bounceIn'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__bounceOut'
                    },
                    timer: 8000,
                    timerProgressBar: true,
                    width: '450px'
                });
            } else if (celebration.type === 'anniversary') {
                Swal.fire({
                    title: '🏆 Work Anniversary!',
                    html: `
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="ti ti-medal" style="font-size: 4rem; color: #0ea5e9;"></i>
                            </div>
                            <h4 style="color: #6366f1; margin-bottom: 15px;">${celebration.name}</h4>
                            <p style="color: #64748b; font-size: 1.1rem;">is celebrating <strong>${celebration.years} year${celebration.years > 1 ? 's' : ''}</strong> of dedicated service!</p>
                            <p style="color: #94a3b8; font-size: 0.9rem;">Department: ${celebration.department}</p>
                            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 10px;">
                                <p style="color: #1e40af; margin: 0; font-weight: 600;">🎊 Thank you for your commitment! 🎊</p>
                            </div>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: '🎊 Congratulations!',
                    confirmButtonColor: '#0ea5e9',
                    background: '#ffffff',
                    customClass: {
                        popup: 'anniversary-popup',
                        title: 'anniversary-title'
                    },
                    showClass: {
                        popup: 'animate__animated animate__bounceIn'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__bounceOut'
                    },
                    timer: 8000,
                    timerProgressBar: true,
                    width: '450px'
                });
            }
        }, delay);
        
        delay += 3000; // Add 3 seconds delay between each notification
    });
    
    // Optional: Show a summary notification if there are multiple celebrations
    if (todayCelebrations.length > 1) {
        setTimeout(() => {
            const birthdayCount = todayCelebrations.filter(c => c.type === 'birthday').length;
            const anniversaryCount = todayCelebrations.filter(c => c.type === 'anniversary').length;
            
            let summaryMessage = '';
            if (birthdayCount > 0 && anniversaryCount > 0) {
                summaryMessage = `We have ${birthdayCount} birthday${birthdayCount > 1 ? 's' : ''} and ${anniversaryCount} work anniversar${anniversaryCount > 1 ? 'ies' : 'y'} to celebrate today!`;
            } else if (birthdayCount > 1) {
                summaryMessage = `We have ${birthdayCount} birthdays to celebrate today!`;
            } else if (anniversaryCount > 1) {
                summaryMessage = `We have ${anniversaryCount} work anniversaries to celebrate today!`;
            }
            
            if (summaryMessage) {
                Swal.fire({
                    title: '🎉 Multiple Celebrations Today! 🎉',
                    text: summaryMessage,
                    icon: 'success',
                    confirmButtonText: 'Let\'s Celebrate!',
                    confirmButtonColor: '#22c55e',
                    timer: 6000,
                    timerProgressBar: true
                });
            }
        }, todayCelebrations.length * 3000 + 2000);
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>