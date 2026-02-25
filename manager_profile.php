<?php
session_start();

// Enable mysqli error reporting - IMPORTANT FIX
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
    // Check database connection first - IMPORTANT FIX
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }
    
    // Test connection
    if (mysqli_connect_errno()) {
        throw new Exception('Database connection error: ' . mysqli_connect_error());
    }

    // Fetch complete manager login info with employee details using JOIN
    $sql = "SELECT el.*, e.*, e.status as employee_status
        FROM employeelogins el
        LEFT JOIN employees e ON el.employee_id = e.employee_id
        WHERE el.login_id = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $login_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Database result error: ' . $stmt->error);
    }
    
    $manager_data['login_info'] = $result->fetch_assoc();
    $stmt->close();
    
    if (!$manager_data['login_info']) {
        throw new Exception("Manager login information not found for login_id: " . $login_id);
    }

    // Process and format the data
    $profile = is_array($manager_data['login_info']) ? $manager_data['login_info'] : [];

    // Fetch department information if department_id exists - FIXED
    if (!empty($profile['department_id']) && is_numeric($profile['department_id'])) {
        $stmt = $conn->prepare("SELECT * FROM departments WHERE department_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $profile['department_id']);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    $manager_data['department_info'] = $result->fetch_assoc();
                }
            }
            $stmt->close();
        }
    }

    // Fetch shift information if shift_id exists - FIXED
    if (!empty($profile['shift_id']) && is_numeric($profile['shift_id'])) {
        $stmt = $conn->prepare("SELECT * FROM shifts WHERE shift_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $profile['shift_id']);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    $manager_data['shift_info'] = $result->fetch_assoc();
                }
            }
            $stmt->close();
        }
    }
    
    // Calculate employment statistics if hire_date exists - FIXED
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
            error_log("Date calculation error: " . $e->getMessage());
            $manager_data['employment_stats'] = null;
        }
    }

    // Format full name with proper handling - FIXED
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

    // Format last login with better handling - FIXED
    $last_login_formatted = 'Never logged in';
    if (!empty($profile['last_login']) && $profile['last_login'] !== '0000-00-00 00:00:00') {
        try {
            $last_login_date = new DateTime($profile['last_login']);
            $last_login_formatted = $last_login_date->format("F d, Y \\a\\t h:i A");
        } catch (Exception $e) {
            error_log("Last login date error: " . $e->getMessage());
            $last_login_formatted = 'Invalid date';
        }
    }

    // Determine profile image path with multiple fallbacks - FIXED
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

    // Format birth date and calculate age - FIXED
    $birth_date_formatted = 'Not provided';
    $age = 'Unknown';
    if (!empty($profile['birth_date']) && $profile['birth_date'] !== '0000-00-00') {
        try {
            $birth_date = new DateTime($profile['birth_date']);
            $birth_date_formatted = $birth_date->format('F d, Y');
            $age_interval = $birth_date->diff(new DateTime());
            $age = $age_interval->y . ' years old';
        } catch (Exception $e) {
            error_log("Birth date error: " . $e->getMessage());
            $birth_date_formatted = 'Invalid date';
        }
    }

    // Format other dates - FIXED
    $date_regular_formatted = 'Not provided';
    if (!empty($profile['date_regular']) && $profile['date_regular'] !== '0000-00-00') {
        try {
            $date_regular = new DateTime($profile['date_regular']);
            $date_regular_formatted = $date_regular->format('F d, Y');
        } catch (Exception $e) {
            error_log("Regular date error: " . $e->getMessage());
            $date_regular_formatted = 'Invalid date';
        }
    }

    $date_ended_formatted = 'N/A';
    if (!empty($profile['date_ended']) && $profile['date_ended'] !== '0000-00-00') {
        try {
            $date_ended = new DateTime($profile['date_ended']);
            $date_ended_formatted = $date_ended->format('F d, Y');
        } catch (Exception $e) {
            error_log("End date error: " . $e->getMessage());
            $date_ended_formatted = 'Invalid date';
        }
    }

    // Store all formatted data (use isset() guards for nested arrays) - FIXED
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
        // safe nested access
        'hire_date' => (is_array($manager_data['employment_stats']) && isset($manager_data['employment_stats']['hire_date_formatted'])) ? $manager_data['employment_stats']['hire_date_formatted'] : 'Not provided',
        'date_regular' => $date_regular_formatted,
        'date_ended' => $date_ended_formatted,
        'employee_status' => $profile['employee_status'] ?? 'Unknown',
        'login_status' => (isset($profile['is_active']) && $profile['is_active']) ? 'Active' : 'Inactive',
        'role' => $profile['role'] ?? 'Unknown',
        'last_login' => $last_login_formatted,
        'photo_path' => $photo_path,
        'total_years_service' => $profile['total_years_service'] ?? '0.00',
        'years_of_service_calculated' => (is_array($manager_data['employment_stats']) && isset($manager_data['employment_stats']['service_length_text'])) ? $manager_data['employment_stats']['service_length_text'] : 'Not calculated',
        'department_name' => (is_array($manager_data['department_info']) && isset($manager_data['department_info']['department_name'])) ? $manager_data['department_info']['department_name'] : ($profile['department'] ?? 'Not assigned'),
        'shift_name' => (is_array($manager_data['shift_info']) && isset($manager_data['shift_info']['shift_name'])) ? $manager_data['shift_info']['shift_name'] : 'Not assigned'
    ];

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Manager profile error: " . $e->getMessage());
    die("Error loading manager profile: " . htmlspecialchars($e->getMessage()));
}

// Get comprehensive system statistics - FIXED ERROR HANDLING
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
    // Count total employees - FIXED
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $system_stats['total_employees'] = intval($row['total'] ?? 0);
        }
        $stmt->close();
    }

    // Count active login accounts - FIXED
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM employeelogins WHERE is_active = 1");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $system_stats['active_employees'] = intval($row['active'] ?? 0);
        }
        $stmt->close();
    }

    // Count inactive login accounts - FIXED
    $stmt = $conn->prepare("SELECT COUNT(*) as inactive FROM employeelogins WHERE is_active = 0");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $system_stats['inactive_employees'] = intval($row['inactive'] ?? 0);
        }
        $stmt->close();
    }

    // Count managers - FIXED
    $stmt = $conn->prepare("SELECT COUNT(*) as managers FROM employeelogins WHERE role = 'manager'");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $system_stats['managers_count'] = intval($row['managers'] ?? 0);
        }
        $stmt->close();
    }

    // Count by employment type - FIXED
    $employment_types = ['Regular', 'Contractual', 'Probationary'];
    foreach ($employment_types as $type) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE employment_type = ?");
        if ($stmt) {
            $stmt->bind_param("s", $type);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $system_stats[strtolower($type) . '_employees'] = intval($row['count'] ?? 0);
                }
            }
            $stmt->close();
        }
    }

    // Count departments (if table exists) - FIXED
    $stmt = $conn->prepare("SHOW TABLES LIKE 'departments'");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $stmt->close();
            // Table exists, now count
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM departments");
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $system_stats['total_departments'] = intval($row['total'] ?? 0);
                }
                $stmt->close();
            }
        } else {
            if ($stmt) $stmt->close();
        }
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
$employment_stats = isset($manager_data['employment_stats']) ? $manager_data['employment_stats'] : null;
$employee_id = $manager_data['formatted_data']['employee_id'];
$total_employees = $system_stats['total_employees'];
$active_employees = $system_stats['active_employees'];

// DON'T close the database connection here - sidebar.php needs it!
// The connection will be closed after all includes are processed
?>

<?php include 'vendor/head.php'; ?>
<?php include 'vendor/sidebar.php'; ?>
<?php include 'manager_header.php'; ?>

<!-- The rest of your HTML remains the same -->
<!-- External icons (Bootstrap Icons) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<div class="pc-container improved-bg">
  <div class="pc-content">
    <div class="container-fluid py-4">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
          <h2 class="mb-0 page-title">Manager Profile</h2>
          <small class="text-muted">Overview & account details</small>
        </div>
      </div>

      <div class="row g-4">
        <!-- LEFT: Profile + Quick Stats -->
        <div class="col-lg-4">
          <div class="card glass p-4 shadow profile-card">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="avatar-wrapper position-relative">
                <img src="<?= htmlspecialchars($photo_path) ?>"
                     class="rounded-circle profile-avatar"
                     alt="Profile photo of <?= htmlspecialchars($fullname) ?>"
                     onerror="this.src='assets/images/default-user.png'"
                     style="cursor:pointer"
                     data-bs-toggle="modal" data-bs-target="#avatarModal">
                <span class="avatar-overlay" data-bs-toggle="modal" data-bs-target="#avatarModal">
                  <i class="bi bi-arrows-fullscreen"></i>
                </span>
              </div>
              <div class="flex-grow-1">
                <h4 class="mb-0"><?= htmlspecialchars($fullname) ?></h4>
                <div class="text-muted small"><?= htmlspecialchars($manager_data['formatted_data']['designation']) ?></div>
                <div class="mt-2">
                  <span class="badge role-badge"><?= htmlspecialchars(ucfirst($manager_data['formatted_data']['role'])) ?></span>
                  <span class="badge status-badge ms-1 <?= $manager_data['formatted_data']['login_status'] === 'Active' ? 'active' : 'inactive' ?>">
                    <?= htmlspecialchars($manager_data['formatted_data']['login_status']) ?>
                  </span>
                </div>
              </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between mb-2 small text-muted">
              <div>Email</div>
              <div><?= htmlspecialchars($manager_data['formatted_data']['email']) ?></div>
            </div>
            <div class="d-flex justify-content-between mb-2 small text-muted">
              <div>Phone</div>
              <div><?= htmlspecialchars($manager_data['formatted_data']['phone']) ?></div>
            </div>
            <div class="d-flex justify-content-between mb-2 small text-muted">
              <div>Last Login</div>
              <div><?= htmlspecialchars($last_login) ?></div>
            </div>

            <div class="mt-3">
              <h6 class="dashboard-section-title">
                <i class="bi bi-graph-up-arrow icon-gradient-primary"></i>
                <span>System Overview</span>
              </h6>
              
              <!-- Main Stats Grid - Responsive Layout -->
              <div class="row g-2 g-sm-3 mb-3">
                <div class="col-6 col-md-6">
                  <div class="stat-card elevated gradient-primary">
                    <div class="stat-icon icon-light">
                      <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-body">
                      <div class="stat-number"><?= number_format(intval($system_stats['total_employees'])) ?></div>
                      <div class="stat-label">Total Employees</div>
                    </div>
                  </div>
                </div>
                
                <div class="col-6 col-md-6">
                  <div class="stat-card elevated gradient-success">
                    <div class="stat-icon icon-light">
                      <i class="bi bi-person-check-fill"></i>
                    </div>
                    <div class="stat-body">
                      <div class="stat-number"><?= number_format(intval($system_stats['active_employees'])) ?></div>
                      <div class="stat-label">Active</div>
                    </div>
                  </div>
                </div>
                
                <div class="col-6 col-md-6">
                  <div class="stat-card elevated gradient-warning">
                    <div class="stat-icon icon-light">
                      <i class="bi bi-person-workspace"></i>
                    </div>
                    <div class="stat-body">
                      <div class="stat-number"><?= number_format(intval($system_stats['managers_count'])) ?></div>
                      <div class="stat-label">Managers</div>
                    </div>
                  </div>
                </div>
                
                <div class="col-6 col-md-6">
                  <div class="stat-card elevated gradient-info">
                    <div class="stat-icon icon-light">
                      <i class="bi bi-building-fill"></i>
                    </div>
                    <div class="stat-body">
                      <div class="stat-number"><?= number_format(intval($system_stats['total_departments'])) ?></div>
                      <div class="stat-label">Departments</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Employment Types Section -->
              <div class="employment-types-section">
                <h6 class="dashboard-section-title">
                  <i class="bi bi-person-vcard icon-gradient-secondary"></i>
                  <span>Employment Types</span>
                </h6>
                
                <div class="employment-types-container">
                  <div class="d-flex gap-2">
                    <div class="type-pill elevated shimmer-primary">
                      <strong class="gradient-text-primary"><?= number_format(intval($system_stats['regular_employees'])) ?></strong>
                      <small class="d-block text-primary">Regular</small>
                      <div class="progress mt-2">
                        <div class="progress-bar shine-primary" 
                             style="width: <?= $system_stats['total_employees'] > 0 ? ($system_stats['regular_employees']/$system_stats['total_employees'])*100 : 0 ?>%"
                             role="progressbar"
                             aria-label="Regular employees percentage">
                        </div>
                      </div>
                    </div>
                    
                    <div class="type-pill elevated shimmer-success">
                      <strong class="gradient-text-success"><?= number_format(intval($system_stats['contractual_employees'])) ?></strong>
                      <small class="d-block text-success">Contract</small>
                      <div class="progress mt-2">
                        <div class="progress-bar shine-success" 
                             style="width: <?= $system_stats['total_employees'] > 0 ? ($system_stats['contractual_employees']/$system_stats['total_employees'])*100 : 0 ?>%"
                             role="progressbar"
                             aria-label="Contractual employees percentage">
                        </div>
                      </div>
                    </div>
                    
                    <div class="type-pill elevated shimmer-warning">
                      <strong class="gradient-text-warning"><?= number_format(intval($system_stats['probationary_employees'])) ?></strong>
                      <small class="d-block text-warning">Probation</small>
                      <div class="progress mt-2">
                        <div class="progress-bar shine-warning" 
                             style="width: <?= $system_stats['total_employees'] > 0 ? ($system_stats['probationary_employees']/$system_stats['total_employees'])*100 : 0 ?>%"
                             role="progressbar"
                             aria-label="Probationary employees percentage">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Tabbed Details -->
        <div class="col-lg-8">
          <div class="card p-3 shadow-sm">
            <ul class="nav nav-tabs custom-tabs mb-3" id="profileTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                  <i class="bi bi-person-lines-fill"></i>Personal
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                  <i class="bi bi-briefcase-fill"></i>Employment
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                  <i class="bi bi-person-gear"></i>Account
                </button>
              </li>
            </ul>

            <div class="tab-content">
              <!-- PERSONAL -->
              <div class="tab-pane fade show active" id="personal" role="tabpanel">
                <div class="detail-section">
                  <div class="section-header">
                    <i class="bi bi-person-vcard"></i>
                    <span>Basic Information</span>
                  </div>
                  <div class="info-grid">
                    <div class="info-item">
                      <label class="form-label">Full Name</label>
                      <div class="value-box">
                        <i class="bi bi-person text-primary"></i>
                        <span><?= htmlspecialchars($fullname) ?></span>
                      </div>
                    </div>
                    <div class="info-item">
                      <label class="form-label">Employee Number</label>
                      <div class="value-box">
                        <i class="bi bi-hash text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['employee_number']) ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="section-header mt-4">
                    <i class="bi bi-calendar-heart"></i>
                    <span>Personal Details</span>
                  </div>
                  <div class="info-grid">
                    <div class="info-item">
                      <label class="form-label">Birth Date</label>
                      <div class="value-box">
                        <i class="bi bi-calendar-date text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['birth_date']) ?></span>
                      </div>
                    </div>
                    <div class="info-item">
                      <label class="form-label">Age</label>
                      <div class="value-box">
                        <i class="bi bi-hourglass-split text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['age']) ?></span>
                      </div>
                    </div>
                    <div class="info-item">
                      <label class="form-label">Gender</label>
                      <div class="value-box">
                        <i class="bi bi-gender-ambiguous text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['gender']) ?></span>
                      </div>
                    </div>
                    <div class="info-item">
                      <label class="form-label">Civil Status</label>
                      <div class="value-box">
                        <i class="bi bi-heart text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['civil_status']) ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="section-header mt-4">
                    <i class="bi bi-geo-alt"></i>
                    <span>Contact Information</span>
                  </div>
                  <div class="info-item full-width">
                    <label class="form-label">Address</label>
                    <div class="value-box">
                      <i class="bi bi-house-door text-primary"></i>
                      <span><?= nl2br(htmlspecialchars($manager_data['formatted_data']['address'])) ?></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- EMPLOYMENT -->
              <div class="tab-pane fade" id="employment" role="tabpanel">
                <div class="detail-section">
                  <div class="section-header">
                    <i class="bi bi-briefcase"></i>
                    <span>Position Details</span>
                  </div>
                  <div class="info-grid">
                    <div class="info-item highlight-box">
                      <label class="form-label">Department</label>
                      <div class="value-box">
                        <i class="bi bi-building text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['department_name']) ?></span>
                      </div>
                    </div>
                    <div class="info-item highlight-box">
                      <label class="form-label">Designation</label>
                      <div class="value-box">
                        <i class="bi bi-person-badge text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['designation']) ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="section-header mt-4">
                    <i class="bi bi-clock-history"></i>
                    <span>Employment Status</span>
                  </div>
                  <div class="info-grid">
                    <div class="info-item">
                      <label class="form-label">Employment Type</label>
                      <div class="value-box">
                        <i class="bi bi-person-workspace text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['employment_type']) ?></span>
                      </div>
                    </div>
                    <div class="info-item">
                      <label class="form-label">Status</label>
                      <div class="value-box">
                        <i class="bi bi-check-circle text-primary"></i>
                        <span><?= htmlspecialchars($manager_data['formatted_data']['employee_status']) ?></span>
                      </div>
                    </div>
                  </div>

                  <?php if ($employment_stats): ?>
                  <div class="employment-stats-card mt-4">
                    <div class="stats-header">
                      <i class="bi bi-graph-up"></i>
                      <span>Employment Timeline</span>
                    </div>
                    <div class="stats-grid">
                      <div class="stat-block">
                        <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-info">
                          <div class="stat-label">Hire Date</div>
                          <div class="stat-value"><?= htmlspecialchars($manager_data['formatted_data']['hire_date']) ?></div>
                        </div>
                      </div>
                      <div class="stat-block">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-info">
                          <div class="stat-label">Service Length</div>
                          <div class="stat-value"><?= htmlspecialchars($manager_data['formatted_data']['years_of_service_calculated']) ?></div>
                        </div>
                      </div>
                      <div class="stat-block">
                        <div class="stat-icon"><i class="bi bi-calendar-plus"></i></div>
                        <div class="stat-info">
                          <div class="stat-label">Regularization</div>
                          <div class="stat-value"><?= htmlspecialchars($manager_data['formatted_data']['date_regular']) ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- ACCOUNT -->
              <div class="tab-pane fade" id="account" role="tabpanel">
                <div class="detail-section">
                    <div class="section-header">
                        <i class="bi bi-shield-lock icon-purple"></i>
                        <span>Account Security</span>
                    </div>
                    <div class="info-grid">
                        <div class="info-item highlight-box">
                            <label class="form-label">Login ID</label>
                            <div class="value-box">
                                <i class="bi bi-fingerprint"></i>
                                <span><?= htmlspecialchars($profile['login_id'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                        <div class="info-item highlight-box">
                            <label class="form-label">Username</label>
                            <div class="value-box">
                                <i class="bi bi-person-badge"></i>
                                <span><?= htmlspecialchars($manager_data['formatted_data']['username']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="section-header mt-4">
                        <i class="bi bi-shield-check icon-success"></i>
                        <span>Account Status</span>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label class="form-label">Account Role</label>
                            <div class="value-box">
                                <i class="bi bi-person-gear"></i>
                                <span><?= htmlspecialchars(ucfirst($manager_data['formatted_data']['role'])) ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <label class="form-label">Account Status</label>
                            <div class="value-box">
                                <i class="bi bi-toggle2-<?= $manager_data['formatted_data']['login_status'] === 'Active' ? 'on' : 'off' ?>"></i>
                                <span><?= htmlspecialchars($manager_data['formatted_data']['login_status']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="account-activity-card mt-4">
                        <div class="stats-header">
                            <i class="bi bi-activity icon-warning"></i>
                            <span>Account Activity</span>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-block">
                                <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">Last Login</div>
                                    <div class="stat-value"><?= htmlspecialchars($last_login) ?></div>
                                </div>
                            </div>
                            <div class="stat-block">
                                <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">Service Years</div>
                                    <div class="stat-value"><?= htmlspecialchars($manager_data['formatted_data']['total_years_service']) ?> years</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- row -->
    </div> <!-- container -->
  </div>
</div>

<!-- Avatar Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-body text-center p-0">
        <img src="<?= htmlspecialchars($photo_path) ?>" alt="Profile photo" class="img-fluid rounded shadow-lg" style="max-width: 350px;">
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el)
    })

    // Image fallback (in case onerror not executed)
    const img = document.querySelector('.profile-avatar');
    if (img) {
        img.addEventListener('error', function () {
            this.src = 'assets/images/default-user.png';
        });
    }

    console.log('Manager Profile Loaded:', {
        fullname: '<?= addslashes($fullname) ?>',
        employee_id: '<?= addslashes($employee_id ?? 'N/A') ?>',
        login_id: '<?= addslashes($profile['login_id'] ?? 'N/A') ?>',
        total_employees: <?= intval($total_employees) ?>,
        active_employees: <?= intval($active_employees) ?>
    });
});
</script>

<style>
:root{
  --bg: #f6f8fb;
  --card: #ffffff;
  --muted: #6c757d;
  --primary: #2563EB;
  --glass-bg: rgba(255,255,255,0.7);
  --radius: 12px;
  --soft-shadow: 0 6px 18px rgba(30,41,59,0.06);
  --glass-border: rgba(255,255,255,0.6);
  font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
}

.improved-bg {
  background: linear-gradient(135deg, #e0e7ff 0%, #f6f8fb 100%);
  min-height: calc(100vh - 80px);
  padding-bottom: 40px;
}

.page-title { font-weight: 600; color: #0f172a; }
.pc-container { background: transparent; min-height: calc(100vh - 80px); padding-bottom: 40px; }

/* Card glass effect */
.card.glass {
    background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,255,255,0.85));
    border: 1px solid var(--glass-border);
    box-shadow: var(--soft-shadow);
    border-radius: var(--radius);
    transition: box-shadow 0.2s;
}
.card.glass:hover, .profile-card:hover {
    box-shadow: 0 8px 28px rgba(30,41,59,0.13);
}

/* Profile avatar */
.avatar-wrapper { width: 84px; height: 84px; border-radius: 12px; overflow: hidden; flex-shrink: 0; position:relative; }
.profile-avatar { width:84px; height:84px; object-fit:cover; display:block; border-radius:12px; transition: box-shadow 0.2s; }
.avatar-wrapper:hover .profile-avatar { box-shadow: 0 0 0 4px #2563eb33; }
.avatar-overlay {
  display: none;
  position: absolute;
  inset: 0;
  background: rgba(37,99,235,0.13);
  color: #2563EB;
  font-size: 1.8rem;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.avatar-wrapper:hover .avatar-overlay { display: flex; }

/* Badges */
.role-badge {
    background: rgba(37,99,235,0.08);
    color: var(--primary);
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-weight:600;
    font-size:0.8rem;
}
.status-badge.active { background: rgba(16,185,129,0.1); color: #059669; padding: 0.25rem 0.6rem; border-radius: 999px; }
.status-badge.inactive { background: rgba(107,114,128,0.08); color: #6b7280; padding: 0.25rem 0.6rem; border-radius: 999px; }

/* stat cards */
.stat-card { display:flex; align-items:center; gap:0.75rem; padding:0.6rem; background: #fff; border-radius:10px; box-shadow:0 4px 12px rgba(17,24,39,0.03); transition: box-shadow 0.2s, transform 0.2s; }
.stat-card:hover { box-shadow:0 8px 24px rgba(17,24,39,0.09); transform: translateY(-2px) scale(1.03);}
.stat-icon { width:40px; height:40px; display:grid; place-items:center; border-radius:8px; font-size:1.1rem; }
.bg-soft-primary { background: rgba(37,99,235,0.08); color: #2563EB; }
.bg-soft-success { background: rgba(16,185,129,0.08); color: #059669; }
.bg-soft-warning { background: rgba(245,158,11,0.08); color: #F59E0B; }
.bg-soft-info { background: rgba(14,165,233,0.08); color: #0EA5E9; }

.stat-body .stat-number { font-weight:700; font-size:1.05rem; }

/* type pills */
.type-pill { background: #fff; padding:0.6rem; border-radius:10px; min-width:86px; text-align:center; box-shadow:0 6px 12px rgba(2,6,23,0.04); transition: box-shadow 0.2s, transform 0.2s;}
.type-pill:hover { box-shadow:0 8px 24px rgba(2,6,23,0.09); transform: translateY(-2px) scale(1.04); }

/* content values */
.form-label { font-size:0.85rem; color:var(--muted); margin-bottom:4px; }
.value { font-weight:600; color:#111827; }

/* tabbed cards */
.tab-content .card { background: #f8fafc; border-radius: 10px; }

/* Detail section styles */
.detail-section {
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.section-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
  color: #1e293b;
  font-weight: 600;
}

.section-header i {
  font-size: 1.25rem;
  color: var(--primary);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.info-item {
  position: relative;
}

.info-item.full-width {
  grid-column: 1 / -1;
}

.value-box {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  transition: all 0.2s;
}

.value-box:hover {
  background: #f1f5f9;
  transform: translateY(-1px);
}

.value-box i {
  font-size: 1.1rem;
}

.highlight-box {
  background: #f0f9ff;
  padding: 1rem;
  border-radius: 10px;
  border: 1px solid #e0f2fe;
}

.employment-stats-card {
  background: linear-gradient(to right, #f0f9ff, #f8fafc);
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid #e0f2fe;
}

.stats-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  color: #0369a1;
  font-weight: 600;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.stat-block {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  transition: all 0.2s;
}

.stat-block:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-icon {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  background: #f0f9ff;
  border-radius: 8px;
  color: #0369a1;
}

.stat-info {
  flex: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
}

.stat-value {
  font-weight: 600;
  color: #0f172a;
}

.custom-tabs .nav-link {
  color: #64748b;
  padding: 0.75rem 1.25rem;
  border: none;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}

.custom-tabs .nav-link:hover {
  color: var(--primary);
}

.custom-tabs .nav-link.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  background: transparent;
}

/* Override text colors for better visibility */
.stat-card.elevated .stat-number {
    color: #111827 !important;
    text-shadow: 0 1px 2px rgba(255,255,255,0.1);
}

.stat-card.elevated .stat-label {
    color: #374151 !important;
    text-shadow: 0 1px 2px rgba(255,255,255,0.1);
}

.type-pill.elevated strong {
    color: #111827;
}

.type-pill.elevated small {
    color: #374151 !important;
}

/* Enhance contrast for gradient cards */
.gradient-primary, .gradient-success, .gradient-warning, .gradient-info {
    background: rgba(255,255,255,0.95);
    border: 1px solid rgba(0,0,0,0.1);
}

/* Add subtle background tints */
.gradient-primary { background-color: #f0f7ff; }
.gradient-success { background-color: #f0fdf4; }
.gradient-warning { background-color: #fffbeb; }
.gradient-info { background-color: #f0f9ff; }

/* Update icon colors for better visibility */
.icon-light {
    background: rgba(0,0,0,0.05);
    color: #111827;
}

/* Update progress bar colors */
.shine-primary { background: #2563eb; }
.shine-success { background: #059669; }
.shine-warning { background: #d97706; }

/* Text colors for stat blocks */
.stat-block .stat-value {
    color: #111827;
}
.stat-block .stat-label {
    color: #374151;
}

.nav-tabs .nav-link i {
    border-radius: 6px;
    margin-right: 8px;
    transition: transform 0.2s;
}

.nav-tabs .nav-link:hover i {
    transform: scale(1.1);
}

#personal-tab i { background: #dbeafe; color: var(--icon-primary); }
#employment-tab i { background: #dcfce7; color: var(--icon-success); }
#account-tab i { background: #f3e8ff; color: var(--icon-purple); }

/* Stat card icons - System Overview */
.stat-card:nth-child(1) .stat-icon { background: #dbeafe; color: var(--icon-primary); }
.stat-card:nth-child(2) .stat-icon { background: #d1fae5; color: var(--icon-success); }
.stat-card:nth-child(3) .stat-icon { background: #fef3c7; color: #d97706; }
.stat-card:nth-child(4) .stat-icon { background: #cffafe; color: #0891b2; }

/* Employment type pills icons */
.type-pill:nth-child(1) { border-left: 4px solid #2563eb; }
.type-pill:nth-child(2) { border-left: 4px solid #059669; }
.type-pill:nth-child(3) { border-left: 4px solid #d97706; }

/* Add smooth transition for all icons */
i { transition: all 0.2s ease-in-out; }

/* Icon hover effects */
.value-box:hover i,
.section-header:hover i,
.stat-block:hover .stat-icon i {
    transform: scale(1.1) rotate(5deg);
}

/* Dashboard section titles */
.dashboard-section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-weight: 600;
    color: #1e293b;
}

.dashboard-section-title i {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    font-size: 1.1rem;
}

/* Elevated cards */
.stat-card.elevated {
    background: white;
    border: 1px solid rgba(0,0,0,0.05);
    padding: 1rem;
}

.type-pill.elevated {
    background: white;
    border: 1px solid rgba(0,0,0,0.05);
    padding: 1rem;
    flex: 1;
    min-width: 100px;
}

/* Account activity card */
.account-activity-card {
    background: linear-gradient(to right, #ede9fe, #f8fafc);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e0e7ff;
}

/* Progress bars */
.progress {
    background-color: rgba(0,0,0,0.05);
    overflow: hidden;
    border-radius: 999px;
}

.progress-bar {
    transition: width 1s ease;
}

/* Enhanced stat cards */
.stat-card.elevated .stat-body {
    display: flex;
    flex-direction: column;
}

.stat-card.elevated .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    color: #1e293b;
}

.stat-card.elevated .stat-label {
    font-size: 0.875rem;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Animations */
.stat-card.elevated:hover,
.type-pill.elevated:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.05);
}

.stat-card.elevated:hover .stat-icon i,
.type-pill.elevated:hover strong {
    transform: scale(1.1);
}

.progress-bar {
    animation: progressAnimation 1s ease-in-out;
}

@keyframes progressAnimation {
    from { width: 0; }
}

/* Gradient backgrounds */
.gradient-primary {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}
.gradient-success {
    background: linear-gradient(135deg, #059669, #047857);
}
.gradient-warning {
    background: linear-gradient(135deg, #d97706, #b45309);
}
.gradient-info {
    background: linear-gradient(135deg, #0891b2, #0e7490);
}

/* Icon gradients */
.icon-gradient-primary {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    -webkit-background-clip: text;
    background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.icon-gradient-secondary {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Light icons for dark backgrounds */
.icon-light {
    background: rgba(255,255,255,0.2);
    color: white;
}

/* Gradient text */
.gradient-text-primary {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 1.5rem;
}
.gradient-text-success {
    background: linear-gradient(135deg, #059669, #047857);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 1.5rem;
}
.gradient-text-warning {
    background: linear-gradient(135deg, #d97706, #b45309);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 1.5rem;
}

/* Shimmer effects */
.shimmer-primary {
    background: linear-gradient(135deg, #f0f9ff, #dbeafe);
    position: relative;
    overflow: hidden;
}
.shimmer-success {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    position: relative;
    overflow: hidden;
}
.shimmer-warning {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    position: relative;
    overflow: hidden;
}

/* Shine effects for progress bars */
.shine-primary {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    position: relative;
    overflow: hidden;
}
.shine-success {
    background: linear-gradient(135deg, #059669, #047857);
    position: relative;
    overflow: hidden;
}
.shine-warning {
    background: linear-gradient(135deg, #d97706, #b45309);
    position: relative;
    overflow: hidden;
}

/* Add shimmer animation */
.shimmer-primary::after,
.shimmer-success::after,
.shimmer-warning::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        to right,
        transparent,
        rgba(255,255,255,0.3),
        transparent
    );
    transform: rotate(30deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%) rotate(30deg);
    }
    100% {
        transform: translateX(100%) rotate(30deg);
    }
}

/* Dark Mode Styles */
body.dark-mode .improved-bg {
    background: linear-gradient(135deg, #1a1c2a 0%, #0f1118 100%);
}

body.dark-mode .card.glass {
    background: linear-gradient(180deg, rgba(30, 32, 44, 0.95), rgba(25, 27, 38, 0.85));
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .page-title {
    color: #e0e0e0;
}

/* Profile Card Dark Mode */
body.dark-mode .profile-card {
    background: linear-gradient(180deg, rgba(30, 32, 44, 0.95), rgba(25, 27, 38, 0.9));
}

body.dark-mode .text-muted {
    color: #a0aec0 !important;
}

/* Stat Cards Dark Mode */
body.dark-mode .stat-card.elevated {
    background: linear-gradient(135deg, #2d3142 0%, #1f2937 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .stat-card .stat-number {
    color: #e0e0e0;
}

body.dark-mode .stat-card .stat-label {
    color: #a0aec0;
}

/* Employment Types Dark Mode */
body.dark-mode .type-pill {
    background: #2d3142;
    border-color: rgba(255, 255, 255, 0.1);
}

body.dark-mode .type-pill strong {
    color: #e0e0e0;
}

body.dark-mode .type-pill small {
    color: #a0aec0;
}

/* Progress Bars Dark Mode */
body.dark-mode .progress {
    background-color: rgba(255, 255, 255, 0.1);
}

/* Tabs Dark Mode */
body.dark-mode .nav-tabs {
    border-color: #4a5568;
}

body.dark-mode .nav-tabs .nav-link {
    color: #a0aec0;
}

body.dark-mode .nav-tabs .nav-link.active {
    background-color: #2d3142;
    border-color: #4a5568;
    color: #e0e0e0;
}

/* Content Sections Dark Mode */
body.dark-mode .detail-section {
    background: #2d3142;
}

body.dark-mode .section-header {
    border-color: #4a5568;
    color: #e0e0e0;
}

body.dark-mode .info-grid .form-label {
    color: #a0aec0;
}

body.dark-mode .value-box {
    background: #1f2937;
    color: #e0e0e0;
}

body.dark-mode .value-box:hover {
    background: #374151;
}

/* Highlight Boxes Dark Mode */
body.dark-mode .highlight-box {
    background: #1f2937;
    border-color: #4a5568;
}

/* Employment Stats Card Dark Mode */
body.dark-mode .employment-stats-card {
    background: linear-gradient(to right, #1f2937, #2d3142);
    border-color: #4a5568;
}

body.dark-mode .stat-block {
    background: #374151;
}

body.dark-mode .stat-icon {
    background: #1f2937;
    color: #e0e0e0;
}

body.dark-mode .stat-label {
    color: #a0aec0;
}

body.dark-mode .stat-value {
    color: #e0e0e0;
}

/* Avatar Modal Dark Mode */
body.dark-mode .modal-content {
    background-color: #2d3142;
}

/* Gradient Text Colors Dark Mode */
body.dark-mode .gradient-text-primary {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
}

body.dark-mode .gradient-text-success {
    background: linear-gradient(135deg, #34d399, #10b981);
}

body.dark-mode .gradient-text-warning {
    background: linear-gradient(135deg, #fbbf24, #d97706);
}

/* Icon Backgrounds Dark Mode */
body.dark-mode .icon-light {
    background: rgba(255, 255, 255, 0.1);
    color: #e0e0e0;
}

/* Status Badges Dark Mode */
body.dark-mode .status-badge.active {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

body.dark-mode .status-badge.inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

/* Role Badge Dark Mode */
body.dark-mode .role-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

/* Shimmer Effects Dark Mode */
body.dark-mode .shimmer-primary {
    background: linear-gradient(135deg, #1f2937, #2d3142);
}

body.dark-mode .shimmer-success {
    background: linear-gradient(135deg, #065f46, #047857);
}

body.dark-mode .shimmer-warning {
    background: linear-gradient(135deg, #92400e, #b45309);
}

/* Custom Icons Dark Mode */
body.dark-mode .section-header i,
body.dark-mode .value-box i {
    background: rgba(255, 255, 255, 0.1);
}

/* Cards Hover Effects Dark Mode */
body.dark-mode .stat-card.elevated:hover,
body.dark-mode .type-pill.elevated:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

/* Employment Types Icons Dark Mode */
body.dark-mode .type-pill:nth-child(1) { border-left-color: #3b82f6; }
body.dark-mode .type-pill:nth-child(2) { border-left-color: #10b981; }
body.dark-mode .type-pill:nth-child(3) { border-left-color: #f59e0b; }

/* Fix for white backgrounds in dark mode */
body.dark-mode .card-body.bg-white {
    background-color: #2d3142 !important;
}

/* Scrollbar Dark Mode */
body.dark-mode ::-webkit-scrollbar-track {
    background: #1f2937;
}

body.dark-mode ::-webkit-scrollbar-thumb {
    background: #4a5568;
}

body.dark-mode ::-webkit-scrollbar-thumb:hover {
    background: #606f7b;
}

/* Media Queries for Responsiveness */
@media (max-width: 576px) {
  /* Reduce padding in profile card */
  .profile-card {
    padding: 1.5rem !important;
  }
  
  /* Stack stat cards vertically on very small screens */
  .row.g-3 {
    --bs-gutter-x: 0.75rem;
    --bs-gutter-y: 0.75rem;
  }
  
  .stat-card {
    padding: 0.875rem;
    gap: 0.5rem;
    min-height: 70px;
  }
  
  .stat-icon {
    width: 40px;
    height: 40px;
    font-size: 1.1rem;
  }
  
  .stat-number {
    font-size: 1.5rem;
  }
  
  .stat-label {
    font-size: 0.8rem;
  }
  
  /* Employment types - allow horizontal scroll on mobile */
  .employment-types-section .d-flex {
    gap: 0.5rem;
    margin: 0 -0.5rem;
    padding: 0 0.5rem 0.5rem;
  }
  
  .type-pill {
    min-width: 85px;
    padding: 0.875rem 0.5rem;
    flex: 0 0 auto;
  }
  
  .type-pill strong {
    font-size: 1.25rem;
  }
  
  .type-pill small {
    font-size: 0.7rem;
  }
}

/* Tablet Optimizations */
@media (min-width: 577px) and (max-width: 768px) {
  .stat-card {
    padding: 0.95rem;
  }
  
  .stat-icon {
    width: 44px;
    height: 44px;
    font-size: 1.15rem;
  }
  
  .stat-number {
    font-size: 1.6rem;
  }
  
  .type-pill {
    min-width: 88px;
    padding: 0.95rem 0.75rem;
  }
  
  .type-pill strong {
    font-size: 1.4rem;
  }
}

/* Desktop Optimizations */
@media (min-width: 992px) {
  .stat-card {
    min-height: 85px;
  }
  
  .stat-icon {
    width: 52px;
    height: 52px;
    font-size: 1.3rem;
  }
  
  .stat-number {
    font-size: 1.875rem;
  }
  
  .type-pill {
    min-width: 95px;
  }
  
  .type-pill strong {
    font-size: 1.6rem;
  }
}

/* Grid System Override for Better Control */
@media (max-width: 575px) {
  .row.g-3 > .col-6 {
    flex: 0 0 100%;
    max-width: 100%;
    margin-bottom: 0.75rem;
  }
  
  .row.g-3 > .col-6:nth-child(odd) {
    padding-right: 0.375rem;
  }
  
  .row.g-3 > .col-6:nth-child(even) {
    padding-left: 0.375rem;
  }
}

@media (min-width: 576px) and (max-width: 767px) {
  .row.g-3 > .col-6 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}

/* Enhanced Visual Effects */
.stat-card.elevated {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid rgba(0,0,0,0.04);
}

.stat-card.elevated:hover .stat-icon {
  transform: scale(1.1) rotate(5deg);
}

/* Gradient Backgrounds for Stat Cards */
.stat-card.gradient-primary {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border-left: 4px solid #2563eb;
}

.stat-card.gradient-success {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-left: 4px solid #059669;
}

.stat-card.gradient-warning {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-left: 4px solid #d97706;
}

.stat-card.gradient-info {
  background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);
  border-left: 4px solid #0891b2;
}

/* Loading Animation for Numbers */
.stat-number {
  animation: countUp 1s ease-out;
}

@keyframes countUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Scroll Indicator for Employment Types on Mobile */
@media (max-width: 576px) {
  .employment-types-section::after {
    content: '→ Scroll';
    position: absolute;
    right: 1rem;
    bottom: -0.5rem;
    font-size: 0.7rem;
    color: #9ca3af;
    pointer-events: none;
    opacity: 0.7;
  }
  
  .employment-types-section {
    position: relative;
  }
}

/* Fix for overflow issues */
.profile-card {
  overflow: hidden;
}

.dashboard-section-title {
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* Improved accessibility */
.stat-card:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}

.type-pill:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}

/* System Overview Responsive Adjustments */
@media screen and (max-width: 1400px) {
  .stat-card.elevated {
    padding: 0.875rem;
  }
  
  .stat-card.elevated .stat-number {
    font-size: 1.25rem;
  }
  
  .stat-icon {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }
  
  .type-pill strong {
    font-size: 1.25rem;
  }
}

@media screen and (max-width: 1200px) {
  .dashboard-section-title {
    font-size: 1rem;
  }
  
  .stat-card.elevated .stat-label {
    font-size: 0.75rem;
  }
  
  .employment-types-section .d-flex {
    flex-wrap: wrap;
  }
  
  .type-pill {
    min-width: 70px;
    padding: 0.75rem 0.5rem;
  }
}

@media screen and (max-width: 992px) {
  .row.g-2.g-sm-3 > .col-6.col-md-6 {
    padding: 0.375rem;
  }
  
  .stat-card.elevated {
    min-height: auto;
  }
}

/* Zoom scaling for different screen sizes */
@media screen and (max-width: 768px) {
  .improved-bg {
    zoom: 0.95;
    -moz-transform: scale(0.95);
    -moz-transform-origin: 0 0;
  }
  
  .employment-types-container .d-flex {
    margin: 0 -0.25rem;
  }
  
  .type-pill {
    margin: 0 0.25rem;
    flex: 1 1 calc(33.333% - 0.5rem);
  }
}

@media screen and (max-width: 576px) {
  .improved-bg {
    zoom: 0.9;
    -moz-transform: scale(0.9);
    -moz-transform-origin: 0 0;
  }
  
  .row.g-2.g-sm-3 {
    margin: 0 -0.25rem;
  }
  
  .stat-card.elevated {
    margin-bottom: 0.5rem;
  }
  
  .type-pill {
    flex: 1 1 calc(50% - 0.5rem);
    margin-bottom: 0.5rem;
  }
  
  .employment-types-section .d-flex {
    flex-wrap: wrap;
    justify-content: space-between;
  }
}

@media screen and (max-width: 400px) {
  .improved-bg {
    zoom: 0.85;
    -moz-transform: scale(0.85);
    -moz-transform-origin: 0 0;
  }
  
  .type-pill {
    flex: 1 1 100%;
  }
  
  .stat-card.elevated .stat-number {
    font-size: 1.1rem;
  }
  
  .stat-card.elevated .stat-label {
    font-size: 0.7rem;
  }
}

/* Fix for Firefox since it doesn't support zoom property */
@-moz-document url-prefix() {
  .card.glass {
    transform-origin: top left;
  }
  
  @media screen and (max-width: 768px) {
    .card.glass {
      transform: scale(0.95);
    }
  }
  
  @media screen and (max-width: 576px) {
    .card.glass {
      transform: scale(0.9);
    }
  }
  
  @media screen and (max-width: 400px) {
    .card.glass {
      transform: scale(0.85);
    }
  }
}

/* Ensure content doesn't overflow */
.card.glass {
  overflow: hidden;
}

.stat-body {
  overflow: hidden;
  text-overflow: ellipsis;
}

.type-pill {
  overflow: hidden;
}

/* Smooth transitions for scaling */
.improved-bg,
.card.glass,
.stat-card.elevated,
.type-pill {
  transition: all 0.3s ease;
}

/* Dark Mode Profile Name & Stats Fixes */
body.dark-mode .profile-card h4.mb-0 {
    color: #ffffff !important;
}

body.dark-mode .stat-card.elevated .stat-number,
body.dark-mode .stat-number {
    color: #ffffff !important;
    text-shadow: none;
}

body.dark-mode .stat-card.elevated .stat-label {
    color: #a0aec0 !important;
    text-shadow: none;
}

body.dark-mode .type-pill.elevated strong {
    color: #ffffff;
}

body.dark-mode .type-pill.elevated small {
    color: #a0aec0 !important;
}
</style>
