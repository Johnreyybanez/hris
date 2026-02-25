<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn_path = file_exists(__DIR__ . '/../connection.php') ? __DIR__ . '/../connection.php' : __DIR__ . '/connection.php';
include_once $conn_path;

$manager_login = null;
$profile = null;

if (isset($_SESSION['login_id'])) {
    $login_id = $_SESSION['login_id'];
    $stmt = $conn->prepare("SELECT * FROM employeelogins WHERE login_id = ? LIMIT 1");
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $manager_login = $result->fetch_assoc();
    $stmt->close();

    if ($manager_login) {
        $employee_id = $manager_login['employee_id'];
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, photo_path FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();
    }
}

$middle_initial = !empty($profile['middle_name']) ? strtoupper(substr($profile['middle_name'], 0, 1)) . '. ' : '';
$fullname = $profile['first_name'] . ' ' . $middle_initial . $profile['last_name'];

$photo_relative = !empty($profile['photo_path']) ? $profile['photo_path'] : '';
$photo_web_path = '../' . ltrim($photo_relative, '/'); // Ensure proper web path
$photo_file_path = realpath(__DIR__ . '/../' . $photo_relative); // Physical path check

$photo_path = (!empty($photo_relative) && $photo_file_path && file_exists($photo_file_path))
    ? $photo_web_path
    : 'assets/images/default-user.jpg';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error_msg = "New password and confirmation do not match.";
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
                $success_msg = "Password updated successfully.";
            } else {
                $error_msg = "Current password is incorrect.";
            }
        } else {
            $error_msg = "Login record not found.";
        }
        $stmt->close();
    }
}
include 'connection.php';
include 'vendor/head.php';
include 'vendor/sidebar.php';
include 'manager_header.php';
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="container mt-4">
      
      <div class="card shadow" style="max-width: 900px; margin: auto;">
        <div class="row g-0">
          <div class="col-md-4 text-center border-end d-flex flex-column align-items-center justify-content-center p-4 bg-light">
            <div class="position-relative" style="width: 150px; height: 150px;">
              <img src="<?= !empty($profile['photo_path']) ? htmlspecialchars($profile['photo_path']) : 'assets/images/default-user.jpg'; ?>"
                   class="rounded-circle mb-2"
                   style="width: 100%; height: 100%; object-fit: cover;">
              <label for="profileImageUpload" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center rounded-circle upload-overlay" style="cursor: pointer;">
                <i class="bi bi-camera-fill text-white fs-4"></i>
                <input type="file" id="profileImageUpload" accept="image/*" style="display: none;">
              </label>
            </div>
            <h5 class="fw-bold text-dark mt-3"><?= htmlspecialchars($fullname) ?></h5>
          </div>

          <div class="col-md-8 p-4">
            <h5 class="mb-4 fw-bold">Change Password</h5>

            <?php if (isset($error_msg)): ?>
              <div class="alert alert-danger"><?= $error_msg ?></div>
            <?php elseif (isset($success_msg)): ?>
              <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>

            <form method="POST" id="passwordForm">
  <!-- Current Password -->
  <div class="mb-3 position-relative">
    <label class="form-label">Current Password</label>
    <div class="input-group">
      <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
      <input type="password" name="current_password" class="form-control password-field" placeholder="Enter current password" required>
      <span class="input-group-text toggle-password" style="cursor:pointer;">
        <i class="bi bi-eye-slash-fill"></i>
      </span>
    </div>
  </div>

  <!-- New Password -->
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

  <!-- Confirm New Password -->
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

.upload-overlay {
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.upload-overlay:hover {
    opacity: 1;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('profileImageUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!validTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Please upload a JPEG, PNG or GIF image'
            });
            return;
        }

        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Image must be less than 5MB'
            });
            return;
        }

        // Create FormData
        const formData = new FormData();
        formData.append('profile_image', file);

        // Show loading
        Swal.fire({
            title: 'Uploading...',
            text: 'Please wait while we update your profile picture',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Upload image
        fetch('update_profile_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update profile image
                const profileImage = document.querySelector('.rounded-circle.mb-2');
                profileImage.src = data.image_url + '?v=' + new Date().getTime();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Profile picture updated successfully'
                });
            } else {
                throw new Error(data.message || 'Failed to update profile picture');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        });
    }
});
</script>


