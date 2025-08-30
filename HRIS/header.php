<?php
include 'connection.php';

$user_image    = 'logo.png'; // fallback image
$user_name     = 'Guest';
$role          = 'guest';    // ✅ Default role to avoid undefined variable
$notifications = [];
$unread_count  = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $user_id = (int) $_SESSION['user_id'];
    $role    = $_SESSION['role'];
    $query   = null; // ✅ Initialize query for user details

    // ✅ Fetch user details
    if ($role === 'admin') {
        $query = mysqli_query($conn, "SELECT username, image FROM users WHERE user_id = $user_id LIMIT 1");
    } elseif (in_array($role, ['employee', 'manager'])) {
        $query = mysqli_query($conn, "SELECT username, image FROM employeelogins WHERE employee_id = $user_id LIMIT 1");
    }

    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $user_name = $row['username'];
        if (!empty($row['image']) && file_exists('uploads/' . $row['image'])) {
            $user_image = 'uploads/' . $row['image'];
        }
    }

    // ✅ Get unread notifications count (for badge)
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as unread_total FROM notifications WHERE user_id = $user_id AND is_read = 0");
    if ($count_query) {
        $count_row = mysqli_fetch_assoc($count_query);
        $unread_count = $count_row['unread_total'] ?? 0;
    }

    // ✅ Get latest 5 notifications (for dropdown)
    $notif_query = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
    if ($notif_query) {
        while ($notif = mysqli_fetch_assoc($notif_query)) {
            $notifications[] = $notif;
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
        
          <!-- THEME TOGGLE DROPDOWN -->
      <li class="dropdown pc-h-item">
        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" title="Change Theme">
          <i class="fas fa-palette"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
          <a class="dropdown-item" href="#" data-theme="light">
            <i class="fas fa-sun text-warning me-2"></i> Light Mode
          </a>
          <a class="dropdown-item" href="#" data-theme="dark">
            <i class="fas fa-moon text-secondary me-2"></i> Dark Mode
          </a>
          <a class="dropdown-item" href="#" data-theme="colored">
            <i class="fas fa-brush text-primary me-2"></i> Colored Mode
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
            <div class="dropdown-header d-flex justify-content-between">
              <h5 class="m-0">Notifications</h5>
              <a href="mark_all_read.php" class="pc-head-link bg-transparent" title="Mark all as read">
                <i class="ti ti-circle-check text-success"></i>
              </a>
            </div>
            <div class="dropdown-divider"></div>
            <div class="dropdown-header px-0 header-notification-scroll" style="max-height: 300px;">
              <div class="list-group list-group-flush">
                <?php if (count($notifications) > 0): ?>
                  <?php foreach ($notifications as $notif): ?>
                    <a  class="list-group-item list-group-item-action <?= $notif['is_read'] ? '' : 'bg-light' ?>">
                      <div class="d-flex">
                        <div class="flex-shrink-0">
                          <div class="user-avtar bg-light-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="fa fa-envelope text-success"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-2">
                          <p class="mb-1"><?= htmlspecialchars($notif['message']) ?></p>
                          <span class="text-muted small"><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></span>
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
            <div class="text-center py-2"><a href="notifications.php" class="link-primary">View all</a></div>
          </div>
        </li>

        <!-- PROFILE / LOGOUT -->
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link dropdown-toggle arrow-none me-0 d-flex align-items-center" data-bs-toggle="dropdown" href="#">
            <img src="<?= $user_image ?>" alt="user-image" class="user-avtar rounded-circle" width="32" height="32">
            <span><?= htmlspecialchars($user_name) ?></span>
            <i class="ti ti-chevron-down"></i>
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
            <div class="dropdown-header">
              <div class="d-flex mb-1">
                <div class="flex-shrink-0">
                  <img src="<?= $user_image ?>" alt="user-image" class="user-avtar wid-35">
                </div>
                <div class="flex-grow-1 ms-3">
                  <h6 class="mb-1"><?= htmlspecialchars($user_name) ?></h6>
                  <span><?= ucfirst($role) ?></span>
                </div>
              </div>
            </div>
            <hr>
            <div class="tab-content">
              <div class="tab-pane fade show active">
              <a href="#" class="dropdown-item" id="logoutBtn">
  <i class="ti ti-power"></i>
  <span>Logout</span>
</a>

              </div>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</header><!-- DARK/LIGHT MODE STYLES -->

<style>
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
</style>
<!-- SweetAlert & Theme Toggle Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn"); // Target logout button
    const themeIcon = document.getElementById("themeIcon"); // Optional icon toggle
    const body = document.body;

    // ========== Apply Selected Theme ==========
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

    // ========== Theme Buttons ==========
    document.querySelectorAll('[data-theme]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const selectedTheme = this.getAttribute('data-theme');
            applyTheme(selectedTheme);
        });
    });

    // ========== Logout Confirmation ==========
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

