<?php
session_start();

include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch counts from the database
$user_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Users"))['total'];
$employee_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Employees"))['total'];
$leave_type_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM LeaveTypes"))['total'];
$shift_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM Shifts"))['total'];

// Fetch current admin info
$admin_id = $_SESSION['user_id'];
$admin_result = mysqli_query($conn, "SELECT * FROM Users WHERE user_id = '$admin_id'");
$admin = mysqli_fetch_assoc($admin_result);
?>


<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">

        <!-- [ Breadcrumb ] -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Dashboard</a></li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Dashboard</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
       

        <!-- [ Stat Cards ] -->
        <div class="row">
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2 text-muted">Total Users</h6>
                        <h4 class="mb-0"><?= $user_count ?> <span class="badge bg-dark border border-primary"><i
                                    class="ti ti-trending-up"></i> 70.5%</span></h4>
                    </div>
                    <div id="total-value-graph-1"></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2 text-muted">Total Employees</h6>
                        <h4 class="mb-0"><?= $employee_count ?> <span class="badge bg-dark border border-warning"><i
                                    class="ti ti-trending-down"></i> 27.4%</span></h4>
                    </div>
                    <div id="total-value-graph-2"></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2 text-muted">Total Leave Types</h6>
                        <h4 class="mb-0"><?= $leave_type_count ?> <span class="badge bg-dark border border-warning"><i
                                    class="ti ti-trending-down"></i> 27.4%</span></h4>
                    </div>
                    <div id="total-value-graph-3"></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2 text-muted">Total Shifts</h6>
                        <h4 class="mb-0"><?= $shift_count ?> <span class="badge bg-dark border border-primary"><i
                                    class="ti ti-trending-up"></i> 59.3%</span></h4>
                    </div>
                    <div id="total-value-graph-4"></div>
                </div>
            </div>
        </div>
<!-- [ Charts + Active Employees ] -->
<div class="row mt-3">
    <!-- Line Chart -->
    <div class="col-md-6 mb-2">
        <div class="card h-100" style="min-height: 250px;">
            <div class="card-header bg-info text-white d-flex align-items-center p-2">
                <i class="fas fa-chart-line me-2"></i> <!-- 📊 Icon -->
                <h6 class="mb-0 fw-bold fs-6">Attendance Overview</h6>
            </div>
            <div class="card-body p-2">
                <canvas id="attendanceChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Active Employees -->
    <div class="col-md-6 mb-2">
        <div class="card h-100" style="min-height: 250px;">
            <div class="card-header bg-secondary text-white d-flex align-items-center p-2">
                <i class="fas fa-users me-2"></i> <!-- 👥 Icon -->
                <h6 class="mb-0 fw-bold fs-6">Active Employees</h6>
            </div>
            <div class="card-body p-1">
                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top small">
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $active_employees = mysqli_query($conn, "SELECT * FROM employees WHERE status = 'Active' ORDER BY last_name ASC");
                            if (mysqli_num_rows($active_employees) > 0) {
                                $i = 1;
                                while ($emp = mysqli_fetch_assoc($active_employees)) {
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars($emp['photo_path']) ?>" alt="Photo"
                                        class="rounded-circle" width="25" height="25" style="object-fit: cover;">
                                </td>
                                <td class="small"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                                <td><span class="badge bg-success small"><?= htmlspecialchars($emp['status']) ?></span></td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="4" class="text-center small">No active employees found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Footer -->
        <footer class="footer mt-4">
            <p class="text-center">Biometrix System & Trading Corp. &#9829; © 2025</p>
        </footer>
    </div>
</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(ctx, {
    type: 'line', // ✅ Changed to line
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], // Example labels
        datasets: [{
            label: 'Attendance Count',
            data: [12, 19, 15, 17, 14, 10, 13], // Example data
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.2)',
            tension: 0.3, // Smooth curves
            fill: true,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
