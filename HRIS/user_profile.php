<?php
session_start();
include 'connection.php';

// Function to get last login
function getLastLogin($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT last_login FROM employeelogins WHERE employee_id = ? ORDER BY last_login DESC LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['last_login']);
        return $date->format("F d, Y \\a\\t h:i A");
    }

    return "Not available";
}

$employee_id = $_SESSION['user_id'];

// Fetch employee profile
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$profile = $result->fetch_assoc()) {
    die("Employee profile not found.");
}
$stmt->close();

// Format full name
$middle_initial = !empty($profile['middle_name']) ? strtoupper(substr($profile['middle_name'], 0, 1)) . '. ' : '';
$fullname = $profile['first_name'] . ' ' . $middle_initial . $profile['last_name'];

// Last login display
$last_login_display = getLastLogin($conn, $employee_id);
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container-fluid py-4">
      <div class="row">
        <!-- LEFT COLUMN: PROFILE -->
        <div class="col-md-4">
          <div class="card text-center shadow-sm mb-4">
            <div class="card-body">
              <img src="<?= !empty($profile['photo_path']) ? htmlspecialchars($profile['photo_path']) : 'assets/images/default-user.jpg'; ?>"
                   class="rounded-circle border mb-3"
                   style="width: 140px; height: 140px; object-fit: cover;">
              <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($fullname) ?></h5>
              <p class="text-muted"><?= htmlspecialchars($profile['email']) ?></p>
              <hr>
              <h6 class="text-primary">About</h6>
              <p class="text-muted small">
                Employee No. <?= htmlspecialchars($profile['employee_number']) ?><br>
                Status: <span class="badge bg-<?= $profile['status'] === 'Active' ? 'success' : 'secondary' ?>">
                  <?= htmlspecialchars($profile['status']) ?>
                </span>
              </p>
              <p class="text-muted small">Last Login: <?= htmlspecialchars($last_login_display) ?></p>
            </div>
          </div>

<!-- 👇 Government IDs -->
<div class="card shadow-sm mt-4">
  <div class="card-body">
    <h5 class="text-primary border-bottom pb-2">Government IDs</h5>
    <div class="row g-3">

      <?php
      $gov_ids_q = mysqli_query($conn, "SELECT * FROM EmployeeGovernmentIDs WHERE employee_id = '$employee_id'");
      $row = mysqli_fetch_assoc($gov_ids_q);
      $sss = $row['sss_number'] ?? '—';
      $philhealth = $row['philhealth_number'] ?? '—';
      $pagibig = $row['pagibig_number'] ?? '—';
      $tin = $row['tin_number'] ?? '—';
      ?>

      <div class="col-md-6">
        <div class="d-flex align-items-center border rounded p-3 shadow-sm">
          <img src="https://upload.wikimedia.org/wikipedia/commons/4/4f/Social_Security_System_%28SSS%29.svg" alt="SSS Icon" width="40" height="40" class="me-3">
          <div>
            <h6 class="mb-0">SSS Number</h6>
            <small class="text-muted"><?= htmlspecialchars($sss) ?></small>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="d-flex align-items-center border rounded p-3 shadow-sm">
          <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRTpeWGac8GtONJUkdu1AOwIYZjx1oDoJFwNQ&s" alt="PhilHealth Icon" width="40" height="40" class="me-3">
          <div>
            <h6 class="mb-0">PhilHealth Number</h6>
            <small class="text-muted"><?= htmlspecialchars($philhealth) ?></small>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="d-flex align-items-center border rounded p-3 shadow-sm">
          <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5b/Pag-IBIG.svg/1894px-Pag-IBIG.svg.png" alt="Pag-IBIG Icon" width="40" height="40" class="me-3">
          <div>
            <h6 class="mb-0">Pag-IBIG Number</h6>
            <small class="text-muted"><?= htmlspecialchars($pagibig) ?></small>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="d-flex align-items-center border rounded p-3 shadow-sm">
          <img src="https://upload.wikimedia.org/wikipedia/commons/5/54/Bureau_of_Internal_Revenue_%28BIR%29.svg" alt="TIN Icon" width="40" height="40" class="me-3">
          <div>
            <h6 class="mb-0">TIN Number</h6>
            <small class="text-muted"><?= htmlspecialchars($tin) ?></small>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<!-- 👆 End Government IDs -->

        </div>

        <!-- RIGHT COLUMN: DETAILS -->
        <div class="col-md-8">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="text-primary border-bottom pb-2">Personal Details</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Full Name</label>
                  <p><?= htmlspecialchars($fullname) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Email</label>
                  <p><?= htmlspecialchars($profile['email']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Phone</label>
                  <p><?= htmlspecialchars($profile['phone']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Gender</label>
                  <p><?= htmlspecialchars($profile['gender']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Birth Date</label>
                  <p><?= htmlspecialchars($profile['birth_date']) ?></p>
                </div>
              </div>

              <h5 class="text-primary border-bottom pb-2">Address</h5>
              <div class="row mb-3">
                <div class="col-md-12">
                  <label class="form-label fw-bold">Address</label>
                  <p><?= nl2br(htmlspecialchars($profile['address'])) ?></p>
                </div>
              </div>

              <h5 class="text-primary border-bottom pb-2">Employment Details</h5>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Biometric ID</label>
                  <p><?= htmlspecialchars($profile['biometric_id'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Date Regular</label>
                  <p><?= !empty($profile['date_regular']) ? htmlspecialchars(date('F d, Y', strtotime($profile['date_regular']))) : 'N/A' ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Hire Date</label>
                  <p><?= htmlspecialchars($profile['hire_date']) ?></p>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Years of Service</label>
                  <p><?= htmlspecialchars($profile['total_years_service']) ?> year(s)</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- END RIGHT COLUMN -->
      </div>
    </div>
  </div>
</div>
