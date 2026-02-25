<?php
session_start();
include 'connection.php';

if (isset($_POST['update_photo']) && isset($_FILES['photo'])) {
    $employee_id = $_POST['employee_id'];
    $photo = $_FILES['photo'];

    // Check for errors
    if ($photo['error'] === 0) {
        $upload_dir = 'uploads/profile_photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $filename = 'photo_' . $employee_id . '.' . $ext;
        $filepath = $upload_dir . $filename;

        // Move uploaded file
        if (move_uploaded_file($photo['tmp_name'], $filepath)) {
            // Update DB
            $stmt = $conn->prepare("UPDATE employees SET photo_path = ? WHERE employee_id = ?");
            $stmt->bind_param("si", $filepath, $employee_id);
            $stmt->execute();

            $_SESSION['success'] = "Profile photo updated.";
        } else {
            $_SESSION['error'] = "Failed to upload photo.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
}

header("Location: user_profile.php"); // or wherever your profile page is
exit;
