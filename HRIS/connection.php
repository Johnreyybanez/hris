<?php
// connection.php

// InfinityFree Database credentials
$servername = "sql301.infinityfree.com"; // Replace with your actual DB host from InfinityFree
$username   = "if0_39823250";     // Your InfinityFree DB username
$password   = "37BPLd0Evf4";     // Your InfinityFree DB password
$dbname     = "if0_39823250_hris_db"; // Your InfinityFree database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
