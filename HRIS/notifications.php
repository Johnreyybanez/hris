<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'head.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<body>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<!-- Centered Content -->
<div class="container d-flex justify-content-center align-items-start" style="min-height: 100vh; padding-top: 100px;">
  <div class="card shadow-lg w-100" style="max-width: 700px;">
    <div class="card-header bg-primary text-white text-center">
      <h5 class="mb-0">All Notifications</h5>
    </div>

    <div class="overflow-auto" style="max-height: 75vh;">
      <ul class="list-group list-group-flush">
        <?php if (mysqli_num_rows($result) > 0): ?>
          <?php while ($notif = mysqli_fetch_assoc($result)): ?>
            <li class="list-group-item <?= $notif['is_read'] ? '' : 'bg-light' ?>">
              <div class="d-flex align-items-start">
                <div class="me-3">
                  <div class="user-avtar bg-light-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-envelope text-success"></i>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <p class="mb-1 text-dark fw-semibold"><?= htmlspecialchars($notif['message']) ?></p>
                  <small class="text-muted"><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></small>
                </div>
              </div>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="list-group-item text-muted text-center">No notifications found.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

</body>
</html>
