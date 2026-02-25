<?php
session_start();
include 'connection.php';

if (isset($_SESSION['employee_id'])) {
    $user_id = (int) $_SESSION['_id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
