<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($profile)) {
    $conn_path = file_exists(__DIR__ . '/../connection.php') ? __DIR__ . '/../connection.php' : __DIR__ . '/connection.php';
    include_once $conn_path;

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
$photo_web_path = '../' . ltrim($photo_relative, '/');
$photo_file_path = realpath(__DIR__ . '/../' . $photo_relative);

$photo_path = (!empty($photo_relative) && $photo_file_path && file_exists($photo_file_path))
    ? $photo_web_path
    : 'assets/images/default-user.jpg';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New password and confirmation do not match.";
    } else {
        $stmt = $conn->prepare("SELECT password_hash FROM employeelogins WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($current_password, $row['password_hash'])) {
                $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE employeelogins SET password_hash = ? WHERE employee_id = ?");
                $update->bind_param("si", $hashed_new, $employee_id);
                $update->execute();
                $_SESSION['success'] = "Password updated successfully.";
            } else {
                $_SESSION['error'] = "Current password is incorrect.";
            }
        } else {
            $_SESSION['error'] = "Login record not found.";
        }
        $stmt->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container mt-4">
      <div class="card shadow" style="max-width: 900px; margin: auto;">
        <div class="row g-0">
          <div class="card shadow" style="max-width: 900px; margin: auto;">
        <div class="row g-0">
          <div class="col-md-4 text-center border-end d-flex flex-column align-items-center justify-content-center p-4 bg-light">
           <img src="<?= !empty($profile['photo_path']) ? htmlspecialchars($profile['photo_path']) : 'assets/images/default-user.jpg'; ?>"
       class="rounded-circle mb-2"
       style="width: 150px; height: 150px; object-fit: cover; ">
          <h5 class="fw-bold text-dark"><?= htmlspecialchars($fullname) ?></h5>
          </div>
          <div class="col-md-8 p-4">
            <h5 class="mb-4 fw-bold">Change Password</h5>
            <form method="POST" id="passwordForm">
              <div class="mb-3 position-relative">
                <label class="form-label">Current Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" name="current_password" class="form-control password-field"placeholder="Confirm new password" required>
                  <span class="input-group-text toggle-password" style="cursor:pointer;">
                    <i class="bi bi-eye-slash-fill"></i>
                  </span>
                </div>
              </div>
              <div class="mb-3 position-relative">
                <label class="form-label">New Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-shield-lock-fill"></i></span>
                  <input type="password" name="new_password" class="form-control password-field" placeholder="Enter new password" required>
                  <span class="input-group-text toggle-password" style="cursor:pointer;">
                    <i class="bi bi-eye-slash-fill"></i>
                  </span>
                </div>
              </div>
              <div class="mb-3 position-relative">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-shield-check"></i></span>
                  <input type="password" name="confirm_password" class="form-control password-field" placeholder="Confirm new password" required>
                  <span class="input-group-text toggle-password" style="cursor:pointer;">
                    <i class="bi bi-eye-slash-fill"></i>
                  </span>
                </div>
              </div>
              <button type="submit" name="change_password" class="btn btn-primary w-100">
                <i class="bi bi-shield-lock"></i> Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SweetAlert Feedback -->
<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $_SESSION['success'] ?>',
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire({
  icon: 'error',
  title: 'Error!',
  text: '<?= $_SESSION['error'] ?>',
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000
});
</script>
<?php unset($_SESSION['error']); endif; ?>

<!-- Password Toggle Script -->
<script>
document.querySelectorAll('.toggle-password').forEach(toggle => {
  toggle.addEventListener('click', () => {
    const input = toggle.parentElement.querySelector('.password-field');
    const icon = toggle.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
    }
  });
});
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Bootstrap JS & Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background-color:whitesmoke;
}
.pc-container {
    padding-top: 60px;
}
.pc-content {
    padding-left: 240px;
}
</style>
