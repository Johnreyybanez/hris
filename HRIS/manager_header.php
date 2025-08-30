<?php
include 'connection.php';

$user_image = 'assets/images/admin/img-add-user.png'; // fallback image
$user_name = 'Guest';
$notifications = [];
$unread_count = 0;

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
        $emp_stmt = mysqli_prepare($conn, "SELECT e.employee_id, e.department, el.role 
            FROM employeelogins el 
            JOIN employees e ON el.employee_id = e.employee_id 
            WHERE el.login_id = ? LIMIT 1");
        mysqli_stmt_bind_param($emp_stmt, "i", $login_id);
        mysqli_stmt_execute($emp_stmt);
        $emp_result = mysqli_stmt_get_result($emp_stmt);
        $emp_data = mysqli_fetch_assoc($emp_result);
        
        if ($emp_data) {
            $employee_id = $emp_data['employee_id'];
            $department = $emp_data['department'];
            $user_role = $emp_data['role'];

            // Fetch notifications based on role
            if ($user_role == 'admin' || $user_role == 'hr') {
                // For admin/HR, show all notifications
                $notif_query = "SELECT en.*, e.first_name, e.last_name, e.department 
                               FROM employee_notifications en
                               LEFT JOIN employees e ON en.employee_id = e.employee_id 
                               ORDER BY en.created_at DESC LIMIT 10";
                $stmt = mysqli_prepare($conn, $notif_query);
            } else {
                // For regular users, show department and personal notifications
                $notif_query = "SELECT en.*, e.first_name, e.last_name, e.department 
                               FROM employee_notifications en
                               JOIN employees e ON en.employee_id = e.employee_id 
                               WHERE en.employee_id = ? OR e.department = ?
                               ORDER BY en.created_at DESC LIMIT 10";
                $stmt = mysqli_prepare($conn, $notif_query);
                mysqli_stmt_bind_param($stmt, "is", $employee_id, $department);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
            
            // Get unread count
            $unread_query = $user_role == 'admin' || $user_role == 'hr' 
                ? "SELECT COUNT(*) as count FROM employee_notifications WHERE is_read = 0"
                : "SELECT COUNT(*) as count FROM employee_notifications en 
                   JOIN employees e ON en.employee_id = e.employee_id 
                   WHERE (en.employee_id = ? OR e.department = ?) AND en.is_read = 0";
            
            $unread_stmt = mysqli_prepare($conn, $unread_query);
            if ($user_role != 'admin' && $user_role != 'hr') {
                mysqli_stmt_bind_param($unread_stmt, "is", $employee_id, $department);
            }
            mysqli_stmt_execute($unread_stmt);
            $unread_result = mysqli_stmt_get_result($unread_stmt);
            $unread_count = mysqli_fetch_assoc($unread_result)['count'];
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
        <li class="pc-h-item d-none d-md-inline-flex">
          <form class="header-search">
            <i data-feather="search" class="icon-search"></i>
            <input type="search" class="form-control" placeholder="Search here. . .">
          </form>
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
              <?php if ($unread_count > 0): ?>
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
                          (<?= htmlspecialchars($notif['department']) ?>)
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
            <span class="d-inline-block rounded-circle overflow-hidden border border-2 border-primary" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
              <img src="<?= $user_image ?>" alt="user-image" class="user-avtar rounded-circle" width="36" height="36" style="object-fit:cover;width:100%;height:100%;display:block;">
            </span>
            <span class="ms-2 fw-semibold"><?= htmlspecialchars($user_name) ?></span>
            <i class="ti ti-chevron-down ms-1"></i>
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
            <div class="profile-header">
                <div class="profile-img-wrapper">
                    <img src="<?= $user_image ?>" alt="Profile" class="profile-img">
                </div>
                <h6 class="mb-1"><?= htmlspecialchars($user_name) ?></h6>
                <span class="text-muted small"><?= ucfirst($role) ?></span>
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
}

.notification-item:hover {
    background: rgba(0,0,0,0.05);
}

.notification-item.unread {
    background: rgba(13, 110, 253, 0.05);
}

/* Profile Dropdown */
.profile-header {
    padding: 1rem;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.profile-img-wrapper {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.5rem;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid #007bff;
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
</style>

<!-- Make sure Bootstrap JS is loaded BEFORE this script block -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

<script>
document.addEventListener("DOMContentLoaded", function () {
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
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
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
                        title: 'Success',
                        text: 'All notifications marked as read',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.style.pointerEvents = 'auto';
                btn.innerHTML = '<i class="fas fa-check-double text-success"></i>';
            });
        });
    }
});
</script>