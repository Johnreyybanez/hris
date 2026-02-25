<?php
session_start();
include 'connection.php';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = strtolower(trim($_POST['role'])); // normalize role
    // Input validation
    if (empty($username) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif ($role === 'admin') {
        // 🔐 Admin Login (with prepared statement)
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        if ($stmt) {
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
                    // Fixed SQL injection vulnerability in notifications
                    $msg = "Admin " . $user['username'] . " logged in.";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    if ($notif_stmt) {
                        $notif_stmt->bind_param("is", $user['user_id'], $msg);
                        $notif_stmt->execute();
                        $notif_stmt->close();
                    }
                } else {
                    $error = "Invalid password for Admin.";
                }
            } else {
                $error = "Admin account not found.";
            }
            $stmt->close();
        } else {
            $error = "Database error occurred.";
        }
    } elseif (in_array($role, ['employee', 'manager'])) {
        // 🔐 Employee or Manager Login
        $stmt = $conn->prepare("SELECT * FROM employeelogins WHERE username = ? AND role = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $username, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $login = $result->fetch_assoc();
                if (password_verify($password, $login['password_hash'])) {
                    $_SESSION['user_id'] = ($role === 'manager') ? $login['employee_id'] : $login['employee_id'];
                    $_SESSION['username'] = $login['username'];
                    $_SESSION['role'] = strtolower($login['role']);
                    $_SESSION['image'] = $login['image'] ?: 'default.png';
                    $_SESSION['login_success'] = true;
                    $_SESSION['login_id'] = $login['login_id'];
                    $_SESSION['redirect_url'] = ($_SESSION['role'] === 'manager') ? "manager_dashboard.php" : "user_dashboard.php";
                    // Update last login
                    $now = date('Y-m-d H:i:s');
                    $update = $conn->prepare("UPDATE employeelogins SET last_login = ? WHERE login_id = ?");
                    if ($update) {
                        $update->bind_param("si", $now, $login['login_id']);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    $error = "Invalid password for " . ucfirst($role) . ".";
                }
            } else {
                $error = ucfirst($role) . " account not found or inactive.";
            }
            $stmt->close();
        } else {
            $error = "Database error occurred.";
        }
    } else {
        $error = "Invalid role selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS Login - Municipality of Aloguinsan</title>
    <?php include 'style.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: url('back.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: "Times New Roman", Times, serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 15, 15, 0.3), rgba(7, 7, 7, 0.4));
            z-index: 0;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg);
                opacity: 0.7;
            }

            50% {
                transform: translateY(-30px) translateX(20px) rotate(180deg);
                opacity: 0.3;
            }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
.login-card {
    position: relative;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* TOP STRIP */
.login-card::before {
    content: "";
    position: absolute;
    top: -26px;
    left: -40px;
    width: 140%;
    height: 17px;
    background: linear-gradient(90deg, #06b6d4, #06b6d4);
    transform: rotate(-6deg);
}
/* BOTTOM STRIP */
.login-card::after {
    content: "";
    position: absolute;
    bottom: -18px;        /* pulled up a bit */
    left: -35px;          /* better alignment */
    width: 150%;          /* full diagonal coverage */
    height: 18px;         /* cleaner thickness */
    background: linear-gradient(90deg, #06b6d4, #06b6d4);
    transform: rotate(-6deg);
    pointer-events: none;
    box-shadow: 0 0 18px rgba(6, 182, 212, 0.55);

}



        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 60px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.13), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(10px);
            text-align: center;
            padding: 18px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .logo-container {
            position: relative;
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo {
            width: 1000px;
            height: 120px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo img {
            width: 150px;
            height: 140px;
            margin: auto;
        }

        .system-title {
            color: white;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .municipality-title {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            font-weight: 500;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .login-form {
            padding: 18px 12px;
        }

        .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .input-wrapper {
            position: relative;
        }

        .form-input,
        .form-select {
            width: 100%;
            height: 38px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 0 14px 0 36px;
            font-size: 12px;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }

        .form-select option {
            background: rgba(42, 42, 43, 0.95);
            color: white;
            padding: 10px;
        }

        .form-input:focus,
        .form-select:focus {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1), 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .form-input:focus+.input-icon,
        .form-select:focus+.input-icon {
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            z-index: 3;
        }

        .toggle-password:hover {
            color: rgba(255, 255, 255, 0.9);
            transform: translateY(-50%) scale(1.1);
        }

        .form-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .form-check {
            display: flex;
            align-items: center;
        }

        .form-check-input {
            width: 16px;
            height: 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            margin-right: 8px;
        }

        .form-check-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            cursor: pointer;
        }

        .forgot-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: rgba(255, 255, 255, 1);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        .login-btn {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            margin-top: 20px;
            font-family: 'Poppins', sans-serif;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.15));
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert {
            background: rgba(220, 53, 69, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }

        /* Chatbot Styles */
        .chatbot-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .chatbot-toggle:hover {
            transform: scale(1.1);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.15));
        }

        .chatbot-toggle i {
            color: white;
            font-size: 24px;
        }

        .chatbot-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 300px;
            height: 400px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.17);
            display: none;
            flex-direction: column;
            z-index: 1000;
            font-family: 'Poppins', sans-serif;
        }

        .chatbot-container.active {
            display: flex;
        }

        .chatbot-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.13), rgba(255, 255, 255, 0.05));
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .chatbot-body {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            color: white;
            font-size: 13px;
        }

        .chatbot-message {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
        }

        .bot .message-bubble {
            background: rgba(0, 1, 107, 0.3);
            color: white;
            margin-left: 50px;
            border-bottom-right-radius: 4px;
        }

        .user .message-bubble {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            margin-right: 50px;
            margin-left: auto;
            text-align: right;
            border-bottom-left-radius: 4px;
        }

        .user .message-avatar {
            margin-right: 0;
            margin-left: 10px;
            order: 2;
        }

        .typing-indicator {
            color: rgba(255, 255, 255, 0.8);
            font-style: italic;
        }

        .dots::after {
            content: '';
            animation: dots 1.5s infinite;
        }

        @keyframes dots {

            0%,
            20% {
                content: '';
            }

            40% {
                content: '.';
            }

            60% {
                content: '..';
            }

            80%,
            100% {
                content: '...';
            }
        }

        .chatbot-footer {
            padding: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
        }

        .chatbot-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 8px;
            color: white;
            font-size: 12px;
            outline: none;
        }

        .chatbot-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .chatbot-send {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            padding: 8px;
            margin-left: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .chatbot-send:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.15));
            transform: scale(1.1);
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 380px;
                padding: 15px;
            }

            .login-form {
                padding: 25px 20px;
            }

            .form-input,
            .form-select,
            .login-btn {
                height: 45px;
            }

            .system-title {
                font-size: 18px;
            }

            .municipality-title {
                font-size: 14px;
            }

            .chatbot-container {
                width: 250px;
                height: 350px;
            }
        }
    </style>
</head>

<body>
    <!-- Floating particles -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <!-- Chatbot -->
    <div class="chatbot-toggle" id="chatbotToggle">
        <i class="fas fa-comment"></i>
    </div>
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">HRIS Assistant</div>
        <div class="chatbot-body" id="chatbotBody">
            <div class="chatbot-message bot">
                <img src="https://img.freepik.com/free-vector/cute-robot-holding-speech-bubble-chatbot_23-2149150119.jpg"
                    alt="Bot Avatar" class="message-avatar">
                <div class="message-bubble">
                    <span class="message-text">Hello! 👋 I'm here to help with HRIS login issues or questions. Ask away!
                        💼🔑</span>
                </div>
            </div>
        </div>
        <div class="chatbot-footer">
            <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Type your message...">
            <button class="chatbot-send" id="chatbotSend"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header ">
                <div class="logo-container">
                    <div class="logo">
                        <img src="Hris.png" alt="Logo">
                    </div>
                </div>
                <h1 class="system-title">HRIS Management System</h1>
                <p class="municipality-title">Biometrix System & Trading Corp.</p>
            </div>
            <!-- Form -->
            <div class="login-form">
                <!-- Replace the error alert div section with this -->
                <?php if (isset($error)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'Login Failed',
                                html: `
            <div style="padding: 10px;">
                <p style="
                    font-size: 16px;
                    color: #555;
                    margin: 10px 0;
                    line-height: 1.6;
                ">
                    <?= htmlspecialchars($error) ?>
                </p>
            </div>
        `,
                                confirmButtonText: '<i class="fas fa-redo"></i> Try Again',
                                confirmButtonColor: '#dc3545',
                                backdrop: `
            rgba(220, 53, 69, 0.1)
            left top
            no-repeat
        `,
                                customClass: {
                                    popup: 'error-popup',
                                    title: 'error-title',
                                    confirmButton: 'error-confirm-btn'
                                },
                                showClass: {
                                    popup: 'animate__animated animate__shakeX animate__faster'
                                },
                                didOpen: () => {
                                    // Optional: Add error sound
                                    // new Audio('assets/sounds/error.mp3').play();

                                    const style = document.createElement('style');
                                    style.textContent = `
                .error-popup {
                    border-radius: 20px !important;
                    box-shadow: 0 20px 60px rgba(220, 53, 69, 0.2) !important;
                    background: rgba(255, 255, 255, 0.95) !important;
                    backdrop-filter: blur(10px) !important;
                }
                
                .error-title {
                    font-size: 24px !important;
                    font-weight: 700 !important;
                    color: #dc3545 !important;
                }
                
                .error-confirm-btn {
                    border-radius: 25px !important;
                    padding: 12px 35px !important;
                    font-weight: 600 !important;
                    font-size: 15px !important;
                    transition: all 0.3s ease !important;
                    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3) !important;
                }
                
                .error-confirm-btn:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4) !important;
                    background-color: #c82333 !important;
                }
            `;
                                    document.head.appendChild(style);
                                }
                            });
                        });
                    </script>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <!-- Username -->
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <div class="input-wrapper">
                            <input type="text" class="form-input" name="username" id="username"
                                placeholder="Enter your username" required
                                value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" class="form-input" name="password" id="password"
                                placeholder="Enter your password" required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                    </div>
                    <!-- Role -->
                    <div class="form-group">
                        <label for="role" class="form-label">
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <div class="input-wrapper">
                            <select name="role" id="role" class="form-select" required>
                                <option value="">-- Select Role --</option>
                                <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="employee" <?= (isset($_POST['role']) && $_POST['role'] == 'employee') ? 'selected' : '' ?>>Employee</option>
                                <option value="manager" <?= (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : '' ?>>Manager</option>
                            </select>
                            <i class="fas fa-user-tag input-icon"></i>
                        </div>
                    </div>
                    <!-- Controls -->
                    <div class="form-controls">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-link">Forgot Password?</a>
                    </div>
                    <!-- Login Button -->
                    <button type="submit" name="login" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Password toggle
            const togglePassword = document.querySelector("#togglePassword");
            const password = document.querySelector("#password");
            if (togglePassword && password) {
                togglePassword.addEventListener("click", function () {
                    const type = password.getAttribute("type") === "password" ? "text" : "password";
                    password.setAttribute("type", type);
                    this.classList.toggle("fa-eye");
                    this.classList.toggle("fa-eye-slash");
                });
            }
            // Add floating animation to inputs
            const inputs = document.querySelectorAll('.form-input, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function () {
                    if (this.parentElement) {
                        this.parentElement.style.transform = 'translateY(-2px)';
                    }
                });
                input.addEventListener('blur', function () {
                    if (this.parentElement) {
                        this.parentElement.style.transform = 'translateY(0)';
                    }
                });
            });
            // Chatbot toggle
            const chatbotToggle = document.querySelector("#chatbotToggle");
            const chatbotContainer = document.querySelector("#chatbotContainer");
            if (chatbotToggle && chatbotContainer) {
                chatbotToggle.addEventListener("click", function () {
                    chatbotContainer.classList.toggle("active");
                });
            }
            // Chatbot send message
            const chatbotInput = document.querySelector("#chatbotInput");
            const chatbotSend = document.querySelector("#chatbotSend");
            const chatbotBody = document.querySelector("#chatbotBody");
            if (chatbotSend && chatbotInput && chatbotBody) {
                async function sendMessage() {
                    const message = chatbotInput.value.trim();
                    if (!message) return;
                    // Add user message
                    addMessage(message, 'user', 'https://img.freepik.com/free-photo/happy-smiling-young-adult-caucasian-man_176420-10231.jpg');
                    chatbotInput.value = "";
                    chatbotBody.scrollTop = chatbotBody.scrollHeight;
                    // Show typing indicator
                    const typingMsg = addTypingMessage('bot', 'https://img.freepik.com/free-vector/cute-robot-holding-speech-bubble-chatbot_23-2149150119.jpg');
                    // Simulate bot response
                    setTimeout(async () => {
                        chatbotBody.removeChild(typingMsg);
                        try {
                            const response = await fetch("chatbot.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({ message })
                            });
                            const data = await response.json();
                            addMessage(data.response || data.error || "Sorry, something went wrong.", 'bot', 'https://img.freepik.com/free-vector/cute-robot-holding-speech-bubble-chatbot_23-2149150119.jpg');
                        } catch (error) {
                            addMessage("Error: Unable to connect.", 'bot', 'https://img.freepik.com/free-vector/cute-robot-holding-speech-bubble-chatbot_23-2149150119.jpg');
                        }
                        chatbotBody.scrollTop = chatbotBody.scrollHeight;
                    }, 1500 + Math.random() * 2000);
                }
                chatbotSend.addEventListener("click", sendMessage);
                chatbotInput.addEventListener("keypress", function (e) {
                    if (e.key === "Enter") sendMessage();
                });
                function addMessage(text, sender, avatarUrl) {
                    const messageDiv = document.createElement("div");
                    messageDiv.className = `chatbot-message ${sender}`;
                    messageDiv.innerHTML = `
                        <img src="${avatarUrl}" alt="${sender} Avatar" class="message-avatar">
                        <div class="message-bubble">
                            <span class="message-text">${text}</span>
                        </div>
                    `;
                    chatbotBody.appendChild(messageDiv);
                    return messageDiv;
                }
                function addTypingMessage(sender, avatarUrl) {
                    const typingDiv = document.createElement("div");
                    typingDiv.className = `chatbot-message ${sender}`;
                    typingDiv.innerHTML = `
                        <img src="${avatarUrl}" alt="${sender} Avatar" class="message-avatar">
                        <div class="message-bubble">
                            <span class="typing-indicator">Typing<span class="dots"></span></span>
                        </div>
                    `;
                    chatbotBody.appendChild(typingDiv);
                    chatbotBody.scrollTop = chatbotBody.scrollHeight;
                    return typingDiv;
                }
            }
        });
    </script>
    <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

<?php if (!empty($_SESSION['login_success']) && !empty($_SESSION['redirect_url'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {

    Swal.fire({
        html: `
            <div style="text-align:center;padding:5px">
                <lottie-player
                    src="https://assets1.lottiefiles.com/packages/lf20_qp1q7mct.json"
                    background="transparent"
                    speed="1"
                    style="width:150px;height:150px;margin:0 auto"
                    autoplay>
                </lottie-player>

                <p class="login-title">LOGIN SUCCESSFUL!</p>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: "Let's Go!",
        confirmButtonColor: "#667eea",
        backdrop: "rgba(102,126,234,0.1)",
        customClass: {
            popup: "animated-popup",
            confirmButton: "custom-confirm-btn"
        },
        didOpen: () => {
            new Audio("assets/sounds/success.mp3").play();
        }
    }).then(() => {
        window.location.href = "<?= htmlspecialchars($_SESSION['redirect_url'], ENT_QUOTES) ?>";
    });

});
</script>

<style>
/* SweetAlert Custom Styles */
.animated-popup {
    border-radius: 20px !important;
    box-shadow: 0 20px 60px rgba(0,0,0,.15) !important;
}

.custom-confirm-btn {
    border-radius: 25px !important;
    padding: 12px 40px !important;
    font-weight: 600 !important;
    font-size: 16px !important;
    transition: .3s ease !important;
    box-shadow: 0 4px 15px rgba(102,126,234,.3) !important;
}

.custom-confirm-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(102,126,234,.4) !important;
}

.login-title {
    font-size: 28px;
    font-weight: 700;
    margin: 5px 0 10px;
    background: linear-gradient(135deg,#31e620,#095c47);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
</style>

<?php
unset($_SESSION['login_success'], $_SESSION['redirect_url']);
endif;
?>
