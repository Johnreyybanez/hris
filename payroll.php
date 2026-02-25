<?php
// enhanced_payroll.php - Complete Enhanced Payroll System with Fixed Calculation Logic
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connection.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Utility Functions
function tableExists($conn, $tableName) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return mysqli_num_rows($result) > 0;
}

// SIMPLIFIED Payroll Calculation Function
function calculateEmployeePayroll($conn, $employee, $start_date, $end_date) {
    $employee_id = $employee['employee_id'];
    $employee_rates = (float)($employee['employee_rates'] ?? 0.0);
    $salary_type = strtolower(trim($employee['salary_type'] ?? 'monthly'));
    
    // Step 1: Calculate all salary rates
    $rates = calculateSalaryRates($employee_rates, $salary_type);
    
    // Step 2: Get basic attendance count
    $attendance_data = getBasicAttendance($conn, $employee_id, $start_date, $end_date);
    
    // Step 3: Calculate base pay according to salary type
    $base_pay = calculateBasePay($salary_type, $rates, $attendance_data);
    
    // Step 4: Calculate additional earnings (simplified)
    $overtime_pay = 0;
    $night_diff_pay = 0;
    
    // Step 5: Calculate attendance deductions
    $late_deduction = 0;
    $undertime_deduction = 0;
    $absent_deduction = $rates['daily_rate'] * $attendance_data['days_absent'];
    
    // Step 6: Calculate gross pay
    $other_earnings = getOtherEarnings($conn, $employee_id, $start_date, $end_date);
    $gross_pay = $base_pay + $overtime_pay + $night_diff_pay + $other_earnings - $late_deduction - $undertime_deduction - $absent_deduction;
    
    // Step 7: Calculate government contributions
    $gov_contributions = calculateMandatoryContributions($rates['monthly_rate']);
    
    // Step 8: Calculate withholding tax
    $withholding_tax = calculateWithholdingTax($gross_pay * 2, $gov_contributions, $employee['tax_status'] ?? 'Single');
    
    // Step 9: Get other deductions
    $other_deductions = getEmployeeDeductions($conn, $employee_id, $start_date, $end_date);
    
    // Step 10: Calculate total deductions and net pay
    $total_deductions = $late_deduction + $undertime_deduction + $absent_deduction + 
                       array_sum($gov_contributions) + $withholding_tax + $other_deductions;
    
    $net_pay = $gross_pay - $total_deductions;
    
    return [
        'basic_pay' => round($base_pay, 2),
        'additions' => [
            'overtime' => round($overtime_pay, 2),
            'night_diff' => round($night_diff_pay, 2),
            'holiday' => 0,
            'other_earnings' => round($other_earnings, 2)
        ],
        'deductions' => [
            'late' => round($late_deduction, 2),
            'undertime' => round($undertime_deduction, 2),
            'absent' => round($absent_deduction, 2),
            'sss' => round($gov_contributions['sss'], 2),
            'philhealth' => round($gov_contributions['philhealth'], 2),
            'pagibig' => round($gov_contributions['pagibig'], 2),
            'tax' => round($withholding_tax, 2),
            'other' => round($other_deductions, 2)
        ],
        'gross_pay' => round($gross_pay, 2),
        'total_deductions' => round($total_deductions, 2),
        'net_pay' => round($net_pay, 2),
        'attendance_summary' => [
            'days_present' => $attendance_data['days_present'],
            'days_absent' => $attendance_data['days_absent'],
            'regular_hours' => 0,
            'overtime_hours' => 0,
            'night_diff_hours' => 0,
            'late_hours' => 0,
            'undertime_hours' => 0,
            'holiday_days' => 0
        ],
        'salary_breakdown' => [
            'salary_type' => $salary_type,
            'monthly_rate' => $rates['monthly_rate'],
            'semi_monthly_rate' => $rates['semi_monthly_rate'],
            'daily_rate' => $rates['daily_rate'],
            'hourly_rate' => $rates['hourly_rate']
        ]
    ];
}

// Step 1: Calculate salary rates
function calculateSalaryRates($employee_rates, $salary_type) {
    switch ($salary_type) {
        case 'monthly':
            $monthly_rate = $employee_rates;
            $semi_monthly_rate = $monthly_rate / 2;
            $daily_rate = $monthly_rate / 22; // For pro-rata calculations
            $hourly_rate = $daily_rate / 8;
            break;
            
        case 'semi-monthly':
            $monthly_rate = $employee_rates * 2;
            $semi_monthly_rate = $employee_rates;
            $daily_rate = $semi_monthly_rate / 11;
            $hourly_rate = $daily_rate / 8;
            break;
            
        case 'weekly':
            $monthly_rate = $employee_rates * 4.33;
            $semi_monthly_rate = $monthly_rate / 2;
            $daily_rate = $employee_rates / 5;
            $hourly_rate = $daily_rate / 8;
            break;
            
        case 'daily':
            $daily_rate = $employee_rates;
            $monthly_rate = $daily_rate * 22;
            $semi_monthly_rate = $monthly_rate / 2;
            $hourly_rate = $daily_rate / 8;
            break;
            
        case 'hourly':
            $hourly_rate = $employee_rates;
            $daily_rate = $hourly_rate * 8;
            $monthly_rate = $daily_rate * 22;
            $semi_monthly_rate = $monthly_rate / 2;
            break;
            
        default:
            $monthly_rate = $employee_rates;
            $semi_monthly_rate = $monthly_rate / 2;
            $daily_rate = $monthly_rate / 22;
            $hourly_rate = $daily_rate / 8;
    }
    
    return [
        'monthly_rate' => $monthly_rate,
        'semi_monthly_rate' => $semi_monthly_rate,
        'daily_rate' => $daily_rate,
        'hourly_rate' => $hourly_rate
    ];
}

// Step 2: Get basic attendance data
function getBasicAttendance($conn, $employee_id, $start_date, $end_date) {
    $data = [
        'days_present' => 0,
        'days_absent' => 0
    ];
    
    if (!tableExists($conn, 'employeedtr')) {
        return $data;
    }
    
    // Simple query that works with basic employeedtr structure
    $sql = "SELECT date, time_in, time_out 
            FROM employeedtr 
            WHERE employee_id = ? 
            AND date BETWEEN ? AND ?
            ORDER BY date";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $data;
    }
    
    mysqli_stmt_bind_param($stmt, "iss", $employee_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $date = $row['date'];
        
        // Check if present (has both time_in and time_out)
        if (!empty($row['time_in']) && !empty($row['time_out'])) {
            $data['days_present']++;
        } else {
            // Check if it's a working day (not weekend)
            $day_of_week = date('N', strtotime($date));
            if ($day_of_week <= 5) { // Monday to Friday
                $data['days_absent']++;
            }
        }
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// Step 3: Calculate base pay
function calculateBasePay($salary_type, $rates, $attendance_data) {
    switch ($salary_type) {
        case 'monthly':
        case 'semi-monthly':
            // Fixed salary employees get full pay minus absent deductions
            $base_pay = $rates['semi_monthly_rate'];
            $absent_deduction = $rates['daily_rate'] * $attendance_data['days_absent'];
            return max(0, $base_pay - $absent_deduction);
            
        case 'daily':
            return $rates['daily_rate'] * $attendance_data['days_present'];
            
        case 'hourly':
            // For hourly employees, we need to calculate hours worked
            // Since we don't have hours data, estimate based on days present
            $estimated_hours = $attendance_data['days_present'] * 8;
            return $rates['hourly_rate'] * $estimated_hours;
            
        case 'weekly':
            $weeks_worked = ceil($attendance_data['days_present'] / 5);
            return $rates['semi_monthly_rate'] * $weeks_worked / 2;
            
        default:
            return $rates['semi_monthly_rate'];
    }
}

// Step 4: Calculate mandatory government contributions
function calculateMandatoryContributions($monthly_rate) {
    // SSS Contribution
    $sss_monthly = calculateSSS($monthly_rate);
    
    // PhilHealth Contribution
    $philhealth_monthly = calculatePhilHealth($monthly_rate);
    
    // Pag-IBIG Contribution
    $pagibig_monthly = calculatePagIbig($monthly_rate);
    
    // Return semi-monthly contributions
    return [
        'sss' => $sss_monthly / 2,
        'philhealth' => $philhealth_monthly / 2,
        'pagibig' => $pagibig_monthly / 2,
        'gsis' => 0
    ];
}

// SSS Calculation
function calculateSSS($monthly_salary) {
    // Simplified SSS calculation
    if ($monthly_salary <= 4250) return 191.25;
    if ($monthly_salary <= 4750) return 213.75;
    if ($monthly_salary <= 5250) return 236.25;
    if ($monthly_salary <= 5750) return 258.75;
    if ($monthly_salary <= 6250) return 281.25;
    if ($monthly_salary <= 6750) return 303.75;
    if ($monthly_salary <= 7250) return 326.25;
    if ($monthly_salary <= 7750) return 348.75;
    if ($monthly_salary <= 8250) return 371.25;
    if ($monthly_salary <= 8750) return 393.75;
    if ($monthly_salary <= 9250) return 416.25;
    if ($monthly_salary <= 9750) return 438.75;
    if ($monthly_salary <= 10250) return 461.25;
    if ($monthly_salary <= 10750) return 483.75;
    if ($monthly_salary <= 11250) return 506.25;
    if ($monthly_salary <= 11750) return 528.75;
    if ($monthly_salary <= 12250) return 551.25;
    if ($monthly_salary <= 12750) return 573.75;
    if ($monthly_salary <= 13250) return 596.25;
    if ($monthly_salary <= 13750) return 618.75;
    if ($monthly_salary <= 14250) return 641.25;
    if ($monthly_salary <= 14750) return 663.75;
    if ($monthly_salary <= 15250) return 686.25;
    if ($monthly_salary <= 15750) return 708.75;
    if ($monthly_salary <= 16250) return 731.25;
    if ($monthly_salary <= 16750) return 753.75;
    if ($monthly_salary <= 17250) return 776.25;
    if ($monthly_salary <= 17750) return 798.75;
    if ($monthly_salary <= 18250) return 821.25;
    if ($monthly_salary <= 18750) return 843.75;
    if ($monthly_salary <= 19250) return 866.25;
    if ($monthly_salary <= 19750) return 888.75;
    if ($monthly_salary <= 20250) return 911.25;
    if ($monthly_salary <= 20750) return 933.75;
    if ($monthly_salary <= 21250) return 956.25;
    if ($monthly_salary <= 21750) return 978.75;
    if ($monthly_salary <= 22250) return 1001.25;
    if ($monthly_salary <= 22750) return 1023.75;
    if ($monthly_salary <= 23250) return 1046.25;
    if ($monthly_salary <= 23750) return 1068.75;
    if ($monthly_salary <= 24250) return 1091.25;
    if ($monthly_salary <= 24750) return 1113.75;
    if ($monthly_salary <= 25250) return 1136.25;
    if ($monthly_salary <= 25750) return 1158.75;
    if ($monthly_salary <= 26250) return 1181.25;
    if ($monthly_salary <= 26750) return 1203.75;
    if ($monthly_salary <= 27250) return 1226.25;
    if ($monthly_salary <= 27750) return 1248.75;
    if ($monthly_salary <= 28250) return 1271.25;
    if ($monthly_salary <= 28750) return 1293.75;
    if ($monthly_salary <= 29250) return 1316.25;
    if ($monthly_salary <= 29750) return 1338.75;
    return 1350.00; // Maximum for salary >= 30000
}

// PhilHealth Calculation
function calculatePhilHealth($monthly_salary) {
    // PhilHealth Premium Rate 2024: 4.5% (Employee Share: 2.25%)
    if ($monthly_salary <= 10000) {
        return 225.00;
    } elseif ($monthly_salary >= 100000) {
        return 2250.00;
    } else {
        return round($monthly_salary * 0.0225, 2);
    }
}

// Pag-IBIG Calculation
function calculatePagIbig($monthly_salary) {
    // Pag-IBIG rates 2024
    if ($monthly_salary <= 1500) {
        return round($monthly_salary * 0.01, 2);
    } else {
        $contribution = round($monthly_salary * 0.02, 2);
        return min(200.00, max(100.00, $contribution));
    }
}

// Step 5: Calculate withholding tax
function calculateWithholdingTax($monthly_taxable_income, $contributions, $tax_status) {
    // Subtract contributions from taxable income
    $total_contributions = array_sum($contributions) * 2;
    $annual_taxable = ($monthly_taxable_income - $total_contributions) * 12;
    
    // Philippines Tax Table (TRAIN Law)
    if ($annual_taxable <= 250000) {
        $annual_tax = 0;
    } elseif ($annual_taxable <= 400000) {
        $annual_tax = ($annual_taxable - 250000) * 0.15;
    } elseif ($annual_taxable <= 800000) {
        $annual_tax = 22500 + ($annual_taxable - 400000) * 0.20;
    } elseif ($annual_taxable <= 2000000) {
        $annual_tax = 102500 + ($annual_taxable - 800000) * 0.25;
    } elseif ($annual_taxable <= 8000000) {
        $annual_tax = 402500 + ($annual_taxable - 2000000) * 0.30;
    } else {
        $annual_tax = 2202500 + ($annual_taxable - 8000000) * 0.35;
    }
    
    // Convert to semi-monthly
    return max(0, $annual_tax / 24);
}

// Function to get other earnings
function getOtherEarnings($conn, $employee_id, $start_date, $end_date) {
    $total = 0.0;
    
    if (tableExists($conn, 'other_earnings')) {
        $sql = "SELECT SUM(amount) as total FROM other_earnings 
                WHERE employee_id = ? 
                AND earning_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $employee_id, $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $total = (float)($row['total'] ?? 0.0);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $total;
}

// Function to get employee deductions
function getEmployeeDeductions($conn, $employee_id, $start_date, $end_date) {
    $total = 0.0;
    
    if (tableExists($conn, 'employeedeductions')) {
        $sql = "SELECT SUM(amount) as total FROM employeedeductions 
                WHERE employee_id = ? 
                AND (
                    (start_date <= ? AND end_date >= ?) OR
                    (is_recurring = 1 AND start_date <= ?)
                )";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isss", $employee_id, $end_date, $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $total = (float)($row['total'] ?? 0.0);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $total;
}

// Function to insert payroll deductions
function insertPayrollDeductions($conn, $payroll_detail_id, $employee_id, $start_date, $end_date) {
    if (!tableExists($conn, 'employeedeductions') || !tableExists($conn, 'payroll_deductions')) {
        return;
    }
    
    $sql = "SELECT deduction_id, amount, remarks 
            FROM employeedeductions 
            WHERE employee_id = ? 
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (is_recurring = 1 AND start_date <= ?)
            )";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return;
    
    mysqli_stmt_bind_param($stmt, "isss", $employee_id, $end_date, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($deduction = mysqli_fetch_assoc($result)) {
        $amount = (float)$deduction['amount'];
        $description = !empty($deduction['remarks']) ? $deduction['remarks'] : 'Employee Deduction';
        
        $insert_sql = "INSERT INTO payroll_deductions 
                      (payroll_detail_id, deduction_type_id, amount, description)
                      VALUES (?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        
        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, "iids", 
                $payroll_detail_id,
                $deduction['deduction_id'],
                $amount,
                $description
            );
            mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Handle Payroll Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    $payroll_group_id = (int)$_POST['payroll_group_id'];
    $cutoff_start = mysqli_real_escape_string($conn, $_POST['cutoff_start']);
    $cutoff_end = mysqli_real_escape_string($conn, $_POST['cutoff_end']);
    $payroll_date = mysqli_real_escape_string($conn, $_POST['payroll_date']);
    
    try {
        mysqli_begin_transaction($conn);
        
        // Check if payroll already exists
        $check_sql = "SELECT payroll_id FROM payroll 
                     WHERE payroll_group_id = ? 
                     AND cutoff_start_date = ? 
                     AND cutoff_end_date = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        
        if (!$check_stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
        
        mysqli_stmt_bind_param($check_stmt, "iss", $payroll_group_id, $cutoff_start, $cutoff_end);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("Payroll already exists for this payroll group and period!");
        }
        mysqli_stmt_close($check_stmt);
        
        // Create payroll record
        $create_sql = "INSERT INTO payroll 
                      (payroll_group_id, cutoff_start_date, cutoff_end_date, payroll_date, status, remarks)
                      VALUES (?, ?, ?, ?, 'Draft', 'Generated with simplified payroll calculation')";
        $create_stmt = mysqli_prepare($conn, $create_sql);
        
        if (!$create_stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));
        
        mysqli_stmt_bind_param($create_stmt, "isss", $payroll_group_id, $cutoff_start, $cutoff_end, $payroll_date);
        
        if (!mysqli_stmt_execute($create_stmt)) {
            throw new Exception("Failed to create payroll record: " . mysqli_error($conn));
        }
        
        $payroll_id = mysqli_insert_id($conn);
        mysqli_stmt_close($create_stmt);
        
        // Get employees in this payroll group
        $employees_sql = "SELECT 
                            e.employee_id, 
                            e.first_name, 
                            e.last_name, 
                            e.middle_name,
                            e.employee_rates, 
                            e.salary_type, 
                            e.payroll_group_id, 
                            e.tax_status
                          FROM employees e
                          WHERE e.payroll_group_id = ? 
                          AND e.status = 'Active'";
        
        $emp_stmt = mysqli_prepare($conn, $employees_sql);
        if (!$emp_stmt) throw new Exception("Prepare failed (employees): " . mysqli_error($conn));
        
        mysqli_stmt_bind_param($emp_stmt, "i", $payroll_group_id);
        mysqli_stmt_execute($emp_stmt);
        $employees = mysqli_stmt_get_result($emp_stmt);
        
        $total_employees = 0;
        $total_gross = $total_net = 0;
        
        while ($employee = mysqli_fetch_assoc($employees)) {
            // Calculate payroll
            $payroll_calc = calculateEmployeePayroll($conn, $employee, $cutoff_start, $cutoff_end);
            
            // Create detailed remarks
            $remarks = "Salary Type: " . ucfirst($employee['salary_type']) .
                      " | Base Pay: ₱" . number_format($payroll_calc['basic_pay'], 2) .
                      " | Days Worked: " . $payroll_calc['attendance_summary']['days_present'] .
                      " | Absent: " . $payroll_calc['attendance_summary']['days_absent'] .
                      " | Gross: ₱" . number_format($payroll_calc['gross_pay'], 2) .
                      " | Net: ₱" . number_format($payroll_calc['net_pay'], 2);
            
            // Insert into payroll_details
            $detail_sql = "INSERT INTO payroll_details 
                          (payroll_id, employee_id, gross_pay, total_deductions, net_pay, remarks)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            
            if (!$detail_stmt) throw new Exception("Prepare failed (detail): " . mysqli_error($conn));
            
            mysqli_stmt_bind_param($detail_stmt, "iiddss",
                $payroll_id,
                $employee['employee_id'],
                $payroll_calc['gross_pay'],
                $payroll_calc['total_deductions'],
                $payroll_calc['net_pay'],
                $remarks
            );
            
            if (!mysqli_stmt_execute($detail_stmt)) {
                throw new Exception("Failed to insert payroll details: " . mysqli_error($conn));
            }
            
            $payroll_detail_id = mysqli_insert_id($conn);
            mysqli_stmt_close($detail_stmt);
            
            // Insert deductions breakdown
            insertPayrollDeductions($conn, $payroll_detail_id, $employee['employee_id'], $cutoff_start, $cutoff_end);
            
            $total_employees++;
            $total_gross += $payroll_calc['gross_pay'];
            $total_net += $payroll_calc['net_pay'];
        }
        
        mysqli_stmt_close($emp_stmt);
        mysqli_commit($conn);
        
        $_SESSION['toast'] = [
            'type' => 'success',
            'title' => 'Success',
            'message' => "Payroll generated successfully for {$total_employees} employees!"
        ];
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?view_payroll=" . $payroll_id);
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['toast'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => "Failed to generate payroll: " . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle Payroll Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payroll_status'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['payroll_status']);
    
    $valid_statuses = ['Draft', 'Finalized', 'Processed'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'title' => 'Invalid Status',
            'message' => 'Selected status is not valid.'
        ];
        header("Location: " . $_SERVER['PHP_SELF'] . ($payroll_id ? "?view_payroll=$payroll_id" : ""));
        exit;
    }
    
    try {
        mysqli_begin_transaction($conn);
        
        $update_sql = "UPDATE payroll SET status = ? WHERE payroll_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $payroll_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Failed to update payroll status: " . mysqli_error($conn));
        }
        
        mysqli_stmt_close($update_stmt);
        mysqli_commit($conn);
        
        $_SESSION['toast'] = [
            'type' => 'success',
            'title' => 'Status Updated',
            'message' => "Payroll status updated to '$new_status' successfully."
        ];
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?view_payroll=$payroll_id");
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['toast'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => "Failed to update payroll status: " . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF'] . "?view_payroll=$payroll_id");
        exit;
    }
}

// View Payroll Details
$view_payroll_id = isset($_GET['view_payroll']) ? (int)$_GET['view_payroll'] : null;
$payroll_details = $payroll_info = $totals_summary = null;

if ($view_payroll_id) {
    // Get payroll information
    $payroll_info_sql = "SELECT p.*, pg.group_name
                        FROM payroll p
                        JOIN payroll_groups pg ON p.payroll_group_id = pg.id
                        WHERE p.payroll_id = ?";
    $info_stmt = mysqli_prepare($conn, $payroll_info_sql);
    if ($info_stmt) {
        mysqli_stmt_bind_param($info_stmt, "i", $view_payroll_id);
        mysqli_stmt_execute($info_stmt);
        $payroll_info = mysqli_fetch_assoc(mysqli_stmt_get_result($info_stmt));
        mysqli_stmt_close($info_stmt);
    }
    
    // Get detailed payroll details
    $details_sql = "SELECT pd.*,
                   CONCAT(e.last_name, ', ', e.first_name, ' ', COALESCE(e.middle_name, '')) as employee_name,
                   e.salary_type, e.employee_rates
                   FROM payroll_details pd
                   JOIN employees e ON pd.employee_id = e.employee_id
                   WHERE pd.payroll_id = ?
                   ORDER BY employee_name";
    
    $details_stmt = mysqli_prepare($conn, $details_sql);
    if ($details_stmt) {
        mysqli_stmt_bind_param($details_stmt, "i", $view_payroll_id);
        mysqli_stmt_execute($details_stmt);
        $payroll_details = mysqli_stmt_get_result($details_stmt);
        mysqli_stmt_close($details_stmt);
    }
    
    // Get payroll totals
    $totals_sql = "SELECT
                    COUNT(*) as employee_count,
                    SUM(gross_pay) as total_gross,
                    SUM(total_deductions) as total_deductions,
                    SUM(net_pay) as total_net
                   FROM payroll_details
                   WHERE payroll_id = ?";
    $totals_stmt = mysqli_prepare($conn, $totals_sql);
    if ($totals_stmt) {
        mysqli_stmt_bind_param($totals_stmt, "i", $view_payroll_id);
        mysqli_stmt_execute($totals_stmt);
        $totals_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($totals_stmt));
        mysqli_stmt_close($totals_stmt);
    }
}

// Get data for display
$groups_sql = "SELECT id as payroll_group_id, group_name, salary_type, payroll_frequency
               FROM payroll_groups WHERE is_active = TRUE ORDER BY group_name";
$groups = mysqli_query($conn, $groups_sql);

// Get recent payrolls
$recent_payrolls_sql = "SELECT
    p.payroll_id,
    p.payroll_date,
    p.cutoff_start_date,
    p.cutoff_end_date,
    p.status,
    pg.group_name,
    COUNT(pd.employee_id) as employee_count,
    COALESCE(SUM(pd.net_pay), 0) as total_net_pay
FROM payroll p
JOIN payroll_groups pg ON p.payroll_group_id = pg.id
LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
GROUP BY p.payroll_id, p.payroll_date, p.cutoff_start_date, p.cutoff_end_date, p.status, pg.group_name
ORDER BY p.created_at DESC
LIMIT 10";
$recent_payrolls = mysqli_query($conn, $recent_payrolls_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'head.php'; ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
        .payroll-breakdown {
            font-size: 0.85em;
            color: #6c757d;
        }
        .employee-info { font-weight: 500; }
        .salary-badge { font-size: 0.75em; }
        .text-nowrap { white-space: nowrap; }
        .breakdown-modal-content {
            max-height: 70vh;
            overflow-y: auto;
        }
        .calculation-badge {
            font-size: 0.7em;
            margin: 1px;
            display: inline-block;
        }
        .breakdown-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #dee2e6;
        }
        .breakdown-item:last-child {
            border-bottom: none;
            font-weight: 600;
            color: #198754;
        }
        .deduction-badge {
            font-size: 0.65em;
            padding: 2px 6px;
        }
        .attendance-summary {
            font-size: 0.8em;
        }
        .totals-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #ced4da;
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10">Simplified Payroll System</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item">Payroll</li>
                                <li class="breadcrumb-item">Generate Payroll</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['toast'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const toast = <?= json_encode($_SESSION['toast']) ?>;
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: toast.type,
                            title: toast.title,
                            text: toast.message,
                            showConfirmButton: false,
                            timer: 5000,
                            timerProgressBar: true
                        });
                    });
                </script>
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
            
            <?php if (!$view_payroll_id): ?>
            <!-- Payroll Generation Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-calculator me-2"></i>Generate Payroll</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="payrollForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="payroll_group_id" class="form-label">Payroll Group <span class="text-danger">*</span></label>
                                <select name="payroll_group_id" id="payroll_group_id" class="form-select" required>
                                    <option value="">Select a Payroll Group</option>
                                    <?php
                                    if ($groups && mysqli_num_rows($groups) > 0) {
                                        while ($group = mysqli_fetch_assoc($groups)):
                                    ?>
                                        <option value="<?= htmlspecialchars($group['payroll_group_id']) ?>">
                                            <?= htmlspecialchars($group['group_name']) ?>
                                            (<?= htmlspecialchars($group['salary_type']) ?>)
                                        </option>
                                    <?php endwhile; } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="payroll_date" class="form-label">Payroll Date <span class="text-danger">*</span></label>
                                <input type="date" name="payroll_date" id="payroll_date" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="cutoff_start" class="form-label">Cutoff Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="cutoff_start" id="cutoff_start" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="cutoff_end" class="form-label">Cutoff End Date <span class="text-danger">*</span></label>
                                <input type="date" name="cutoff_end" id="cutoff_end" class="form-control" required>
                            </div>
                            
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Payroll Calculation Logic:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li><strong>Monthly/Semi-Monthly:</strong> Fixed salary with absent deductions</li>
                                        <li><strong>Daily:</strong> Pay = Daily Rate × Days Present</li>
                                        <li><strong>Hourly:</strong> Pay = Hourly Rate × Estimated Hours</li>
                                        <li><strong>Includes:</strong> Government Contributions (SSS, PhilHealth, Pag-IBIG)</li>
                                        <li><strong>Deductions:</strong> Absences, Withholding Tax, Other Deductions</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                                <button type="submit" name="generate_payroll" class="btn btn-primary">
                                    <i class="fas fa-play me-1"></i>Generate Payroll
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Payrolls -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Payrolls</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="recentPayrollsTable">
                            <thead>
                                <tr>
                                    <th>Payroll ID</th>
                                    <th>Group</th>
                                    <th>Period</th>
                                    <th>Payroll Date</th>
                                    <th>Employees</th>
                                    <th>Total Net Pay</th>
                                    <th>Status</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_payrolls && mysqli_num_rows($recent_payrolls) > 0): ?>
                                    <?php while ($payroll = mysqli_fetch_assoc($recent_payrolls)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($payroll['payroll_id']) ?></td>
                                            <td><?= htmlspecialchars($payroll['group_name']) ?></td>
                                            <td class="text-nowrap">
                                                <?= date('M d', strtotime($payroll['cutoff_start_date'])) ?> -
                                                <?= date('M d, Y', strtotime($payroll['cutoff_end_date'])) ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($payroll['payroll_date'])) ?></td>
                                            <td class="text-center"><?= number_format($payroll['employee_count']) ?></td>
                                            <td class="text-end">₱<?= number_format($payroll['total_net_pay'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $payroll['status'] == 'Draft' ? 'warning' : ($payroll['status'] == 'Finalized' ? 'info' : 'success') ?>">
                                                    <?= htmlspecialchars($payroll['status']) ?>
                                                </span>
                                            </td>
                                            <td class="no-print">
                                                <a href="?view_payroll=<?= $payroll['payroll_id'] ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-sm btn-warning ms-1"
                                                        onclick="showEditStatusModal(<?= $payroll['payroll_id'] ?>, '<?= htmlspecialchars($payroll['status']) ?>')">
                                                    <i class="fas fa-edit"></i> Edit Status
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No payrolls found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Payroll Details View -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-file-invoice me-2"></i>Payroll Details - ID: <?= htmlspecialchars($view_payroll_id) ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($payroll_info): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Payroll Group:</strong> <?= htmlspecialchars($payroll_info['group_name'] ?? 'N/A') ?><br>
                                    <strong>Period:</strong>
                                    <?php
                                    if ($payroll_info['cutoff_start_date'] && $payroll_info['cutoff_end_date']) {
                                        echo date('M d', strtotime($payroll_info['cutoff_start_date'])) . ' - ' . date('M d, Y', strtotime($payroll_info['cutoff_end_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?><br>
                                    <strong>Payroll Date:</strong>
                                    <?= $payroll_info['payroll_date'] ? date('M d, Y', strtotime($payroll_info['payroll_date'])) : 'N/A' ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <span class="badge bg-<?= ($payroll_info['status'] ?? 'Draft') == 'Draft' ? 'warning' : (($payroll_info['status'] ?? 'Draft') == 'Finalized' ? 'info' : 'success') ?>">
                                        <?= htmlspecialchars($payroll_info['status'] ?? 'Draft') ?>
                                    </span><br>
                                    <strong>Employees:</strong> <?= number_format($totals_summary['employee_count'] ?? 0) ?><br>
                                    <strong>Total Gross:</strong> ₱<?= number_format($totals_summary['total_gross'] ?? 0, 2) ?>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Payroll information not found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card totals-card">
                        <div class="card-body text-center">
                            <h4 class="text-success mb-3">₱<?= number_format($totals_summary['total_net'] ?? 0, 2) ?></h4>
                            <p class="mb-1"><strong>Total Net Pay</strong></p>
                            <p class="mb-1 text-muted small">
                                Gross: ₱<?= number_format($totals_summary['total_gross'] ?? 0, 2) ?>
                            </p>
                            <p class="mb-0 text-muted small">
                                Deductions: ₱<?= number_format($totals_summary['total_deductions'] ?? 0, 2) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payroll Details Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Employee Payroll Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="payrollTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Salary Type</th>
                                    <th class="text-end">Basic Pay</th>
                                    <th class="text-end">Gross Pay</th>
                                    <th class="text-end">Deductions</th>
                                    <th class="text-end">Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payroll_details && mysqli_num_rows($payroll_details) > 0): ?>
                                    <?php while ($detail = mysqli_fetch_assoc($payroll_details)): ?>
                                        <tr>
                                            <td>
                                                <div class="employee-info">
                                                    <?= htmlspecialchars($detail['employee_name']) ?>
                                                </div>
                                                <div class="attendance-summary text-muted">
                                                    <?php if (!empty($detail['remarks'])): ?>
                                                        <?php
                                                        $parts = explode(' | ', $detail['remarks']);
                                                        foreach ($parts as $part) {
                                                            if (strpos($part, 'Days Worked') !== false || 
                                                                strpos($part, 'Absent') !== false) {
                                                                echo '<small>' . htmlspecialchars($part) . '</small><br>';
                                                            }
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge salary-badge bg-info">
                                                    <?= htmlspecialchars($detail['salary_type']) ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    Rate: ₱<?= number_format($detail['employee_rates'] ?? 0, 2) ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <?php
                                                $basic_pay = 0;
                                                if (!empty($detail['remarks'])) {
                                                    $parts = explode(' | ', $detail['remarks']);
                                                    foreach ($parts as $part) {
                                                        if (strpos($part, 'Base Pay') !== false) {
                                                            preg_match('/₱([\d,]+\.?\d*)/', $part, $matches);
                                                            if (!empty($matches[1])) {
                                                                $basic_pay = str_replace(',', '', $matches[1]);
                                                            }
                                                        }
                                                    }
                                                }
                                                ?>
                                                <strong>₱<?= number_format($basic_pay ?: ($detail['gross_pay'] * 0.8), 2) ?></strong>
                                            </td>
                                            <td class="text-end">
                                                <strong>₱<?= number_format($detail['gross_pay'] ?? 0, 2) ?></strong>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-danger">₱<?= number_format($detail['total_deductions'] ?? 0, 2) ?></strong>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success">
                                                    ₱<?= number_format($detail['net_pay'] ?? 0, 2) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No payroll details found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card-footer">
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Payroll Generation
                    </a>
                    <button onclick="showEditStatusModal(<?= $view_payroll_id ?>, '<?= htmlspecialchars($payroll_info['status'] ?? 'Draft') ?>')" 
                            class="btn btn-warning ms-2">
                        <i class="fas fa-edit me-1"></i>Edit Status
                    </button>
                    <button onclick="window.print()" class="btn btn-primary ms-2">
                        <i class="fas fa-print me-1"></i>Print Report
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Status Edit Modal -->
            <div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editStatusModalLabel">Edit Payroll Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body">
                                <input type="hidden" name="payroll_id" id="modal_payroll_id">
                                <div class="mb-3">
                                    <label for="payroll_status" class="form-label">Select New Status</label>
                                    <select name="payroll_status" id="payroll_status" class="form-select" required>
                                        <option value="Draft">Draft</option>
                                        <option value="Finalized">Finalized</option>
                                        <option value="Processed">Processed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_payroll_status" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Set default cutoff dates
    setDefaultCutoffDates();
    
    // Initialize DataTables
    initializeDataTables();
    
    // Form validation
    $('#payrollForm').on('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
        e.preventDefault();
        confirmPayrollGeneration();
    });
    
    // Reset form functionality
    $('button[type="reset"]').on('click', function() {
        setTimeout(setDefaultCutoffDates, 100);
    });
});

function setDefaultCutoffDates() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const day = today.getDate();
    
    let startDate, endDate;
    
    // Semi-monthly periods
    if (day <= 15) {
        startDate = new Date(year, month, 1);
        endDate = new Date(year, month, 15);
    } else {
        startDate = new Date(year, month, 16);
        endDate = new Date(year, month + 1, 0);
    }
    
    $('#cutoff_start').val(formatDate(startDate));
    $('#cutoff_end').val(formatDate(endDate));
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function initializeDataTables() {
    // Recent Payrolls Table
    if ($('#recentPayrollsTable').length) {
        $('#recentPayrollsTable').DataTable({
            pageLength: 10,
            order: [[0, "desc"]],
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Search payrolls...",
                zeroRecords: "No matching payrolls found",
                emptyTable: "No payrolls available",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last", 
                    next: "Next", 
                    previous: "Previous"
                }
            },
            dom:
                "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
            columnDefs: [
                { targets: [4, 5], className: "text-center" },
                { targets: [7], orderable: false, searchable: false }
            ],
            initComplete: function() {
                addSearchIcon(this);
            }
        });
    }
    
    <?php if ($view_payroll_id): ?>
    if ($('#payrollTable').length) {
        $('#payrollTable').DataTable({
            pageLength: 25,
            order: [[0, "asc"]],
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Search employees...",
                zeroRecords: "No matching employees found",
                emptyTable: "No payroll data available",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last", 
                    next: "Next", 
                    previous: "Previous"
                }
            },
            dom:
                "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
            columnDefs: [
                { targets: [2, 3, 4, 5], className: "text-end" }
            ],
            initComplete: function() {
                addSearchIcon(this);
            }
        });
    }
    <?php endif; ?>
}

// Function to add search icon to DataTables search box
function addSearchIcon(tableInstance) {
    // Use setTimeout to ensure DOM is ready
    setTimeout(() => {
        const searchBox = $('.dataTables_filter', tableInstance.table().container());
        if (searchBox.length && searchBox.find('.ti-search').length === 0) {
            searchBox.addClass('position-relative');
            searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
            searchBox.find('input').addClass('form-control ps-5 dt-search-input');
        }
    }, 100);
}

function validateForm() {
    const groupId = $('#payroll_group_id').val();
    const startDate = $('#cutoff_start').val();
    const endDate = $('#cutoff_end').val();
    const payrollDate = $('#payroll_date').val();
    
    if (!groupId || !startDate || !endDate || !payrollDate) {
        showError('Please fill in all required fields');
        return false;
    }
    
    if (new Date(startDate) >= new Date(endDate)) {
        showError('Cutoff end date must be after start date');
        return false;
    }
    
    if (new Date(payrollDate) < new Date(endDate)) {
        showError('Payroll date should be on or after cutoff end date');
        return false;
    }
    
    return true;
}

function confirmPayrollGeneration() {
    const groupName = $('#payroll_group_id option:selected').text();
    const startDate = $('#cutoff_start').val();
    const endDate = $('#cutoff_end').val();
    
    Swal.fire({
        title: 'Generate Payroll?',
        html: `<strong>${groupName}</strong><br>
              Period: ${formatDisplayDate(startDate)} to ${formatDisplayDate(endDate)}<br><br>
              <small>This will calculate payroll with basic calculations.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Generate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        width: 500
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingDialog();
            document.getElementById('payrollForm').submit();
        }
    });
}

function showLoadingDialog() {
    Swal.fire({
        title: 'Generating Payroll...',
        html: 'Processing payroll calculations...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

  // Function to add search icon
  function addSearchIcon() {
    const searchBox = $('.dataTables_filter');
    if (searchBox.length && searchBox.find('.ti-search').length === 0) {
      searchBox.addClass('position-relative');
      searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
      searchBox.find('input').addClass('form-control ps-5 dt-search-input');
    }
  }
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        confirmButtonText: 'OK'
    });
}

function showEditStatusModal(payrollId, currentStatus) {
    document.getElementById('modal_payroll_id').value = payrollId;
    document.getElementById('payroll_status').value = currentStatus;
    const modal = new bootstrap.Modal(document.getElementById('editStatusModal'));
    modal.show();
}

function formatDisplayDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}
</script>
</body>
</html>