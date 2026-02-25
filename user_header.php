<?php

include 'connection.php';
$user_image = 'assets/images/logo-dark.svg';
$user_name = 'Guest';
$notifications = [];
$unread_count = 0;
$profiles = [];
$role = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if ($role === 'admin') {
    $query = mysqli_query($conn, "SELECT username, image FROM users WHERE user_id = $user_id LIMIT 1");
    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $user_name = $row['username'];
        if (!empty($row['image']) && file_exists($row['image'])) {
            $user_image = $row['image'];
        }
    }
} elseif ($role === 'employee') {
    // Get username
    $query = mysqli_query($conn, "SELECT username FROM employeelogins WHERE employee_id = $user_id LIMIT 1");
    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $user_name = $row['username'];
    }

    // Get photo
    $stmt = $conn->prepare("SELECT photo_path FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profiles = $result->fetch_assoc();
    $stmt->close();

    $photo_relative = !empty($profiles['photo_path']) ? $profiles['photo_path'] : '';
    $photo_web_path = '../' . ltrim($photo_relative, '/');
    $photo_file_path = realpath(__DIR__ . '/../' . $photo_relative);

    $user_image = (!empty($photo_relative) && $photo_file_path && file_exists($photo_file_path))
        ? $photo_web_path
        : 'assets/images/default-user.jpg';

    // ✅ Fetch training and notifications using employee_notifications table
    $trainings_q = mysqli_query($conn, "
        SELECT training_id, training_title, start_date 
        FROM employeetrainings 
        WHERE employee_id = $user_id AND start_date >= CURDATE()
        ORDER BY start_date ASC 
        LIMIT 5
    ");

    while ($row = mysqli_fetch_assoc($trainings_q)) {
        $training_id = $row['training_id'];
        $training_link = "userupcoming_training.php?training_id=" . $training_id;

        // Look for corresponding notification in employee_notifications table
        $notif_q = mysqli_query($conn, "
            SELECT is_read 
            FROM employee_notifications 
            WHERE employee_id = $user_id 
              AND link = '$training_link' 
            LIMIT 1
        ");
        $notif = mysqli_fetch_assoc($notif_q);
        $is_read = $notif['is_read'] ?? 0;

       $notifications[] = [
    'notification_id' => $training_link,
    'message' => "Upcoming Training: {$row['training_title']} on " . date('M d', strtotime($row['start_date'])),
    'created_at' => $row['start_date'],
    'link' => $training_link,  // ✅ ADD THIS LINE
    'is_read' => $is_read
];


        if ($is_read == 0) $unread_count++;
    }

    // Sort and limit
    usort($notifications, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    $notifications = array_slice($notifications, 0, 5);

   

// ✅ 2. Approved OB Notifications
    $ob_request = mysqli_query($conn, "
        SELECT ob_id, date, approved_at 
        FROM employeeofficialbusiness 
        WHERE employee_id = $user_id AND status = 'Approved' 
        ORDER BY approved_at DESC 
        LIMIT 5
    ");

    while ($ob = mysqli_fetch_assoc($ob_request)) {
        $ob_id = $ob['ob_id'];
        $ob_link = "user_ob_request.php?ob_id=$ob_id";

        $notif_ob_q = mysqli_query($conn, "
            SELECT is_read FROM employee_notifications 
            WHERE employee_id = $user_id AND link = '$ob_link' 
            LIMIT 1
        ");
        $notif_ob = mysqli_fetch_assoc($notif_ob_q);
        $is_read = $notif_ob['is_read'] ?? 0;

        if (!$notif_ob) {
            $message = "Your Official Business request (OB #$ob_id) was approved.";
            mysqli_query($conn, "
                INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at)
                VALUES ($user_id, '$message', '$ob_link', 0, NOW())
            ");
            $is_read = 0;
        }

        $notifications[] = [
            'notification_id' => $ob_id,
            'message' => "OB Approved: #" . $ob_id . " for " . date('M d', strtotime($ob['date'])),
            'created_at' => $ob['approved_at'],
            'link' => $ob_link,
            'is_read' => $is_read
        ];

        if ($is_read == 0) $unread_count++;
    }

    // ✅ Sort by latest created_at
    usort($notifications, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    // ✅ 3. Approved Missing Log Requests
    $ml_request = mysqli_query($conn, "
        SELECT request_id, date, approved_at 
        FROM missingtimelogrequests 
        WHERE employee_id = $user_id AND status = 'Approved' 
        ORDER BY approved_at DESC 
        LIMIT 5
    ");

    while ($ml = mysqli_fetch_assoc($ml_request)) {
        $request_id = $ml['request_id'];
        $ml_link = "user_missing_log.php?request_id=$request_id";

        $notif_ml_q = mysqli_query($conn, "
            SELECT is_read FROM employee_notifications 
            WHERE employee_id = $user_id AND link = '$ml_link' 
            LIMIT 1
        ");
        $notif_ml = mysqli_fetch_assoc($notif_ml_q);
        $is_read = $notif_ml['is_read'] ?? 0;

        // Insert notification if not already there
        if (!$notif_ml) {
            $message = "Your Missing Log Request (#$request_id) was approved.";
            mysqli_query($conn, "
                INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at)
                VALUES ($user_id, '$message', '$ml_link', 0, NOW())
            ");
            $is_read = 0;
        }

        $notifications[] = [
            'notification_id' => $request_id,
            'message' => "Missing Log Approved: #" . $request_id . " for " . date('M d', strtotime($ml['date'])),
            'created_at' => $ml['approved_at'],
            'link' => $ml_link,
            'is_read' => $is_read
        ];

        if ($is_read == 0) $unread_count++;
    }
    // ✅ 4. Approved Leave Requests
    $leave_request = mysqli_query($conn, "
        SELECT leave_request_id, start_date, end_date, approved_at, leave_type_id 
        FROM employeeleaverequests 
        WHERE employee_id = $user_id AND status = 'Approved'
        ORDER BY approved_at DESC 
        LIMIT 5
    ");

    while ($lv = mysqli_fetch_assoc($leave_request)) {
        $leave_id = $lv['leave_request_id'];
        $leave_link = "user_leave_request.php?leave_request_id=$leave_id";

        // Check if notification already exists
        $notif_lv_q = mysqli_query($conn, "
            SELECT is_read FROM employee_notifications 
            WHERE employee_id = $user_id AND link = '$leave_link' 
            LIMIT 1
        ");
        $notif_lv = mysqli_fetch_assoc($notif_lv_q);
        $is_read = $notif_lv['is_read'] ?? 0;

        if (!$notif_lv) {
            $msg = "Your Leave Request (#$leave_id) from " . date('M d', strtotime($lv['start_date'])) . " to " . date('M d', strtotime($lv['end_date'])) . " was approved.";
            mysqli_query($conn, "
                INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at)
                VALUES ($user_id, '$msg', '$leave_link', 0, NOW())
            ");
            $is_read = 0;
        }

        $notifications[] = [
            'notification_id' => $leave_id,
            'message' => "Leave Approved: " . date('M d', strtotime($lv['start_date'])) . " to " . date('M d', strtotime($lv['end_date'])),
            'created_at' => $lv['approved_at'],
            'link' => $leave_link,
            'is_read' => $is_read
        ];

        if ($is_read == 0) $unread_count++;
    }
// ✅ 5. Approved Overtime Requests
$overtime_qe = mysqli_query($conn, "
    SELECT overtime_id, date, approval_status, updated_at
    FROM overtime 
    WHERE employee_id = $user_id AND approval_status = 'Approved' 
    ORDER BY updated_at DESC 
    LIMIT 5
");

while ($ot = mysqli_fetch_assoc($overtime_qe)) {
    $ot_id = $ot['overtime_id'];
    $ot_link = "user_overtime.php?overtime_id=$ot_id";

    $notif_ot_q = mysqli_query($conn, "
        SELECT is_read 
        FROM employee_notifications 
        WHERE employee_id = $user_id AND link = '$ot_link' 
        LIMIT 1
    ");
    $notif_ot = mysqli_fetch_assoc($notif_ot_q);
    $is_read = $notif_ot['is_read'] ?? 0;

    // If not yet stored in notifications, insert it
    if (!$notif_ot) {
        $message = "Your Overtime Request (#$ot_id) on " . date('M d', strtotime($ot['date'])) . " was approved.";
        mysqli_query($conn, "
            INSERT INTO employee_notifications (employee_id, message, link, is_read, created_at)
            VALUES ($user_id, '$message', '$ot_link', 0, NOW())
        ");
        $is_read = 0;
    }

    $notifications[] = [
        'notification_id' => $ot_id,
        'message' => "Overtime Approved: #" . $ot_id . " for " . date('M d', strtotime($ot['date'])),
        'created_at' => $ot['updated_at'], // Now defined
        'link' => $ot_link,
        'is_read' => $is_read
    ];

    if ($is_read == 0) $unread_count++;
}


    // ✅ Mark as read if message link is clicked
    if (isset($_GET['training_id'])) {
    $training_id = (int) $_GET['training_id'];
    $link = "userupcoming_training.php?training_id=$training_id"; // ✅ Correct

        $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE employee_id = ? AND link = ?");
        $stmt->bind_param("is", $user_id, $link);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_GET['ob_id'])) {
        $ob_id = (int) $_GET['ob_id'];
        $link = "user_ob_request.php?ob_id=$ob_id";

        $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE employee_id = ? AND link = ?");
        $stmt->bind_param("is", $user_id, $link);
        $stmt->execute();
        $stmt->close();
    }
        if (isset($_GET['request_id'])) {
        $req_id = (int) $_GET['request_id'];
        $link = "user_missing_log.php?request_id=$req_id";

        $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE employee_id = ? AND link = ?");
        $stmt->bind_param("is", $user_id, $link);
        $stmt->execute();
        $stmt->close();
    }
    if (isset($_GET['leave_request_id'])) {
        $leave_id = (int) $_GET['leave_request_id'];
        $link = "user_leave_request.php?leave_request_id=$leave_id";

        $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE employee_id = ? AND link = ?");
        $stmt->bind_param("is", $user_id, $link);
        $stmt->execute();
        $stmt->close();
    }
if (isset($_GET['overtime_id'])) {
    $ot_id = (int) $_GET['overtime_id'];
    $link = "user_overtime.php?overtime_id=$ot_id";

    $stmt = $conn->prepare("UPDATE employee_notifications SET is_read = 1 WHERE employee_id = ? AND link = ?");
    $stmt->bind_param("is", $user_id, $link);
    $stmt->execute();
    $stmt->close();
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
  <form class="header-search position-relative">
    <i data-feather="search" class="icon-search"></i>
    <input type="search" id="globalSearchInput" class="form-control" placeholder="Search here. . .">
    <div id="globalSearchResults" class="dropdown-menu show" style="display: none; max-height: 300px; overflow-y: auto; position: absolute; top: 100%; left: 0; width: 100%; z-index: 999;"></div>
  </form>
</li>

      </ul>
    </div>
       <!-- ================= NOTIFICATIONS HTML ================= -->
<li class="dropdown pc-h-item">
  <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#">
    <i class="ti ti-bell"></i>
    <?php if ($unread_count > 0): ?>
      <span class="badge bg-danger pc-h-badge"><?= $unread_count ?></span>
    <?php endif; ?>
  </a>
  <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown filed-requests-card">
    <div class="dropdown-header d-flex justify-content-between">
      <h5 class="m-0">Notifications</h5>
      <a href="mark_all_read.php" class="pc-head-link bg-transparent" title="Mark all as read">
        <i class="ti ti-circle-check text-success"></i>
      </a>
    </div>
    <div class="dropdown-divider "></div>
    <div class="dropdown-header px-0 header-notification-scroll" style="max-height: 300px;">
      <div id="notificationList" class="list-group list-group-flush">
        <?php if (count($notifications) > 0): ?>
  <?php foreach ($notifications as $notif): ?>
    <a href="<?= htmlspecialchars($notif['link']) ?>" class="list-group-item list-group-item-action <?= $notif['is_read'] ? '' : 'bg-light' ?>">
      <div class="d-flex filed-requests-card">
        <div class="flex-shrink-0 ">
          <div class="user-avtar bg-light-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
            <i class="fa fa-bell text-warning"></i>
          </div>
        </div>
        <div class="flex-grow-1 ms-2 ">
          <p class="mb-1"><?= htmlspecialchars($notif['message']) ?></p>
          <span class="text-muted small"><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></span>
        </div>
      </div>
    </a>
  <?php endforeach; ?>
<?php else: ?>
  <div id="noNotifications" class="text-center p-3 text-muted">
    <div id="no-notif-lottie" style="width:100px; height:100px; margin:0 auto;"></div>
    <p class="mt-2 mb-0">No notifications</p>
  </div>
  
<?php endif; ?>

        
      </div>
    </div>
    <div class="dropdown-divider"></div>
    <div class="text-center py-2"><a href="employee_notifications.php" class="link-primary">View all</a></div>
  </div>
</li>
       <!-- PROFILE / LOGOUT -->
<li class="dropdown pc-h-item header-user-profile">
<a class="pc-head-link dropdown-toggle arrow-none me-0 d-flex align-items-center" 
   data-bs-toggle="dropdown" href="#"
   style="transition: all 0.2s ease;">
  <img src="<?= !empty($profiles['photo_path']) ? htmlspecialchars($profiles['photo_path']) : 'assets/images/default-user.jpg'; ?>"
       class="rounded-circle"
       style="width: 28px; height: 28px; object-fit: cover; transition: all 0.2s ease;">
  
  <span class="ms-2"><?= htmlspecialchars($user_name) ?></span>
  <i class="ti ti-chevron-down ms-1"></i>
</a>

<style>


.pc-head-link:hover span,
.pc-head-link:focus span,
.pc-head-link:active span,
.pc-head-link:hover i,
.pc-head-link:focus i,
.pc-head-link:active i {
    color: black !important;            /* Make text & icon black */
}


</style>



  <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown filed-requests-card">
    
    <!-- Background image added here -->
    <div class="dropdown-header p-3 position-relative text-white" style="border-radius: 0.375rem 0.375rem 0 0; overflow: hidden;">
  <img src="asset/images/bg.jpg" 
       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;" 
       alt="Background">
      
      

      <div class="d-flex mb-1 position-relative ">
        <div class="flex-shrink-0 ">
          <img src="<?= !empty($profiles['photo_path']) ? htmlspecialchars($profiles['photo_path']) : 'assets/images/default-user.jpg'; ?>"
               class="rounded-circle mb-2"
               style="width: 60px; height: 60px; object-fit: cover; border: 2px solid white;">
        </div>
        <div class="flex-grow-1 ms-3">
          <h6 class="mb-1 text-white" style="text-shadow: 1px 1px 2px black;">
            <?= htmlspecialchars($user_name) ?>
          </h6>
          <span style="color: #f8f9fa; text-shadow: 1px 1px 2px black;"><?= ucfirst($role) ?></span>

        </div>
      </div>
    </div>

    <hr>
          

<div class="tab-content"> 
    <div class="tab-pane fade show active">
        
        <!-- Dark Mode Toggle -->
       <div class="dropdown-item darkmode-switch">
    <span>Dark Mode</span>
    <label class="switch">
        <input type="checkbox" id="darkModeToggle">
        <span class="slider"></span>
    </label>
</div>


        <!-- Logout Button -->
        <a href="#" class="dropdown-item" id="logoutBtn">
            <i class="ti ti-power text-danger"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
<!-- Lottie script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.1/lottie.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const noNotif = document.getElementById('no-notif-lottie');
    if (noNotif) {
        lottie.loadAnimation({
            container: noNotif,
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: 'asset/images/nonotify.json' // <-- your JSON animation file
        });
    }
});
</script>

<script>
// Dark mode toggle functionality
const toggleSwitch = document.getElementById('darkModeToggle');
const body = document.body;

// Load saved mode
if (localStorage.getItem('dark-mode') === 'enabled') {
    body.classList.add('dark-mode');
    toggleSwitch.checked = true;
}

toggleSwitch.addEventListener('change', () => {
    if (toggleSwitch.checked) {
        body.classList.add('dark-mode');
        localStorage.setItem('dark-mode', 'enabled');
    } else {
        body.classList.remove('dark-mode');
        localStorage.setItem('dark-mode', 'disabled');
    }
});

</script>

          </div>
        </li>
      </ul>
    </div>
  </div>
</header>
<!-- SweetAlert & Theme Toggle Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Lottie script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.1/lottie.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn"); 
    const body = document.body; 

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Check if dark mode is active
            const isDark = body.classList.contains('dark-mode');

            Swal.fire({
                html: `
                    <div id="logout-lottie" style="width:120px; height:120px; margin:0 auto;"></div>
                    <h2 class="mt-3">Are you sure?</h2>
                    <p>You will be logged out of your session.</p>
                `,
                showCancelButton: true,
                confirmButtonColor: isDark ? "#3085d6" : "#3085d6",
                cancelButtonColor: isDark ? "#d33" : "#d33",
                confirmButtonText: "Yes, logout",
                cancelButtonText: "Cancel",
                background: isDark ? "#1e1e1e" : "#fff",
                color: isDark ? "#fff" : "#000",
                didOpen: () => {
                    // Load Lottie animation inside the SweetAlert
                    lottie.loadAnimation({
                        container: document.getElementById('logout-lottie'),
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: 'asset/images/signout.json' // <-- path to your JSON animation
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "logout.php";
                }
            });
        });
    }
});
</script>

<!-- Include Lottie -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.1/lottie.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('globalSearchInput');
  const resultBox = document.getElementById('globalSearchResults');
  let searchData = [];

  fetch('user_search_results.php')
    .then(res => res.json())
    .then(data => searchData = data);

  input.addEventListener('input', function () {
    const value = this.value.toLowerCase();
    resultBox.innerHTML = '';

    if (value === '') {
      resultBox.style.display = 'none';
      return;
    }

    const filtered = searchData.filter(item => item.title.toLowerCase().includes(value));
    
    if (filtered.length > 0) {
      filtered.forEach(item => {
        const link = document.createElement('a');
        link.href = item.url;
        link.className = 'dropdown-item';
        link.textContent = item.title;
        resultBox.appendChild(link);
      });
    } else {
      // Container for Lottie animation
      const noResult = document.createElement('div');
      noResult.className = 'dropdown-item text-center';
      
      const lottieContainer = document.createElement('div');
      lottieContainer.style.width = '100px';
      lottieContainer.style.height = '100px';
      lottieContainer.style.margin = '0 auto';
      noResult.appendChild(lottieContainer);

      const text = document.createElement('p');
      text.className = 'mt-2 mb-0 text-muted';
      text.textContent = 'No page found.';
      noResult.appendChild(text);

      resultBox.appendChild(noResult);

      // Load Lottie animation
      lottie.loadAnimation({
        container: lottieContainer,
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'asset/images/nodata.json' // <-- your JSON animation path
      });
    }

    resultBox.style.display = 'block';
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.header-search')) {
      resultBox.style.display = 'none';
    }
  });
});
</script>

 <style>
/* Dark mode toggle switch */
/* Dark mode toggle switch */
.darkmode-switch {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
}

.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 28px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc;
  transition: 0.4s;
  border-radius: 34px;
  font-size: 12px;
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8px;
  font-family: Arial, sans-serif;
  user-select: none;
}

.slider::before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 4px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
  z-index: 2;
}

/* Text for off and on */
.slider::after {
  content: "Off";
  position: absolute;
  right: 8px;
  z-index: 1;
  pointer-events: none;
  user-select: none;
}

/* When checked */
input:checked + .slider {
  background-color: #2196F3;
}

input:checked + .slider::after {
  content: "On";
  left: 8px;
  right: auto;
}

/* Move the circle to the right when checked */
input:checked + .slider::before {
  transform: translateX(32px);
}


/* Dark mode full system styles */
body.dark-mode {
    background-color: #121212;
    color: #e0e0e0;
}

/* All headings, text, and icons in dark mode */
body.dark-mode h1,
body.dark-mode h2,
body.dark-mode h3,
body.dark-mode h4,
body.dark-mode h5,
body.dark-mode h6,
body.dark-mode p,
body.dark-mode i,
body.dark-mode span,
body.dark-mode a {
    color: #ffffff !important;
}

/* General search form-group dark mode */
body.dark-mode .form-group input[type="search"] {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
    border-color: #444 !important;
}
body.dark-mode .form-group input[type="search"]::placeholder {
    color: #bbbbbb !important;
}
body.dark-mode .form-group i {
    color: #ffffff !important;
}

/* Header search dark mode */
body.dark-mode .header-search .form-control {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
    border-color: #444 !important;
}
body.dark-mode .header-search .form-control::placeholder {
    color: #bbbbbb !important;
}
body.dark-mode .header-search .icon-search {
    color: #ffffff !important;
}

/* Dropdown results in dark mode */
body.dark-mode #globalSearchResults {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
    border: 1px solid #444 !important;
}
body.dark-mode #globalSearchResults a {
    color: #ffffff !important;
}
body.dark-mode #globalSearchResults a:hover {
    background-color: #333333 !important;
}
/* ===== Date/Time Card Dark Mode ===== */
body.dark-mode .card.date-time-card {
    background: linear-gradient(to right, #1e1e1e, #2a2a2a) !important;
    border-color: #444 !important;
}
body.dark-mode .card.date-time-card span {
    color: #ffffff !important;
}
/* Cards and panels */
body.dark-mode .navbar-content,
body.dark-mode .card-active,
body.dark-mode .pc-container,
body.dark-mode .panel,
body.dark-mode .modal-content {
    background-color: #1e1e1e !important;
    color: #e0e0e0 !important;
    border-color: #333 !important;
}

/* Tables */
body.dark-mode table {
    background-color: #1e1e1e !important;
   
}

body.dark-mode thead {
    background-color: #2a2a2a !important;
}

body.dark-mode tbody tr {
    background-color: #1e1e1e !important;
    
}
body.dark-mode tbody td {
    background-color: #1e1e1e !important;
}
body.dark-mode .table-responsive {
    background-color: #1e1e1e; /* Dark background */
    color: #f1f1f1; /* Light text */
    
}

/* Form elements */
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background-color: #2a2a2a !important;
    color: #ffffff !important;
    
}

/* Navbar / headers */
body.dark-mode .navbar,
body.dark-mode .pc-header,

body.dark-mode .card-header {
    background-color: #1a1a1a !important;
    border-bottom: 1px solid #333 !important;
}
/* Fix Bootstrap text-dark and text-muted in dark mode */
body.dark-mode .text-dark {
    color: #ffffff !important;
}
body.dark-mode .pc-header  {
    border-color: #444 !important;
}

/* Remove or darken the header bottom border in dark mode */
body.dark-mode header,
body.dark-mode .pc-header {
    border-bottom: 1px solid #333 !important; /* Dark gray line */
    box-shadow: none !important; /* Optional: remove shadow */
}

/* Remove or darken the sidebar right border in dark mode */
body.dark-mode .pc-sidebar {
    border-right: 1px solid #333 !important; /* Dark gray line */
    box-shadow: none !important; /* Optional */
}

body.dark-mode .text-muted {
    color: #ffffff !important;
}
/* Dark mode for "My Filed Requests" widget */
body.dark-mode .filed-requests-card {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
    border: 1px solid #333 !important;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5) !important; /* Dark mode shadow */
}


body.dark-mode .filed-requests-card .card-header {
    background-color: #2a2a2a !important;
    border-bottom: 1px solid #444 !important;
}

body.dark-mode .filed-requests-card h5,
body.dark-mode .filed-requests-card small {
    color: #ffffff !important;
}

body.dark-mode .filed-requests-card .card-body {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
}

body.dark-mode .filed-requests-card table {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
}

body.dark-mode .filed-requests-card thead th {
    background-color: #2a2a2a !important;
    color: #ffffff !important;
}

body.dark-mode .filed-requests-card tbody tr {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
    border-color: #333 !important;
}

body.dark-mode .filed-requests-card .text-muted {
    color: #bbbbbb !important;
}
/* Make table text white in dark mode for My Filed Requests */
body.dark-mode .filed-requests-card tbody td {
    color: #ffffff !important;
}
/* Dropdown container in dark mode */
body.dark-mode .tab-content,
body.dark-mode .tab-pane,
body.dark-mode .dropdown-item {
    background-color: #1e1e1e !important; /* Dark background */
    color: #ffffff !important; /* White text */
}

/* For the Dark Mode label */
body.dark-mode .darkmode-switch span {
    color: #ffffff !important;
}

/* For the slider track in dark mode */
body.dark-mode .darkmode-switch .slider {
    background-color: #444 !important;
}

/* For the circle knob in dark mode */
body.dark-mode .darkmode-switch .slider:before {
    background-color: #fff !important;
}

/* Logout icon and text in dark mode */
body.dark-mode #logoutBtn i
 {
    color: #ffffff !important; /* White icon and text */
}
/* Dark mode modal close button (white color) */
body.dark-mode .modal-header .btn-close {
    filter: brightness(0) invert(1);
     color: #ffffff !important; /* White icon and text */ /* Invert the close button color to white */
}

body.dark-mode #logoutBtn span {
    color: #fe0606ff !important;
}

/* Hover effect in dark mode */
body.dark-mode .dropdown-item:hover {
    background-color: #333 !important;
}
body.dark-mode .dropdown-item:focus {
    background-color: #444 !important;
}
/* ===== Notification Bell Dark Mode ===== */
body.dark-mode .pc-h-item > .pc-head-link {
    background-color: #1e1e1e !important; /* Dark background */
    color: #fff !important; /* White text/icon */
}

body.dark-mode .pc-h-item > .pc-head-link:hover {
    background-color: #333 !important; /* Darker on hover */
    color: #fff !important;
}

/* Keep it dark when dropdown is active/open */
body.dark-mode .pc-h-item.show > .pc-head-link {
    background-color: #333 !important;
    color: #fff !important;
}

/* ===== Dropdown Menu in Dark Mode ===== */
body.dark-mode .dropdown-notification {
    background-color: #1e1e1e !important;
    color: #fff !important;
    border: 1px solid #333 !important;
}

/* Notification items in dark mode */
body.dark-mode .list-group-item {
    background-color: transparent !important;
    color: #fff !important;
    border-bottom: 1px solid #333 !important;
}

body.dark-mode .list-group-item:hover {
    background-color: #333 !important;
}

/* Unread notification highlight */
body.dark-mode .list-group-item.bg-light {
    background-color: #2a2a2a !important;
}
/* Dark mode bell circle + icon */
body.dark-mode .user-avtar {
    background-color: #222 !important; /* dark circle */
}

body.dark-mode .user-avtar i {
    color: #fff !important; /* white bell icon */
}

body.dark-mode  .user-profile-container {
    position: relative;
    z-index: 1;
  }
body.dark-mode  .user-profile-container .card {
    max-width: 900px;
    margin: auto;
    background-color: #1e1e1e;
    color: #eee;
    box-shadow: 0 0 10px rgba(0,0,0,0.7);
    border: none;
  }
body.dark-mode  .user-profile-container .col-md-4 {
    background-color: #2a2a2a;
    color: #eee;
    border-right: 1px solid #444;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }
 body.dark-mode .user-profile-container .col-md-4 h5 {
    color: #eee;
  }
 body.dark-mode .user-profile-container img.rounded-circle {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 50%;
    margin-bottom: 0.5rem;
    border: 2px solid #444;
  }
  body.dark-mode .user-profile-container .col-md-8 {
    padding: 1.5rem;
  }
body.dark-mode  .user-profile-container h5.mb-4 {
    color: #eee;
    font-weight: 700;
  }
body.dark-mode  label.form-label {
    color: #ccc;
    font-weight: 600;
  }
body.dark-mode  .input-group-text {
    background-color: #2a2a2a !important;
    color: #eee !important;
    border: 1px solid #555 !important;
  }
body.dark-mode  input.form-control {
    background-color: #2a2a2a;
    color: #eee;
    border: 1px solid #555;
  }
body.dark-mode  input.form-control::placeholder {
    color: #aaa;
  }
body.dark-mode  button.btn-primary {
    background-color: #3a6ef0;
    border-color: #3a6ef0;
    color: white;
    font-weight: 600;
  }
body.dark-mode  button.btn-primary:hover {
    background-color: #2a53d1;
    border-color: #2a53d1;
  }
  /* Password toggle icon color */
body.dark-mode  .toggle-password i {
    color: #ccc;
    cursor: pointer;
  }
  /* Dark Mode base */
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
/* Dark mode styles for DataTable */
body.dark-mode #documentTable,
body.dark-mode #overtimeTable,
body.dark-mode #violationsTable,
body.dark-mode #obTable,
body.dark-mode #leaveTable,
body.dark-mode #deductionTable,
body.dark-mode #benefitsTable,
body.dark-mode #dtrTable,
body.dark-mode #leaveBalanceTable,
body.dark-mode #missingLogsTable,
body.dark-mode #trainingTable,
body.dark-mode #upcomingtrainingTable,
body.dark-mode #shiftTable{
  background-color: #222 !important;
  color: #ddd !important;
  border-color: #444 !important;
}

body.dark-mode #documentTable thead,
body.dark-mode #violationsTable thead,
body.dark-mode #overtimeTable thead,
body.dark-mode #obTable thead,
body.dark-mode #leaveTable thead,
body.dark-mode #deductionTable thead,
body.dark-mode #benefitsTable thead,
body.dark-mode #dtrTable thead,
body.dark-mode #leaveBalanceTable thead,
body.dark-mode #missingLogsTable thead,
body.dark-mode #trainingTable thead,
body.dark-mode #upcomingtrainingTable thead,
  
body.dark-mode #shiftTable thead {
  background-color: #3a3a3a !important;
  color: #eee !important;
}

body.dark-mode #documentTable thead th,
 body.dark-mode #violationsTable thead th,
  body.dark-mode #overtimeTable thead th,
   body.dark-mode #obTable thead th,
    body.dark-mode #leaveTable thead th,
   body.dark-mode #deductionTable thead th,
  body.dark-mode #benefitsTable thead th,
   body.dark-mode #dtrTable  thead th,
    body.dark-mode #leaveBalanceTable thead th,
    body.dark-mode #missingLogsTable thead th,
 body.dark-mode #trainingTable thead th,
  body.dark-mode #upcomingtrainingTable thead th,
  body.dark-mode #shiftTable thead th 
     {
  border-color: #555 !important;
}

body.dark-mode #documentTable tbody tr,
 body.dark-mode #violationsTable tbody tr,
 body.dark-mode #overtimeTable tbody tr,
 body.dark-mode #leaveTable tbody tr,
 body.dark-mode #obTable tbody tr,
 body.dark-mode #deductionTable tbody tr,
 body.dark-mode #benefitsTable tbody tr,
 body.dark-mode #dtrTable tbody tr,
 body.dark-mode #leaveBalanceTable tbody tr,
 body.dark-mode #missingLogsTable tbody tr,
  body.dark-mode #trainingTable tbody tr,
 body.dark-mode #upcomingtrainingTable tbody tr,
 body.dark-mode #shiftTable tbody tr {
  background-color: #222 !important;
  color: #ddd !important;
}
body.dark-mode #documentTable tbody td,
 body.dark-mode #violationsTable tbody td,
  body.dark-mode #overtimeTable tbody td,
   body.dark-mode #deductionTable tbody td,
   body.dark-mode #obTable tbody td,
    body.dark-mode #leaveTable tbody td,
    body.dark-mode #benefitsTable tbody td,
 body.dark-mode #dtrTable tbody td,
  body.dark-mode #leaveBalanceTable tbody td,
   body.dark-mode #missingLogsTable tbody td,
   body.dark-mode #trainingTable tbody td,
    body.dark-mode #upcomingtrainingTable tbody td,
    body.dark-mode #shiftTable tbody td {
  background-color: #222 !important;
  color: #ddd !important;
}
body.dark-mode #documentTable tbody tr:hover,
 body.dark-mode #violationsTable tbody tr:hover,
 body.dark-mode #overtimeTable tbody tr:hover,
 body.dark-mode #violationsTable tbody tr:hover,
 body.dark-mode #obTable tbody tr:hover,
 body.dark-mode #leaveTable tbody tr:hover,
 body.dark-mode #deductionTable tbody tr:hover,
 body.dark-mode #benefitsTable tbody tr:hover,
 body.dark-mode #dtrTable tbody tr:hover,
 body.dark-mode #leaveBalanceTable tbody tr:hover,
 body.dark-mode #missingLogsTable tbody tr:hover,
 body.dark-mode #trainingTable tbody tr:hover,
 body.dark-mode #upcomingtrainingTable tbody tr:hover,
 body.dark-mode #shiftTable tbody tr:hover {
  background-color: #333 !important;
}

body.dark-mode #documentTable tbody tr a,
 body.dark-mode #violationsTable tbody tr a,
  body.dark-mode #overtime tbody tr a,
   body.dark-mode #violationsTable tbody tr a,
   body.dark-mode #obTable tbody tr a,
    body.dark-mode #leaveTable tbody tr a,
   body.dark-mode #deductionTable tbody tr a,
 body.dark-mode #benefitsTable tbody tr a,
  body.dark-mode #dtrTable tbody tr a,
   body.dark-mode #leaveBalanceTable tbody tr a,
   body.dark-mode #missingLogsTable tbody tr a,
    body.dark-mode #trainingTable tbody tr a,
    body.dark-mode #upcomingtrainingTable tbody tr a,
    body.dark-mode #shiftTable tbody tr a {
  color: #a1c9ff !important;
}

body.dark-mode .dataTables_paginate .paginate_button {
  color: #ccc !important;
  background: transparent !important;
  border: none !important;
}

body.dark-mode .dataTables_paginate .paginate_button.current {
  background-color: #4dabf7 !important;
  color: #121212 !important;
  border-radius: 4px !important;
}



body.dark-mode .input-group-text {
  background-color: #333 !important;
  border-color: #555 !important;
  color: #ddd !important;
}

body.dark-mode input#dt-search-input {
  background-color: #333 !important;
  color: #ddd !important;
  border-color: #555 !important;
}

/* Override card background and text color */
body.dark-mode .card-body.bg-white.text-dark {
  background-color: #1e1e1e !important;
  color: #ddd !important;
}

/* Pagination icon color with CSS variable */
body {
  --icon-color: #333;
}



.dataTables_paginate .paginate_button i {
  color: var(--icon-color);
}

.pagination-icons-white i {
  color: red !important;
  filter: brightness(0) invert(1) !important;
}

/* Inputs */
body.dark-mode input.form-control,
body.dark-mode .form-select,
body.dark-mode .input-group-text,
body.dark-mode textarea.form-control {
  background-color: #333 !important;
  border-color: #555 !important;
  color: #ddd !important;
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
body.dark-mode .dtr-instructions {
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
.header-logo {
  background-color: white;
  color: black;
  margin-bottom: -35px;
}

.header-logo h2 {
  color: black !important;
}

.header-logo h4 {
  color: #6c757d !important;
}

/* Dark mode styles when body has .dark-mode */
.dark-mode .header-logo {
  background-color: #121212 !important;
  color: #eee !important;
}

.dark-mode .header-logo h2 {
  color: #eee !important;
}

.dark-mode .header-logo h4 {
  color: #bbb !important;
}

</style>