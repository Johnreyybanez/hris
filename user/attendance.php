<?php
session_start();
include '../connection.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../login.php");
    exit();
}

// Fetch DTR
$dtr_result = mysqli_query($conn, "SELECT * FROM attendance WHERE user_id='$user_id' ORDER BY date DESC");

// Fetch Shift Schedule (Example table: shift_schedule)
$schedule_result = mysqli_query($conn, "SELECT * FROM shift_schedule WHERE user_id='$user_id'");

// Fetch Missing Log Requests (Example table: missing_logs)
$missing_logs_result = mysqli_query($conn, "SELECT * FROM missing_logs WHERE user_id='$user_id' ORDER BY request_date DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Time Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">

    <h2>Time Attendance</h2>

    <!-- DTR Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Daily Time Records (DTR)</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th></tr></thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($dtr_result)): ?>
                    <tr>
                        <td><?= $row['date']; ?></td>
                        <td><?= $row['time_in']; ?></td>
                        <td><?= $row['time_out']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Shift Schedule -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">Shift Schedule</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead><tr><th>Day</th><th>Start Time</th><th>End Time</th></tr></thead>
                <tbody>
                <?php while($shift = mysqli_fetch_assoc($schedule_result)): ?>
                    <tr>
                        <td><?= $shift['day']; ?></td>
                        <td><?= $shift['start_time']; ?></td>
                        <td><?= $shift['end_time']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Submit Missing Time Log Request -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">Submit Missing Time Log Request</div>
        <div class="card-body">
            <form action="submit_missing_log.php" method="POST">
                <div class="mb-3">
                    <label>Date:</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Reason:</label>
                    <textarea name="reason" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- Missing Log Request History -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Missing Log Request History</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead><tr><th>Date</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                <?php while($req = mysqli_fetch_assoc($missing_logs_result)): ?>
                    <tr>
                        <td><?= $req['date']; ?></td>
                        <td><?= $req['reason']; ?></td>
                        <td><?= ucfirst($req['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
