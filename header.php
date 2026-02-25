<?php
include 'connection.php';

$user_image = 'logo.png';
$user_name  = 'Guest';
$role       = 'guest';
$notifications = [];
$unread_count  = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $user_id = (int) $_SESSION['user_id'];
    $role    = $_SESSION['role'];
    $query   = null;

    if ($role === 'admin') {
        $query = mysqli_query($conn, "SELECT username, image FROM users WHERE user_id = $user_id LIMIT 1");
    } elseif ($role === 'employee' || $role === 'manager') {
        $query = mysqli_query($conn, "SELECT username, image FROM employeelogins WHERE employee_id = $user_id LIMIT 1");
    }

    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $user_name = $row['username'];
        if (!empty($row['image']) && file_exists($row['image'])) {
            $user_image = $row['image'];
        }
    }

    // Get unread count
    $unread_query = mysqli_query($conn, "SELECT COUNT(*) as unread_total FROM notifications WHERE user_id = $user_id AND is_read = 0");
    if ($unread_query && mysqli_num_rows($unread_query) > 0) {
        $unread_row = mysqli_fetch_assoc($unread_query);
        $unread_count = (int) $unread_row['unread_total'];
    }

    // Fetch notifications for display
    $notif_query = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
    if ($notif_query) {
        while ($notif = mysqli_fetch_assoc($notif_query)) {
            $notifications[] = $notif;
        }
    }
}
?>
<!-- Font Awesome 6 (Free) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<header class="pc-header">
    <div class="header-wrapper">
        <!-- Left Section - Menu Toggle -->
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <!-- Sidebar Toggle (Desktop) -->
                <li class="pc-h-item pc-sidebar-collapse">
                    <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                     <i class="fa-solid fa-sliders"></i>
                    </a>
                </li>
                
                <!-- Sidebar Toggle (Mobile) -->
                <li class="pc-h-item pc-sidebar-popup">
                    <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                       <i class="fa-solid fa-sliders"></i>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Right Section - Notifications and Profile -->
        <div class="ms-auto">
            <ul class="list-unstyled">
                <!-- Notifications Dropdown -->
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#">
                        <i class="ti ti-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-success pc-h-badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
                        <!-- Header -->
                        <div class="dropdown-header d-flex justify-content-between">
                            <h5 class="m-0">Notifications</h5>
                            <a href="mark_all_read.php" class="pc-head-link bg-transparent" title="Mark all as read">
                                <i class="ti ti-circle-check text-success"></i>
                            </a>
                        </div>
                        
                        <div class="dropdown-divider"></div>
                        
                        <!-- Notifications List -->
                        <div class="dropdown-header px-0 header-notification-scroll" style="max-height: 300px;">
                            <div class="list-group list-group-flush">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <a href="<?= htmlspecialchars($notif['notification_id'] ?? '#') ?>" 
                                           class="list-group-item list-group-item-action <?= $notif['is_read'] ? '' : 'bg-light' ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <div class="user-avtar bg-light-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 36px; height: 36px;">
                                                        <i class="fa fa-envelope text-success"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    <p class="mb-1"><?= htmlspecialchars($notif['message']) ?></p>
                                                    <span class="text-muted small">
                                                        <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center p-3 text-muted">No notifications</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="dropdown-divider"></div>
                        <div class="text-center py-2">
                            <a href="notifications.php" class="link-primary">View all</a>
                        </div>
                    </div>
                </li>

                <!-- Profile Dropdown -->
                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0 d-flex align-items-center profile-trigger" 
                       data-bs-toggle="dropdown" href="#">
                        <div class="position-relative">
                            <img src="<?= $user_image ?>" alt="user-image" 
                                 class="user-avtar rounded-circle profile-avatar-small" width="32px" height="32px">
                            <div class="status-dot"></div>
                        </div>
                        <div class="user-info d-none d-md-block ms-2">
                            <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                        </div>
                        <i class="ti ti-selector ms-2 dropdown-arrow"></i>
                    </a>

                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown enhanced-dropdown">
                        <!-- Profile Header -->
                        <div class="profile-header-enhanced">
                            <div class="profile-bg-pattern"></div>
                            <div class="profile-bg-overlay"></div>
                            
                            <div class="profile-content">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 position-relative">
                                        <img src="<?= $user_image ?>" alt="user-image" class="profile-main-avatar">
                                        <div class="avatar-ring"></div>
                                        <div class="status-indicator-large"></div>
                                    </div>
                                    <div class="flex-grow-1 ms-3 profile-details">
                                        <h6 class="profile-name mb-1"><?= htmlspecialchars($user_name) ?></h6>
                                        <div class="role-badge">
                                            <i class="fas fa-crown me-1"></i>
                                            <span><?= ucfirst($role) ?></span>
                                        </div>
                                        <div class="profile-stats mt-2">
                                            <small class="last-seen">
                                                <i class="fas fa-circle text-success me-1"></i>
                                                Online now
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Menu Items -->
                        <div class="dropdown-body">
                            <a href="users.php" class="dropdown-item-enhanced">
                                <div class="item-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">My Profile</span>
                                    <small class="item-desc">View and edit your profile</small>
                                </div>
                            </a>
                            
                            <a href="signatory_settings.php" class="dropdown-item-enhanced">
                                <div class="item-icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">Settings</span>
                                    <small class="item-desc">Account preferences</small>
                                </div>
                            </a>
                            
                            <a href="notifications.php" class="dropdown-item-enhanced">
                                <div class="item-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">Notifications</span>
                                    <small class="item-desc">Manage your alerts</small>
                                </div>
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <!-- Logout Button -->
                            <button class="dropdown-item-enhanced logout-btn" id="logoutBtn">
                                <div class="item-icon logout-icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <div class="item-content">
                                    <span class="item-title">Sign Out</span>
                                    <small class="item-desc">Logout from your account</small>
                                </div>
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</header>

<style>
       /* Ensure dropdown link is fully visible and not clipped */
.pc-head-link.profile-trigger {
    overflow: visible !important;
    max-width: 100%;
    white-space: nowrap;
}
@media (max-width: 767px) {
    .header-user-profile .user-info {
        display: none !important;  /* avatar only */
    }

    .pc-head-link.profile-trigger {
        padding: 4px 6px; /* reduce height */
    }

    .profile-avatar-small {
        width: 28px;
        height: 28px;
    }

    .dropdown-arrow {
        display: none;  /* optional on mobile */
    }
}

    .pc-header {
    box-shadow: 0 2px 5px rgba(116, 112, 112, 1);
}

/* Profile Trigger */
.profile-trigger {
    transition: all 0.3s ease;
}

.profile-trigger:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(103, 126, 234, 0.3);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Profile Avatar */
.profile-avatar-small {
    border: 2px solid rgba(53, 51, 51, 0.8);
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.profile-trigger:hover .profile-avatar-small {
    transform: scale(1.1);
    border-color: #444546ff;
}

.status-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background: #10b981;
    border: 2px solid white;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.1); }
}

/* User Info */
.user-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.dropdown-arrow {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.dropdown.show .dropdown-arrow {
    transform: rotate(180deg);
}

/* Enhanced Dropdown */
.enhanced-dropdown {
    min-width: 320px;
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    background: white;
    margin-top: 0.5rem;
    overflow: hidden;
    animation: dropdownSlide 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Profile Header */
.profile-header-enhanced {
    position: relative;
    padding: 2rem 1.5rem;
    background: linear-gradient(135deg, #cecfd6ff 0%, #333333ff 100%);
    color: white;
    overflow: hidden;
}

.profile-bg-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(5deg); }
}

.profile-bg-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(0,0,0,0.1), transparent);
}

.profile-content {
    position: relative;
    z-index: 2;
}

/* Main Avatar */
.profile-main-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(53, 51, 51, 0.3);
    transition: all 0.3s ease;
}

.profile-main-avatar:hover {
    transform: scale(1.05);
    border-color: rgba(48, 47, 47, 0.8);
}

.avatar-ring {
    position: absolute;
    top: -5px;
    left: -5px;
    right: -5px;
    bottom: -5px;
    border: 2px solid rgba(51, 50, 50, 0.3);
    border-radius: 50%;
    border-top-color: rgba(44, 43, 43, 0.8);
    animation: rotate 3s linear infinite;
}

@keyframes rotate {
    to { transform: rotate(360deg); }
}

.status-indicator-large {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 18px;
    height: 18px;
    background: #10b981;
    border: 3px solid rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

/* Profile Details */
.profile-name {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.role-badge {
    background: rgba(7, 7, 7, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    display: inline-flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.last-seen {
    display: flex;
    align-items: center;
    opacity: 0.9;
}

/* Dropdown Items */
.dropdown-body {
    padding: 1rem 0;
}

.dropdown-item-enhanced {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.5rem;
    color: inherit;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
}

.dropdown-item-enhanced::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 0;
    background: linear-gradient(135deg, #949496ff 0%, #2a2a2bff 100%);
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-item-enhanced:hover {
    background: linear-gradient(90deg, rgba(103, 126, 234, 0.1) 0%, transparent 100%);
    color: #667eea;
    transform: translateX(5px);
}

.dropdown-item-enhanced:hover::before {
    width: 4px;
}

.item-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.dropdown-item-enhanced:hover .item-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 25px rgba(51, 50, 51, 0.4);
}

.item-content {
    flex: 1;
    text-align: left;
}

.item-title {
    display: block;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.125rem;
}

.item-desc {
    display: block;
    font-size: 0.8rem;
    opacity: 0.7;
    font-weight: 400;
}

/* Logout Button */
.logout-btn {
    margin-top: 0.5rem;
    border-top: 1px solid rgba(0,0,0,0.1);
    padding-top: 1.25rem;
}

.logout-btn:hover {
    color: #dc3545;
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1) 0%, transparent 100%);
}

.logout-btn:hover::before {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.logout-icon {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.logout-btn:hover .logout-icon {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
}

/* Divider */
.dropdown-divider {
    height: 1px;
    margin: 0.5rem 1.5rem;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
    border: none;
}

/* Responsive */
@media (max-width: 768px) {
    .enhanced-dropdown {
        min-width: 280px;
    }
    
    .profile-header-enhanced {
        padding: 1.5rem 1rem;
    }
    
    .profile-main-avatar {
        width: 60px;
        height: 60px;
    }
}
</style>
<audio id="logoutSound" src="assets/sounds/logout.mp3" preload="auto"></audio>
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn");

    if (logoutBtn) {
        logoutBtn.addEventListener("click", function (e) {
            e.preventDefault();

            Swal.fire({
                title: "Are you sure?",
                html: `
                    <div style="padding: 10px;">
                        <lottie-player
                            src="https://assets4.lottiefiles.com/packages/lf20_5tkzkblw.json"
                            background="transparent"
                            speed="1"
                            style="width:140px;height:140px;margin:auto;"
                            autoplay>
                        </lottie-player>
                        <p style="
                            font-size: 16px;
                            color: #666;
                            margin: 15px 0 0 0;
                            line-height: 1.5;
                        ">
                            You will be logged out of your session.
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-out-alt"></i> Yes, Logout',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d",
                reverseButtons: true,
                backdrop: `
                    rgba(220, 53, 69, 0.1)
                    left top
                    no-repeat
                `,
                customClass: {
                    popup: 'logout-popup',
                    title: 'logout-title',
                    confirmButton: 'logout-confirm-btn',
                    cancelButton: 'logout-cancel-btn'
                },
                showClass: {
                    popup: 'animate__animated animate__zoomIn animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp animate__faster'
                },
                didOpen: () => {
                    const sound = document.getElementById("logoutSound");
                    if (sound) sound.play();
                    
                    // Add custom styles
                    const style = document.createElement('style');
                    style.textContent = `
                        .logout-popup {
                            border-radius: 20px !important;
                            box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
                            padding: 20px !important;
                        }
                        
                        .logout-title {
                            font-size: 26px !important;
                            font-weight: 700 !important;
                            color: #333 !important;
                        }
                        
                        .logout-confirm-btn,
                        .logout-cancel-btn {
                            border-radius: 25px !important;
                            padding: 12px 30px !important;
                            font-weight: 600 !important;
                            font-size: 15px !important;
                            transition: all 0.3s ease !important;
                            min-width: 120px !important;
                        }
                        
                        .logout-confirm-btn {
                            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3) !important;
                        }
                        
                        .logout-confirm-btn:hover {
                            transform: translateY(-2px) !important;
                            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4) !important;
                        }
                        
                        .logout-cancel-btn {
                            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.2) !important;
                        }
                        
                        .logout-cancel-btn:hover {
                            transform: translateY(-2px) !important;
                            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3) !important;
                        }
                    `;
                    document.head.appendChild(style);
                }

            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Logging out...",
                        html: `
                            <div style="padding: 20px;">
                                <lottie-player
                                    src="https://assets3.lottiefiles.com/packages/lf20_usmfx6bp.json"
                                    background="transparent"
                                    speed="1"
                                    style="width:120px;height:120px;margin:auto;"
                                    autoplay loop>
                                </lottie-player>
                                <p style="
                                    font-size: 15px;
                                    color: #888;
                                    margin: 15px 0 0 0;
                                ">
                                    Please wait...
                                </p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        backdrop: `
                            rgba(0, 0, 0, 0.4)
                            left top
                            no-repeat
                        `,
                        customClass: {
                            popup: 'logout-loading-popup'
                        },
                        didOpen: () => {
                            const style = document.createElement('style');
                            style.textContent = `
                                .logout-loading-popup {
                                    border-radius: 20px !important;
                                    box-shadow: 0 20px 60px rgba(0,0,0,0.2) !important;
                                }
                            `;
                            document.head.appendChild(style);
                        }
                    });

                    setTimeout(() => {
                        window.location.href = "logout.php";
                    }, 1500);
                }
            });
        });
    }
});
</script>
