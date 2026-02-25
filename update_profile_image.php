<?php
// Prevent any output before JSON
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new Exception($errstr);
});

session_start();

// Check if connection file exists
if (!file_exists('connection.php')) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found'
    ]);
    exit;
}

include 'connection.php';

// Check database connection
if (!isset($conn) || !is_object($conn) || $conn->connect_error) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

function handleProfileImageUpload($file, $employee_id, $login_id, $conn) {
    try {
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!isset($file) || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Validate file type using both MIME type and extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file['type'], $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Please upload JPG, PNG or GIF');
        }
        
        // Additional security check using getimagesize
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            throw new Exception('Invalid image file');
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size must be less than 5MB');
        }
        
        // Prepare upload directory
        $upload_dir = 'uploads/profile_images/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Generate filename and move file
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $employee_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Start transaction for multiple updates
        $conn->begin_transaction();

        // Update employeelogins table
        $stmt1 = $conn->prepare("UPDATE employeelogins SET image = ? WHERE login_id = ?");
        $stmt1->bind_param("si", $filepath, $login_id);
        
        // Update employees table
        $stmt2 = $conn->prepare("UPDATE employees SET photo_path = ? WHERE employee_id = ?");
        $stmt2->bind_param("si", $filepath, $employee_id);

        if (!$stmt1->execute() || !$stmt2->execute()) {
            $conn->rollback();
            unlink($filepath);
            throw new Exception('Failed to update database');
        }

        // Commit transaction
        $conn->commit();

        // Clean up old image
        $old_image_query = mysqli_query($conn, "SELECT image FROM employeelogins WHERE login_id = $login_id");
        if ($old_image_row = mysqli_fetch_assoc($old_image_query)) {
            $old_image = $old_image_row['image'];
            if ($old_image && file_exists($old_image) && $old_image !== $filepath) {
                unlink($old_image);
            }
        }

        $stmt1->close();
        $stmt2->close();

        return [
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'image_url' => $filepath
        ];

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

try {
    if (!isset($_SESSION['login_id'])) {
        throw new Exception('Not authorized');
    }

    $login_id = (int) $_SESSION['login_id'];
    // Get employee_id
    $stmt = $conn->prepare("SELECT employee_id FROM employeelogins WHERE login_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database preparation failed');
    }

    $stmt->bind_param("i", $login_id);
    if (!$stmt->execute()) {
        throw new Exception('Database query failed');
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if (!$row) {
        throw new Exception('Employee not found');
    }

    $employee_id = $row['employee_id'];
    $stmt->close();

    // Handle upload with both IDs
    $result = handleProfileImageUpload($_FILES['profile_image'] ?? null, $employee_id, $login_id, $conn);
    
    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode($result);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// Ensure no additional output
exit;
?>