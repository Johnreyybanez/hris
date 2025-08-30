<?php
session_start();

// Redirect to login.php if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit(); // Always exit after a header redirect
}
?>
