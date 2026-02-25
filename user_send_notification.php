<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';
include 'connection.php';
include 'user_smtp_config.php'; // must define $admin_app_password

ini_set('display_errors', 1);
error_reporting(E_ALL);

// === COMMON VARIABLES ===
$request_type = $_SESSION['request_type'] ?? 'Leave';
$action = $_SESSION['action'] ?? '';
$employee_id = $_SESSION['target_employee_id'] ?? null;
$notify = $_SESSION['notify'] ?? false;
$admin_username = $_SESSION['username'] ?? 'Admin';

// === Get Employee Info ===
$employee_email = '';
$employee_name = '';
if ($employee_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($first_name, $last_name, $employee_email);
        $stmt->fetch();
        $employee_name = "$first_name $last_name";
    }
    $stmt->close();
}

// === 1. EMAIL TO ADMIN: When employee submits request ===
if ($notify === true && $action === 'submitted') {
    // Get all admin emails from users table
    $result = $conn->query("SELECT email FROM users");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $admin_email = $row['email'];
            if (!empty($admin_email)) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $admin_email; // admin sender
                    $mail->Password   = $admin_app_password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom($admin_email, 'HRIS Notification');
                    $mail->addAddress($admin_email); // send to self

                    $mail->isHTML(true);
                    $mail->Subject = "New $request_type Request Submitted";
                    $mail->Body    = "
                        Dear Admin,<br><br>
                        A new <b>$request_type</b> request has been <b>submitted</b> by <b>$employee_name</b>.<br><br>
                        Please review the request in the HRIS system.<br><br>
                        Thank you.
                    ";
                    $mail->send();
                    error_log("✅ Submitted request email sent to admin: $admin_email");
                } catch (Exception $e) {
                    error_log("❌ PHPMailer error (admin notify): " . $mail->ErrorInfo);
                }
            }
        }
    }
}

// === 2. EMAIL TO EMPLOYEE: When admin/manager approves/rejects request ===
if ($notify === true && in_array($action, ['approved', 'rejected']) && !empty($employee_email)) {
    try {
        // Fetch any valid admin email to send from
        $result = $conn->query("SELECT email FROM users LIMIT 1");
        $row = $result->fetch_assoc();
        $admin_email = $row['email'] ?? null;

        if (!$admin_email || !$admin_app_password) {
            error_log("❌ Admin email/app password not set");
        } else {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $admin_email;
            $mail->Password   = $admin_app_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($admin_email, 'HRIS Notification');
            $mail->addAddress($employee_email, $employee_name);

            $mail->isHTML(true);
            $mail->Subject = "$request_type Request $action";
            $mail->Body    = "
                Dear <b>$employee_name</b>,<br><br>
                Your <b>$request_type</b> request has been <b>$action</b> by <b>$admin_username</b>.<br><br>
                Please log in to HRIS to see more details.<br><br>
                Thank you.
            ";

            $mail->send();
            error_log("✅ Request $action email sent to employee: $employee_email");
        }
    } catch (Exception $e) {
        error_log("❌ PHPMailer error (employee notify): " . $mail->ErrorInfo);
    }
}

// === CLEAR SESSION FLAGS ===
unset($_SESSION['notify'], $_SESSION['target_employee_id'], $_SESSION['request_type'], $_SESSION['action']);
?>
