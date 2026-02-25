<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch counts from the database
$user_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total'];
$employee_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees"))['total'];
$leave_type_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM leavetypes"))['total'];
$shift_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM shifts"))['total'];

// Additional stat queries
$active_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status = 'Active'"))['total'];
$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employeeleaverequests WHERE status = 'Pending'"))['total'];
$departments_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM departments"))['total'];
$today_attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employeedtr WHERE DATE(date) = CURDATE()"))['total'];
$inactive_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status = 'Inactive'"))['total'];

// Fetch current admin info
$admin_id = $_SESSION['user_id'];
$admin_result = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$admin_id'");
$admin = mysqli_fetch_assoc($admin_result);
?>
<!-- Modern Palette-Based Design CSS -->
<style>
/* Mantis-Themed Color Palette - Vibrant & Diverse */
:root {
    /* Diverse Mantis-Inspired Colors */
    --palette-1: #8BC34A; /* Fresh Lime Green */
    --palette-2: #26C6DA; /* Cyan Blue */
    --palette-3: #FF7043; /* Coral Orange */
    --palette-4: #AB47BC; /* Purple */
    --palette-5: #FFCA28; /* Amber Yellow */
    --palette-6: #EC407A; /* Pink */
    --palette-7: #5C6BC0; /* Indigo Blue */
    --palette-8: #66BB6A; /* Medium Green */

    --bg-primary: #FAFAFA;
    --text-dark: #212121;
    --text-light: #616161;

    /* Dark gray shadows for depth */
    --shadow-base:
        0 6px 10px rgba(64, 64, 64, 0.35),
        0 10px 30px rgba(64, 64, 64, 0.25);

    --shadow-hover:
        0 10px 24px rgba(64, 64, 64, 0.45),
        0 22px 48px rgba(64, 64, 64, 0.35);
}

/* =========================
   STATS GRID
========================= */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

/* =========================
   STAT CARDS
========================= */
.stat-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-base);
    border: 2px solid transparent;
    transition: transform 0.35s ease, box-shadow 0.35s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    transition: width 0.4s ease;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
    border-color: currentColor;
}

.stat-card:hover::before {
    width: 100%;
    opacity: 0.08;
}

/* =========================
   COLOR CLASSES
========================= */
.card-color-1 { color: var(--palette-1); }
.card-color-1::before { background: var(--palette-1); }

.card-color-2 { color: var(--palette-2); }
.card-color-2::before { background: var(--palette-2); }

.card-color-3 { color: var(--palette-3); }
.card-color-3::before { background: var(--palette-3); }

.card-color-4 { color: var(--palette-4); }
.card-color-4::before { background: var(--palette-4); }

.card-color-5 { color: var(--palette-5); }
.card-color-5::before { background: var(--palette-5); }

.card-color-6 { color: var(--palette-6); }
.card-color-6::before { background: var(--palette-6); }

.card-color-7 { color: var(--palette-7); }
.card-color-7::before { background: var(--palette-7); }

.card-color-8 { color: var(--palette-8); }
.card-color-8::before { background: var(--palette-8); }

/* =========================
   STAT CARD CONTENT
========================= */
.stat-card-content {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.stat-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--text-light);
    margin-bottom: 8px;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text-dark);
    margin-bottom: 12px;
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-number {
    transform: scale(1.08);
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0, 0, 0, 0.08);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-light);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-badge {
    background: currentColor;
    color: #ffffff;
}

/* =========================
   ICON CONTAINER
========================= */
.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: currentColor;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(64, 64, 64, 0.45);
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.stat-card:hover .stat-icon {
    transform: rotate(10deg) scale(1.1);
    box-shadow: 0 14px 32px rgba(64, 64, 64, 0.55);
}

.stat-icon i {
    font-size: 28px;
    color: #ffffff;
}

/* =========================
   CHART STYLING
========================= */
.chart-title-wrapper h5 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.chart-subtitle {
    font-size: 0.8rem;
    color: var(--text-light);
    font-weight: 500;
}

.chart-icon-badge {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(64, 64, 64, 0.45);
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.chart-card:hover .chart-icon-badge {
    transform: rotate(360deg) scale(1.1);
    box-shadow: 0 14px 32px rgba(64, 64, 64, 0.55);
}

.chart-icon-badge i {
    font-size: 22px;
    color: #ffffff;
}

/* Colorful Gradients for Chart Icons */
.chart-icon-badge.icon-gradient-1 {
    background: linear-gradient(135deg, #8BC34A, #26C6DA);
}

.chart-icon-badge.icon-gradient-2 {
    background: linear-gradient(135deg, #AB47BC, #EC407A);
}

.chart-icon-badge.icon-gradient-3 {
    background: linear-gradient(135deg, #FF7043, #FFCA28);
}

/* =========================
   CHART CARDS
========================= */
.chart-card {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: var(--shadow-base);
    overflow: hidden;
    border: 2px solid transparent;
    transition: transform 0.35s ease, box-shadow 0.35s ease;
}

.chart-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-hover);
    border-color: rgba(0, 0, 0, 0.1);
}

.chart-header {
    padding: 24px 28px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* =========================
   PAGE HEADER
========================= */
.page-header-title h2 {
    font-weight: 800;
    background: linear-gradient(135deg, #8BC34A, #26C6DA, #AB47BC);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* =========================
   ANIMATIONS
========================= */
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card {
    animation: slideUp 0.6s ease-out backwards;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }
.stat-card:nth-child(5) { animation-delay: 0.25s; }
.stat-card:nth-child(6) { animation-delay: 0.3s; }
.stat-card:nth-child(7) { animation-delay: 0.35s; }
.stat-card:nth-child(8) { animation-delay: 0.4s; }

/* =========================
   RESPONSIVE
========================= */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-number {
        font-size: 2rem;
    }

    .stat-icon {
        width: 56px;
        height: 56px;
    }

    .stat-icon i {
        font-size: 24px;
    }
}

/* =========================
   SPARKLINE BAR CHART (Refined)
========================= */
.mini-chart {
    display: flex;
    gap: 3px;
    align-items: flex-end;
    height: 32px;
    margin: 10px 0 0 0;
    width: 100%;
}

.mini-chart span {
    flex: 1;
    background: linear-gradient(to top, currentColor, rgba(255,255,255,0.3));
    border-radius: 4px 4px 2px 2px;
    opacity: 0.85;
    animation: growBar 0.8s ease forwards;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mini-chart span:hover {
    opacity: 1;
    transform: scaleY(1.05);
}

.mini-chart.thin-bars span {
    flex: 0.6;
    gap: 2px;
}

@keyframes growBar {
    from { 
        height: 0;
        opacity: 0;
    }
    to {
        opacity: 0.85;
    }
}

/* =========================
   SMOOTH LINE CHART
========================= */
.mini-line {
    width: 100%;
    height: 32px;
    margin-top: 10px;
    position: relative;
}

.mini-line svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}

.mini-line polyline {
    stroke: currentColor;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.15));
    animation: drawLine 1.2s ease forwards;
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
}

@keyframes drawLine {
    to {
        stroke-dashoffset: 0;
    }
}

/* =========================
   GRADIENT AREA CHART
========================= */
.mini-area {
    width: 100%;
    height: 32px;
    margin-top: 10px;
    position: relative;
}

.mini-area svg {
    width: 100%;
    height: 100%;
}

.mini-area polygon {
    fill: url(#areaGradient);
    animation: fadeInArea 1s ease forwards;
    opacity: 0;
}

.mini-area polyline {
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

@keyframes fadeInArea {
    to {
        opacity: 1;
    }
}

/* =========================
   MODERN PROGRESS BAR
========================= */
.progress-mini {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    margin-top: 10px;
    overflow: hidden;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, 
        currentColor, 
        rgba(255,255,255,0.4) 50%, 
        currentColor);
    background-size: 200% 100%;
    border-radius: 12px;
    animation: progressGrow 1.2s ease forwards, shimmer 2s infinite;
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 50%;
    background: linear-gradient(to bottom, rgba(255,255,255,0.3), transparent);
    border-radius: 12px 12px 0 0;
}

.progress-mini.danger .progress-fill {
    opacity: 0.9;
}

@keyframes progressGrow {
    from { width: 0; }
}

@keyframes shimmer {
    0%, 100% { background-position: 0% 0%; }
    50% { background-position: 100% 0%; }
}

/* =========================
   ANIMATED PULSE DOTS
========================= */
.pulse-dots {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    align-items: center;
}

.pulse-dots span {
    width: 8px;
    height: 8px;
    background: currentColor;
    border-radius: 50%;
    animation: pulseDot 1.8s infinite ease-in-out;
    box-shadow: 0 0 8px currentColor;
    position: relative;
}

.pulse-dots span::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border: 2px solid currentColor;
    border-radius: 50%;
    opacity: 0;
    animation: ripple 1.8s infinite ease-out;
}

.pulse-dots span:nth-child(2) { 
    animation-delay: .3s;
}

.pulse-dots span:nth-child(2)::before { 
    animation-delay: .3s;
}

.pulse-dots span:nth-child(3) { 
    animation-delay: .6s;
}

.pulse-dots span:nth-child(3)::before { 
    animation-delay: .6s;
}

@keyframes pulseDot {
    0%, 100% { 
        opacity: 0.4;
        transform: scale(0.8);
    }
    50% { 
        opacity: 1;
        transform: scale(1.2);
    }
}

@keyframes ripple {
    0% {
        opacity: 0.6;
        transform: scale(1);
    }
    100% {
        opacity: 0;
        transform: scale(2);
    }
}

/* =========================
   WAVE PULSE LINE
========================= */
.pulse-line {
    height: 4px;
    margin-top: 12px;
    background: currentColor;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
    opacity: 0.7;
}

.pulse-line::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,0.6),
        transparent
    );
    animation: wave 1.5s infinite linear;
}

@keyframes wave {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* =========================
   WAFFLE CHART WITH ICONS
========================= */
#waffleChart {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    grid-template-rows: repeat(10, 1fr);
    gap: 6px;
    margin: 20px auto;
    width: 280px;
    height: 280px;
}

.waffle-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.3s ease;
    cursor: pointer;
    animation: popIn 0.4s ease backwards;
    border-radius: 4px;
}

.waffle-icon:hover {
    transform: scale(1.3);
    z-index: 10;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
}

.waffle-male {
    color: #4ECDC4;
}

.waffle-female {
    color: #FF6B6B;
}

@keyframes popIn {
    from {
        opacity: 0;
        transform: scale(0);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.waffle-legend {
    display: flex;
    gap: 24px;
    justify-content: center;
    align-items: center;
    margin-top: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
}

.legend-icon {
    font-size: 20px;
}

.waffle-stats {
    text-align: center;
    margin-bottom: 10px;
    font-size: 1.5rem;
    font-weight: 800;
    color: #2D3436;
}

.waffle-stats-label {
    font-size: 0.85rem;
    color: #636E72;
    font-weight: 500;
    margin-top: 4px;
}
</style>


<div class="pc-container">
    <div class="pc-content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item">
                                <h6><a href="dashboard.php" style="font-weight:bold;">Home</a></h6>
                            </li>
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Dashboard Overview</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      <!-- STAT CARDS -->
<div class="stats-grid">

    <!-- Card 1: Gradient Bar Chart -->
    <div class="stat-card card-color-1">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?= $user_count ?></div>
                <div class="mini-chart">
                    <span style="height:45%"></span>
                    <span style="height:60%"></span>
                    <span style="height:50%"></span>
                    <span style="height:85%"></span>
                    <span style="height:70%"></span>
                    <span style="height:95%"></span>
                    <span style="height:80%"></span>
                    
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
    </div>

    <!-- Card 2: Smooth Line Chart -->
    <div class="stat-card card-color-2">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Total Employees</div>
                <div class="stat-number"><?= $employee_count ?></div>
                <div class="mini-line">
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                        <polyline points="0,22 16,20 33,15 50,17 66,12 83,8 100,5"/>
                    </svg>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        </div>
    </div>

    <!-- Card 3: Modern Progress Bar -->
    <div class="stat-card card-color-3">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Active Employees</div>
                <div class="stat-number"><?= $active_employees ?></div>
                <div class="progress-mini">
                    <div class="progress-fill" style="width:78%"></div>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>

    <!-- Card 4: Animated Pulse Dots -->
    <div class="stat-card card-color-4">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Leave Types</div>
                <div class="stat-number"><?= $leave_type_count ?></div>
                <div class="pulse-dots">
                    <span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
    </div>

    <!-- Card 5: Progress Bar (Danger) -->
    <div class="stat-card card-color-5">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Pending Leaves</div>
                <div class="stat-number"><?= $pending_leaves ?></div>
                <div class="progress-mini danger">
                    <div class="progress-fill" style="width:42%"></div>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>

    <!-- Card 6: Gradient Area Chart -->
    <div class="stat-card card-color-6">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Total Shifts</div>
                <div class="stat-number"><?= $shift_count ?></div>
                <div class="mini-area">
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:currentColor;stop-opacity:0.4" />
                                <stop offset="100%" style="stop-color:currentColor;stop-opacity:0.05" />
                            </linearGradient>
                        </defs>
                        <polygon points="0,30 0,24 20,20 40,18 60,10 80,12 100,6 100,30"/>
                        <polyline points="0,24 20,20 40,18 60,10 80,12 100,6"/>
                        
                    </svg>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-business-time"></i></div>
        </div>
    </div>

    <!-- Card 7: Thin Bar Chart -->
    <div class="stat-card card-color-7">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Departments</div>
                <div class="stat-number"><?= $departments_count ?></div>
                <div class="mini-chart thin-bars">
                    <span style="height:35%"></span>
                    <span style="height:55%"></span>
                    <span style="height:70%"></span>
                    <span style="height:45%"></span>
                    <span style="height:60%"></span>
                    <span style="height:80%"></span>
                    <span style="height:50%"></span>
                    <span style="height:65%"></span>
                    <span style="height:75%"></span>
                    <span style="height:40%"></span>
                    <span style="height:55%"></span>
                    <span style="height:85%"></span>
                    <span style="height:60%"></span>
                    <span style="height:70%"></span>
                    <span style="height:45%"></span>
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-building"></i></div>
        </div>
    </div>

    <!-- Card 8: Wave Pulse Line -->
    <div class="stat-card card-color-8">
        <div class="stat-card-content">
            <div class="stat-info">
                <div class="stat-label">Today's Attendance</div>
                <div class="stat-number"><?= $today_attendance ?></div>
                <div class="pulse-line"></div>
            </div>
            <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
        </div>
    </div>

</div>

<!-- [ Three Charts in a Row ] -->
<div class="row g-4 mt-2">

    <!-- 1. Zigzag Attendance Chart -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title-wrapper">
                    <h5>Attendance Trends</h5>
                    <small class="chart-subtitle">Weekly zigzag pattern</small>
                </div>
                <div class="chart-icon-badge icon-gradient-1">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>

            <div class="card-body p-4" style="height:380px">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 2. Waffle Chart with Icons -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title-wrapper">
                    <h5>Employee Gender Distribution</h5>
                    <small class="chart-subtitle">Male vs Female breakdown</small>
                </div>
                <div class="chart-icon-badge icon-gradient-2">
                    <i class="fas fa-users"></i>
                </div>
            </div>

            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center" style="height:380px">
                <div class="waffle-stats">
                    <?= $active_employees + $inactive_employees ?>
                    <div class="waffle-stats-label">Total Employees</div>
                </div>
                <div id="waffleChart"></div>
                <div class="waffle-legend">
                    <div class="legend-item">
                        <i class="fas fa-male legend-icon waffle-male"></i>
                        <span>Male (<?= round(($active_employees / ($active_employees + $inactive_employees)) * 100) ?>%)</span>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-female legend-icon waffle-female"></i>
                        <span>Female (<?= round(($inactive_employees / ($active_employees + $inactive_employees)) * 100) ?>%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Donut Chart -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title-wrapper">
                    <h5>Employee Status</h5>
                    <small class="chart-subtitle">Active vs Inactive</small>
                </div>
                <div class="chart-icon-badge icon-gradient-3">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>

            <div class="card-body p-4 d-flex align-items-center justify-content-center" style="height:380px">
                <canvas id="employeeDonutChart"></canvas>
            </div>
        </div>
    </div>

</div>

<br>

<!-- Footer -->
<footer class="footer mt-5">
    <p class="text-center" style="color:#636E72;font-weight:500;">
        Biometrix System & Trading Corp. ♥ © 2025
    </p>
</footer>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ===============================
// ZIGZAG DATA GENERATOR
// ===============================
function zigzagData(data, amplitude = 4) {
    let zigzag = [];
    let direction = 1;

    for (let i = 0; i < data.length; i++) {
        zigzag.push(data[i]);

        if (i < data.length - 1) {
            zigzag.push(data[i] + amplitude * direction);
            direction *= -1;
        }
    }
    return zigzag;
}

// ===============================
// ZIGZAG LABELS
// ===============================
const baseLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
let zigzagLabels = [];

baseLabels.forEach((label, i) => {
    zigzagLabels.push(label);
    if (i < baseLabels.length - 1) zigzagLabels.push('');
});

// ===============================
// ZIGZAG LINE CHART
// ===============================
const ctx = document.getElementById('attendanceChart').getContext('2d');

new Chart(ctx,{
    type:'line',
    data:{
        labels: zigzagLabels,
        datasets:[
            {
                label:'Present',
                data: zigzagData([85,92,88,95,90,78,82], 5),
                borderColor:'#36a82b',
                backgroundColor:'transparent',
                tension:0,
                pointRadius:4,
                borderWidth:3
            },
            {
                label:'Late',
                data: zigzagData([10,6,9,4,7,12,8], 2),
                borderColor:'#FFC107',
                backgroundColor:'transparent',
                tension:0,
                pointRadius:4,
                borderWidth:3
            },
            {
                label:'Absent',
                data: zigzagData([5,2,3,1,3,10,6], 2),
                borderColor:'#FF6B6B',
                backgroundColor:'transparent',
                tension:0,
                pointRadius:4,
                borderWidth:3
            }
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'top',
                labels:{
                    usePointStyle:true,
                    font:{size:11,weight:'600'},
                    padding:10
                }
            }
        },
        scales:{
            y:{
                beginAtZero:true,
                grid:{color:'rgba(0,0,0,.05)'},
                ticks:{font:{size:10}}
            },
            x:{
                grid:{display:false},
                ticks:{font:{size:10}}
            }
        }
    }
});

// ===============================
// WAFFLE CHART WITH ICONS
// ===============================
function createWaffleChart(maleCount, femaleCount) {
    const total = maleCount + femaleCount;
    const waffleContainer = document.getElementById('waffleChart');
    waffleContainer.innerHTML = '';
    
    // Calculate percentages
    const malePercentage = Math.round((maleCount / total) * 100);
    
    // Create 100 icons (10x10 grid)
    for (let i = 0; i < 100; i++) {
        const icon = document.createElement('div');
        icon.className = 'waffle-icon';
        
        // Assign icon based on percentage
        if (i < malePercentage) {
            icon.innerHTML = '<i class="fas fa-male waffle-male"></i>';
            icon.title = `Male Employee (${i + 1}%)`;
        } else {
            icon.innerHTML = '<i class="fas fa-female waffle-female"></i>';
            icon.title = `Female Employee (${i + 1}%)`;
        }
        
        // Add staggered animation delay
        icon.style.animationDelay = `${i * 0.008}s`;
        
        waffleContainer.appendChild(icon);
    }
}

// Initialize waffle chart (using active as male, inactive as female for demo)
createWaffleChart(<?= $active_employees ?>, <?= $inactive_employees ?>);

// ===============================
// EMPLOYEE DONUT CHART
// ===============================
const donutCtx = document.getElementById('employeeDonutChart').getContext('2d');

const centerTextPlugin = {
    id:'centerText',
    beforeDraw(chart){
        if(chart.config.type !== 'doughnut') return;
        const {ctx,width,height} = chart;
        const total = chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
        ctx.save();
        ctx.font='bold 1.6em Inter';
        ctx.fillStyle='#2D3436';
        ctx.textAlign='center';
        ctx.textBaseline='middle';
        ctx.fillText(total,width/2,height/2-10);
        ctx.font='0.8em Inter';
        ctx.fillStyle='#636E72';
        ctx.fillText('Total Employees',width/2,height/2+16);
        ctx.restore();
    }
};

Chart.register(centerTextPlugin);

new Chart(donutCtx,{
    type:'doughnut',
    data:{
        labels:['Active Employees','Inactive Employees'],
        datasets:[{
            data:[<?= $active_employees ?>,<?= $inactive_employees ?>],
            backgroundColor:['#4ECDC4','#FF6B6B'],
            borderWidth:4,
            hoverOffset:15
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        cutout:'65%',
        plugins:{
            legend:{
                position:'bottom',
                labels:{
                    usePointStyle:true,
                    font:{size:11,weight:'600'},
                    padding:10
                }
            }
        }
    }
});
</script>
</document_content>