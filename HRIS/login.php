<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = strtolower(trim($_POST['role'])); // normalize role

    if ($role === 'admin') {
        // 🔐 Admin Login (with prepared statement)
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['image'] = $user['image'] ?: 'default.png';
                $_SESSION['login_success'] = true;
                $_SESSION['redirect_url'] = "dashboard.php";

                $msg = "Admin {$user['username']} logged in.";
                mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ({$user['user_id']}, '" . mysqli_real_escape_string($conn, $msg) . "')");
            } else {
                $error = "Invalid password for Admin.";
            }
        } else {
            $error = "Admin account not found.";
        }
        $stmt->close();

    } elseif (in_array($role, ['employee', 'manager'])) {
        // 🔐 Employee or Manager Login
        $stmt = $conn->prepare("SELECT * FROM employeelogins WHERE username = ? AND role = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $login = $result->fetch_assoc();
            if (password_verify($password, $login['password_hash'])) {
                $_SESSION['user_id'] = $login['employee_id'] ?: $login['manager_id'];
                $_SESSION['username'] = $login['username'];
                $_SESSION['role'] = strtolower($login['role']);
                $_SESSION['image'] = $login['image'] ?: 'default.png';
                $_SESSION['login_success'] = true;
                $_SESSION['login_id'] = $login['login_id']; // ✅ Needed for audit/OB approval

                $_SESSION['redirect_url'] = ($_SESSION['role'] === 'manager') ? "manager_dashboard.php" : "user_dashboard.php";

                // Update last login
                $now = date('Y-m-d H:i:s');
                $update = $conn->prepare("UPDATE employeelogins SET last_login = ? WHERE login_id = ?");
                $update->bind_param("si", $now, $login['login_id']);
                $update->execute();
                $update->close();

            } else {
                $error = "Invalid password for " . ucfirst($role) . ".";
            }
        } else {
            $error = ucfirst($role) . " account not found or inactive.";
        }
        $stmt->close();
    } else {
        $error = "Invalid role selected.";
    }
}
?>



<?php include 'style.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<body>
<?php include 'preloader.php'; ?>
<div class="auth-main relative" style="font-family: 'Times New Roman', Times, serif;">
  <div class="auth-wrapper v1 flex items-center w-full h-full">
    <div class="auth-form flex items-center justify-center grow flex-col relative p-6" style="min-height: 600px;"> <!-- 🔥 Set min-height smaller -->
      <div class="w-full max-w-[350px] relative">
        <div class="auth-bg">
          <span class="absolute top-[-100px] right-[-100px] w-[300px] h-[300px] block rounded-full bg-theme-bg-1 animate-[floating_7s_infinite]"></span>
          <span class="absolute top-[150px] right-[-150px] w-5 h-5 block rounded-full bg-primary-500 animate-[floating_9s_infinite]"></span>
          <span class="absolute left-[-150px] bottom-[150px] w-5 h-5 block rounded-full bg-theme-bg-1 animate-[floating_7s_infinite]"></span>
          <span class="absolute left-[-100px] bottom-[-100px] w-[300px] h-[300px] block rounded-full bg-theme-bg-2 animate-[floating_9s_infinite]"></span>
        </div>
        <div class="card sm:my-12 w-full shadow-lg overflow-hidden rounded">
          <div class="text-center p-5" style="background-color: #00016b; color: white;">
            <img src="logo.png" alt="Logo" style="max-width: 150px; margin: 0 auto;" class="mb-2">
          </div>

          <div class="card-body !p-8">
            <?php if (isset($error)): ?>
              <div class="alert alert-danger text-center mb-3" style="color: red;">
                <?= htmlspecialchars($error) ?>
              </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
              <!-- Username -->
              <label for="username" class="form-label">Username</label>
              <div class="mb-3" style="position: relative;">
                <i class="fa fa-user" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #999;"></i>
                <input type="text" class="form-control" name="username" placeholder="Enter username" required style="padding-left: 2.2rem;" />
              </div>

              <!-- Password -->
              <label for="password" class="form-label">Password</label>
              <div class="mb-3" style="position: relative;">
                <i class="fa fa-lock" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #999;"></i>
                <input type="password" class="form-control" name="password" id="password" placeholder="Enter password" required style="padding-left: 2.2rem; padding-right: 2.2rem;" />
                <i class="fa fa-eye" id="togglePassword" style="position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: #999;"></i>
              </div>

              <!-- Role -->
              <label for="role" class="form-label">Select Role</label>
              <div class="mb-4" style="position: relative;">
                <i class="fa fa-user-tag" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #999;"></i>
                <select name="role" id="role" class="form-select" required style="padding-left: 2.2rem;">
                  <option value="">-- Select Role --</option>
                  <option value="admin">Admin</option>
                  <option value="employee">Employee</option>
                  <option value="manager">Manager</option>
                </select>
              </div>

              <!-- Remember & Forgot -->
              <div class="flex mt-1 justify-between items-center flex-wrap">
                <div class="form-check">
                  <input class="form-check-input input-primary" type="checkbox" id="remember" />
                  <label class="form-check-label text-muted" for="remember">Remember me?</label>
                </div>
                <h6 class="font-normal text-primary-500 mb-0"><a href="#">Forgot Password?</a></h6>
              </div>

              <!-- Login Button -->
              <div class="mt-4 text-center">
                <button type="submit" name="login" class="btn btn-primary mx-auto shadow-2xl w-full">Login</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Toggle Password Script -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      this.classList.toggle("fa-eye");
      this.classList.toggle("fa-eye-slash");
    });
  });
</script>

<!-- SweetAlert Success -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_SESSION['login_success']) && isset($_SESSION['redirect_url'])): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Login Successful!',
    text: 'Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!',
    confirmButtonColor: '#3085d6'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "<?= $_SESSION['redirect_url'] ?>";
    }
  });
</script>
<?php
unset($_SESSION['login_success']);
unset($_SESSION['redirect_url']);
?>
<?php endif; ?>

</body>
</html>
