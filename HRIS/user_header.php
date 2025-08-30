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
          <form class="header-search">
            <i data-feather="search" class="icon-search"></i>
            <input type="search" class="form-control" placeholder="Search here. . .">
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
    <a href="<?= htmlspecialchars($notif['link']) ?>" class="list-group-item list-group-item-action <?= $notif['is_read'] ? '' : 'bg-light' ?>">
      <div class="d-flex">
        <div class="flex-shrink-0">
          <div class="user-avtar bg-light-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
            <i class="fa fa-bell text-warning"></i>
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
    <div class="text-center py-2"><a href="employee_notifications.php" class="link-primary">View all</a></div>
  </div>
</li>
       <!-- PROFILE / LOGOUT -->
<li class="dropdown pc-h-item header-user-profile">
 <a class="pc-head-link dropdown-toggle arrow-none me-0 d-flex align-items-center" data-bs-toggle="dropdown" href="#">
  <img src="<?= !empty($profiles['photo_path']) ? htmlspecialchars($profiles['photo_path']) : 'assets/images/default-user.jpg'; ?>"
       class="rounded-circle"
       style="width: 28px; height: 28px; object-fit: cover;">
  
  <span class="ms-2"><?= htmlspecialchars($user_name) ?></span>
  <i class="ti ti-chevron-down ms-1"></i>
</a>


  <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
    
    <!-- Background image added here -->
    <div class="dropdown-header p-3 position-relative text-white"
         style="
           background-image: url('https://static.vecteezy.com/system/resources/thumbnails/006/691/202/small/abstract-background-with-soft-gradient-color-and-dynamic-shadow-on-background-background-for-wallpaper-eps-10-free-vector.jpg');
           background-size: cover;
           background-position: center;
           border-radius: 0.375rem 0.375rem 0 0;
         ">
      
      

      <div class="d-flex mb-1 position-relative">
        <div class="flex-shrink-0">
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
              <a href="#" class="dropdown-item" id="logoutBtn">
 <i class="ti ti-power text-danger"></i>

  <span>Logout</span>
</a>
              </div>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</header>
<!-- SweetAlert & Theme Toggle Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn"); // Target logout button
    const body = document.body; 
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

