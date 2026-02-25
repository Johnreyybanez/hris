<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'] ?? null;
// Query leave balance
$query = "
    SELECT SUM(balance) AS total_balance
    FROM EmployeeLeaveCredits
    WHERE employee_id = ?
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $employee_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$total_balance = floatval($row['total_balance'] ?? 0);
mysqli_stmt_close($stmt);

// Calculate days (assuming balance is in months * 30)
$days = $total_balance * 30;

// Decide class based on balance

if (!$employee_id) {
    die("Employee not logged in.");
}
// SECOND QUERY: fetch leave balances per leave type
// Fetch leave balances per type with name
$leave_rows = [];
$leave_query = "
    SELECT elc.leave_type_id, lt.name AS leave_type_name, elc.balance
    FROM EmployeeLeaveCredits elc
    JOIN LeaveTypes lt ON elc.leave_type_id = lt.leave_type_id
    WHERE elc.employee_id = ?
";
$stmt2 = mysqli_prepare($conn, $leave_query);
mysqli_stmt_bind_param($stmt2, "i", $employee_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
while ($row2 = mysqli_fetch_assoc($result2)) {
    $leave_rows[] = $row2;
}
mysqli_stmt_close($stmt2);

// Filed Requests - Unified from Leave, OB, Overtime
$filed_requests = [];

// Leave Requests
$sql_leave = "SELECT 'Leave' AS type, requested_at AS date_filed, start_date, end_date, status FROM employeeleaverequests WHERE employee_id = ? ORDER BY requested_at DESC LIMIT 3";
$stmt = $conn->prepare($sql_leave);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $filed_requests[] = $row;
}

// Official Business
$sql_ob = "SELECT 'Official Business' AS type, requested_at AS date_filed, date AS start_date, date AS end_date, status FROM employeeofficialbusiness WHERE employee_id = ? ORDER BY requested_at DESC LIMIT 3";
$stmt = $conn->prepare($sql_ob);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $filed_requests[] = $row;
}

// Overtime
$sql_ot = "SELECT 'Overtime' AS type, created_at AS date_filed, date AS start_date, date AS end_date, approval_status AS status FROM overtime WHERE employee_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt = $conn->prepare($sql_ot);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $filed_requests[] = $row;
}

// Sort all requests by date_filed (latest first)
usort($filed_requests, function ($a, $b) {
    return strtotime($b['date_filed']) - strtotime($a['date_filed']);
});
// Defaults
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$total_notifications = 0;
$notifications = [];
$unread_count = 0;
// 1. upcoming training
if ($role === 'employee' && $user_id > 0) {
    $trainings_q = mysqli_query($conn, "
        SELECT training_id, training_title, start_date 
        FROM employeetrainings 
        WHERE employee_id = $user_id AND start_date >= CURDATE()
        ORDER BY start_date ASC 
        LIMIT 5
    ");

    while ($row = mysqli_fetch_assoc($trainings_q)) {
        $training_id = $row['training_id'];
        $training_title = $row['training_title'];
        $start_date = $row['start_date'];
        $training_link = "userupcoming_training.php?training_id=" . $training_id;

        // Find if there’s a matching unread notification
        $notif_q = mysqli_query($conn, "
           SELECT is_read 
            FROM employee_notifications 
            WHERE employee_id = $user_id 
              AND link = '$training_link' 
            LIMIT 1
        ");
        $notif = mysqli_fetch_assoc($notif_q);
        $is_read = $notif['is_read'] ?? 0;

        $notifications[] = [
            'notification_id' => $training_link,
            'message' => "Upcoming Training: {$training_title} on " . date('M d', strtotime($start_date)),
            'created_at' => $start_date,
            'is_read' => $is_read
        ];

        if ($is_read == 0) {
            $unread_count++;
        }
    }
// 2. Overtime Request Notifications (Approved & Unread Only)
$overtime_q = mysqli_query($conn, "
    SELECT overtime_id, date 
    FROM overtime 
    WHERE employee_id = $user_id AND approval_status = 'Approved'
    ORDER BY date DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($overtime_q)) {
    $ot_id = $row['overtime_id'];
    $ot_date = $row['date'];
  $ot_link = "user_overtime.php?overtime_id=" . $ot_id;


    // Check if already read
    $notif_q = mysqli_query($conn, "
        SELECT is_read 
        FROM employee_notifications 
        WHERE employee_id = $user_id 
          AND link = '$ot_link' 
        LIMIT 1
    ");
    $notif = mysqli_fetch_assoc($notif_q);

    if ($notif && $notif['is_read'] == 0) {
        $notifications[] = [
            'notification_id' => $ot_link,
            'message' => "Overtime request on " . date('M d, Y', strtotime($ot_date)) . " is Approved",
            'created_at' => $ot_date,
            'is_read' => 0
        ];
        $unread_count++;
    }
}

// 3. Official Business Notifications (Approved & Unread Only)
$ob_qe = mysqli_query($conn, "
    SELECT ob_id, date, status 
    FROM employeeofficialbusiness 
    WHERE employee_id = $user_id AND status = 'Approved'
    ORDER BY date DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($ob_qe)) {
    $ob_id = $row['ob_id'];
    $ob_date = $row['date'];
    $ob_link = "user_ob_status.php?id=" . $ob_id;

    // Check if already read
    $notif_q = mysqli_query($conn, "
        SELECT is_read 
        FROM employee_notifications 
        WHERE employee_id = $user_id 
          AND link = '$ob_link' 
        LIMIT 1
    ");
    $notif = mysqli_fetch_assoc($notif_q);
    
    if ($notif && $notif['is_read'] == 0) {
        $notifications[] = [
            'notification_id' => $ob_link,
            'message' => "Official Business on " . date('M d, Y', strtotime($ob_date)) . " is Approved",
            'created_at' => $ob_date,
            'is_read' => 0
        ];
         if ($is_read == 0) {
            $unread_count++;
    }
    
}}

// 4. Missing Time Log Notifications (Approved & Unread Only)
$missing_q = mysqli_query($conn, "
    SELECT request_id, date 
    FROM MissingTimeLogRequests 
    WHERE employee_id = $user_id AND status = 'Approved'
    ORDER BY requested_at DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($missing_q)) {
    $request_id = $row['request_id'];
    $log_date = $row['date'];
    $log_link = "user_missing_time_log.php?id=" . $request_id;

    // Only show if unread
    $notif_q = mysqli_query($conn, "
        SELECT is_read 
        FROM employee_notifications 
        WHERE employee_id = $user_id 
          AND link = '$log_link'
        LIMIT 1
    ");

    if ($notif_q && mysqli_num_rows($notif_q) > 0) {
        $notif = mysqli_fetch_assoc($notif_q);
        if ($notif['is_read'] == 0) {
            $notifications[] = [
                'notification_id' => $log_link,
                'message' => "Missing Time Log on " . date('M d', strtotime($log_date)) . " is Approved",
                'created_at' => $log_date,
                'is_read' => 0
            ];
            $unread_count++;
        }
    }
}
// 5. Leave Request Notifications (Approved & Unread Only)
$leave_q = mysqli_query($conn, "
    SELECT leave_request_id, start_date, end_date 
    FROM EmployeeLeaveRequests 
    WHERE employee_id = $user_id AND status = 'Approved'
    ORDER BY approved_at DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($leave_q)) {
    $leave_id = $row['leave_request_id'];
    $start_date = $row['start_date'];
    $end_date = $row['end_date'];
    $leave_link = "user_leave_status.php?id=" . $leave_id;

    // Check if notification already read
    $notif_q = mysqli_query($conn, "
        SELECT is_read 
        FROM employee_notifications 
        WHERE employee_id = $user_id 
          AND link = '$leave_link'
        LIMIT 1
    ");

    if ($notif_q && mysqli_num_rows($notif_q) > 0) {
        $notif = mysqli_fetch_assoc($notif_q);
        if ($notif['is_read'] == 0) {
            $notifications[] = [
                'notification_id' => $leave_link,
                'message' => "Leave from " . date('M d', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . " is Approved",
                'created_at' => $start_date,
                'is_read' => 0
            ];
            $unread_count++;
        }
    }
}

    $total_notifications = $unread_count;

    usort($notifications, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    $notifications = array_slice($notifications, 0, 5);
}

function getLastLogin($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT last_login FROM employeelogins WHERE employee_id = ? ORDER BY last_login DESC LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($row['last_login'])) {
            $date = new DateTime($row['last_login']);
            return $date->format("F d, Y \\a\\t h:i A");
        }
    }

    return "Not available";
}

$today = date('Y-m-d');
$query = "SELECT * FROM holidaycalendar WHERE date > '$today' ORDER BY date ASC LIMIT 1";
$result = mysqli_query($conn, $query);
$holiday = mysqli_fetch_assoc($result);

$holiday_text = "No upcoming holidays";
if ($holiday) {
    $formatted_date = date('F j', strtotime($holiday['date']));
    $holiday_text = "$formatted_date – {$holiday['name']}";
}

$employee_id = $_SESSION['employee_id'] ?? 0;
$last_login_display = getLastLogin($conn, $employee_id);

$todays_shift = null;
$shift_result = mysqli_query($conn, "SELECT * FROM shifts ORDER BY shift_id ASC LIMIT 1");
if ($shift_result && mysqli_num_rows($shift_result) > 0) {
    $todays_shift = mysqli_fetch_assoc($shift_result);
}

?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<!-- [ Main Content ] start -->
<div class="pc-container">
   <div class="pc-content position-relative" style="z-index: 0;">
    <!-- Company Logo Watermark -->
    <div style="
        position: absolute;
        top: 50%;
        left: 50%;
        top: 60%;
        transform: translate(-50%, -50%);
        opacity: 0.04;
        z-index: 0;
        pointer-events: none;
    ">
        <img src="asset/images/logo.webp" alt="Company Logo" style="max-width: 600px;">
    </div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="fw-bold">Employee Dashboard</h2>
                <p class="text-muted">Welcome to your HRIS Employee Portal</p>
            </div>
      <!-- DateTime Widget with adjusted height -->


<!-- Date/Time Card with crisp Analog Clock -->
<div class="card border shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-3 date-time-card"
     style="max-width: 300px; height: 80px; background: linear-gradient(to right, #f8f9fa, #ffffff); border-radius: 12px;">

  <!-- Analog Clock -->
  <canvas id="analogClock" width="10" height="10" style="background: transparent; display:block;"></canvas>

  <!-- Date & Digital Time -->
  <div class="d-flex flex-column justify-content-center">
    <span id="currentDate" class="text-dark fw-bold" style="font-size: 0.95rem;"></span>
    <span id="currentTime" class="text-muted" style="font-size: 0.85rem;"></span>
  </div>
</div>

<script>
(function () {
  const size = 60; // CSS size in px of the clock
  const canvas = document.getElementById('analogClock');

  // Make canvas crisp on high-DPI screens
  function setupCanvas() {
    const dpr = window.devicePixelRatio || 1;
    canvas.style.width = size + 'px';
    canvas.style.height = size + 'px';
    canvas.width = Math.round(size * dpr);
    canvas.height = Math.round(size * dpr);
    return dpr;
  }

  const dpr = setupCanvas();
  const ctx = canvas.getContext('2d');

  // Main render loop
  function render() {
    // Reset transform and clear the full pixel buffer
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Scale to devicePixelRatio so 1 unit = 1 CSS pixel
    ctx.scale(dpr, dpr);

    const radius = size / 2;
    ctx.translate(radius, radius); // center at 0,0 in CSS pixels

    drawFace(ctx, radius);
    drawNumbers(ctx, radius);
    drawHands(ctx, radius);

    // restore transform for next frame (we reset at top so not strictly necessary)
    ctx.setTransform(1, 0, 0, 1, 0, 0);

    requestAnimationFrame(render);
  }

  // Draw outer ring and subtle glass gradient
  function drawFace(ctx, radius) {
    // outer circle background (transparent center)
    const grad = ctx.createRadialGradient(0, 0, radius * 0.1, 0, 0, radius);
    grad.addColorStop(0, 'rgba(255,255,255,0.05)');
    grad.addColorStop(0.9, 'rgba(255,255,255,0.02)');
    grad.addColorStop(1, 'rgba(0,0,0,0.04)');

    ctx.beginPath();
    ctx.arc(0, 0, radius - 1, 0, 2 * Math.PI);
    ctx.fillStyle = 'transparent';
    ctx.fill();

    // subtle rim stroke
    ctx.lineWidth = 1.5;
    ctx.strokeStyle = '#cfcfcf';
    ctx.stroke();

    // center dot
    ctx.beginPath();
    ctx.arc(0, 0, 2.5, 0, 2 * Math.PI);
    ctx.fillStyle = '#000';
    ctx.fill();
  }

  // Draw numbers (1..12) crisply
  function drawNumbers(ctx, radius) {
   ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#fff' : '#111';

    ctx.font = Math.round(radius * 0.18) + 'px Arial';
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'center';

    for (let num = 1; num <= 12; num++) {
      const ang = num * Math.PI / 6;
      const x = Math.sin(ang) * (radius * 0.78);
      const y = -Math.cos(ang) * (radius * 0.78);
      ctx.fillText(String(num), x, y);
    }
  }

  // Draw hour, minute, second hands with smooth motion
  function drawHands(ctx, radius) {
    const now = new Date();
    const ms = now.getMilliseconds();
    const s = now.getSeconds() + ms / 1000;        // smooth second
    const m = now.getMinutes() + s / 60;
    const h = (now.getHours() % 12) + m / 60;

    // hour
    drawHand(ctx, (h * Math.PI / 6), radius * 0.52, 4, '#626060ff');

    // minute
    drawHand(ctx, (m * Math.PI / 30), radius * 0.75, 3, '#007af5');

    // second (smooth)
    drawHand(ctx, (s * Math.PI / 30), radius * 0.85, 1.6, '#d9534f');
  }

  function drawHand(ctx, pos, length, width, color) {
    ctx.beginPath();
    ctx.lineWidth = width;
    ctx.lineCap = 'round';
    ctx.strokeStyle = color;
    ctx.moveTo(0, 0);
    ctx.rotate(pos);
    ctx.lineTo(0, -length);
    ctx.stroke();
    ctx.rotate(-pos);
  }

  // Update date & DOM time (crisp text)
  function updateDateTime() {
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    document.getElementById('currentTime').textContent = now.toLocaleTimeString();
  }

  // re-setup canvas if window is resized / DPR changes
  let lastDpr = dpr;
  window.addEventListener('resize', () => {
    const newDpr = window.devicePixelRatio || 1;
    if (newDpr !== lastDpr) {
      setupCanvas();
      lastDpr = newDpr;
    }
  });

  // Start
  requestAnimationFrame(render);
  updateDateTime();
  setInterval(updateDateTime, 1000);
})();
</script>


<!-- Optional Animation CSS -->
<style>
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

.bounce-icon {
  animation: bounce 2s infinite;
}
</style>

<!-- JavaScript to update time -->
<script>
function updateDateTime() {
  const now = new Date();
  const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
  const timeOptions = { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true };

  document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
  document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
}

// Run once and then every second
updateDateTime();
setInterval(updateDateTime, 1000);
</script>



</div>
        <!-- Dashboard Cards -->
        <div class="row g-4">

           <!-- Leave Balances -->
<div class="col-sm-6 col-xl-3">
  <div class="card shadow border-0 text-center bg-primary text-white <?php echo $balance_class; ?>">
    <div class="card-body">
      <!-- Custom SVG Icon -->
      <img src="asset/images/Leave_balance.svg" alt="Leave Icon" class="mb-3" style="width: 48px; height: 48px;" />
      
      <h5 class="fw-bold">Leave Balances</h5>
     <p class="mb-0">
    <?php 
        echo rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . ($days == 1 ? '' : 's'); 
    ?>
</p>
    </div>
  </div>
</div>


           <!-- Today's Shift -->
<div class="col-sm-6 col-xl-3">
    <div class="card shadow border-0 text-center bg-success text-white">
        <div class="card-body">
            <!-- Custom SVG Clock Icon -->
            <img src="asset/images/schedule.svg" alt="Leave Icon" class="mb-3" style="width: 48px; height: 48px;" />
      

            <h5 class="fw-bold" style="color: black;">Today's Shift</h5>
            <?php if ($todays_shift): ?>
                <p class="mb-0" style="color: #f8f9fa;">
                    <?= date('h:i A', strtotime($todays_shift['time_in'])) ?> -
                    <?= date('h:i A', strtotime($todays_shift['time_out'])) ?>
                </p>
            <?php else: ?>
                <p class="mb-0" style="color: #f8f9fa;">No shift found</p>
            <?php endif; ?>
        </div>
    </div>
</div>

           <!-- Upcoming Holiday -->
<div class="col-sm-6 col-xl-3">
    <div class="card shadow border-0 text-center bg-warning text-white">
        <div class="card-body">
            <!-- Custom SVG Holiday Icon -->
                  <img src="asset/images/holiday.svg" alt="Leave Icon" class="mb-3" style="width: 48px; height: 48px;" />


            <h5 class="fw-bold" style="color: black;">Upcoming Holiday</h5>
            <p class="mb-0" style="color: #f8f9fa;"><?= htmlspecialchars($holiday_text); ?></p>
        </div>
    </div>
</div>

            <!-- Notifications -->
<div class="col-sm-6 col-xl-3">
    <div class="card shadow border-0 text-center bg-danger text-white">
        <div class="card-body">
            <!-- Custom SVG Bell Icon -->
             <img src="asset/images/notification.svg" alt="Leave Icon" class="mb-3" style="width: 48px; height: 48px;" />

            <h5 class="fw-bold" style="color: black;">Notifications</h5>
            <p class="mb-0" style="color: #f8f9fa;">
                <?= $total_notifications > 0 
                    ? "$total_notifications New Notification" . ($total_notifications > 1 ? "s" : "") 
                    : "No New Notifications" ?>
            </p>
        </div>
    </div>
</div>


 <!-- Row containing two widgets side by side -->
<div class="row mt-4">
    <!-- My Filed Requests Widget (Left Side) -->
    <div class="col-md-6 mb-3 ">
        <div class="card shadow border-0 h-100 filed-requests-card" style="height: 400px;"> <!-- fixed height -->
            <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 fw-semibold text-dark">My Filed Requests</h5>
                    <small class="text-muted">Latest filed leave, OB, or overtime requests and their statuses</small>
                </div>
            </div>
            <div class="card-body px-3 py-2 bg-white text-dark overflow-auto" style="max-height: 320px;"> <!-- scrollable -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="text-uppercase bg-white border-bottom">
                            <tr>
                                <th class="text-dark">Request Type</th>
                                <th class="text-dark">Date Filed</th>
                                <th class="text-dark">Date Range</th>
                                <th class="text-dark">Status</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <?php if (empty($filed_requests)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No filed requests.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filed_requests as $req): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($req['type']) ?></td>
                                        <td><?= date("F j, Y", strtotime($req['date_filed'])) ?></td>
                                        <td>
                                            <?= date("M j", strtotime($req['start_date'])) ?>
                                            <?= ($req['start_date'] != $req['end_date']) ? ' - ' . date("j, Y", strtotime($req['end_date'])) : '' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = strtolower($req['status']);
                                            $badge_class = match ($status) {
                                                'pending' => 'bg-warning',
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger',
                                                default => 'bg-secondary',
                                            };
                                            ?>
                                            <span class="badge <?= $badge_class ?> text-white"><?= ucfirst($status) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Balance Widget (Right Side) -->

<!-- Leave Balance Table -->
<div class="col-md-6 mb-3">
    <div class="card shadow border-0 h-100 filed-requests-card" style="height: 400px;">
        <div class="card-header bg-light border-bottom">
            <h5 class="mb-1 fw-semibold text-dark">Leave Balance</h5>
            <small class="text-muted">Description: Vacation Leave, Sick Leave, etc. with remaining days</small>
        </div>
        <div class="card-body px-3 py-2 bg-white text-dark overflow-auto" style="max-height: 320px;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-uppercase bg-white border-bottom">
                        <tr>
                            <th class="text-dark">Leave Type</th>
                            <th class="text-dark text-center">Remaining Days</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php if (!empty($leave_rows)): ?>
        <?php foreach ($leave_rows as $leave): 
            $balance = floatval($leave['balance']);

            if ($balance <= 2) {
                $balance_class = 'balance-low';
            } elseif ($balance <= 5) {
                $balance_class = 'balance-medium';
            } else {
                $balance_class = 'balance-high';
            }
        ?>
        <tr>
            <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
           <td class="<?php echo $balance_class; ?> text-center"> 
    <?php 
        // Convert months to days (assuming 30 days in a month)
        $days = $balance * 30;

        // Show .5 if applicable, but remove unnecessary .0
        echo rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . ($days == 1 ? '' : 's'); 
    ?>
</td>

        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="2" class="text-center text-muted">No leave records found</td>
        </tr>
    <?php endif; ?>
</tbody>


                </table>
            </div>
        </div>
    </div>
</div>

<style>
.balance-low { color: #d9534f; font-weight: bold; }
.balance-medium { color: #f0ad4e; font-weight: bold; }
.balance-high { color: #5cb85c; font-weight: bold; }
</style>

<!-- Icons and Styles -->
        <link href="https://unpkg.com/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    </div>
</div>
<script>
// Function to update date and time without seconds
function updateDateTime() {
    const now = new Date();

    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    };

    const formatted = now.toLocaleString('en-US', options);
    document.getElementById('datetime').innerHTML = formatted;
}

// Update every 30 seconds (optional, since seconds are not shown)
setInterval(updateDateTime, 30000);
updateDateTime(); // initial call
</script>

<style>
/* Modern clean table header */
.table thead th {
    background-color: transparent !important;
    color: #000 !important;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
}
</style>
