<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($profile)) {
    $conn_path = file_exists(__DIR__ . '/../connection.php') ? __DIR__ . '/../connection.php' : __DIR__ . '/connection.php';
    include_once $conn_path;

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
        die("Unauthorized access.");
    }

    $employee_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, photo_path FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
}

$middle_initial = !empty($profile['middle_name']) ? strtoupper(substr($profile['middle_name'], 0, 1)) . '. ' : '';
$fullname = $profile['first_name'] . ' ' . $middle_initial . $profile['last_name'];

$photo_relative = !empty($profile['photo_path']) ? $profile['photo_path'] : '';
$photo_web_path = '../' . ltrim($photo_relative, '/'); // Ensure proper web path
$photo_file_path = realpath(__DIR__ . '/../' . $photo_relative); // Physical path check

$photo_path = (!empty($photo_relative) && $photo_file_path && file_exists($photo_file_path))
    ? $photo_web_path
    : 'assets/images/default-user.jpg';

?>


<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
  <div class="navbar-wrapper">

   <div class="sidebar-user text-center p-3" style="position: relative; color: white;">
  <img src="asset/images/aloguinsan.jpg" 
       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;" 
       alt="Background">
     

      <!-- User Info -->
      <div style="position: relative; z-index: 1;">
        <img src="<?= !empty($profile['photo_path']) ? htmlspecialchars($profile['photo_path']) : 'assets/images/default-user.jpg'; ?>"
             class="rounded-circle mb-2"
             style="width: 70px; height: 70px; object-fit: cover; border: 2px solid #fff; background: #fff;">

        <h6 class="mb-0 text-white" style="text-shadow: 1px 1px 2px black;">
    <?= htmlspecialchars($fullname) ?>
  </h6>

  <small class="text-white" style="text-shadow: 1px 1px 2px black;">
    <?= ucfirst($_SESSION['role'] ?? 'User') ?>
  </small>
      </div>
    </div>




        <div class="navbar-content">
            <ul class="pc-navbar">
                
                <li class="pc-item">
                    <a href="user_dashboard.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>
                                <li class="pc-item">
    <a href="user_profile.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-user"></i></span>
        <span class="pc-mtext">My Profile</span>
    </a>
</li>
<li class="pc-item pc-hasmenu">
    <a href="#!" class="pc-link">
        <span class="pc-micon"><i class="ti ti-clock"></i></span>
        <span class="pc-mtext">Time Attendance</span>
        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
    </a>
    <ul class="pc-submenu">
       <li class="pc-item">
    <a href="user_employeedtr.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-calendar-stats"></i></span>
        <span class="pc-mtext">View DTR</span>
    </a>
</li>
<li class="pc-item">
    <a href="user_shift.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-clock"></i></i></span>
        <span class="pc-mtext">Shift Schedule</span>
    </a>
</li>


<li class="pc-item">
            <a href="user_missing_time_log.php" class="pc-link">
                <span class="pc-micon"><i class="ti ti-file-alert"></i></span>
                Missing Log Req
            </a>
        </li>

    </ul>
</li>
                              


                <li class="pc-item pc-hasmenu">
    <a href="#!" class="pc-link">
        <span class="pc-micon"><i class="ti ti-plane-departure"></i></span>
        <span class="pc-mtext">Leave Management</span>
        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
    </a>
    <ul class="pc-submenu">
       <li class="pc-item">
    <a href="user_leave_request.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-calendar-plus"></i></span>
        File Leave Request
    </a>
</li>

        
        <li class="pc-item">
            <a href="user_leave_balance.php" class="pc-link">
                <span class="pc-micon"><i class="ti ti-clipboard-list"></i></span>
                Leave Balances
            </a>
        </li>
        <li class="pc-item">
            <a href="#" class="pc-link">
                <span class="pc-micon"><i class="ti ti-clipboard-list"></i></span>
                Leave Credit Logs
            </a>
        </li>
    </ul>
</li>
   
 <!-- File OB Request -->
<li class="pc-item pc-hasmenu">
    <a href="#!" class="pc-link">
        <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
        <span class="pc-mtext">Official Business</span>
        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
    </a>

    <ul class="pc-submenu">
       <li class="pc-item">
    <a href="user_ob_request.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-calendar-plus"></i></span>
       File OB Request
    </a>
</li>

       
       
    </ul>
</li>
        <li class="pc-item pc-hasmenu">
    <a href="#!" class="pc-link">
        <span class="pc-micon"><i class="ti ti-alarm"></i></span>
        <span class="pc-mtext">Overtime</span>
        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
    </a>

    <ul class="pc-submenu">
       <li class="pc-item">
    <a href="user_overtime.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-calendar-plus"></i></span>
       File Overtime Request
    </a>
</li>

       
       
    </ul>
</li>       
              

<!-- Benefits & Deduction -->

<li class="pc-item pc-hasmenu">
  <a href="#!" class="pc-link">
    <span class="pc-micon"><i class="bi bi-wallet2"></i></span>
    <span class="pc-mtext">Benefits & Deductions</span>
     <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
  </a>
  <ul class="pc-submenu">
    <li class="pc-item">
      <a href="user_benefits.php" class="pc-link">
        <span class="pc-micon"><i class="bi bi-award-fill"></i></span>
        Employee Benefits
      </a>
    </li>
    <li class="pc-item">
      <a href="user_deductions.php" class="pc-link">
        <span class="pc-micon"><i class="bi bi-receipt-cutoff"></i></span>
        Employee Deduction
      </a>
    </li>
  </ul>
</li>

<!-- training -->
<li class="pc-item pc-hasmenu">
  <a href="#!" class="pc-link d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
      <span class="pc-micon"><i class="bi bi-mortarboard"></i></span>
      <span class="pc-mtext ms-2">Trainings</span>
    </div>
    <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
  </a>


  <ul class="pc-submenu">
    <li class="pc-item">
      <a href="usertrainings_completed.php" class="pc-link">
        <span class="pc-micon"><i class="ti ti-certificate"></i></span>
        Complete Trainings
      </a>
    </li>
    <li class="pc-item">
      <a href="userupcoming_training.php" class="pc-link">
         <span class="pc-micon"><i class="ti ti-calendar-event"></i></span>
        Upcoming Trainings
      </a>
    </li>
  </ul>
</li>

<!-- Violations -->

        <li class="pc-item">
  <a href="user_violations.php" class="pc-link d-flex align-items-center">
    <span class="pc-micon"><i class="ti ti-alert-circle"></i></span>
    <span class="pc-mtext">Violations & Sanctions</span>
  </a>
</li>



 <li class="pc-item">
    <a href="user_documents.php" class="pc-link">
        <span class="pc-micon"><i class="bi bi-folder2"></i></span>
        <span class="pc-mtext">Documents</span>
    </a>
</li>
 <li class="pc-item">
    <a href="useraccount_settings.php" class="pc-link">
        <span class="pc-micon"><i class="bi bi-person-gear"></i></span>
        <span class="pc-mtext">Account Settings</span>
    </a>
</li>
                    </ul>
                </li>
               
            </ul>
            
        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->