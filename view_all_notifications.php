<?php
session_start();
include 'connection.php';
include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';

// view_all_notifications.php
if (!isset($_SESSION['login_id'])) {
    header("Location: login.php");
    exit;
}

$login_id = (int) $_SESSION['login_id'];
$notifications = [];
$employee_id = null;
$debug_info = []; // For debugging

// Debug: Check if connection exists
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get employee_id using prepared statement
$emp_stmt = mysqli_prepare($conn, "SELECT employee_id FROM employeelogins WHERE login_id = ? LIMIT 1");
if (!$emp_stmt) {
    die("Prepare failed for employee query: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
mysqli_stmt_execute($emp_stmt);
$emp_result = mysqli_stmt_get_result($emp_stmt);

if ($emp_row = mysqli_fetch_assoc($emp_result)) {
    $employee_id = (int) $emp_row['employee_id'];
    $debug_info[] = "Employee ID found: " . $employee_id;
    
    // Pagination setup
    $notifPage = isset($_GET['notif_page']) ? max(1, (int)$_GET['notif_page']) : 1;
    $notifPerPage = 10; // Items per page
    $startIdx = ($notifPage - 1) * $notifPerPage;

    // Get user role
    $user_role_query = mysqli_prepare($conn, "SELECT role FROM employeelogins WHERE login_id = ?");
    mysqli_stmt_bind_param($user_role_query, "i", $login_id);
    mysqli_stmt_execute($user_role_query);
    $role_result = mysqli_stmt_get_result($user_role_query);
    $user_role = mysqli_fetch_assoc($role_result)['role'];
    mysqli_stmt_close($user_role_query);

    // Initialize query components
    if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
        // For admin/HR/manager - show all notifications
        $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM employee_notifications");
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $notifCount = mysqli_fetch_assoc($count_result)['total'];
        mysqli_stmt_close($count_stmt);

        // Get unread count for admin/HR/manager
        $unread_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM employee_notifications WHERE is_read = 0");
        mysqli_stmt_execute($unread_stmt);
        $unread_result = mysqli_stmt_get_result($unread_stmt);
        $unread_count = mysqli_fetch_assoc($unread_result)['unread'];
        mysqli_stmt_close($unread_stmt);

        // Get all notifications with related information
        $notif_stmt = mysqli_prepare($conn, "
            SELECT 
                en.*,
                e.first_name,
                e.last_name,
                e.department_id,
                d.name as department_name,
                CASE 
                    WHEN en.created_at > NOW() THEN 'Scheduled'
                    WHEN en.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'Today'
                    WHEN en.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 'Yesterday'
                    ELSE DATE_FORMAT(en.created_at, '%M %d, %Y')
                END as display_date,
                'All' as notification_type,
                lr.leave_type_id,
                lt.name as leave_type_name,
                lr.start_date,
                lr.end_date,
                lr.status as leave_status
            FROM employee_notifications en
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN employeeleaverequests lr ON CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(en.message, '#', -1), ' ', 1) AS UNSIGNED) = lr.leave_request_id
            LEFT JOIN leavetypes lt ON lr.leave_type_id = lt.leave_type_id
            ORDER BY en.created_at DESC 
            LIMIT ?, ?
        ");
        mysqli_stmt_bind_param($notif_stmt, "ii", $startIdx, $notifPerPage);
    } else {
        // For regular employees - get department_id
        $dept_query = mysqli_prepare($conn, "SELECT department_id FROM employees WHERE employee_id = ?");
        mysqli_stmt_bind_param($dept_query, "i", $employee_id);
        mysqli_stmt_execute($dept_query);
        $dept_result = mysqli_stmt_get_result($dept_query);
        $user_dept_id = mysqli_fetch_assoc($dept_result)['department_id'];
        mysqli_stmt_close($dept_query);

        // Count notifications for user's department and personal
        $count_stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) as total 
            FROM employee_notifications en 
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            WHERE en.employee_id = ? OR e.department_id = ?
        ");
        mysqli_stmt_bind_param($count_stmt, "ii", $employee_id, $user_dept_id);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $notifCount = mysqli_fetch_assoc($count_result)['total'];
        mysqli_stmt_close($count_stmt);

        // Get unread count for user
        $unread_stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) as unread 
            FROM employee_notifications en 
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            WHERE (en.employee_id = ? OR e.department_id = ?) AND en.is_read = 0
        ");
        mysqli_stmt_bind_param($unread_stmt, "ii", $employee_id, $user_dept_id);
        mysqli_stmt_execute($unread_stmt);
        $unread_result = mysqli_stmt_get_result($unread_stmt);
        $unread_count = mysqli_fetch_assoc($unread_result)['unread'];
        mysqli_stmt_close($unread_stmt);

        // Get notifications for user's department and personal
        $notif_stmt = mysqli_prepare($conn, "
            SELECT 
                en.*,
                e.first_name,
                e.last_name,
                e.department_id,
                d.name as department_name,
                CASE 
                    WHEN en.created_at > NOW() THEN 'Scheduled'
                    WHEN en.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'Today'
                    WHEN en.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 'Yesterday'
                    ELSE DATE_FORMAT(en.created_at, '%M %d, %Y')
                END as display_date,
                CASE 
                    WHEN en.employee_id = ? THEN 'Personal'
                    ELSE 'Department'
                END as notification_type,
                lr.leave_type_id,
                lt.name as leave_type_name,
                lr.start_date,
                lr.end_date,
                lr.status as leave_status
            FROM employee_notifications en
            LEFT JOIN employees e ON en.employee_id = e.employee_id 
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN employeeleaverequests lr ON CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(en.message, '#', -1), ' ', 1) AS UNSIGNED) = lr.leave_request_id
            LEFT JOIN leavetypes lt ON lr.leave_type_id = lt.leave_type_id
            WHERE en.employee_id = ? OR e.department_id = ?
            ORDER BY en.created_at DESC 
            LIMIT ?, ?
        ");
        mysqli_stmt_bind_param($notif_stmt, "iiiii", $employee_id, $employee_id, $user_dept_id, $startIdx, $notifPerPage);
    }

    // Execute notification query and fetch results
    mysqli_stmt_execute($notif_stmt);
    $notif_result = mysqli_stmt_get_result($notif_stmt);
    
    if (!$notif_result) {
        error_log("Query Error: " . mysqli_error($conn));
        die("Execute failed for notifications query: " . mysqli_error($conn));
    }
    
    // Fetch all notifications
    $pageNotifications = [];
    while ($notif = mysqli_fetch_assoc($notif_result)) {
        $pageNotifications[] = $notif;
    }
    mysqli_stmt_close($notif_stmt);

    // Calculate total pages
    $notifPages = ceil($notifCount / $notifPerPage);
}

// Helper function for relative time
function timeAgo($timestamp) {
    $time = time() - strtotime($timestamp);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time / 60) . ' minute' . (floor($time / 60) != 1 ? 's' : '') . ' ago';
    if ($time < 86400) return floor($time / 3600) . ' hour' . (floor($time / 3600) != 1 ? 's' : '') . ' ago';
    if ($time < 2592000) return floor($time / 86400) . ' day' . (floor($time / 86400) != 1 ? 's' : '') . ' ago';
    if ($time < 31536000) return floor($time / 2592000) . ' month' . (floor($time / 2592000) != 1 ? 's' : '') . ' ago';
    return floor($time / 31536000) . ' year' . (floor($time / 31536000) != 1 ? 's' : '') . ' ago';
}

// Debug mode - uncomment the next line to see debug information
// echo "<pre>" . print_r($debug_info, true) . "</pre>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>All Notifications</title>
    <style>
        .notification-list {
            max-height: 70vh;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .notification-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .notification-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .notification-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            animation: fadeIn 0.5s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
            background-color: rgba(13, 110, 253, 0.05) !important;
        }
        
        .notification-item.unread {
            border-left-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.02);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover .notification-icon {
            transform: scale(1.1);
        }
        
        .timestamp {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .notification-stats {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }

        /* Pagination Styles */
        .pagination {
            margin-bottom: 0;
        }
        
        .page-link {
            border-radius: 0.375rem;
            margin: 0 2px;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
            transform: translateY(-1px);
        }
        
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .page-item.disabled .page-link {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #6c757d;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 768px) {
            .notification-item {
                padding: 0.75rem;
            }
            
            .notification-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
            
            .timestamp {
                font-size: 0.75rem;
            }
            
            .notification-stats {
                flex-direction: column;
                gap: 5px;
            }

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-item {
                margin: 2px;
            }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10">Notifications</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="manager_dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">All Notifications</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debug Information (uncomment to show) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <h6>Debug Information:</h6>
                            <div class="debug-info">
                                <strong>Login ID:</strong> <?= $login_id ?><br>
                                <strong>Employee ID:</strong> <?= $employee_id ?? 'Not found' ?><br>
                                <strong>User Role:</strong> <?= $user_role ?? 'Not found' ?><br>
                                <strong>Total Count:</strong> <?= $notifCount ?? 0 ?><br>
                                <strong>Unread Count:</strong> <?= $unread_count ?? 0 ?><br>
                                <strong>Current Page:</strong> <?= $notifPage ?><br>
                                <strong>Total Pages:</strong> <?= $notifPages ?? 0 ?><br>
                                <strong>Page Notifications:</strong> <?= count($pageNotifications) ?> items<br>
                                <strong>Debug Info:</strong><br>
                                <?php foreach ($debug_info as $info): ?>
                                    - <?= htmlspecialchars($info) ?><br>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="fas fa-bell text-primary me-2"></i>
                                Notifications
                                <?php if (isset($unread_count) && $unread_count > 0): ?>
                                    <span class="badge bg-danger ms-2 pulse-animation"><?= $unread_count ?></span>
                                <?php endif; ?>
                            </h5>
                            
                            <?php if (!empty($pageNotifications)): ?>
                                <div class="notification-stats">
                                    <span class="badge bg-primary"><?= $notifCount ?> Total</span>
                                    <?php if (isset($unread_count) && $unread_count > 0): ?>
                                        <span class="badge bg-warning"><?= $unread_count ?> Unread</span>
                                        <button id="markAllReadBtn" 
                                                class="btn btn-light btn-sm border"
                                                data-employee-id="<?= $employee_id ?>">
                                            <i class="fas fa-check-double me-1"></i>
                                            Mark All as Read (<?= $unread_count ?>)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if (!empty($pageNotifications)): ?>
                                <div class="list-group list-group-flush notification-list" id="notificationList">
                                    <?php foreach ($pageNotifications as $index => $notif): ?>
                                        <div class="notification-item list-group-item list-group-item-action <?= $notif['is_read'] == 0 ? 'unread' : '' ?>"
                                             data-notification-id="<?= $notif['id'] ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="notification-icon me-3">
                                                    <?php if ($notif['is_read'] == 0): ?>
                                                        <i class="fas fa-bell text-primary"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <div>
                                                            <h6 class="mb-0 notification-message">
                                                                <?= htmlspecialchars($notif['message']) ?>
                                                                <?php if (isset($notif['notification_type'])): ?>
                                                                    <span class="badge bg-secondary ms-1"><?= $notif['notification_type'] ?></span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                Employee: <?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?> 
                                                                (<?= htmlspecialchars($notif['department_name'] ?? 'No Department') ?>)
                                                            </small>
                                                            <?php if (!empty($notif['leave_type_name'])): ?>
                                                                <div class="mt-1">
                                                                    <span class="badge bg-info">
                                                                        <?= htmlspecialchars($notif['leave_type_name']) ?>
                                                                    </span>
                                                                    <?php if ($notif['start_date'] && $notif['end_date']): ?>
                                                                        <small class="text-muted ms-2">
                                                                            <?= date('M j, Y', strtotime($notif['start_date'])) ?> - 
                                                                            <?= date('M j, Y', strtotime($notif['end_date'])) ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($notif['leave_status'])): ?>
                                                                        <span class="badge bg-<?= $notif['leave_status'] == 'Approved' ? 'success' : ($notif['leave_status'] == 'Rejected' ? 'danger' : 'warning') ?> ms-2">
                                                                            <?= htmlspecialchars($notif['leave_status']) ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="badge <?= $notif['is_read'] == 0 ? 'bg-primary' : 'bg-secondary' ?>">
                                                                <?= $notif['is_read'] == 0 ? 'New' : 'Read' ?>
                                                            </span>
                                                            <span class="badge bg-info ms-1">ID: <?= $notif['id'] ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                                        <small class="text-muted timestamp">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?= htmlspecialchars($notif['display_date']) ?> - 
                                                            <?= timeAgo($notif['created_at']) ?> 
                                                            (<?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>)
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Pagination Controls -->
                                <?php if ($notifPages > 1): ?>
                                    <div class="card-footer bg-white">
                                        <nav aria-label="Notifications pagination">
                                            <ul class="pagination justify-content-center mb-0">
                                                <!-- Previous Button -->
                                                <li class="page-item<?= $notifPage <= 1 ? ' disabled' : '' ?>">
                                                    <a class="page-link" href="?notif_page=<?= $notifPage-1 ?>" tabindex="-1">Previous</a>
                                                </li>

                                                <?php
                                                // Pagination logic - limit to 9 pages shown
                                                $maxPagesToShow = 9;
                                                $startPage = 1;
                                                $endPage = $notifPages;
                                                
                                                if ($notifPages > $maxPagesToShow) {
                                                    $halfMax = floor($maxPagesToShow / 2);
                                                    
                                                    if ($notifPage <= $halfMax) {
                                                        // Show first 9 pages
                                                        $startPage = 1;
                                                        $endPage = $maxPagesToShow;
                                                    } elseif ($notifPage > ($notifPages - $halfMax)) {
                                                        // Show last 9 pages
                                                        $startPage = $notifPages - $maxPagesToShow + 1;
                                                        $endPage = $notifPages;
                                                    } else {
                                                        // Show 9 pages centered around current page
                                                        $startPage = $notifPage - $halfMax;
                                                        $endPage = $notifPage + $halfMax;
                                                    }
                                                }
                                                ?>

                                                <!-- First page and ellipsis if needed -->
                                                <?php if ($startPage > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?notif_page=1">1</a>
                                                    </li>
                                                    <?php if ($startPage > 2): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Page numbers -->
                                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                    <li class="page-item<?= $i == $notifPage ? ' active' : '' ?>">
                                                        <a class="page-link" href="?notif_page=<?= $i ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>

                                                <!-- Last page and ellipsis if needed -->
                                                <?php if ($endPage < $notifPages): ?>
                                                    <?php if ($endPage < ($notifPages - 1)): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?notif_page=<?= $notifPages ?>"><?= $notifPages ?></a>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Next Button -->
                                                <li class="page-item<?= $notifPage >= $notifPages ? ' disabled' : '' ?>">
                                                    <a class="page-link" href="?notif_page=<?= $notifPage+1 ?>">Next</a>
                                                </li>
                                            </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                            Showing <?= min($notifCount, $startIdx + 1) ?>-<?= min($notifCount, $startIdx + count($pageNotifications)) ?> of <?= $notifCount ?> notifications (Page <?= $notifPage ?> of <?= $notifPages ?>)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="empty-state py-5">
                                    <i class="fas fa-bell-slash text-muted mb-3"></i>
                                    <h6>No Notifications</h6>
                                    <p class="text-muted mb-0">You'll see your notifications here when you receive them</p>
                                    <?php if ($employee_id === null): ?>
                                        <p class="text-danger mt-2">
                                            <small>Error: Employee ID not found for your login. Please contact administrator.</small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="manager_dashboard.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Back to Dashboard
                                </a>
                                
                                <!-- Debug link -->
                                <a href="?debug=1&notif_page=<?= $notifPage ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-bug me-1"></i>
                                    Debug Info
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mark individual notification as read when clicked
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    const notifId = this.dataset.notificationId;
                    
                    if (this.classList.contains('unread')) {
                        markAsRead(notifId, this);
                    }
                });
            });

            // Mark all notifications as read
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function() {
                    const employeeId = this.dataset.employeeId;
                    const btn = this;
                    const originalText = btn.innerHTML;
                    
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Marking...';
                    btn.disabled = true;
                    
                    fetch('mark_all_notifications_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `employee_id=${employeeId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update all unread notifications
                            document.querySelectorAll('.notification-item.unread').forEach(item => {
                                item.classList.remove('unread');
                                const icon = item.querySelector('.notification-icon i');
                                const badge = item.querySelector('.badge');
                                
                                if (icon) {
                                    icon.classList.replace('fa-bell', 'fa-check');
                                    icon.classList.replace('text-primary', 'text-success');
                                }
                                if (badge && badge.textContent === 'New') {
                                    badge.textContent = 'Read';
                                    badge.classList.replace('bg-primary', 'bg-secondary');
                                }
                            });
                            
                            // Remove unread count badge
                            const unreadBadge = document.querySelector('.card-title .badge-danger');
                            if (unreadBadge) {
                                unreadBadge.remove();
                            }
                            
                            // Update stats
                            const warningBadge = document.querySelector('.badge-warning');
                            if (warningBadge) {
                                warningBadge.remove();
                            }
                            
                            btn.style.display = 'none';
                        } else {
                            alert('Error marking notifications as read');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error marking notifications as read');
                    })
                    .finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                });
            }

            // Function to mark single notification as read
            function markAsRead(notifId, element) {
                fetch('mark_single_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notifId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        element.classList.remove('unread');
                        const icon = element.querySelector('.notification-icon i');
                        const badge = element.querySelector('.badge');
                        
                        if (icon) {
                            icon.classList.replace('fa-bell', 'fa-check');
                            icon.classList.replace('text-primary', 'text-success');
                        }
                        if (badge && badge.textContent === 'New') {
                            badge.textContent = 'Read';
                            badge.classList.replace('bg-primary', 'bg-secondary');
                        }
                        
                        // Update unread count
                        updateUnreadCount();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }

            function updateUnreadCount() {
                const unreadItems = document.querySelectorAll('.notification-item.unread').length;
                const unreadBadge = document.querySelector('.card-title .badge-danger');
                const warningBadge = document.querySelector('.badge-warning');
                
                if (unreadItems === 0) {
                    if (unreadBadge) unreadBadge.remove();
                    if (warningBadge) warningBadge.remove();
                    const markAllBtn = document.getElementById('markAllReadBtn');
                    if (markAllBtn) markAllBtn.style.display = 'none';
                } else {
                    if (unreadBadge) unreadBadge.textContent = unreadItems;
                    if (warningBadge) warningBadge.textContent = unreadItems + ' Unread';
                }
            }

            // Smooth scrolling for pagination
            document.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add loading state
                    const notificationList = document.getElementById('notificationList');
                    if (notificationList) {
                        notificationList.classList.add('loading');
                    }
                });
            });

            // Auto-refresh notifications every 30 seconds (optional)
            setInterval(function() {
                // Only refresh if user is on first page to avoid disrupting navigation
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('notif_page') || '1';
                
                if (currentPage === '1') {
                    // You can implement an AJAX refresh here if needed
                    // refreshNotifications();
                }
            }, 30000);
        }); 
    </script>
    
    <!-- DataTables JS & CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap 5 JS (required for modal and header buttons) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>