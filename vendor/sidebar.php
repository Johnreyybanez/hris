<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullname = '';
$profile = [
    'photo_path' => '',
];

$conn_path = file_exists(__DIR__ . '/../connection.php') ? __DIR__ . '/../connection.php' : __DIR__ . '/connection.php';
include_once $conn_path;

// Use employeelogins and login_id for session/profile
if (isset($_SESSION['login_id'])) {
    $login_id = $_SESSION['login_id'];

    $stmt = $conn->prepare("SELECT username, image, employee_id FROM employeelogins WHERE login_id = ? LIMIT 1");
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $stmt->bind_result($username, $image, $employee_id);
    if ($stmt->fetch()) {
        $fullname = $username;
        if (!empty($image)) {
            $image_path = (strpos($image, 'uploads/') === 0) ? $image : 'uploads/' . ltrim($image, '/');
            // Check if the image file actually exists
            if (file_exists($image_path)) {
                $profile['photo_path'] = $image_path;
            } else {
                $profile['photo_path'] = 'assets/images/admin/img-add-user.png';
            }
        } else {
            $profile['photo_path'] = 'assets/images/admin/img-add-user.png';
        }
    }
    $stmt->close();

    if (!empty($employee_id)) {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt->bind_result($first_name, $last_name);
        if ($stmt->fetch()) {
            $fullname = trim($first_name . ' ' . $last_name);
        }
        $stmt->close();
    }
}

// Function to get profile image with fallback
function getProfileImage($photo_path) {
    $default_image = 'assets/images/default-user.jpg';
    
    // If no photo path is set, return default
    if (empty($photo_path)) {
        return $default_image;
    }
    
    // Check if the file exists
    if (file_exists($photo_path)) {
        return $photo_path;
    }
    
    // If file doesn't exist, return default
    return $default_image;
}

$profile_image = getProfileImage($profile['photo_path']);
?>
<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <!-- Profile Section with Background Image -->
        <div class="sidebar-user text-center p-3" 
             style="position: relative; color: white; min-height: 150px; overflow: hidden; border: none;">
             
            <!-- Background Image -->
            <div class="profile-background" style="
                position: absolute; 
                top: 0; 
                left: 0; 
                width: 100%; 
                height: 100%; 
                background-image: url('asset/images/aloguinsan.jpg');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                z-index: 0;
            "></div>
            
            <!-- Dark Overlay for better text readability -->
            <div class="profile-overlay" style="
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, rgba(0,0,0,0.3), rgba(0,0,0,0.2));
                z-index: 1;
                border: none;
            "></div>
            
            <!-- Profile Content -->
            <div class="profile-content" style="position: relative; z-index: 2; padding-top: 20px;">
                <!-- Profile Picture -->
                <img src="<?= htmlspecialchars($profile_image); ?>"
                     class="rounded-circle mb-2 profile-picture"
                     style="width: 60px; height: 60px; object-fit: cover; border: 3px solid #007bff !important; outline: none !important; box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3) !important;"
                     onerror="this.src='assets/images/default-user.jpg';"
                     alt="Profile Picture">
                
                <!-- User Info -->
                <h6 class="mb-0" style="color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.7); font-weight: 600;">
                    <?= htmlspecialchars($fullname) ?>
                </h6>
                <small style="color: rgba(255,255,255,0.9); text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">
                    <?= ucfirst($_SESSION['role'] ?? 'User') ?>
                </small>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="navbar-content">
            <ul class="pc-navbar">
                <li class="pc-item">
                    <a href="manager_dashboard.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="manager_profile.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-user"></i></span>
                        <span class="pc-mtext">My Profile</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="leave_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-calendar-check"></i></span>
                        <span class="pc-mtext">Leave Approval</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="ob_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
                        <span class="pc-mtext">OB Approval</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="timelog_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-clock-off"></i></span>
                        <span class="pc-mtext">Missing Time Log Approval</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="overtime_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-clock-edit"></i></span>
                        <span class="pc-mtext">Overtime Approval</span>
                    </a>
                </li>
                <li class="pc-item pc-hasmenu">
                    <a href="#leave-management-submenu" class="pc-link" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="leave-management-submenu">
                        <span class="pc-micon"><i class="ti ti-plane-departure"></i></span>
                        <span class="pc-mtext">Leave Management</span>
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu collapse" id="leave-management-submenu">
                        <li class="pc-item">
                            <a href="manager_leave_request.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-calendar-plus"></i></span>
                                <span class="pc-mtext">File Leave Request</span>
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="manager_ob.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
                                <span class="pc-mtext">OB Request</span>
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="manager_timelog.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clock-edit"></i></span>
                                <span class="pc-mtext">Missing Time Log</span>
                            </a>
                        </li>
                       <li class="pc-item">
                            <a href="manager_overtime.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clock-hour-10"></i></span>
                                <span class="pc-mtext">Overtime</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="pc-item">
                    <a href="manager_setting.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-settings"></i></span>
                        <span class="pc-mtext">Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    /* Sidebar background color */
.pc-sidebar {
    background-color:  #00264d; /* Deep blue */
    color: white;
}

/* Menu text color */
.pc-sidebar .pc-navbar .pc-link {
    color: #ffffff;
}

/* Hover effect for menu items */
.pc-sidebar .pc-navbar .pc-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

/* Ensure active menu items stand out */
.pc-sidebar .pc-navbar .pc-item.active > .pc-link {
    background-color: rgba(255, 255, 255, 0.25);
}

/* Additional CSS for enhanced profile section */
.sidebar-user {
    border-radius: 0px;
    margin-bottom: 10px;
}


/* Profile picture styling with blue border */
.profile-picture {
    border: 3px solid #007bff !important;
    border-radius: 50% !important;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3) !important;
    transition: all 0.3s ease !important;
}

/* Hover effect for profile picture */
.profile-content img:hover {
    transform: scale(1.05);
    transition: transform 0.3s ease;
    border-color: #0056b3 !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.5) !important;
}

/* Enhanced profile picture with glow effect */
.profile-picture:hover {
    box-shadow: 
        0 0 0 3px rgba(0, 123, 255, 0.5),
        0 0 20px rgba(0, 123, 255, 0.3) !important;
}

/* Animation for profile section */
.sidebar-user {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>