<?php
session_start();
include 'connection.php';

// Only allow access for manager
if (!isset($_SESSION['login_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'manager') {
    header("Location: login.php");
    exit;
}

$login_id = $_SESSION['login_id'];

// Initialize variables
$manager_data = [
    'login_info' => null,
    'profile' => null,
    'employment_stats' => null,
    'department_info' => null,
    'shift_info' => null,
    'formatted_data' => []
];

try {
    // Fetch complete manager login info with employee details using JOIN
    $sql = "SELECT el.*, e.*, e.status as employee_status
        FROM employeelogins el
        LEFT JOIN employees e ON el.employee_id = e.employee_id
        WHERE el.login_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $manager_data['login_info'] = $result->fetch_assoc();
    $stmt->close();
    if (!$manager_data['login_info']) {
        throw new Exception("Manager login information not found");
    }

    // Process and format the data
    $profile = is_array($manager_data['login_info']) ? $manager_data['login_info'] : [];

    // Fetch department information if department_id exists
    if (!empty($profile['department_id'])) {
        $stmt = $conn->prepare("SELECT * FROM departments WHERE department_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $profile['department_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $manager_data['department_info'] = $result->fetch_assoc();
            $stmt->close();
        }
    }

    // Fetch shift information if shift_id exists
    if (!empty($profile['shift_id'])) {
        $stmt = $conn->prepare("SELECT * FROM shifts WHERE shift_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $profile['shift_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $manager_data['shift_info'] = $result->fetch_assoc();
            $stmt->close();
        }
    }
    
    // Calculate employment statistics if hire_date exists
    if (!empty($profile['hire_date'])) {
        try {
            $hire_date = new DateTime($profile['hire_date']);
            $current_date = new DateTime();
            $interval = $hire_date->diff($current_date);
            
            // Calculate additional employment metrics
            $years_service = $interval->y;
            $months_service = $interval->m;
            $total_days = $interval->days;
            
            // Calculate if eligible for regularization (assuming 6 months probation)
            $regularization_date = clone $hire_date;
            $regularization_date->add(new DateInterval('P6M'));
            $is_eligible_regular = $current_date >= $regularization_date;
            
            $manager_data['employment_stats'] = [
                'years_of_service' => $years_service,
                'months_of_service' => $months_service,
                'total_days' => $total_days,
                'hire_date_formatted' => $hire_date->format('F d, Y'),
                'regularization_date' => $regularization_date->format('F d, Y'),
                'is_eligible_regular' => $is_eligible_regular,
                'service_length_text' => $years_service > 0 ? 
                    "{$years_service} years, {$months_service} months" : 
                    "{$months_service} months, {$interval->d} days"
            ];
        } catch (Exception $e) {
            $manager_data['employment_stats'] = null;
        }
    }

    // Format full name with proper handling
    $first_name = trim($profile['first_name'] ?? '');
    $middle_name = trim($profile['middle_name'] ?? '');
    $last_name = trim($profile['last_name'] ?? '');

    $middle_initial = '';
    if (!empty($middle_name)) {
        $middle_initial = strtoupper(substr($middle_name, 0, 1)) . '. ';
    }

    $fullname = trim($first_name . ' ' . $middle_initial . $last_name);
    
    // Fallback to username if no full name
    if (empty($fullname)) {
        $fullname = $profile['username'] ?? 'Manager';
    }

    // Format last login with better handling
    $last_login_formatted = 'Never logged in';
    if (!empty($profile['last_login'])) {
        try {
            $last_login_date = new DateTime($profile['last_login']);
            $last_login_formatted = $last_login_date->format("F d, Y \\a\\t h:i A");
        } catch (Exception $e) {
            $last_login_formatted = 'Invalid date';
        }
    }

    // Determine profile image path with multiple fallbacks
    $photo_path = 'assets/images/admin/img-add-user.png'; // Default
    
    // Check employee photo_path first
    if (!empty($profile['photo_path'])) {
        if (file_exists(__DIR__ . '/' . $profile['photo_path'])) {
            $photo_path = $profile['photo_path'];
        } elseif (file_exists(__DIR__ . '/uploads/' . $profile['photo_path'])) {
            $photo_path = 'uploads/' . $profile['photo_path'];
        }
    } elseif (!empty($profile['image'])) {
        if (file_exists(__DIR__ . '/' . $profile['image'])) {
            $photo_path = $profile['image'];
        } elseif (file_exists(__DIR__ . '/uploads/' . $profile['image'])) {
            $photo_path = 'uploads/' . $profile['image'];
        }
    }

    // Format birth date and calculate age
    $birth_date_formatted = 'Not provided';
    $age = 'Unknown';
    if (!empty($profile['birth_date'])) {
        try {
            $birth_date = new DateTime($profile['birth_date']);
            $birth_date_formatted = $birth_date->format('F d, Y');
            $age_interval = $birth_date->diff(new DateTime());
            $age = $age_interval->y . ' years old';
        } catch (Exception $e) {
            $birth_date_formatted = 'Invalid date';
        }
    }

    // Format other dates
    $date_regular_formatted = 'Not provided';
    if (!empty($profile['date_regular'])) {
        try {
            $date_regular = new DateTime($profile['date_regular']);
            $date_regular_formatted = $date_regular->format('F d, Y');
        } catch (Exception $e) {
            $date_regular_formatted = 'Invalid date';
        }
    }

    $date_ended_formatted = 'N/A';
    if (!empty($profile['date_ended'])) {
        try {
            $date_ended = new DateTime($profile['date_ended']);
            $date_ended_formatted = $date_ended->format('F d, Y');
        } catch (Exception $e) {
            $date_ended_formatted = 'Invalid date';
        }
    }

    // Store all formatted data
    $manager_data['formatted_data'] = [
        'employee_id' => $profile['employee_id'] ?? 'N/A',
        'fullname' => $fullname,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'last_name' => $last_name,
        'username' => $profile['username'] ?? 'N/A',
        'email' => $profile['email'] ?? 'Not provided',
        'phone' => $profile['phone'] ?? 'Not provided',
        'address' => $profile['address'] ?? 'Not provided',
        'birth_date' => $birth_date_formatted,
        'age' => $age,
        'gender' => $profile['gender'] ?? 'Not specified',
        'civil_status' => $profile['civil_status'] ?? 'Not specified',
        'employee_number' => $profile['employee_number'] ?? 'Not assigned',
        'biometric_id' => $profile['biometric_id'] ?? 'Not assigned',
        'department' => $profile['department'] ?? 'Not assigned',
        'designation' => $profile['designation'] ?? 'Not assigned',
        'employment_type' => $profile['employment_type'] ?? 'Not specified',
        'hire_date' => $manager_data['employment_stats']['hire_date_formatted'] ?? 'Not provided',
        'date_regular' => $date_regular_formatted,
        'date_ended' => $date_ended_formatted,
        'employee_status' => $profile['employee_status'] ?? 'Unknown',
        'login_status' => (isset($profile['is_active']) && $profile['is_active']) ? 'Active' : 'Inactive',
        'role' => $profile['role'] ?? 'Unknown',
        'last_login' => $last_login_formatted,
        'photo_path' => $photo_path,
        'total_years_service' => $profile['total_years_service'] ?? '0.00',
        'years_of_service_calculated' => $manager_data['employment_stats']['service_length_text'] ?? 'Not calculated',
        'department_name' => $manager_data['department_info']['department_name'] ?? ($profile['department'] ?? 'Not assigned'),
        'shift_name' => $manager_data['shift_info']['shift_name'] ?? 'Not assigned'
    ];

} catch (Exception $e) {
    die("Error loading manager profile: " . $e->getMessage());
}

// Get comprehensive system statistics
$system_stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'inactive_employees' => 0,
    'total_departments' => 0,
    'managers_count' => 0,
    'regular_employees' => 0,
    'contractual_employees' => 0,
    'probationary_employees' => 0
];

try {
    // Count total employees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $system_stats['total_employees'] = $row['total'];
        $stmt->close();
    }

    // Count active login accounts
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM employeelogins WHERE is_active = 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $system_stats['active_employees'] = $row['active'];
        $stmt->close();
    }

    // Count inactive login accounts
    $stmt = $conn->prepare("SELECT COUNT(*) as inactive FROM employeelogins WHERE is_active = 0");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $system_stats['inactive_employees'] = $row['inactive'];
        $stmt->close();
    }

    // Count managers
    $stmt = $conn->prepare("SELECT COUNT(*) as managers FROM employeelogins WHERE role = 'manager'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $system_stats['managers_count'] = $row['managers'];
        $stmt->close();
    }

    // Count by employment type
    $employment_types = ['Regular', 'Contractual', 'Probationary'];
    foreach ($employment_types as $type) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE employment_type = ?");
        if ($stmt) {
            $stmt->bind_param("s", $type);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $system_stats[strtolower($type) . '_employees'] = $row['count'];
            $stmt->close();
        }
    }

    // Count departments (if table exists)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM departments");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $system_stats['total_departments'] = $row['total'];
        }
        $stmt->close();
    }

} catch (Exception $e) {
    // Continue with default values if any query fails
    error_log("Error fetching system statistics: " . $e->getMessage());
}

// Extract frequently used variables for backward compatibility
$fullname = $manager_data['formatted_data']['fullname'];
$last_login = $manager_data['formatted_data']['last_login'];
$photo_path = $manager_data['formatted_data']['photo_path'];
$profile = $manager_data['login_info'];
$employment_stats = $manager_data['employment_stats'];
$employee_id = $manager_data['formatted_data']['employee_id'];
$total_employees = $system_stats['total_employees'];
$active_employees = $system_stats['active_employees'];

// Close database connection at the very end, after all includes
?>

<?php include 'vendor/head.php'; ?>
<?php include 'vendor/sidebar.php'; ?>
<?php include 'manager_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container-fluid py-4">
      <div class="row">
        <!-- LEFT COLUMN: PROFILE -->
        <div class="col-md-4">
          <div class="card text-center shadow-sm mb-4">
            <div class="card-body">
              <img src="<?= htmlspecialchars($photo_path) ?>"
                   class="rounded-circle border mb-3"
                   style="width: 140px; height: 140px; object-fit: cover;"
                   onerror="this.src='assets/images/default-user.png'">
              <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($fullname) ?></h5>
              <p class="text-muted"><?= htmlspecialchars($manager_data['formatted_data']['email']) ?></p>
              <p class="text-muted small"><?= htmlspecialchars($manager_data['formatted_data']['designation']) ?></p>
              <hr>
              <h6 class="text-primary">Account Information</h6>
              <p class="text-muted small">
                Username: <?= htmlspecialchars($manager_data['formatted_data']['username']) ?><br>
                Employee ID: <?= htmlspecialchars($manager_data['formatted_data']['employee_id']) ?><br>
                Role: <span class="badge bg-info"><?= htmlspecialchars($manager_data['formatted_data']['role']) ?></span><br>
                Status: <span class="badge bg-<?= $manager_data['formatted_data']['login_status'] === 'Active' ? 'success' : 'secondary' ?>">
                  <?= htmlspecialchars($manager_data['formatted_data']['login_status']) ?>
                </span>
              </p>
              <p class="text-muted small">Last Login: <?= htmlspecialchars($last_login) ?></p>
            </div>
          </div>

          <!-- ENHANCED SYSTEM STATISTICS -->
          <div class="card shadow-sm mb-4">
            <div class="card-body">
              <h6 class="text-primary border-bottom pb-2">System Overview</h6>
              <div class="row text-center mb-3">
                <div class="col-6">
                  <h4 class="text-primary mb-0"><?= $system_stats['total_employees'] ?></h4>
                  <small class="text-muted">Total Employees</small>
                </div>
                <div class="col-6">
                  <h4 class="text-success mb-0"><?= $system_stats['active_employees'] ?></h4>
                  <small class="text-muted">Active Users</small>
                </div>
              </div>
              <div class="row text-center mb-3">
                <div class="col-6">
                  <h4 class="text-warning mb-0"><?= $system_stats['managers_count'] ?></h4>
                  <small class="text-muted">Managers</small>
                </div>
                <div class="col-6">
                  <h4 class="text-info mb-0"><?= $system_stats['total_departments'] ?></h4>
                  <small class="text-muted">Departments</small>
                </div>
              </div>
              <h6 class="text-secondary border-bottom pb-2">Employment Types</h6>
              <div class="row text-center">
                <div class="col-4">
                  <h5 class="text-success mb-0"><?= $system_stats['regular_employees'] ?></h5>
                  <small class="text-muted">Regular</small>
                </div>
                <div class="col-4">
                  <h5 class="text-warning mb-0"><?= $system_stats['contractual_employees'] ?></h5>
                  <small class="text-muted">Contract</small>
                </div>
                <div class="col-4">
                  <h5 class="text-danger mb-0"><?= $system_stats['probationary_employees'] ?></h5>
                  <small class="text-muted">Probation</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: DETAILS -->
        <div class="col-md-8">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="text-primary border-bottom pb-2">Personal Information</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Full Name</label>
                  <p class="mb-2"><?= htmlspecialchars($fullname) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Employee Number</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['employee_number']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Email Address</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['email']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Phone Number</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['phone']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Birth Date</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['birth_date']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Age</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['age']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Gender</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['gender']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Civil Status</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['civil_status']) ?></p>
                </div>
                <div class="col-md-12">
                  <label class="form-label fw-bold">Address</label>
                  <p class="mb-2"><?= nl2br(htmlspecialchars($manager_data['formatted_data']['address'])) ?></p>
                </div>
              </div>

              <h5 class="text-primary border-bottom pb-2">Employment Details</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Department</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['department_name']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Designation/Position</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['designation']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Employment Type</label>
                  <p class="mb-2">
                    <span class="badge bg-<?= $manager_data['formatted_data']['employment_type'] === 'Regular' ? 'success' : 'warning' ?>">
                      <?= htmlspecialchars($manager_data['formatted_data']['employment_type']) ?>
                    </span>
                  </p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Employee Status</label>
                  <p class="mb-2">
                    <span class="badge bg-<?= $manager_data['formatted_data']['employee_status'] === 'Active' ? 'success' : 'secondary' ?>">
                      <?= htmlspecialchars($manager_data['formatted_data']['employee_status']) ?>
                    </span>
                  </p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Hire Date</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['hire_date']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Years of Service</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['years_of_service_calculated']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Regularization Date</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['date_regular']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Biometric ID</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['biometric_id']) ?></p>
                </div>
                <?php if (!empty($manager_data['formatted_data']['shift_name']) && $manager_data['formatted_data']['shift_name'] !== 'Not assigned'): ?>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Work Shift</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['shift_name']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($manager_data['formatted_data']['date_ended']) && $manager_data['formatted_data']['date_ended'] !== 'N/A'): ?>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Employment End Date</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['date_ended']) ?></p>
                </div>
                <?php endif; ?>
              </div>

              <h5 class="text-primary border-bottom pb-2">System Account Information</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Login ID</label>
                  <p class="mb-2"><?= htmlspecialchars($profile['login_id'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Username</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['username']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Account Role</label>
                  <p class="mb-2">
                    <span class="badge bg-primary">
                      <?= htmlspecialchars(ucfirst($manager_data['formatted_data']['role'])) ?>
                    </span>
                  </p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Account Status</label>
                  <p class="mb-2">
                    <span class="badge bg-<?= $manager_data['formatted_data']['login_status'] === 'Active' ? 'success' : 'danger' ?>">
                      <?= htmlspecialchars($manager_data['formatted_data']['login_status']) ?>
                    </span>
                  </p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Last Login</label>
                  <p class="mb-2"><?= htmlspecialchars($last_login) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Total Service Years (DB)</label>
                  <p class="mb-2"><?= htmlspecialchars($manager_data['formatted_data']['total_years_service']) ?> years</p>
                </div>
              </div>

              <?php if ($employment_stats): ?>
              <h5 class="text-primary border-bottom pb-2">Employment Statistics</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Total Days Employed</label>
                  <p class="mb-2"><?= number_format($employment_stats['total_days']) ?> days</p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Expected Regularization</label>
                  <p class="mb-2"><?= htmlspecialchars($employment_stats['regularization_date']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Regularization Eligible</label>
                  <p class="mb-2">
                    <span class="badge bg-<?= $employment_stats['is_eligible_regular'] ? 'success' : 'warning' ?>">
                      <?= $employment_stats['is_eligible_regular'] ? 'Yes' : 'No' ?>
                    </span>
                  </p>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- END RIGHT COLUMN -->
      </div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced error handling and image loading
document.addEventListener('DOMContentLoaded', function() {
    // Handle profile image loading errors
    const profileImg = document.querySelector('.rounded-circle');
    if (profileImg) {
        profileImg.addEventListener('error', function() {
            this.src = 'assets/images/default-user.png';
            console.log('Profile image failed to load, using default');
        });
    }

    // Add loading states for better UX
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    });

    // Animate cards in
    setTimeout(() => {
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }, 100);

    // Add tooltips for badges
    const badges = document.querySelectorAll('.badge');
    badges.forEach(badge => {
        badge.setAttribute('title', 'Click for more details');
        badge.style.cursor = 'pointer';
    });

    // Console log for debugging
    console.log('Manager Profile Loaded:', {
        fullname: '<?= addslashes($fullname) ?>',
        employee_id: '<?= addslashes($employee_id) ?>',
        login_id: '<?= addslashes($profile['login_id'] ?? 'N/A') ?>',
        total_employees: <?= $total_employees ?>,
        active_employees: <?= $active_employees ?>
    });
});
</script>

<style>
/* Enhanced styling for better visual appeal */
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.badge
.badge {
    padding: 0.5em 0.8em;
    font-size: 0.85em;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.rounded-circle {
    transition: transform 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.rounded-circle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.form-label {
    color: #495057;
    font-size: 0.9em;
    margin-bottom: 0.3rem;
}

.text-primary {
    color: #007bff !important;
}

.border-bottom {
    border-bottom: 2px solid #e9ecef !important;
    margin-bottom: 1rem;
}

.row.text-center h4, .row.text-center h5 {
    font-weight: 600;
}

.card-body p {
    color: #6c757d;
    line-height: 1.5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .col-md-4, .col-md-8 {
        margin-bottom: 1rem;
    }
    
    .rounded-circle {
        width: 100px !important;
        height: 100px !important;
    }
    
    .card-body {
        padding: 1rem;
    }
}

/* Loading animation */
.card.loading {
    opacity: 0.7;
    pointer-events: none;
}

.card.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Print styles */
@media print {
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .badge {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #ddd !important;
    }
}
</style>