<?php
ob_start();
session_start();
include 'connection.php';

/* =============================================================================
   HELPER FUNCTIONS
============================================================================= */

/**
 * Calculate night differential hours (6PM - 3AM)
 * @param string $timeIn Actual time in (HH:MM)
 * @param string $timeOut Actual time out (HH:MM)
 * @param string $scheduleIn Scheduled time in
 * @param string $scheduleOut Scheduled time out
 * @return string Night differential hours in HH:MM format
 */
function calculateNightDifferential($timeIn, $timeOut, $scheduleIn, $scheduleOut) {
    if (empty($timeIn) || empty($timeOut)) {
        return '00:00';
    }

    list($inHour, $inMin) = explode(':', $timeIn);
    list($outHour, $outMin) = explode(':', $timeOut);
    
    $inHour = intval($inHour);
    $inMin = intval($inMin);
    $outHour = intval($outHour);
    $outMin = intval($outMin);

    // Check if it's an overnight shift
    $isOvernightShift = ($inHour >= 12 && $outHour < 12);
    if (!$isOvernightShift) {
        return '00:00';
    }

    // Night differential period: 6PM (18:00) to 3AM
    $nightStart = 18;
    $nightEnd = 3;
    $nightMinutes = 0;
    $currentHour = $inHour;
    $currentMin = $inMin;

    // Calculate minutes within night differential period
    while ($currentHour < $outHour || ($currentHour == $outHour && $currentMin < $outMin)) {
        $checkHour = $currentHour % 24;
        
        if ($checkHour >= $nightStart || $checkHour < $nightEnd) {
            $startMin = $currentMin;
            $endMin = ($currentHour == $outHour) ? $outMin : 60;
            $nightMinutes += ($endMin - $startMin);
        }
        
        $currentMin = 0;
        $currentHour++;
    }

    $hours = floor($nightMinutes / 60);
    $minutes = $nightMinutes % 60;
    
    return sprintf("%02d:%02d", $hours, $minutes);
}

/**
 * Calculate total work time excluding break periods
 * @param string $time_in Time in
 * @param string $break_out Break start time
 * @param string $break_in Break end time
 * @param string $time_out Time out
 * @return string Total work time in HH:MM:SS format
 */
function calculateTotalWork($time_in, $break_out, $break_in, $time_out) {
    if (!$time_in || !$time_out) {
        return "00:00:00";
    }

    // Calculate work time before break
    $sec1 = strtotime($break_out ?? $time_out) - strtotime($time_in);
    
    // Calculate work time after break
    $sec2 = ($break_out && $break_in) ? strtotime($time_out) - strtotime($break_in) : 0;
    
    $total = $sec1 + $sec2;
    
    return gmdate("H:i:s", $total);
}
/* =============================================================================
   MULTI-EMPLOYEE SHIFT ASSIGNMENT HANDLER (MANUAL PROTECTED)
============================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shifts'])) {

    if (empty($_POST['employee_ids'])) {
        $_SESSION['error'] = "No employees selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $employee_ids = $_POST['employee_ids'];
    $shift_id     = intval($_POST['shift_id']);
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];

    /* =====================
       FETCH SHIFT DETAILS
    ===================== */
    $shift_sql = "SELECT * FROM Shifts WHERE shift_id = ?";
    $stmt = mysqli_prepare($conn, $shift_sql);
    mysqli_stmt_bind_param($stmt, "i", $shift_id);
    mysqli_stmt_execute($stmt);
    $shift = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$shift) {
        $_SESSION['error'] = "Invalid shift selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $sched_in  = $shift['time_in'];
    $sched_out = $shift['time_out'];
    $is_flex   = (int)$shift['is_flexible'];

    /* =====================
       LOOP EMPLOYEES
    ===================== */
    foreach ($employee_ids as $emp_id) {

        $current = strtotime($start_date);
        $end     = strtotime($end_date);

        while ($current <= $end) {

            $date = date('Y-m-d', $current);

            /* =====================
               FETCH EXISTING DTR
            ===================== */
            $dtr_sql = "
                SELECT 
                    dtr_id,
                    TIME_FORMAT(time_in, '%H:%i') AS actual_in,
                    TIME_FORMAT(time_out, '%H:%i') AS actual_out,
                    TIME_FORMAT(break_out, '%H:%i') AS break_out,
                    TIME_FORMAT(break_in, '%H:%i') AS break_in
                FROM EmployeeDTR
                WHERE employee_id = ? AND date = ?
            ";

            $stmt = mysqli_prepare($conn, $dtr_sql);
            mysqli_stmt_bind_param($stmt, "is", $emp_id, $date);
            mysqli_stmt_execute($stmt);
            $dtr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            // Skip if no DTR exists
            if (!$dtr) {
                $current = strtotime("+1 day", $current);
                continue;
            }

            /* =====================
               TIME CALCULATIONS
            ===================== */

            // ---- LATE ----
            $late = "00:00:00";
            if ($dtr['actual_in'] && !$is_flex) {
                if (strtotime($dtr['actual_in']) > strtotime($sched_in)) {
                    $late = gmdate(
                        "H:i:s",
                        strtotime($dtr['actual_in']) - strtotime($sched_in)
                    );
                }
            }

            // ---- UNDERTIME ----
            $ut = "00:00:00";
            if ($dtr['actual_out'] && !$is_flex) {
                $actual_out = strtotime($dtr['actual_out']);
                $sched_out_ts = strtotime($sched_out);

                // Overnight shift handling
                if ($sched_out_ts < strtotime($sched_in)) {
                    $sched_out_ts += 86400;
                    if ($actual_out < strtotime($dtr['actual_in'])) {
                        $actual_out += 86400;
                    }
                }

                if ($actual_out < $sched_out_ts) {
                    $ut = gmdate("H:i:s", $sched_out_ts - $actual_out);
                }
            }

            // ---- TOTAL WORK ----
            $total_work = "00:00:00";
            if ($dtr['actual_in'] && $dtr['actual_out']) {
                $in  = strtotime($dtr['actual_in']);
                $out = strtotime($dtr['actual_out']);

                if ($out < $in) {
                    $out += 86400; // overnight
                }

                $seconds = $out - $in;

                if ($dtr['break_out'] && $dtr['break_in']) {
                    $bo = strtotime($dtr['break_out']);
                    $bi = strtotime($dtr['break_in']);
                    if ($bi > $bo) {
                        $seconds -= ($bi - $bo);
                    }
                }

                $total_work = gmdate("H:i:s", max(0, $seconds));
            }

            // ---- NIGHT DIFFERENTIAL ----
            $night = calculateNightDifferential(
                $dtr['actual_in'],
                $dtr['actual_out'],
                $sched_in,
                $sched_out
            );

            /* =====================
               UPDATE DTR (MANUAL LOCK)
            ===================== */
            $update_sql = "
                UPDATE EmployeeDTR
                SET
                    shift_id = ?,
                    late_time = ?,
                    undertime_time = ?,
                    total_work_time = ?,
                    night_time = ?,
                    is_manual = 1,
                    approval_status = 'Pending',
                    remarks = CONCAT(
                        IFNULL(remarks, ''),
                        ' | Shift manually reassigned'
                    ),
                    updated_at = NOW()
                WHERE dtr_id = ?
            ";

            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $stmt,
                "issssi",
                $shift_id,
                $late,
                $ut,
                $total_work,
                $night,
                $dtr['dtr_id']
            );
            mysqli_stmt_execute($stmt);

            $current = strtotime("+1 day", $current);
        }
    }

    $_SESSION['success'] =
        "Shift schedule updated successfully. All affected DTRs are MANUAL & protected.";

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


/* =============================================================================
   DTR FILTER AND DATA RETRIEVAL
============================================================================= */

$employee_id = $_POST['employee_id'] ?? $_GET['employee_id'] ?? '';
$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? '';
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? '';
$display_rows = [];

if (!empty($from_date) && !empty($to_date)) {
    $query = "
        SELECT 
            d.*,
            CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
            TIME_FORMAT(s.time_in, '%H:%i') AS schedule_in,
            TIME_FORMAT(s.time_out, '%H:%i') AS schedule_out,
            TIME_FORMAT(d.time_in, '%H:%i') AS actual_in,
            TIME_FORMAT(d.time_out, '%H:%i') AS actual_out,
            TIME_FORMAT(d.break_out, '%H:%i') AS break_out,
            TIME_FORMAT(d.break_in, '%H:%i') AS break_in
        FROM EmployeeDTR d
        JOIN Employees e ON d.employee_id = e.employee_id
        LEFT JOIN Shifts s ON d.shift_id = s.shift_id
        WHERE d.date BETWEEN '$from_date' AND '$to_date' 
        AND e.status = 'active'
    ";
    
    if (!empty($employee_id)) {
        $query .= " AND e.employee_id = '$employee_id'";
    }
    
    $query .= " ORDER BY employee_name, d.date DESC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $display_rows[] = $row;
    }
}

/* =============================================================================
   FETCH MASTER DATA (EMPLOYEES & SHIFTS)
============================================================================= */

$employees_query = "
    SELECT employee_id, last_name, first_name, middle_name 
    FROM Employees 
    WHERE status = 'active' 
    ORDER BY last_name
";
$employees = mysqli_query($conn, $employees_query);

$shifts_query = "
    SELECT shift_id, shift_name, time_in, time_out 
    FROM Shifts 
    ORDER BY shift_name
";
$shifts = mysqli_query($conn, $shifts_query);

/* =============================================================================
   HTML PAGE RENDERING
============================================================================= */

include 'head.php';
include 'sidebar.php';
include 'header.php';
?>

<style>
/* =============================================================================
   PRINT STYLES
============================================================================= */
@media print {
    th:last-child, 
    td:last-child {
        display: none !important;
    }
    
    #dtrTable thead th {
        position: relative !important;
    }
}

/* =============================================================================
   TABLE STYLES
============================================================================= */
#dtrTable {
    table-layout: auto !important;
    width: 100% !important;
    border-collapse: collapse;
}

#dtrTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #16c216ff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

#dtrTable th {
    font-weight: bold;
    font-size: 11px;
    color: #0a0a0aff;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    background: transparent;
    position: sticky;
    top: 0;
}

#dtrTable td {
    font-size: 15px;
    text-align: center;
    vertical-align: middle;
    height: 40px;
    padding: 8px;
    white-space: nowrap;
    word-break: normal;
    background-color: #fff;
}

#dtrTable tbody tr:hover {
    background-color: #f8f9ff;
    transition: background-color 0.3s ease;
}

#dtrTable th:nth-child(2), 
#dtrTable td:nth-child(2) {
    text-align: left !important;
    padding-left: 12px !important;
}

#dtrTable button {
    margin: 2px;
    font-size: 11px;
    padding: 4px 8px;
}

#dtrTable .btn {
    display: inline-block;
    white-space: nowrap;
}

.table-responsive {
    max-height: calc(100vh - 350px);
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 8px;
}

/* =============================================================================
   ALERT & BADGE STYLES
============================================================================= */
.alert-info, 
.alert-warning {
    background-color: #e7f3ff;
    border-color: #b8daff;
    color: #004085;
}

.manual-edit-badge {
    display: inline-block;
    background-color: #fff3cd;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 5px;
    border: 1px solid #ffc107;
}

.manual-edit-badge i {
    font-size: 9px;
}

/* =============================================================================
   EMPLOYEE SELECTION STYLES
============================================================================= */
.employee-select-container {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px;
    max-height: 300px;
    overflow-y: auto;
    background-color: #f8f9fa;
}

.employee-checkbox-item {
    padding: 8px 10px;
    margin: 3px 0;
    background: white;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
    transition: all 0.2s;
}

.employee-checkbox-item:hover {
    background: #e3f2fd;
    border-color: #2196f3;
}

.employee-checkbox-item input[type="checkbox"] {
    margin-right: 8px;
    cursor: pointer;
}

.employee-checkbox-item label {
    margin: 0;
    cursor: pointer;
    user-select: none;
    font-size: 14px;
}

.employee-search-box {
    margin-bottom: 10px;
}

.bulk-action-btns {
    margin-bottom: 10px;
    display: flex;
    gap: 8px;
}

.bulk-action-btns button {
    font-size: 12px;
    padding: 4px 10px;
}

.selected-count {
    display: inline-block;
    background: #2196f3;
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}
</style>

<!-- =============================================================================
     MAIN CONTENT CONTAINER
============================================================================= -->
<div class="pc-container">
    <div class="pc-content">
        
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Employees Shifts Management</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item">Shifts</li>
                            <li class="breadcrumb-item">Employees Shifts</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ASSIGN SHIFTS SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-calendar-check"></i> 
                            Assign Shifts to Multiple Employees
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignShiftForm">
                            <div class="row">
                                
                                <!-- EMPLOYEE SELECTION COLUMN -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Select Employees 
                                        <span class="selected-count" id="selectedCount">0 selected</span>
                                    </label>
                                    
                                    <!-- Search Box -->
                                    <div class="employee-search-box">
                                        <input 
                                            type="text" 
                                            id="employeeSearch" 
                                            class="form-control form-control-sm" 
                                            placeholder="🔍 Search employees..."
                                        >
                                    </div>
                                    
                                    <!-- Bulk Action Buttons -->
                                    <div class="bulk-action-btns">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-success" 
                                            onclick="selectAllEmployees()"
                                        >
                                            <i class="fas fa-check-double"></i> Select All
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-secondary" 
                                            onclick="clearAllEmployees()"
                                        >
                                            <i class="fas fa-times"></i> Clear All
                                        </button>
                                    </div>
                                    
                                    <!-- Employee Checkboxes Container -->
                                    <div class="employee-select-container" id="employeeContainer">
                                        <?php 
                                        mysqli_data_seek($employees, 0); 
                                        while ($emp = mysqli_fetch_assoc($employees)): 
                                        ?>
                                            <div 
                                                class="employee-checkbox-item" 
                                                data-name="<?= strtolower($emp['last_name'] . ', ' . $emp['first_name']) ?>"
                                            >
                                                <input 
                                                    type="checkbox" 
                                                    name="employee_ids[]" 
                                                    value="<?= $emp['employee_id'] ?>" 
                                                    id="emp_<?= $emp['employee_id'] ?>"
                                                    onchange="updateSelectedCount()"
                                                >
                                                <label for="emp_<?= $emp['employee_id'] ?>">
                                                    <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                
                                <!-- SHIFT DETAILS COLUMN -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <!-- Shift Selection -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Shift</label>
                                            <select name="shift_id" class="form-select" required>
                                                <option value="">--Select Shift--</option>
                                                <?php 
                                                mysqli_data_seek($shifts, 0); 
                                                while ($shift = mysqli_fetch_assoc($shifts)): 
                                                ?>
                                                    <option value="<?= $shift['shift_id'] ?>">
                                                        <?= $shift['shift_name'] ?> 
                                                        (<?= $shift['time_in'] ?> - <?= $shift['time_out'] ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Start Date -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Start Date</label>
                                            <input 
                                                type="date" 
                                                name="start_date" 
                                                class="form-control" 
                                                required
                                            >
                                        </div>
                                        
                                        <!-- End Date -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">End Date</label>
                                            <input 
                                                type="date" 
                                                name="end_date" 
                                                class="form-control" 
                                                required
                                            >
                                        </div>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <div class="text-end mt-1">
                                        <button type="submit" name="assign_shifts" class="btn btn-primary">
                                            <i class="fas fa-calendar-check"></i> Assign Shift
                                        </button>
                                    </div>
                                </div>
                                
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- =============================================================================
     JAVASCRIPT
============================================================================= -->
<script>
/**
 * Search functionality for employee list
 */
document.getElementById('employeeSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.employee-checkbox-item');
    
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        item.style.display = name.includes(searchTerm) ? 'block' : 'none';
    });
});

/**
 * Select all visible employees
 */
function selectAllEmployees() {
    const checkboxes = document.querySelectorAll(
        '.employee-checkbox-item:not([style*="display: none"]) input[type="checkbox"]'
    );
    checkboxes.forEach(cb => cb.checked = true);
    updateSelectedCount();
}

/**
 * Clear all employee selections
 */
function clearAllEmployees() {
    const checkboxes = document.querySelectorAll(
        '.employee-checkbox-item input[type="checkbox"]'
    );
    checkboxes.forEach(cb => cb.checked = false);
    updateSelectedCount();
}

/**
 * Update the selected employee count display
 */
function updateSelectedCount() {
    const checked = document.querySelectorAll(
        '.employee-checkbox-item input[type="checkbox"]:checked'
    ).length;
    document.getElementById('selectedCount').textContent = checked + ' selected';
}

/**
 * Form validation before submission
 */
document.getElementById('assignShiftForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll(
        '.employee-checkbox-item input[type="checkbox"]:checked'
    ).length;
    
    if (checked === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'No Employees Selected',
            text: 'Please select at least one employee to assign the shift.',
        });
    }
});

// Initialize count on page load
updateSelectedCount();
</script>

<!-- =============================================================================
     SUCCESS NOTIFICATION
============================================================================= -->
<?php if (isset($_SESSION['success'])): ?>
<script>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: '<?= $_SESSION['success'] ?>',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
</script>
<?php 
    unset($_SESSION['success']); 
endif; 
?>
