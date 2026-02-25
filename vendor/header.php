<?php
include 'connection.php';

$user_image = 'assets/images/logo-dark.svg'; 
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
       
        if (!empty($row['image']) && file_exists($row['image'])) {
            $user_image = $row['image'];
        } elseif (!empty($row['image']) && file_exists('uploads/' . $row['image'])) {
            $user_image = 'uploads/' . $row['image'];
        } else {
            $user_image = 'assets/images/default-user.png';
        }
    } else {
        $user_image = 'assets/images/default-user.png';
        $role = '';
    }

    $employee_id = null;
    // Get employee_id first
    $emp_query = "SELECT employee_id FROM employeelogins WHERE login_id = ? LIMIT 1";
    $emp_stmt = $conn->prepare($emp_query);
    $emp_stmt->bind_param("i", $login_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $employee_id = ($emp_row = $emp_result->fetch_assoc()) ? $emp_row['employee_id'] : null;
    $emp_stmt->close();

    if ($employee_id) {
        // Get user role
        $role_query = "SELECT role FROM employeelogins WHERE employee_id = ? LIMIT 1";
        $role_stmt = $conn->prepare($role_query);
        $role_stmt->bind_param("i", $employee_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $user_role = ($role_row = $role_result->fetch_assoc()) ? $role_row['role'] : null;
        $role_stmt->close();

        // Get notifications based on role
        if ($user_role == 'manager') {
            $notifications_sql = "
                SELECT 
                    en.*,
                    e.first_name,
                    e.last_name,
                    d.department_name,
                    CASE 
                        WHEN en.created_at > NOW() - INTERVAL 1 HOUR THEN 'Just now'
                        WHEN en.created_at > NOW() - INTERVAL 24 HOUR THEN 'Today'
                        ELSE DATE_FORMAT(en.created_at, '%b %d, %Y')
                    END as display_date
                FROM employee_notifications en
                LEFT JOIN employees e ON en.employee_id = e.employee_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                WHERE e.department_id = (
                    SELECT department_id FROM employees WHERE employee_id = ?
                )
                ORDER BY en.created_at DESC LIMIT 5";
            
            if ($notif_stmt = $conn->prepare($notifications_sql)) {
                $notif_stmt->bind_param("i", $employee_id);
                $notif_stmt->execute();
                $result = $notif_stmt->get_result();
                
                if ($result) {
                    while ($notif = $result->fetch_assoc()) {
                        $notifications[] = $notif;
                        if (!$notif['is_read']) {
                            $unread_count++;
                        }
                    }
                }
                $notif_stmt->close();
            }
        } else if ($user_role == 'hr' || $user_role == 'admin') {
            // Show all notifications for HR and admin
            $notifications_sql = "
                SELECT 
                    en.*,
                    e.first_name,
                    e.last_name,
                    d.department_name,
                    CASE 
                        WHEN en.created_at > NOW() - INTERVAL 1 HOUR THEN 'Just now'
                        WHEN en.created_at > NOW() - INTERVAL 24 HOUR THEN 'Today'
                        ELSE DATE_FORMAT(en.created_at, '%b %d, %Y')
                    END as display_date
                FROM employee_notifications en
                LEFT JOIN employees e ON en.employee_id = e.employee_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                ORDER BY en.created_at DESC LIMIT 5";
            
            if ($notif_stmt = $conn->prepare($notifications_sql)) {
                $notif_stmt->execute();
                $result = $notif_stmt->get_result();
                
                if ($result) {
                    while ($notif = $result->fetch_assoc()) {
                        $notifications[] = $notif;
                        if (!$notif['is_read']) {
                            $unread_count++;
                        }
                    }
                }
                $notif_stmt->close();
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

        <!-- THEME TOGGLE DROPDOWN -->
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" title="Change Theme">
            <i id="themeIcon" class="fas <?= $current_theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
          </a>
          <div class="dropdown-menu dropdown-theme dropdown-menu-end pc-h-dropdown">
            <a class="dropdown-item" href="#" data-theme="light">
              <i class="fas fa-sun text-warning"></i>
              <span>Light Theme</span>
            </a>
            <a class="dropdown-item" href="#" data-theme="dark">
              <i class="fas fa-moon text-info"></i>
              <span>Dark Theme</span>
            </a>
            <a class="dropdown-item" href="#" data-theme="colored">
              <i class="fas fa-palette text-success"></i>
              <span>Colored Theme</span>
            </a>
          </div>
        </li>

        <!-- NOTIFICATIONS -->
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#">
            <i class="ti ti-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="badge bg-success pc-h-badge"><?= $unread_count ?></span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
            <div class="px-3 py-2 d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Notifications</h6>
              <?php if ($unread_count > 0): ?>
                  <a href="#" class="text-muted small" id="markAllRead">Mark all read</a>
              <?php endif; ?>
            </div>
            <div class="dropdown-divider"></div>
            <div class="notifications-list px-2">
              <?php if (count($notifications) > 0): ?>
                  <?php foreach ($notifications as $notif): ?>
                      <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" 
                           data-notification-id="<?= $notif['id'] ?>">
                          <div class="d-flex align-items-start">
                              <div class="flex-shrink-0">
                                  <i class="fas fa-bell <?= !$notif['is_read'] ? 'text-primary' : 'text-muted' ?>"></i>
                              </div>
                              <div class="flex-grow-1 ms-3">
                                  <p class="mb-1 small"><?= htmlspecialchars($notif['message']) ?></p>
                                  <div class="d-flex justify-content-between align-items-center">
                                      <small class="text-muted">
                                          <?php if ($notif['department_name']): ?>
                                              <i class="fas fa-building me-1"></i>
                                              <?= htmlspecialchars($notif['department_name']) ?> •
                                          <?php endif; ?>
                                          <?= $notif['display_date'] ?>
                                      </small>
                                      <?php if (!$notif['is_read']): ?>
                                          <span class="badge bg-primary rounded-pill">New</span>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              <?php else: ?>
                  <div class="text-center py-3 text-muted">
                      <i class="fas fa-bell-slash mb-2"></i>
                      <p class="small mb-0">No notifications</p>
                  </div>
              <?php endif; ?>
            </div>
            <?php if (count($notifications) > 0): ?>
                <div class="dropdown-divider"></div>
                <div class="px-3 py-2 text-center">
                    <a href="view_all_notifications.php" class="small">View all notifications</a>
                </div>
            <?php endif; ?>
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

<!-- DARK/LIGHT MODE STYLES -->
<style>
/* ========== WEATHER AND DATETIME WIDGETS ========== */
.weather-widget, .datetime-widget {
  padding: 8px 12px;
  border-radius: 8px;
  background-color: rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.weather-widget:hover, .datetime-widget:hover {
  background-color: rgba(0, 0, 0, 0.1);
}

.weather-info, .datetime-widget {
  min-width: 120px;
}

#currentTime {
  font-size: 14px;
  line-height: 1.2;
}

#currentDate {
  font-size: 11px;
  line-height: 1.2;
}

.weather-location {
  font-size: 10px;
  line-height: 1.2;
}

/* Responsive adjustments */
@media (max-width: 1199px) {
  .weather-widget {
    display: none !important;
  }
}

@media (max-width: 767px) {
  .datetime-widget {
    display: none !important;
  }
}

/* ========== DARK MODE ========== */
body.dark-mode {
  background-color: #121212 !important;
  color: #ffffff !important;
}
  
body.dark-mode *,
body.dark-mode p, body.dark-mode h1, body.dark-mode h2,
body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
body.dark-mode span, body.dark-mode label, body.dark-mode a,
body.dark-mode .text-muted, body.dark-mode .form-control,
body.dark-mode .dropdown-menu, body.dark-mode .list-group-item,
body.dark-mode .modal-title, body.dark-mode .card-title,
body.dark-mode .table th, body.dark-mode .table td,
body.dark-mode .pagination a, body.dark-mode .pagination li,
body.dark-mode .page-link, body.dark-mode small, body.dark-mode i,
body.dark-mode svg, body.dark-mode [data-feather] {
  color: #ffffff !important;
  fill: #ffffff !important;
  stroke: #ffffff !important;
}

body.dark-mode .dropdown-menu, body.dark-mode .modal-content,
body.dark-mode .list-group-item, body.dark-mode .pc-header,
body.dark-mode .card, body.dark-mode .pc-sidebar,
body.dark-mode .form-control, body.dark-mode input,
body.dark-mode textarea, body.dark-mode select,
body.dark-mode .pagination .page-link {
  background-color: #1e1e1e !important;
  border-color: #444444 !important;
}

body.dark-mode .weather-widget, body.dark-mode .datetime-widget {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border-color: rgba(255, 255, 255, 0.2) !important;
}

body.dark-mode .weather-widget:hover, body.dark-mode .datetime-widget:hover {
  background-color: rgba(255, 255, 255, 0.15) !important;
}

body.dark-mode .form-control::placeholder {
  color: #cccccc !important;
}

body.dark-mode .pagination .page-item.active .page-link {
  background-color: #007bff !important;
  border-color: #007bff !important;
  color: #ffffff !important;
}

body.dark-mode .card-header, body.dark-mode .card-header h5,
body.dark-mode .card-header small, body.dark-mode .card-header .text-muted {
  background-color: #1e1e1e !important;
  color: #ffffff !important;
  border-color: #444444 !important;
}

/* ========== COLORED MODE ========== */
body.colored-mode {
  background-color: #f0f4ff !important;
  color: #1a237e !important;
}

body.colored-mode *,
body.colored-mode p, body.colored-mode h1, body.colored-mode h2,
body.colored-mode h3, body.colored-mode h4, body.colored-mode h5, body.colored-mode h6,
body.colored-mode span, body.colored-mode label, body.colored-mode a,
body.colored-mode .text-muted, body.colored-mode .form-control,
body.colored-mode .dropdown-menu, body.colored-mode .list-group-item,
body.colored-mode .modal-title, body.colored-mode .card-title,
body.colored-mode .table th, body.colored-mode .table td,
body.colored-mode .pagination a, body.colored-mode .pagination li,
body.colored-mode .page-link, body.colored-mode small, body.colored-mode i,
body.colored-mode svg, body.colored-mode [data-feather] {
  color: #1a237e !important;
  fill: #1a237e !important;
  stroke: #1a237e !important;
}

body.colored-mode .dropdown-menu, body.colored-mode .modal-content,
body.colored-mode .list-group-item, body.colored-mode .pc-header,
body.colored-mode .card, body.colored-mode .pc-sidebar,
body.colored-mode .form-control, body.colored-mode input,
body.colored-mode textarea, body.colored-mode select,
body.colored-mode .pagination .page-link {
  background-color: #e3f2fd !important;
  border-color: #90caf9 !important;
}

body.colored-mode .weather-widget, body.colored-mode .datetime-widget {
  background-color: rgba(26, 35, 126, 0.1) !important;
  border-color: rgba(26, 35, 126, 0.2) !important;
}

body.colored-mode .weather-widget:hover, body.colored-mode .datetime-widget:hover {
  background-color: rgba(26, 35, 126, 0.15) !important;
}

body.colored-mode .form-control::placeholder {
  color: #5c6bc0 !important;
}

body.colored-mode .pagination .page-item.active .page-link {
  background-color: #3949ab !important;
  border-color: #3949ab !important;
  color: #ffffff !important;
}

body.colored-mode .card-header, body.colored-mode .card-header h5,
body.colored-mode .card-header small, body.colored-mode .card-header .text-muted {
  background-color: #e8eaf6 !important;
  color: #1a237e !important;
  border-color: #c5cae9 !important;
}

/* Dropdown Styling */
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

/* Theme Dropdown */
.dropdown-theme .dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
}

.dropdown-theme .dropdown-item i {
    width: 20px;
    text-align: center;
    margin-right: 10px;
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

/* Dark Mode Adjustments */
body.dark-mode .dropdown-menu {
    background-color: #2d2d2d;
    border-color: #444;
}

body.dark-mode .dropdown-item:hover {
    background-color: rgba(255,255,255,0.1);
}

body.dark-mode .notification-item {
    background: rgba(255,255,255,0.05);
}

body.dark-mode .notification-item:hover {
    background: rgba(255,255,255,0.1);
}

body.dark-mode .notification-item.unread {
    background: rgba(13, 110, 253, 0.15);
}
</style>

<!-- Make sure Bootstrap JS is loaded BEFORE this script block -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

    fetchWeather();
    setInterval(fetchWeather, 600000); // 10 minutes
});
</script>

<!-- SweetAlert & Theme Toggle Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn"); 
    const themeIcon = document.getElementById("themeIcon"); 
    const body = document.body;

    
    function applyTheme(theme) {
        body.classList.remove('dark-mode', 'colored-mode');
        if (theme === 'dark') {
            body.classList.add('dark-mode');
            if (themeIcon) themeIcon.className = 'fas fa-sun';
        } else if (theme === 'colored') {
            body.classList.add('colored-mode');
            if (themeIcon) themeIcon.className = 'fas fa-palette';
        } else {
            if (themeIcon) themeIcon.className = 'fas fa-moon';
        }
        localStorage.setItem('theme', theme);
    }

    // Load saved theme or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    document.querySelectorAll('[data-theme]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const selectedTheme = this.getAttribute('data-theme');
            applyTheme(selectedTheme);
        });
    });

    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent immediate redirect
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
                    window.location.href = "logout.php"; // Redirect on confirm
                }
            });
        });
    }
});
</script>