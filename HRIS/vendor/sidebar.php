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
        <div class="sidebar-user text-center p-3">
            <img src="<?= htmlspecialchars($profile_image); ?>"
                 class="rounded-circle mb-2"
                 style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #007bff;"
                 onerror="this.src='assets/images/default-user.jpg';"
                 alt="Profile Picture">
            <h6 class="mb-0"><?= htmlspecialchars($fullname) ?></h6>
            <small class="text-muted"><?= ucfirst($_SESSION['role'] ?? 'User') ?></small>
        </div>

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
                        Leave Approval
                    </a>
                </li>
                <li class="pc-item">
                    <a href="ob_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
                        OB Approval
                    </a>
                </li>
                <li class="pc-item">
                    <a href="timelog_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-clock-off"></i></span>
                        Missing Time Log Approval
                    </a>
                </li>
                <li class="pc-item">
                    <a href="overtime_approval.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-clock-edit"></i></span>
                        Overtime Approval
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
                                File Leave Request
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="manager_ob.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
                                OB Request
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="manager_timelog.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clock-edit"></i></span>
                                Missing Time Log
                            </a>
                        </li>
                       <li class="pc-item">
                            <a href="manager_overtime.php" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-clock-hour-10"></i></span>
                                Overtime
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

<script>
const canvas = document.getElementById('particle-canvas');
const ctx = canvas.getContext('2d');
let particles = [];
const numParticles = 80;

function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}
window.addEventListener('resize', resizeCanvas);
resizeCanvas();

class Particle {
    constructor() {
        this.reset();
    }
    reset() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.radius = Math.random() * 2 + 1;
        this.vx = (Math.random() - 0.5) * 0.5;
        this.vy = (Math.random() - 0.5) * 0.5;
        this.alpha = Math.random() * 0.5 + 0.3;
    }
    update() {
        this.x += this.vx;
        this.y += this.vy;
        if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) {
            this.reset();
        }
    }
    draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 255, 255, ${this.alpha})`;
        ctx.fill();
    }
}

for (let i = 0; i < numParticles; i++) {
    particles.push(new Particle());
}

function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let p of particles) {
        p.update();
        p.draw();
    }
    requestAnimationFrame(animate);
}
animate();
</script>