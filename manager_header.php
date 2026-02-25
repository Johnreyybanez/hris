<?php
include 'connection.php';

$user_image = 'assets/images/admin/img-add-user.png'; // fallback image
$user_name = 'Guest';
$notifications = [];
$unread_count = 0;
$employee_id = null;

if (isset($_SESSION['login_id'])) {
    $login_id = (int) $_SESSION['login_id'];

    $query = mysqli_query($conn, "SELECT username, image, role FROM employeelogins WHERE login_id = $login_id LIMIT 1");

    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $user_name = $row['username'];
        $role = $row['role'] ?? '';
        // Use default avatar if image is missing or file does not exist
        if (!empty($row['image']) && file_exists($row['image'])) {
            $user_image = $row['image'];
        } elseif (!empty($row['image']) && file_exists('uploads/' . $row['image'])) {
            $user_image = 'uploads/' . $row['image'];
        } else {
            $user_image = 'assets/images/admin/img-add-user.png';
        }
    } else {
        $user_image = 'assets/images/admin/img-add-user.png';
        $role = '';
    }

    // Get notifications for the current user
    if (isset($_SESSION['login_id'])) {
        // Get employee_id first
        $emp_stmt = mysqli_prepare($conn, "SELECT e.employee_id, e.department_id, el.role 
            FROM employeelogins el 
            JOIN employees e ON el.employee_id = e.employee_id 
            WHERE el.login_id = ? LIMIT 1");
        
        if ($emp_stmt === false) {
            error_log("Prepare failed: " . mysqli_error($conn));
        } else {
            mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
            mysqli_stmt_execute($emp_stmt);
            $emp_result = mysqli_stmt_get_result($emp_stmt);
            $emp_data = mysqli_fetch_assoc($emp_result);
            mysqli_stmt_close($emp_stmt);
        
            if ($emp_data) {
                $employee_id = $emp_data['employee_id'];
                $department_id = $emp_data['department_id'];
                $user_role = $emp_data['role'];

                // Fetch notifications based on role
                if ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager') {
                    // For admin/HR/manager, show all notifications
                    $notif_query = "SELECT en.*, e.first_name, e.last_name, e.department_id, d.name as department_name 
                                   FROM employee_notifications en
                                   LEFT JOIN employees e ON en.employee_id = e.employee_id 
                                   LEFT JOIN departments d ON e.department_id = d.department_id
                                   ORDER BY en.created_at DESC LIMIT 10";
                    $stmt = mysqli_prepare($conn, $notif_query);
                } else {
                    // For regular users, show department and personal notifications
                    $notif_query = "SELECT en.*, e.first_name, e.last_name, e.department_id, d.name as department_name 
                                   FROM employee_notifications en
                                   JOIN employees e ON en.employee_id = e.employee_id 
                                   LEFT JOIN departments d ON e.department_id = d.department_id
                                   WHERE en.employee_id = ? OR e.department_id = ?
                                   ORDER BY en.created_at DESC LIMIT 10";
                    $stmt = mysqli_prepare($conn, $notif_query);
                    if ($stmt !== false) {
                        mysqli_stmt_bind_param($stmt, "ii", $employee_id, $department_id);
                    }
                }
                
                if ($stmt !== false) {
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if ($result) {
                        $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
                        error_log("Notifications fetched: " . count($notifications));
                    } else {
                        error_log("Error fetching notifications: " . mysqli_error($conn));
                        $notifications = [];
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    error_log("Failed to prepare notification statement: " . mysqli_error($conn));
                    $notifications = [];
                }
                
                // Get unread count
                $unread_query = ($user_role == 'admin' || $user_role == 'hr' || $user_role == 'manager')
                    ? "SELECT COUNT(*) as count FROM employee_notifications WHERE is_read = 0"
                    : "SELECT COUNT(*) as count FROM employee_notifications en 
                       JOIN employees e ON en.employee_id = e.employee_id 
                       WHERE (en.employee_id = ? OR e.department_id = ?) AND en.is_read = 0";
                
                $unread_stmt = mysqli_prepare($conn, $unread_query);
                if ($unread_stmt !== false) {
                    if ($user_role != 'admin' && $user_role != 'hr' && $user_role != 'manager') {
                        mysqli_stmt_bind_param($unread_stmt, "ii", $employee_id, $department_id);
                    }
                    mysqli_stmt_execute($unread_stmt);
                    $unread_result = mysqli_stmt_get_result($unread_stmt);
                    $unread_count = mysqli_fetch_assoc($unread_result)['count'];
                    mysqli_stmt_close($unread_stmt);
                }
            }
        }
    }
}
?>

<header class="pc-header">
  <div class="header-wrapper">
    <div class="me-auto pc-mob-drp">
      <ul class="list-unstyled">
        <li class="pc-h-item pc-sidebar-collapse">
          <a href="#" class="pc-head-link ms-0" id="sidebar-hide"><i class="fas fa-bars"></i></a>
        </li>
        <li class="pc-h-item pc-sidebar-popup">
          <a href="#" class="pc-head-link ms-0" id="mobile-collapse"><i class="fas fa-bars"></i></a>
        </li>
        <li class="dropdown pc-h-item d-inline-flex d-md-none">
          <a class="pc-head-link dropdown-toggle arrow-none m-0" data-bs-toggle="dropdown" href="#"><i class="ti ti-search"></i></a>
          <div class="dropdown-menu pc-h-dropdown drp-search">
            <form class="px-3">
              <div class="form-group mb-0 d-flex align-items-center">
                <i data-feather="search"></i>
                <input type="search" class="form-control border-0 shadow-none" placeholder="Search here. . .">
              </div>
            </form>
          </div>
        </li>
      </ul>
    </div>

    <div class="ms-auto">
      <ul class="list-unstyled">
        <!-- WEATHER WIDGET -->
        <li class="pc-h-item d-none d-lg-inline-flex">
          <div class="weather-widget d-flex align-items-center me-3">
            <div class="weather-info text-center">
              <div class="d-flex align-items-center">
                <i id="weatherIcon" class="fas fa-sun text-warning me-2"></i>
                <div>
                  <span id="temperature" class="fw-bold">--°C</span>
                  <div class="weather-location small text-muted" id="location">Loading...</div>
                </div>
              </div>
            </div>
          </div>
        </li>

        <!-- DATE AND TIME -->
        <li class="pc-h-item d-none d-md-inline-flex">
          <div class="datetime-widget me-3">
            <div class="text-center">
              <div id="currentTime" class="fw-bold">--:--:--</div>
              <div id="currentDate" class="small text-muted">-- -- ----</div>
            </div>
          </div>
        </li>

        <!-- THEME TOGGLE BUTTON -->
        <li class="pc-h-item d-none d-lg-inline-flex">
          <div class="theme-toggle-container me-3">
            <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
              <i class="fas fa-sun theme-icon" id="themeIcon"></i>
              <span id="themeText">Light</span>
            </button>
          </div>
        </li>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <!-- NOTIFICATIONS -->
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#">
            <i class="ti ti-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="badge bg-danger pc-h-badge"><?= $unread_count ?></span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
            <div class="dropdown-header p-3 bg-light border-bottom">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                  Notifications 
                  <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?= $unread_count ?></span>
                  <?php endif; ?>
                </h6>
              </div>
              <?php if ($unread_count > 0 && $employee_id): ?>
                <button class="btn btn-primary btn-sm w-100 mt-2 mark-all-read" data-employee-id="<?= $employee_id ?>">
                  <i class="fas fa-check-double me-1"></i>
                  Mark All as Read (<?= $unread_count ?>)
                </button>
              <?php endif; ?>
            </div>
            <div class="dropdown-divider"></div>
            <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
              <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                  <div class="notification-item p-2 <?= $notif['is_read'] ? '' : 'unread' ?>" data-id="<?= $notif['id'] ?>">
                    <div class="d-flex align-items-center">
                      <div class="flex-shrink-0">
                        <div class="notification-icon">
                          <i class="fas <?= $notif['is_read'] ? 'fa-check text-success' : 'fa-bell text-primary' ?>"></i>
                        </div>
                      </div>
                      <div class="flex-grow-1 ms-3">
                        <p class="mb-1 notification-text"><?= htmlspecialchars($notif['message']) ?></p>
                        <small class="text-muted d-block">
                          <?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?> 
                          (<?= htmlspecialchars($notif['department_name'] ?? 'No Department') ?>)
                        </small>
                        <small class="text-muted">
                          <i class="far fa-clock me-1"></i>
                          <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                        </small>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center p-3 text-muted">
                  <i class="fas fa-bell-slash mb-2"></i>
                  <p class="mb-0">No notifications</p>
                </div>
              <?php endif; ?>
            </div>
            <div class="dropdown-divider"></div>
            <div class="p-2 text-center">
              <a href="view_all_notifications.php" class="btn btn-primary btn-sm w-100">
                View All Notifications
              </a>
            </div>
          </div>
        </li>

        <!-- PROFILE / LOGOUT -->
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link dropdown-toggle arrow-none me-0 d-flex align-items-center" data-bs-toggle="dropdown" href="#">
            <span class="d-inline-block rounded-circle overflow-hidden header-profile-wrapper" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
              <img src="<?= $user_image ?>" alt="user-image" class="user-avtar rounded-circle header-profile-picture" width="34" height="34" style="object-fit:cover;width:100%;height:100%;display:block;border:1px solid #007bff !important;">
            </span>
            <span class="ms-2 fw-semibold"><?= htmlspecialchars($user_name) ?></span>
            <i class="ti ti-chevron-down ms-1"></i>
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
            <div class="profile-header" style="position: relative; color: white; overflow: hidden;">
                <!-- Background Image -->
                <div class="profile-dropdown-background" style="
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
                
                <!-- Dark Overlay -->
                <div class="profile-dropdown-overlay" style="
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, rgba(0,0,0,0.4), rgba(0,0,0,0.3));
                    z-index: 1;
                "></div>
                
                <!-- Profile Content -->
                <div class="profile-dropdown-content" style="position: relative; z-index: 2; padding: 1rem; text-align: center;">
                    <div class="profile-img-wrapper" style="
                        width: 64px;
                        height: 64px;
                        margin: 0 auto 0.5rem;
                        border-radius: 50%;
                        overflow: hidden;
                        border: 1px solid #007bff !important;
                        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3) !important;
                    ">
                        <img src="<?= $user_image ?>" alt="Profile" class="profile-img dropdown-profile-picture" style="
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            border-radius: 50%;
                        ">
                    </div>
                    <h6 class="mb-1" style="color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.7); font-weight: 600;">
                        <?= htmlspecialchars($user_name) ?>
                    </h6>
                    <span class="text-muted small" style="color: rgba(255,255,255,0.9) !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">
                        <?= ucfirst($role) ?>
                    </span>
                </div>
            </div>
            <div class="p-2">
                <a class="dropdown-item rounded" href="manager_profile.php">
                    <i class="fas fa-user me-2"></i> My Profile
                </a>
                <a class="dropdown-item rounded" href="manager_setting.php">
                    <i class="fas fa-cog me-2"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item rounded text-danger" href="#" id="logoutBtn">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</header>

<!-- Dropdown Styling -->
<style>
body.dark-mode .loader-bg {
    background-color: #121212 !important; /* Dark mode loader background */
    transition: background-color 0.3s ease; /* Smooth transition */
}

/* Light mode loader background */
.loader-bg {
    background-color: #ffffff;
    transition: background-color 0.3s ease;
}
.dropdown-menu {
    border: 0;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
    border-radius: 0.5rem;
    padding: 0.5rem;
    min-width: 200px;
}

.dropdown-notification {
    width: 320px;
    max-height: 400px;
    overflow-y: auto;
}

.dropdown-user-profile {
    width: 250px;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: rgba(0,0,0,0.05);
}

/* Notification Styling */
.notification-item {
    padding: 0.75rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    background: rgba(0,0,0,0.02);
    transition: all 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background: rgba(0,0,0,0.05);
}

.notification-item.unread {
    background: rgba(13, 110, 253, 0.05);
    border-left: 3px solid #0d6efd;
}

/* Profile Dropdown with Background */
.profile-header {
    padding: 1rem;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    min-height: 120px;
    border-radius: 0.5rem 0.5rem 0 0;
}

.profile-img-wrapper {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.5rem;
    border-radius: 50%;
    overflow: hidden;
    border: none;
}

.profile-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mark-all-read {
    transition: all 0.2s ease;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.mark-all-read:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.dropdown-header {
    background: #f8f9fa;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}

.notification-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(13, 110, 253, 0.1);
    border-radius: 50%;
}

/* Animation for profile dropdown */
.profile-header {
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

/* Header profile picture styling with blue border */
.header-profile-picture {
    border: 3px solid #007bff !important;
    border-radius: 50% !important;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3) !important;
    transition: all 0.3s ease !important;
}

.header-profile-wrapper {
    border: 3px solid #007bff !important;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3) !important;
    transition: all 0.3s ease !important;
}

/* Hover effects for header profile picture */
.header-profile-picture:hover,
.header-profile-wrapper:hover {
    border-color: #0056b3 !important;
    box-shadow: 
        0 0 0 3px rgba(0, 123, 255, 0.5),
        0 0 15px rgba(0, 123, 255, 0.4) !important;
    transform: scale(1.05);
}

/* Dropdown profile picture styling */
.dropdown-profile-picture {
    border: 3px solid #007bff !important;
    border-radius: 50% !important;
    transition: all 0.3s ease !important;
}

/* Hover effect for profile picture in dropdown */
.profile-dropdown-content img:hover,
.dropdown-profile-picture:hover {
    transform: scale(1.05);
    transition: transform 0.3s ease;
    border-color: #0056b3 !important;
}

/* Enhanced profile img wrapper hover */
.profile-img-wrapper:hover {
    border-color: #0056b3 !important;
    box-shadow: 
        0 0 0 3px rgba(0, 123, 255, 0.5),
        0 0 20px rgba(0, 123, 255, 0.4) !important;
}

/* ========== THEME TOGGLE BUTTON STYLES ========== */
.theme-toggle-container {
    position: relative;
}

.theme-toggle {
    background: transparent !important;
    border: 1px solid #444 !important;
    border-radius: 25px;
    padding: 8px 16px;
    color: inherit !important;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 100px;
    justify-content: center;
}

.theme-toggle:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    transform: translateY(-2px);
}

.theme-toggle:active {
    transform: translateY(0);
}

/* Dark mode specific styles for theme toggle */
body.dark-mode .theme-toggle {
    background: #2d2d2d !important;
    border-color: #555 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .theme-toggle:hover {
    background: #3d3d3d !important;
    border-color: #666 !important;
}

body.dark-mode .theme-icon {
    color: #e0e0e0 !important;
}

/* Update theme text color in dark mode */
body.dark-mode #themeText {
    color: #e0e0e0 !important;
}

/* Ensure icon color matches text in both modes */
.theme-toggle .theme-icon {
    color: inherit !important;
    transition: transform 0.3s ease;
}

.theme-toggle:hover .theme-icon {
    transform: rotate(180deg);
}

/* Dark mode base */
body.dark-mode {
  background-color: #121212;
  color: #e0e0e0;
}

/* Container background */
body.dark-mode .pc-container {
  background-color: #1e1e1e;
}

/* Card backgrounds */
body.dark-mode .card {
  background-color: #2c2c2c;
  color: #e0e0e0;
  border-color: #444;
  box-shadow: 0 0 10px rgba(0,0,0,0.7);
}

body.dark-mode .border {
  border-color: #444 !important; /* dark gray border for dark mode */
}

body.dark-mode .card {
  background-color: #1e1e1e; /* optional: make card background dark too */
  color: #ddd; /* text color for dark mode */
}

body.dark-mode .text-muted {
  color: #aaa !important; /* lighter muted text for dark mode */
}

body.dark-mode .text-primary {
  color: #4ea8ff !important; /* slightly lighter blue for dark mode */
}


/* Text muted for dark mode */
body.dark-mode .text-muted {
  color: #aaa !important;
}

/* Links or headings */
body.dark-mode h5, 
body.dark-mode h6, 
body.dark-mode label {
  color: #ddd;
}

/* Badge colors (example for active/inactive) */
body.dark-mode .badge.bg-success {
  background-color: #4caf50;
}
body.dark-mode .badge.bg-secondary {
  background-color: #757575;
}

/* Inputs and form controls */
body.dark-mode input.form-control,
body.dark-mode .input-group-text {
  background-color: #333;
  color: #eee;
  border-color: #555;
}

/* Tooltip fix */
body.dark-mode .tooltip-inner {
  background-color: #444;
  color: #eee;
}

/* For the logo watermark */

/* Breadcrumb */
body.dark-mode .breadcrumb {
  background: #2a2a2a;
  box-shadow: 0 2px 6px rgba(0,0,0,0.8);
  color: #bbb;
}

body.dark-mode .breadcrumb-item a {
  color: #aaa;
}
body.dark-mode .breadcrumb-item a:hover {
  color: #fff;
  text-decoration: underline;
}
body.dark-mode .breadcrumb-item.active {
  color: #4dabf7;
}
/* Buttons */

body.dark-mode .btn-dark {
  background-color: #333 !important;
  border-color: #333 !important;
  color: #eee !important;
}
body.dark-mode .btn-outline-secondary {
  color: #ddd;
  border-color: #666;
}
body.dark-mode .btn-outline-secondary:hover {
  background-color: #444;
  border-color: #eee;
  color: #eee;
}
/* Dark mode styles for manager approval tables */
body.dark-mode #leaveTable,
body.dark-mode #overtimeTable,
body.dark-mode #obTable,
body.dark-mode #timelogTable {
  background-color: #222 !important;
  color: #ddd !important;
  border-color: #444 !important;
}

/* Table headers */
body.dark-mode #leaveTable thead,
body.dark-mode #overtimeTable thead,
body.dark-mode #obTable thead,
body.dark-mode #timelogTable thead {
  background-color: #3a3a3a !important;
  color: #eee !important;
}

body.dark-mode #leaveTable thead th,
body.dark-mode #overtimeTable thead th,
body.dark-mode #obTable thead th,
body.dark-mode #timelogTable thead th {
  border-color: #555 !important;
  color: #eee !important;
}

/* Table body rows */
body.dark-mode #leaveTable tbody tr,
body.dark-mode #overtimeTable tbody tr,
body.dark-mode #obTable tbody tr,
body.dark-mode #timelogTable tbody tr {
  background-color: #222 !important;
  color: #ddd !important;
}

/* Table cells */
body.dark-mode #leaveTable tbody td,
body.dark-mode #overtimeTable tbody td,
body.dark-mode #obTable tbody td,
body.dark-mode #timelogTable tbody td {
  background-color: #222 !important;
  color: #ddd !important;
  border-color: #444 !important;
}

/* Row hover effect */
body.dark-mode #leaveTable tbody tr:hover,
body.dark-mode #overtimeTable tbody tr:hover,
body.dark-mode #obTable tbody tr:hover,
body.dark-mode #timelogTable tbody tr:hover {
  background-color: #333 !important;
}

/* Modal styling */
/* Add this to your existing dark mode styles */

/* Modal Dark Mode Styling */
body.dark-mode .modal-content {
    background-color: #1e1e1e !important;
    color: #e0e0e0 !important;
    border: 1px solid #444 !important;
}

/* Modal Header */
body.dark-mode .modal-header {
    background-color: #2d2d2d !important;
    border-bottom: 1px solid #444 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .modal-title {
    color: #e0e0e0 !important;
}

/* Modal Body */
body.dark-mode .modal-body {
    background-color: #1e1e1e !important;
    color: #e0e0e0 !important;
}

/* Form Elements inside Modal */
body.dark-mode .modal-body input,
body.dark-mode .modal-body select,
body.dark-mode .modal-body textarea {
    background-color: #333 !important;
    border: 1px solid #555 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .modal-body input:focus,
body.dark-mode .modal-body select:focus,
body.dark-mode .modal-body textarea:focus {
    background-color: #404040 !important;
    border-color: #666 !important;
    box-shadow: 0 0 0 0.2rem rgba(66, 70, 73, 0.5) !important;
}

/* Input Group Add-ons */
body.dark-mode .modal-body .input-group-text {
    background-color: #404040 !important;
    border-color: #555 !important;
    color: #e0e0e0 !important;
}

/* Modal Footer */
body.dark-mode .modal-footer {
    background-color: #2d2d2d !important;
    border-top: 1px solid #444 !important;
}

/* Close Button */
body.dark-mode .modal-header .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%) !important;
}

/* Labels */
body.dark-mode .modal-body label {
    color: #e0e0e0 !important;
}

/* Help Text */
body.dark-mode .modal-body .form-text {
    color: #aaa !important;
}

/* Placeholder Text */
body.dark-mode .modal-body input::placeholder,
body.dark-mode .modal-body textarea::placeholder {
    color: #888 !important;
}

/* Disabled Inputs */
body.dark-mode .modal-body input:disabled,
body.dark-mode .modal-body select:disabled,
body.dark-mode .modal-body textarea:disabled {
    background-color: #2a2a2a !important;
    color: #888 !important;
}

/* Modal Backdrop */
body.dark-mode .modal-backdrop {
    background-color: #000 !important;
}

/* Status Badges in Modal */
body.dark-mode .modal-body .badge {
    border: 1px solid #555 !important;
}

/* Modal Scrollbar */
body.dark-mode .modal-body::-webkit-scrollbar {
    width: 8px;
}

body.dark-mode .modal-body::-webkit-scrollbar-track {
    background: #2d2d2d;
}

body.dark-mode .modal-body::-webkit-scrollbar-thumb {
    background-color: #555;
    border-radius: 4px;
}

/* Modal Loading States */
body.dark-mode .modal-content .spinner-border {
    border-color: #e0e0e0;
    border-right-color: transparent;
}

/* Alert Messages in Modal */
body.dark-mode .modal-body .alert {
    background-color: #2d2d2d !important;
    border-color: #444 !important;
    color: #e0e0e0 !important;
}

/* Modal Buttons */
body.dark-mode .modal-footer .btn-secondary {
    background-color: #4a4a4a !important;
    border-color: #555 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .modal-footer .btn-primary {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
}

body.dark-mode .modal-footer .btn-danger {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
}

body.dark-mode .modal-footer .btn-success {
    background-color: #198754 !important;
    border-color: #198754 !important;
}

/* Date/Time Inputs */
body.dark-mode .modal-body input[type="date"],
body.dark-mode .modal-body input[type="time"],
body.dark-mode .modal-body input[type="datetime-local"] {
    color-scheme: dark !important;
}

/* Modal animations */
@keyframes darkModalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

body.dark-mode .modal.show .modal-content {
    animation: darkModalFadeIn 0.3s ease-out;
}

body.dark-mode .modal-header,
body.dark-mode .modal-footer {
  border-color: #444 !important;
}

/* Form elements in modals and filters */
body.dark-mode .form-control,
body.dark-mode .form-select,
body.dark-mode .input-group-text,
body.dark-mode textarea.form-control {
  background-color: #333 !important;
  border-color: #555 !important;
  color: #ddd !important;
}

body.dark-mode .form-control:disabled,
body.dark-mode .form-control[readonly] {
  background-color: #2a2a2a !important;
  color: #999 !important;
}

/* DataTables specific elements */
body.dark-mode .dataTables_wrapper .dataTables_length,
body.dark-mode .dataTables_wrapper .dataTables_filter,
body.dark-mode .dataTables_wrapper .dataTables_info,
body.dark-mode .dataTables_wrapper .dataTables_processing {
  color: #ddd !important;
}

body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {
  color: #ddd !important;
  background: transparent !important;
  border: 1px solid #444 !important;
}

body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current,
body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
  background: #4dabf7 !important;
  color: #fff !important;
  border-color: #4dabf7 !important;
}

/* Status badges */
body.dark-mode .badge.bg-warning {
  background-color: #856404 !important;
  color: #fff !important;
}

body.dark-mode .badge.bg-success {
  background-color: #1e7e34 !important;
  color: #fff !important;
}

body.dark-mode .badge.bg-danger {
  background-color: #dc3545 !important;
  color: #fff !important;
}

/* Card styling */
body.dark-mode .card {
  background-color: #2d2d2d !important;
  border-color: #444 !important;
}

body.dark-mode .card-header {
  background-color: #333 !important;
  border-bottom-color: #444 !important;
  color: #ddd !important;
}

/* Filter section */
body.dark-mode .form-label {
  color: #ddd !important;
}

/* Buttons */
body.dark-mode .btn-primary {
  background-color: #0d6efd !important;
  border-color: #0d6efd !important;
}

body.dark-mode .btn-danger {
  background-color: #dc3545 !important;
  border-color: #dc3545 !important;
}

body.dark-mode .btn-success {
  background-color: #198754 !important;
  border-color: #198754 !important;
}

/* Search input */
body.dark-mode .dataTables_filter input {
  background-color: #333 !important;
  border-color: #555 !important;
  color: #ddd !important;
}

/* Breadcrumb */
body.dark-mode .breadcrumb {
  background-color: transparent !important;
}

body.dark-mode .breadcrumb-item,
body.dark-mode .breadcrumb-item a {
  color: #ddd !important;
}

body.dark-mode .breadcrumb-item.active {
  color: #aaa !important;
}
/* Modal */
body.dark-mode .modal-content {
  background-color: #2b2b2b !important;
  color: #ddd !important;
}

/* Icons */


/* Tooltip */
body.dark-mode .tooltip-inner {
  background-color: #555 !important;
  color: #eee !important;
}

/* Scrollbar (optional) */
body.dark-mode ::-webkit-scrollbar {
  width: 8px;
}
body.dark-mode ::-webkit-scrollbar-thumb {
  background: #555;
  border-radius: 4px;
}
body.dark-mode .card-body.bg-white.text-dark {
  background-color: #1e1e1e !important;
  color: #ddd !important;
}
body.dark-mode {
    background-color: #121212 !important;
    color: #e0e0e0 !important;
}


/* For your card backgrounds and other white areas */

body.dark-mode .bg-light {
    background-color: #1e1e1e !important;
    color: #ccc !important;
    border-color: #333 !important;
}

/* Adjust input group backgrounds if needed */
body.dark-mode .input-group-text.bg-white {
    background-color: #2c2c2c !important;
    color: #ccc;
}

/* And buttons */
body.dark-mode .btn-primary {
    background-color: #3a6ea5;
    border-color: #3a6ea5;
}

/* etc, customize as needed */
body.dark-mode .pagination .page-link {
  background-color: #1e1e1e !important;
  border-color: #444444 !important;
}
body.dark-mode .loader-bg {
  background-color: #121212; /* Dark background in dark mode */
}
body.dark_mode .dtr-instructions {
  background-color: #1e3a5f; /* Darker blue tone */
  color: #d1e7ff; /* Light text */
  border-color: #3b82f6; /* Bright blue border */
}
/* Dark mode for DataTables Copy button only */
body.dark-mode .buttons-copy {
    background-color: #333 !important;
    color: #fff !important;
    border: 1px solid #555 !important;
}

body.dark-mode .buttons-copy:hover {
    background-color: #444 !important;
    border-color: #666 !important;
    color: #fff !important;
}

/* Dark mode styles for toggle and notification icons */
body.dark-mode .pc-head-link {
    color: #e0e0e0 !important;
    background: transparent !important;
}

body.dark-mode .pc-head-link:hover {
    color: #ffffff !important;
    background-color: rgba(255, 255, 255, 0.1) !important;
}

body.dark-mode .pc-head-link i {
    color: #e0e0e0 !important;
}

body.dark-mode .pc-head-link .ti-search,
body.dark-mode .pc-head-link .ti-bell {
    color: #e0e0e0 !important;
    transition: all 0.3s ease;
}

body.dark-mode .pc-head-link:hover .ti-search,
body.dark-mode .pc-head-link:hover .ti-bell {
    color: #ffffff !important;
    transform: scale(1.1);
}

body.dark-mode .pc-h-item .pc-head-link {
    background-color: transparent !important;
}

body.dark-mode .pc-h-badge {
    box-shadow: 0 0 0 2px #1e1e1e !important;
}
</style>

<!-- Add this before closing </head> tag -->
<script src="assets/js/theme-manager.js"></script>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationModalLabel">Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="notifModalMessage"></p>
        <small class="text-muted" id="notifModalDate"></small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add this modal template at the end of the file, before the scripts -->
<div class="modal fade" id="dataTableModal" tabindex="-1" aria-labelledby="dataTableModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dataTableModalLabel">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Modal content will be dynamically loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modify the script section at the end of the file -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // ========== THEME TOGGLE FUNCTIONALITY ==========
    function initializeTheme() {
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        
        // Get saved theme or use system preference as default
        const savedTheme = localStorage.getItem('theme') || 
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        
        function applyTheme(theme) {
            document.body.classList.toggle('dark-mode', theme === 'dark');
            themeIcon.className = `fas fa-${theme === 'dark' ? 'moon' : 'sun'} theme-icon`;
            themeText.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
            localStorage.setItem('theme', theme);
            
            // Dispatch event for other components that might need to know about theme changes
            document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
        }
        
        // Apply initial theme
        applyTheme(savedTheme);
        
        // Theme toggle click handler
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const currentTheme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                // Add loading state
                this.style.pointerEvents = 'none';
                themeIcon.classList.add('fa-spin');
                
                // Simulate loading for smooth transition
                setTimeout(() => {
                    applyTheme(newTheme);
                    
                    // Remove loading state
                    this.style.pointerEvents = 'auto';
                    themeIcon.classList.remove('fa-spin');
                }, 200);
            });
        }
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    // Notification Modal logic
    document.querySelectorAll('.notification-modal-trigger').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var message = this.getAttribute('data-message');
            var date = this.getAttribute('data-date');
            document.getElementById('notifModalMessage').textContent = message;
            document.getElementById('notifModalDate').textContent = date;
            var notifModal = new bootstrap.Modal(document.getElementById('notificationModal'));
            notifModal.show();
        });
    });

    // Add click functionality to notification items
    document.querySelectorAll('.notification-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            const isUnread = this.classList.contains('unread');
            
            if (isUnread) {
                // Mark as read
                fetch('mark_single_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.remove('unread');
                        const icon = this.querySelector('.notification-icon i');
                        if (icon) {
                            icon.classList.replace('fa-bell', 'fa-check');
                            icon.classList.replace('text-primary', 'text-success');
                        }
                        
                        // Update unread count
                        updateUnreadCount();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    });

    function updateUnreadCount() {
        const unreadItems = document.querySelectorAll('.notification-item.unread').length;
        const headerBadge = document.querySelector('.pc-h-badge');
        const dropdownBadge = document.querySelector('.dropdown-header .badge-danger');
        const markAllBtn = document.querySelector('.mark-all-read');
        
        if (unreadItems === 0) {
            if (headerBadge) headerBadge.remove();
            if (dropdownBadge) dropdownBadge.remove();
            if (markAllBtn) markAllBtn.style.display = 'none';
        } else {
            if (headerBadge) headerBadge.textContent = unreadItems;
            if (dropdownBadge) dropdownBadge.textContent = unreadItems;
            if (markAllBtn) {
                markAllBtn.innerHTML = `<i class="fas fa-check-double me-1"></i>Mark All as Read (${unreadItems})`;
            }
        }
    }

    // ========== DATE AND TIME UPDATE ==========
    function updateDateTime() {
        const now = new Date();
        
        // Format time (HH:MM:SS)
        const timeString = now.toLocaleTimeString('en-US', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        // Format date (Day, Month Date, Year)
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        document.getElementById('currentTime').textContent = timeString;
        document.getElementById('currentDate').textContent = dateString;
    }

    // Update time immediately and then every second
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ========== WEATHER API ==========
    function getWeatherIcon(weatherCode, isDay) {
        const iconMap = {
            0: isDay ? 'fa-sun' : 'fa-moon', // Clear sky
            1: isDay ? 'fa-sun' : 'fa-moon', // Mainly clear
            2: 'fa-cloud-sun', // Partly cloudy
            3: 'fa-cloud', // Overcast
            45: 'fa-smog', // Fog
            48: 'fa-smog', // Depositing rime fog
            51: 'fa-cloud-drizzle', // Light drizzle
            53: 'fa-cloud-drizzle', // Moderate drizzle
            55: 'fa-cloud-drizzle', // Dense drizzle
            61: 'fa-cloud-rain', // Slight rain
            63: 'fa-cloud-rain', // Moderate rain
            65: 'fa-cloud-showers-heavy', // Heavy rain
            71: 'fa-snowflake', // Slight snow
            73: 'fa-snowflake', // Moderate snow
            75: 'fa-snowflake', // Heavy snow
            80: 'fa-cloud-rain', // Slight rain showers
            81: 'fa-cloud-rain', // Moderate rain showers
            82: 'fa-cloud-showers-heavy', // Violent rain showers
            95: 'fa-bolt', // Thunderstorm
            96: 'fa-bolt', // Thunderstorm with slight hail
            99: 'fa-bolt' // Thunderstorm with heavy hail
        };
        return iconMap[weatherCode] || 'fa-question';
    }

    function fetchWeather() {
        // Try to get user's location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    // Fetch weather data from Open-Meteo API (free, no API key required)
                    fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&timezone=auto`)
                        .then(response => response.json())
                        .then(data => {
                            const weather = data.current_weather;
                            const temp = Math.round(weather.temperature);
                            const weatherCode = weather.weathercode;
                            const isDay = weather.is_day;
                            
                            document.getElementById('temperature').textContent = `${temp}°C`;
                            document.getElementById('weatherIcon').className = `fas ${getWeatherIcon(weatherCode, isDay)} me-2`;
                            
                            // Set icon color based on weather
                            const iconElement = document.getElementById('weatherIcon');
                            iconElement.classList.remove('text-warning', 'text-info', 'text-secondary', 'text-primary');
                            if (weatherCode === 0 && isDay) {
                                iconElement.classList.add('text-warning'); // Sunny
                            } else if (weatherCode >= 61 && weatherCode <= 82) {
                                iconElement.classList.add('text-info'); // Rainy
                            } else if (weatherCode >= 71 && weatherCode <= 75) {
                                iconElement.classList.add('text-secondary'); // Snowy
                            } else {
                                iconElement.classList.add('text-primary'); // Cloudy/Other
                            }
                        })
                        .catch(error => {
                            console.error('Weather fetch error:', error);
                            document.getElementById('temperature').textContent = 'N/A';
                            document.getElementById('weatherIcon').className = 'fas fa-exclamation-triangle text-warning me-2';
                        });
                    
                    // Fetch location name using reverse geocoding
                    fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`)
                        .then(response => response.json())
                        .then(data => {
                            const city = data.city || data.locality || data.principalSubdivision || 'Unknown';
                            document.getElementById('location').textContent = city;
                        })
                        .catch(error => {
                            console.error('Location fetch error:', error);
                            document.getElementById('location').textContent = 'Location unavailable';
                        });
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    document.getElementById('temperature').textContent = 'N/A';
                    document.getElementById('location').textContent = 'Location denied';
                    document.getElementById('weatherIcon').className = 'fas fa-map-marker-alt text-secondary me-2';
                }
            );
        } else {
            document.getElementById('temperature').textContent = 'N/A';
            document.getElementById('location').textContent = 'Geolocation not supported';
            document.getElementById('weatherIcon').className = 'fas fa-exclamation-triangle text-warning me-2';
        }
    }

    // Fetch weather on load and every 10 minutes
    fetchWeather();
    setInterval(fetchWeather, 600000); // 10 minutes

    // Logout Confirmation
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: "Are you sure?",
                text: "You will be logged out of your session.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, logout",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "logout.php";
                }
            });
        });
    }

    // Add mark all as read functionality
    const markAllBtn = document.querySelector('.mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            const employeeId = this.dataset.employeeId;
            const btn = this;
            
            // Disable button and show loading state
            btn.disabled = true;
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Marking...';
            
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
                    // Update UI to show all notifications as read
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const icon = item.querySelector('.notification-icon i');
                        if (icon) {
                            icon.classList.replace('fa-bell', 'fa-check');
                            icon.classList.replace('text-primary', 'text-success');
                        }
                    });
                    
                    // Update badges and counters
                    const unreadBadge = document.querySelector('.badge.bg-danger');
                    const headerBadge = document.querySelector('.pc-h-badge');
                    if (unreadBadge) unreadBadge.remove();
                    if (headerBadge) headerBadge.remove();
                    
                    // Hide mark all read button
                    btn.style.display = 'none';
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Successfully marked ${data.affected_rows || 'all'} notifications as read`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to mark notifications as read',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    
                    // Re-enable button on error
                    btn.disabled = false;
                    btn.style.pointerEvents = 'auto';
                    btn.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark All as Read';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to connect to server. Please try again.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                
                // Re-enable button on error
                btn.disabled = false;
                btn.style.pointerEvents = 'auto';
                btn.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark All as Read';
            });
        });
    }

    // DataTables Modal Handler
    function initializeDataTableModals() {
        const tables = document.querySelectorAll('.table');
        tables.forEach(table => {
            if ($.fn.DataTable.isDataTable(table)) {
                const dt = $(table).DataTable();

                // Handle view button clicks
                $(table).on('click', '.view-btn, .edit-btn, .details-btn', function(e) {
                    e.preventDefault();
                    const modal = new bootstrap.Modal(document.getElementById('dataTableModal'));
                    const modalBody = document.querySelector('#dataTableModal .modal-body');
                    const rowData = dt.row($(this).closest('tr')).data();
                    
                    // Update modal title based on button type
                    const modalTitle = document.querySelector('#dataTableModalLabel');
                    if (this.classList.contains('view-btn')) {
                        modalTitle.textContent = 'View Details';
                    } else if (this.classList.contains('edit-btn')) {
                        modalTitle.textContent = 'Edit Details';
                    } else {
                        modalTitle.textContent = 'Details';
                    }

                    // Get the data-url attribute if it exists, otherwise use default handling
                    const url = this.getAttribute('data-url');
                    if (url) {
                        // Load content from URL
                        fetch(url)
                            .then(response => response.text())
                            .then(data => {
                                modalBody.innerHTML = data;
                                modal.show();
                            })
                            .catch(error => {
                                console.error('Error loading modal content:', error);
                                modalBody.innerHTML = '<div class="alert alert-danger">Error loading content</div>';
                                modal.show();
                            });
                    } else {
                        // Default handling with row data
                        let content = '<div class="container-fluid">';
                        for (let key in rowData) {
                            if (rowData.hasOwnProperty(key) && !key.startsWith('_')) {
                                content += `<div class="row mb-2">
                                    <div class="col-sm-4 font-weight-bold">${key}:</div>
                                    <div class="col-sm-8">${rowData[key]}</div>
                                </div>`;
                            }
                        }
                        content += '</div>';
                        modalBody.innerHTML = content;
                        modal.show();
                    }
                });
            }
        });
    }

    // Initialize modals when DataTables are ready
    $(document).on('init.dt', function() {
        initializeDataTableModals();
    });

    // Re-initialize modals after DataTables reload/draw
    $(document).on('draw.dt', function() {
        initializeDataTableModals();
    });
});
</script>