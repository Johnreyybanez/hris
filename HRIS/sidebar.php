


<?php
// Include DB connection
include 'connection.php';
?>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header text-center py-3" style="background-color: #00016b;">
            <a href="dashboard.php" class="b-brand d-block">
                <img src="logo.png" class="img-fluid logo-lg" alt="logo" style="max-width: 140px;">
            </a>
        </div>

        <div class="navbar-content">
            <ul class="pc-navbar">
                <li class="pc-item">
                    <a href="dashboard.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>
                <li class="pc-item">
                    <a href="employees.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-users"></i></span>
                        <span class="pc-mtext">Employee List</span>
                    </a>
                </li>

                <li class="pc-item">
                    <a href="employee_dtr.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-clock"></i></span>
                        <span class="pc-mtext">Employee DTR</span>
                    </a>
                </li>

                <li class="pc-item">
                    <a href="backup.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-database"></i></span>
                        <span class="pc-mtext">Import DB</span>
                    </a>
                </li>
              
                <!-- Dropdown: Work Management -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-briefcase"></i></span>
                        <span class="pc-mtext">Work Management</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="pc-item">
                            <a href="work_suspensions.php" class="pc-link">
                                <i class="ti ti-alert-circle"></i> Work Suspensions
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="holiday_calendar.php" class="pc-link">
                                <i class="ti ti-calendar"></i> Holiday Calendar
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="pc-item">
                    <a href="leave_policies.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-file"></i></span>
                        <span class="pc-mtext">Leave Policies</span>
                    </a>
                </li>

                <li class="pc-item">
                    <a href="day_types.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-sun"></i></span>
                        <span class="pc-mtext">Day Types</span>
                    </a>
                </li>

                <li class="pc-item">
                    <a href="attendance.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-list"></i></span>
                        <span class="pc-mtext">Time Attendance Rules</span>
                    </a>
                </li>
                <li class="pc-item pc-caption">
                    <label>Reports</label>
                    <i class="ti ti-chrome"></i>
                </li>
                <!-- Dropdown: Reports -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-chart-bar"></i></span>
                        <span class="pc-mtext">Reports</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="pc-item">
                            <a href="summary.php" class="pc-link">
                                <i class="ti ti-calendar-stats"></i> Attendance Summary
                            </a>
                        </li>
                        <li class="pc-item">
                            <a href="report.php" class="pc-link">
                                <i class="ti ti-report"></i> Generate Reports
                            </a>
                        </li>
                    </ul>
                </li>
                 <!-- Multiple Data Menu -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-folders"></i></span>
                        <span class="pc-mtext">Multiple Data</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="pc-item"><a href="departments.php" class="pc-link"><i class="ti ti-building"></i> Department</a></li>
                        <li class="pc-item"><a href="designations.php" class="pc-link"><i class="ti ti-id"></i> Designation</a></li>
                        <li class="pc-item"><a href="employment_types.php" class="pc-link"><i class="ti ti-briefcase"></i> Employment Types</a></li>
                        <li class="pc-item"><a href="office_locations.php" class="pc-link"><i class="ti ti-map-pin"></i> Office Location</a></li>
                        <li class="pc-item"><a href="shifts.php" class="pc-link"><i class="ti ti-clock"></i> Shift</a></li>
                        <li class="pc-item"><a href="shiftdays.php" class="pc-link"><i class="ti ti-calendar-time"></i> Shift Days</a></li>
                        <li class="pc-item"><a href="leave_types.php" class="pc-link"><i class="ti ti-notebook"></i> Leave Type</a></li>
                        <li class="pc-item"><a href="pay_periods.php" class="pc-link"><i class="ti ti-calendar-stats"></i> Pay Periods</a></li>
                        <li class="pc-item"><a href="deduction_types.php" class="pc-link"><i class="ti ti-receipt-tax"></i> Deduction Types</a></li>
                        <li class="pc-item"><a href="benefit_types.php" class="pc-link"><i class="ti ti-gift"></i> Benefit Types</a></li>
                        <li class="pc-item"><a href="loan_types.php" class="pc-link"><i class="ti ti-cash-banknote"></i> Loan Types</a></li>
                        <li class="pc-item"><a href="violation_types.php" class="pc-link"><i class="ti ti-alert-triangle"></i> Violation Types</a></li>
                        <li class="pc-item"><a href="sanction_types.php" class="pc-link"><i class="ti ti-scale"></i> Sanction Types</a></li>
                        <li class="pc-item"><a href="performance_criteria.php" class="pc-link"><i class="ti ti-stars"></i> Performance Criteria</a></li>
                        <li class="pc-item"><a href="training_categories.php" class="pc-link"><i class="ti ti-school"></i> Training Categories</a></li>
                    
            </ul>
               <li class="pc-item">
    <a href="leave_requests.php" class="pc-link position-relative">
        <span class="pc-micon position-relative">
            <i class="ti ti-check"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="leave-badge">
                0
            </span>
        </span>
        <span class="pc-mtext">Leave Requests</span>
    </a>
</li>

<li class="pc-item">
    <a href="overtime.php" class="pc-link position-relative">
        <span class="pc-micon position-relative">
            <i class="ti ti-clock"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" id="overtime-badge">
                0
            </span>
        </span>
        <span class="pc-mtext">Overtime Requests</span>
    </a>
</li>

<li class="pc-item">
    <a href="missingtimelogrequests.php" class="pc-link position-relative">
        <span class="pc-micon position-relative">
            <i class="ti ti-calendar"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info text-dark" id="missing-badge">
                0
            </span>
        </span>
        <span class="pc-mtext">Missing Time Logs</span>
    </a>
</li>

<li class="pc-item">
    <a href="ob_requests.php" class="pc-link position-relative">
        <span class="pc-micon position-relative">
            <i class="ti ti-building"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" id="ob-badge">
                0
            </span>
        </span>
        <span class="pc-mtext">OB Requests</span>
    </a>
</li>


                <li class="pc-item">
                    <a href="users.php" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-user"></i></span>
                        <span class="pc-mtext">Users</span>
                    </a>
                </li>

               
        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->

<script>
// Fetch counts from backend
function fetchPendingCounts() {
    fetch('fetch_pending_counts.php')
    .then(response => response.json())
    .then(data => {
        // Update badges
        document.getElementById('leave-badge').innerText = data.leave_count;
        document.getElementById('overtime-badge').innerText = data.overtime_count;
        document.getElementById('missing-badge').innerText = data.missing_count;
        document.getElementById('ob-badge').innerText = data.ob_count;

        // Show popup if there are new requests
        if (data.new_request) {
            Swal.fire({
                title: '📢 New Pending Requests!',
                html: `
                    <p><b>${data.leave_count}</b> Leave Request(s)</p>
                    <p><b>${data.overtime_count}</b> Overtime Request(s)</p>
                    <p><b>${data.missing_count}</b> Missing Time Log(s)</p>
                    <p><b>${data.ob_count}</b> OB Request(s)</p>
                `,
                icon: 'info',
                confirmButtonText: 'View Leave Requests',
                showCancelButton: true,
                cancelButtonText: 'Dismiss',
                timer: 10000,
                timerProgressBar: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'leave_requests.php';
                }
            });
        }
    });
}

// Check every 5 seconds
setInterval(fetchPendingCounts, 5000);
fetchPendingCounts(); // Run immediately on load
</script>
