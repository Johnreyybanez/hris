<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$notif_id = intval($_GET['id']);

// Use prepared statement for security
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notif_id, $user_id);
$stmt->execute();
$stmt->close();

// Fetch notification securely
$stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $notif_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notif = $result->fetch_assoc();
$stmt->close();

if (!$notif) {
    echo "<div style='padding: 20px; text-align: center; color: red;'>Notification not found or access denied.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'head.php'; ?>
<body>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<div class="container mt-5">
  <div class="card mx-auto" style="max-width: 600px;">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0">Notification Details</h5>
    </div>
    <div class="card-body">
      <p class="fw-bold"><?= htmlspecialchars($notif['message']) ?></p>
      <p class="text-muted">Received on: <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></p>
      <a href="notifications.php" class="btn btn-secondary mt-3">Back to Notifications</a>
    </div>
  </div>
</div>

</body>
</html>
