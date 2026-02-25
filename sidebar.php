<?php
// Include DB connection
include 'connection.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.pc-link {
    transition: background-color 0.3s, color 0.3s, border-radius 0.3s;
    border-radius: 0;
}

.pc-link:hover {
    background-color: #000 !important;
    color: #fff !important;
    border-radius: 8px !important;
}

.pc-link:hover .pc-micon i,
.pc-link:hover .pc-mtext {
    color: #fff !important;
}

/* Bold all sidebar text */
.pc-mtext {
    font-weight: 600 !important;
}

.pc-submenu .pc-link {
    font-weight: 600 !important;
}

/* Section Labels Styling */
.section-label {
    padding: 8px 16px;
    margin: 8px 0 4px 0;
    font-size: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
}

.section-label.payroll {
    border-left-color: #28a745;
    background-color: #f1f8e9;
    color: #2e7d32;
}

.section-label.hris {
    border-left-color: #007bff;
    background-color: #e3f2fd;
    color: #1565c0;
}

.section-label.requests {
    border-left-color: #fd7e14;
    background-color: #fff3cd;
    color: #856404;
}
.pc-sidebar {
    box-shadow: 5px 0 5px rgba(0, 0, 0, 0.35);
}
/* Add shadow to all sidebar icons */
.pc-sidebar i,
.pc-sidebar svg {
    filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.45));
    transition: filter 0.3s ease;
}

/* Stronger shadow on hover */
.pc-link:hover i,
.pc-link:hover svg {
    filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.6));
}
.badge {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
}
.pc-sidebar i {
    filter: drop-shadow(0 0 4px rgba(0, 0, 0, 0.4));
}

</style>

<!-- Sidebar -->

<nav class="pc-sidebar">

    <div class="navbar-wrapper">

        <!-- Logo -->

        <div class="m-header py-3 px-4" style="box-shadow: 0 1px 4px rgba(0,0,0,.1);">

            <a href="dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none">

                <img src="Hris.png" class="img-fluid" style="max-width:45px;" alt="logo">

              <div class="d-flex flex-column">

                    <h2 class="mb-0 fw-bold" style="font-size: 24px;">HRIS</h2>

                    <small class="text-dark" style="font-size: 10px;">Biometrix System & Trading Corp.</small>

                </div>

            </a>

        </div>

        <!-- Navbar content -->

        <div class="navbar-content">

            <ul class="pc-navbar">

                <!-- Dashboard -->

                <li class="pc-item">

                    <a href="dashboard.php" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-grid"></i></span>

                        <span class="pc-mtext">Dashboard</span>

                    </a>

                </li>

                <!-- Employee List -->

                <li class="pc-item">

                    <a href="employees.php" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-people"></i></span>

                        <span class="pc-mtext">Employee List</span>

                    </a>

                </li>

                <!-- HRIS Menu -->

                <li class="pc-item pc-hasmenu">

                    <a href="#!" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-building"></i></span>

                        <span class="pc-mtext">HRIS</span>

                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>

                    </a>

                    <ul class="pc-submenu">

                        <li class="section-label hris">HRIS Features</li>

                        <li class="pc-item">

                            <a href="employee_dtr.php" class="pc-link">

                                <span class="pc-micon"><i class="bi bi-clock-history"></i></span>

                                <span class="pc-mtext">Employee DTR</span>

                            </a>

                        </li>

                        <li class="pc-item">

                            <a href="backup.php" class="pc-link">

                                <span class="pc-micon"><i class="bi bi-database"></i></span>

                                <span class="pc-mtext">Import DB</span>

                            </a>

                        </li>
                        <li class="pc-item">
                            <a href="import.php" class="pc-link">
                                <span class="pc-micon"><i class="fas fa-server me-2"></i></span>
                                <span class="pc-mtext">Import in Sentry</span>
                            </a>
                        </li>

                        <li class="pc-item">

                            <a href="employeeleavecredits.php" class="pc-link">

                                <span class="pc-micon"><i class="bi bi-clipboard-check"></i></span>

                                <span class="pc-mtext">Leave Credits</span>

                            </a>

                        </li>

                        <li class="section-label requests">Employee Requests</li>

                        <li class="pc-item">
                            <a href="leave_requests.php" class="pc-link position-relative">
                                <span class="pc-micon position-relative">
                                    <i class="bi bi-check-circle"></i>
                                    <!-- Badge positioned top-right -->
                                    <span class="badge bg-danger position-absolute top-0 translate-middle p-1" 
                                        id="leave-badge"
                                        style="font-size: 0.6rem; border-radius: 50%;">
                                        0
                                    </span>
                                </span>
                                <span class="pc-mtext">Leave Requests</span>
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="overtime.php" class="pc-link position-relative">
                                <span class="pc-micon position-relative">
                                    <i class="bi bi-clock"></i>
                                    <span class="badge bg-warning text-dark position-absolute top-0 translate-middle p-1" 
                                        id="overtime-badge"
                                        style="font-size: 0.6rem; border-radius: 50%;">
                                        0
                                    </span>
                                </span>
                                <span class="pc-mtext">Overtime Requests</span>
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="missingtimelogrequests.php" class="pc-link position-relative">
                                <span class="pc-micon position-relative">
                                    <i class="bi bi-calendar-x"></i>
                                    <span class="badge bg-info text-dark position-absolute top-0 translate-middle p-1" 
                                        id="missing-badge"
                                        style="font-size: 0.6rem; border-radius: 50%;">
                                        0
                                    </span>
                                </span>
                                <span class="pc-mtext">Missing Time Logs</span>
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="ob_requests.php" class="pc-link position-relative">
                                <span class="pc-micon position-relative">
                                    <i class="bi bi-building-check"></i>
                                    <span class="badge bg-primary position-absolute top-0 translate-middle p-1" 
                                        id="ob-badge"
                                        style="font-size: 0.6rem; border-radius: 50%;">
                                        0
                                    </span>
                                </span>
                                <span class="pc-mtext">OB Requests</span>
                            </a>
                        </li>


                    </ul>

                </li>
                <!-- Payroll Dropdown -->
                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link">
                        <span class="pc-micon"><i class="bi bi-receipt"></i></span>
                        <span class="pc-mtext">Payroll</span>
                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <!-- Payroll Section -->
                        <li class="section-label payroll">💰 Payroll Features</li>

                        <li class="pc-item">
                            <a href="payroll.php" class="pc-link">
                                <i class="bi bi-receipt-cutoff"></i> Generate Payroll
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="payroll_groups.php" class="pc-link">
                                <i class="bi bi-cash-stack"></i> Payroll Groups
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="deduction_schedule.php" class="pc-link">
                                <i class="bi bi-calendar-check"></i> Deduction Schedule
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="payroll_details.php" class="pc-link">
                                <i class="bi bi-list-ul"></i> Payroll Details
                            </a>
                        </li>

                        <li class="pc-item">
                            <a href="payroll_deductions.php" class="pc-link">
                                <i class="bi bi-dash-circle"></i> Payroll Deductions
                            </a>
                        </li>
                    </ul>
                </li>


                <!-- Master Files -->

                <li class="pc-item pc-hasmenu">

                    <a href="#!" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-folder2-open"></i></span>

                        <span class="pc-mtext">Master Files</span>

                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>

                    </a>

                    <ul class="pc-submenu">

                        <li class="pc-item"><a href="departments.php" class="pc-link"><i class="bi bi-building"></i> Department</a></li>

                        <li class="pc-item"><a href="designations.php" class="pc-link"><i class="bi bi-person-badge"></i> Designation</a></li>

                        <li class="pc-item"><a href="employment_types.php" class="pc-link"><i class="bi bi-briefcase"></i> Employment Types</a></li>

                        <li class="pc-item"><a href="office_locations.php" class="pc-link"><i class="bi bi-geo-alt"></i> Office Location</a></li>

                        <li class="pc-item"><a href="shifts.php" class="pc-link"><i class="bi bi-clock"></i> Shift</a></li>

                        <li class="pc-item"><a href="shiftdays.php" class="pc-link"><i class="bi bi-calendar-week"></i> Shift Days</a></li>

                        <li class="pc-item"><a href="leave_types.php" class="pc-link"><i class="bi bi-journal-text"></i> Leave Type</a></li>

                        <li class="pc-item"><a href="violation_types.php" class="pc-link"><i class="bi bi-exclamation-triangle"></i> Violation Types</a></li>

                        <li class="pc-item"><a href="sanction_types.php" class="pc-link"><i class="bi bi-shield-check"></i> Sanction Types</a></li>

                        <li class="pc-item"><a href="performance_criteria.php" class="pc-link"><i class="bi bi-star"></i> Performance Criteria</a></li>

                        <li class="pc-item"><a href="training_categories.php" class="pc-link"><i class="bi bi-mortarboard"></i> Training Categories</a></li>

                        <li class="pc-item"><a href="guide.php" class="pc-link"><i class="bi bi-book"></i> Decimal to Minutes Guide</a></li>

                    </ul>

                </li>

                <!-- Reports -->

                <li class="pc-item pc-hasmenu">

                    <a href="#!" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-bar-chart"></i></span>

                        <span class="pc-mtext">Reports</span>

                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>

                    </a>

                    <ul class="pc-submenu">

                        <li class="pc-item"><a href="summary.php" class="pc-link"><i class="bi bi-calendar3"></i> Attendance Summary</a></li>

                        <li class="pc-item"><a href="atten_reports.php" class="pc-link"><i class="bi bi-file-earmark-text"></i> Attendance Reports</a></li>

                        <li class="pc-item"><a href="report.php" class="pc-link"><i class="bi bi-file-earmark-bar-graph"></i> CS FORM 48 Report</a></li>

                        <li class="pc-item"><a href="dtr.php" class="pc-link"><i class="bi bi-calendar3"></i> DTR Summary</a></li>

                    </ul>

                </li>

                <!-- Work Management -->

                <li class="pc-item pc-hasmenu">

                    <a href="#!" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-briefcase"></i></span>

                        <span class="pc-mtext">Work Management</span>

                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>

                    </a>

                    <ul class="pc-submenu">

                        <li class="pc-item"><a href="work_suspensions.php" class="pc-link"><i class="bi bi-exclamation-circle"></i> Work Suspensions</a></li>

                        <li class="pc-item"><a href="holiday_calendar.php" class="pc-link"><i class="bi bi-calendar-event"></i> Holiday Calendar</a></li>

                    </ul>

                </li>

                <!-- Settings -->

                <li class="pc-item pc-hasmenu">

                    <a href="#!" class="pc-link">

                        <span class="pc-micon"><i class="bi bi-gear"></i></span>

                        <span class="pc-mtext">Settings</span>

                        <span class="pc-arrow"><i class="bi bi-chevron-right"></i></span>

                    </a>

                    <ul class="pc-submenu">

                        <li class="section-label hris">System Policies</li>

                        <li class="pc-item"><a href="leave_policies.php" class="pc-link"><i class="bi bi-file-earmark"></i> Leave Policies</a></li>

                        <li class="pc-item"><a href="day_types.php" class="pc-link"><i class="bi bi-sun"></i> Day Types</a></li>

                        <li class="pc-item"><a href="attendance.php" class="pc-link"><i class="bi bi-list-check"></i> Time Attendance Rules</a></li>

                        <li class="section-label hris">System Configuration</li>

                        <li class="pc-item"><a href="signatory_settings.php" class="pc-link"><i class="bi bi-pencil"></i> Signatory Settings</a></li>

                        <li class="pc-item"><a href="users.php" class="pc-link"><i class="bi bi-people"></i> Users Management</a></li>

                    </ul>

                </li>

            </ul>

        </div>

    </div>

</nav>
<!-- [ Sidebar Menu ] end -->

<link rel="stylesheet" href="https://unpkg.com/@tabler/icons-webfont@latest/tabler-icons.min.css">


<script>

// Fetch counts for badges

function fetchPendingCounts() {

    fetch('fetch_pending_counts.php')

    .then(res => res.json())

    .then(data => {

        document.getElementById('leave-badge').innerText = data.leave_count;

        document.getElementById('overtime-badge').innerText = data.overtime_count;

        document.getElementById('missing-badge').innerText = data.missing_count;

        document.getElementById('ob-badge').innerText = data.ob_count;

        if(data.new_request) {

            Swal.fire({

                title: '📢 New Pending Requests!',

                html: `

                    <p><i class="bi bi-check-circle text-danger"></i> <b>${data.leave_count}</b> Leave Request(s)</p>

                    <p><i class="bi bi-clock text-warning"></i> <b>${data.overtime_count}</b> Overtime Request(s)</p>

                    <p><i class="bi bi-calendar-x text-info"></i> <b>${data.missing_count}</b> Missing Time Log(s)</p>

                    <p><i class="bi bi-building-check text-primary"></i> <b>${data.ob_count}</b> OB Request(s)</p>

                `,

                icon: 'info',

                confirmButtonText: 'View Leave Requests',

                showCancelButton: true,

                cancelButtonText: 'Dismiss',

                timer: 10000,

                timerProgressBar: true

            }).then(result => {

                if(result.isConfirmed) window.location.href = 'leave_requests.php';

            });

        }

    });

}

setInterval(fetchPendingCounts, 5000);

fetchPendingCounts();

</script>

