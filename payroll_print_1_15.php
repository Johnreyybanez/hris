<?php
session_start();
include 'connection.php';

// Check if this is a print request
if (!isset($_POST['print_mode']) || $_POST['print_mode'] !== '1') {
    die("❌ Invalid access. This page is for printing only.");
}

// Get form data
$employee_id = mysqli_real_escape_string($conn, $_POST['employee_id'] ?? '');
$payroll_group_id = mysqli_real_escape_string($conn, $_POST['payroll_group_id'] ?? '');
$payroll_id = mysqli_real_escape_string($conn, $_POST['payroll_id'] ?? '');

if (empty($payroll_id)) {
    die("❌ Error: Payroll period is required.");
}

// Fetch the selected payroll's cutoff dates
$payroll_query = "SELECT cutoff_start_date, cutoff_end_date, payroll_date 
                 FROM payroll WHERE payroll_id = '$payroll_id' AND status IN ('Finalized', 'Processed')";
$payroll_result = mysqli_query($conn, $payroll_query);
if (!$payroll_result) {
    die("Error fetching payroll data: " . mysqli_error($conn));
}
if (mysqli_num_rows($payroll_result) == 0) {
    die("Error: Invalid or inactive payroll period selected.");
}
$payroll_data = mysqli_fetch_assoc($payroll_result);
$cutoff_start_date = $payroll_data['cutoff_start_date'];
$cutoff_end_date = $payroll_data['cutoff_end_date'];
$payroll_date = $payroll_data['payroll_date'];

// Fetch signatory settings for payroll documents
$signatory_query = "SELECT signatory_name, designation, signature_image 
                   FROM signatory_settings 
                   WHERE document_type = 'payroll' AND is_active = 1 
                   ORDER BY id ASC";
$signatory_result = mysqli_query($conn, $signatory_query);

// Default designations to look for if no payroll signatories found
$default_designations = ['PAYROLL MAKER', 'HRMO-III', 'Municipal Mayor'];
$signatories = [];

// First try to get payroll-specific signatories
if ($signatory_result && mysqli_num_rows($signatory_result) > 0) {
    while ($sig_row = mysqli_fetch_assoc($signatory_result)) {
        $signatories[] = $sig_row;
    }
} else {
    // If no payroll signatories, try to fetch by default designations
    foreach ($default_designations as $designation) {
        $fallback_query = "SELECT signatory_name, designation, signature_image 
                          FROM signatory_settings 
                          WHERE designation = '" . mysqli_real_escape_string($conn, $designation) . "' 
                          AND is_active = 1 
                          LIMIT 1";
        $fallback_result = mysqli_query($conn, $fallback_query);
        
        if ($fallback_result && mysqli_num_rows($fallback_result) > 0) {
            $fallback_row = mysqli_fetch_assoc($fallback_result);
            $signatories[] = $fallback_row;
        } else {
            // Ultimate fallback to hardcoded values
            $signatories[] = [
                'signatory_name' => $designation, 
                'designation' => $designation, 
                'signature_image' => ''
            ];
        }
    }
}
$query = "
SELECT 
    e.employee_id,
    e.biometric_id,
    CONCAT(e.last_name, ', ', e.first_name, 
        CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != '' 
        THEN CONCAT(' ', e.middle_name) ELSE '' END) AS name,
    COALESCE(pg.group_name, 'No Group') AS payroll_group,
    COALESCE(d.name, 'No Department') AS department,
    COALESCE(e.employee_rates, 0) AS basic_pay,
    COALESCE(pd.gross_pay, e.employee_rates, 0) AS gross_pay,
    COALESCE(pd.total_deductions, 0) AS total_deductions,
    COALESCE(pd.net_pay, e.employee_rates, 0) AS net_pay
    -- Add actual columns here if available, e.g.:
    -- COALESCE(pd.life_retirement_employee, 0) AS life_retirement_employee,
    -- COALESCE(pd.life_retirement_employer, 0) AS life_retirement_employer,
    -- COALESCE(pd.philhealth_employee, 0) AS philhealth_employee,
    -- COALESCE(pd.philhealth_employer, 0) AS philhealth_employer,
    -- COALESCE(pd.ec, 0) AS ec,
    -- COALESCE(pd.cash_advance, 0) AS cash_advance,
    -- COALESCE(pd.agec_coop, 0) AS agec_coop,
    -- COALESCE(pd.pagibig_loan, 0) AS pagibig_loan,
    -- ... and so on for other loans
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN payroll_groups pg ON e.payroll_group_id = pg.id
LEFT JOIN payroll_details pd ON e.employee_id = pd.employee_id AND pd.payroll_id = '$payroll_id'
LEFT JOIN payroll p ON pd.payroll_id = p.payroll_id
WHERE e.status = 'active'
";

if (!empty($employee_id)) {
    $query .= " AND e.employee_id = '$employee_id'";
}

if (!empty($payroll_group_id)) {
    $query .= " AND e.payroll_group_id = '$payroll_group_id'";
}

$query .= " ORDER BY pg.group_name, e.last_name ASC, e.first_name ASC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$display_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['basic_pay'] = floatval($row['basic_pay']);
    $row['gross_pay'] = floatval($row['gross_pay']);
    $row['total_deductions'] = floatval($row['total_deductions']);
    $row['net_pay'] = floatval($row['net_pay']);
    // If actual deduction columns are added to the query, include them here, e.g.:
    // $row['life_retirement_employee'] = floatval($row['life_retirement_employee']);
    // $row['pagibig_loan'] = floatval($row['pagibig_loan']);
    $display_rows[] = $row;
}

$no_results = empty($display_rows);

// Get current date and time for print timestamp
$print_date = date('F d, Y g:i A');
$pay_period = date('F d, Y', strtotime($cutoff_start_date)) . ' - ' . date('F d, Y', strtotime($cutoff_end_date));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Summary - Municipality of Aloguinsan</title>
    <style>
           /* Print-optimized styles for LANDSCAPE format */
        @page {
            size: 14in 8.5in; /* Legal paper size - LANDSCAPE */
            margin: 0.5in 0.75in; /* Top/bottom 0.5in, left/right 0.75in */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 10pt;
            color: #000;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header p {
            font-size: 10pt;
            margin-bottom: 2px;
        }

        .info-section {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 10pt;
        }

        .info-left, .info-right {
            flex: 1;
        }

        .info-right {
            text-align: right;
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8pt;
        }

        .payroll-table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
            word-wrap: break-word;
            line-height: 1.1;
        }

        .payroll-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.1;
        }

        .payroll-table .text-left {
            text-align: left;
            padding-left: 5px;
        }

        .payroll-table .text-right {
            text-align: right;
            padding-right: 5px;
        }

        .group-total {
            background-color: #e3f2fd !important;
            font-weight: bold;
            font-size: 8pt;
        }

        .group-total td {
            border: 1px solid #000;
        }

        .grand-total {
            background-color: #e8f5e8 !important;
            font-weight: bold;
            font-size: 9pt;
        }

        .grand-total td {
            border: 1px solid #000;
        }

        .footer {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .signature-block {
            text-align: center;
            width: 160px;
            font-size: 10pt;
            margin-left: 80px;
            margin-right: 80px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 35px;
            position: relative;
        }

        .signature-image {
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            max-height: 30px;
            max-width: 100px;
            opacity: 0.8;
        }

        .print-info {
            font-size: 8pt;
            color: #666;
            text-align: right;
            margin-top: 15px;
        }

        /* Column width optimization for LANDSCAPE legal size */
        .col-name { width: 12%; }
        .col-designation { width: 10%; }
        .col-rate { width: 6%; }
        .col-amount { width: 5%; }
        .col-small { width: 3.5%; }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .no-print {
                display: none !important;
            }
        }

        .print-button {
            position: fixed;
            top: 15px;
            right: 15px;
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button class="print-button no-print" onclick="window.print();">
        🖨️ Print Document (Landscape)
    </button>

    <!-- Header -->
    <div class="header">
        <h1>MUNICIPALITY OF ALOGUINSAN REGULAR PAYROLL</h1>
        <p><strong>Pay Period:</strong> <?= htmlspecialchars($pay_period) ?> (Pay Date: <?= date('F d, Y', strtotime($payroll_date)) ?>)</p>
    </div>

    <!-- Payroll Table -->
    <?php if ($no_results): ?>
        <center>
            <h4>No Records Found</h4>
            <p>Try adjusting your filters and search again.</p>
        </center>
    <?php else: ?>
        <table class="payroll-table">
            <thead>
                <!-- First row for group headers -->
                <tr>
                    <th rowspan="2" class="col-name">EMPLOYEE NAME</th>
                    <th rowspan="2" class="col-designation">Designation</th>
                    <th rowspan="2" class="col-rate">MONTHLY RATE</th>
                    <th rowspan="2" class="col-amount">GROSS PAY</th>
                    <th rowspan="2" class="col-amount">WITH HOLDING TAX</th>
                    <th colspan="2" class="col-amount" style="background-color: #e8f4fd; border: 2px solid #0066cc; font-weight: bold;">LIFE/RETIREMENT</th>
                    <th colspan="2" class="col-amount" style="background-color: #e8f4fd; border: 2px solid #0066cc; font-weight: bold;">PHILHEALTH</th>
                    <th rowspan="2" class="col-small">EC</th>
                    <th rowspan="2" class="col-small">CASH ADVANCE</th>
                    <th colspan="9" class="col-amount" style="background-color: #fff2e8; border: 2px solid #cc6600; font-weight: bold;">LOANS</th>
                    <th rowspan="2" class="col-amount">TOTAL DEDUCTIONS</th>
                    <th rowspan="2" class="col-amount">NET PAY</th>
                </tr>
                <!-- Second row for individual column headers -->
                <tr>
                    <th class="col-small" style="background-color: #e8f4fd;">EMPLOYEE<br>SHARE</th>
                    <th class="col-small" style="background-color: #e8f4fd;">EMPLOYER<br>SHARE</th>
                    <th class="col-small" style="background-color: #e8f4fd;">EMPLOYEE<br>SHARE</th>
                    <th class="col-small" style="background-color: #e8f4fd;">EMPLOYER<br>SHARE</th>
                    <th class="col-small" style="background-color: #fff2e8;">AGEC COOP</th>
                    <th class="col-small" style="background-color: #fff2e8;">CFI LOAN</th>
                    <th class="col-small" style="background-color: #fff2e8;">LBP LOAN</th>
                    <th class="col-small" style="background-color: #fff2e8;">1ST VALLEY<br>BANK</th>
                    <th class="col-small" style="background-color: #fff2e8;">DBP LOAN</th>
                    <th class="col-small" style="background-color: #fff2e8;">PAG-IBIG<br>LOAN</th>
                    <th class="col-small" style="background-color: #fff2e8;">GSIS PLREG</th>
                    <th class="col-small" style="background-color: #fff2e8;">GSIS MPL</th>
                    <th class="col-small" style="background-color: #fff2e8;">GSIS MPL LITE</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_group = '';
                $group_totals = [
                    'basic_pay' => 0.0,
                    'gross_pay' => 0.0,
                    'total_deductions' => 0.0,
                    'net_pay' => 0.0,
                    // Removed tax_deduction and pagibig_deduction from totals
                    // Add other deductions if included in query, e.g., 'life_retirement_employee' => 0.0
                ];
                $grand_totals = $group_totals;
                
                foreach ($display_rows as $row): 
                    if ($row['payroll_group'] != $current_group) {
                        if ($current_group != '') {
                            echo '<tr class="group-total">';
                            echo '<td class="text-left"><strong>SUBTOTAL - ' . htmlspecialchars($current_group) . '</strong></td>';
                            echo '<td></td>';
                            echo '<td class="text-right">₱' . number_format($group_totals['basic_pay'], 2) . '</td>';
                            echo '<td class="text-right">₱' . number_format($group_totals['gross_pay'], 2) . '</td>';
                            echo '<td class="text-right">₱0.00</td>'; // Placeholder for WITH HOLDING TAX
                            echo '<td class="text-right">₱0.00</td>'; // LIFE/RETIREMENT EMPLOYEE
                            echo '<td class="text-right">₱0.00</td>'; // LIFE/RETIREMENT EMPLOYER
                            echo '<td class="text-right">₱0.00</td>'; // PHILHEALTH EMPLOYEE
                            echo '<td class="text-right">₱0.00</td>'; // PHILHEALTH EMPLOYER
                            echo '<td class="text-right">₱0.00</td>'; // EC
                            echo '<td class="text-right">₱0.00</td>'; // CASH ADVANCE
                            echo '<td class="text-right">₱0.00</td>'; // AGEC COOP
                            echo '<td class="text-right">₱0.00</td>'; // CFI LOAN
                            echo '<td class="text-right">₱0.00</td>'; // LBP LOAN
                            echo '<td class="text-right">₱0.00</td>'; // 1ST VALLEY BANK
                            echo '<td class="text-right">₱0.00</td>'; // DBP LOAN
                            echo '<td class="text-right">₱0.00</td>'; // PAG-IBIG LOAN
                            echo '<td class="text-right">₱0.00</td>'; // GSIS PLREG
                            echo '<td class="text-right">₱0.00</td>'; // GSIS MPL
                            echo '<td class="text-right">₱0.00</td>'; // GSIS MPL LITE
                            echo '<td class="text-right">₱' . number_format($group_totals['total_deductions'], 2) . '</td>';
                            echo '<td class="text-right">₱' . number_format($group_totals['net_pay'], 2) . '</td>';
                            echo '</tr>';
                            $group_totals = array_map(function() { return 0.0; }, $group_totals);
                        }
                        $current_group = $row['payroll_group'];
                    }
                    
                    $group_totals['basic_pay'] += floatval($row['basic_pay']);
                    $group_totals['gross_pay'] += floatval($row['gross_pay']);
                    $group_totals['total_deductions'] += floatval($row['total_deductions']);
                    $group_totals['net_pay'] += floatval($row['net_pay']);
                    // Add other deductions to totals if included in query, e.g.:
                    // $group_totals['life_retirement_employee'] += floatval($row['life_retirement_employee']);
                    
                    $grand_totals['basic_pay'] += floatval($row['basic_pay']);
                    $grand_totals['gross_pay'] += floatval($row['gross_pay']);
                    $grand_totals['total_deductions'] += floatval($row['total_deductions']);
                    $grand_totals['net_pay'] += floatval($row['net_pay']);
                ?>
                <tr>
                    <td class="text-left"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="text-left"><?= htmlspecialchars($row['department']) ?></td>
                    <td class="text-right">₱<?= number_format($row['basic_pay'], 2) ?></td>
                    <td class="text-right">₱<?= number_format($row['gross_pay'], 2) ?></td>
                    <td class="text-right">₱0.00</td> <!-- Placeholder for WITH HOLDING TAX -->
                    <td class="text-right">₱0.00</td> <!-- LIFE/RETIREMENT EMPLOYEE -->
                    <td class="text-right">₱0.00</td> <!-- LIFE/RETIREMENT EMPLOYER -->
                    <td class="text-right">₱0.00</td> <!-- PHILHEALTH EMPLOYEE -->
                    <td class="text-right">₱0.00</td> <!-- PHILHEALTH EMPLOYER -->
                    <td class="text-right">₱0.00</td> <!-- EC -->
                    <td class="text-right">₱0.00</td> <!-- CASH ADVANCE -->
                    <td class="text-right">₱0.00</td> <!-- AGEC COOP -->
                    <td class="text-right">₱0.00</td> <!-- CFI LOAN -->
                    <td class="text-right">₱0.00</td> <!-- LBP LOAN -->
                    <td class="text-right">₱0.00</td> <!-- 1ST VALLEY BANK -->
                    <td class="text-right">₱0.00</td> <!-- DBP LOAN -->
                    <td class="text-right">₱0.00</td> <!-- PAG-IBIG LOAN -->
                    <td class="text-right">₱0.00</td> <!-- GSIS PLREG -->
                    <td class="text-right">₱0.00</td> <!-- GSIS MPL -->
                    <td class="text-right">₱0.00</td> <!-- GSIS MPL LITE -->
                    <td class="text-right">₱<?= number_format($row['total_deductions'], 2) ?></td>
                    <td class="text-right">₱<?= number_format($row['net_pay'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($current_group != ''): ?>
                <tr class="group-total">
                    <td class="text-left"><strong>SUBTOTAL - <?= htmlspecialchars($current_group) ?></strong></td>
                    <td></td>
                    <td class="text-right">₱<?= number_format($group_totals['basic_pay'], 2) ?></td>
                    <td class="text-right">₱<?= number_format($group_totals['gross_pay'], 2) ?></td>
                    <td class="text-right">₱0.00</td> <!-- WITH HOLDING TAX -->
                    <td class="text-right">₱0.00</td> <!-- LIFE/RETIREMENT EMPLOYEE -->
                    <td class="text-right">₱0.00</td> <!-- LIFE/RETIREMENT EMPLOYER -->
                    <td class="text-right">₱0.00</td> <!-- PHILHEALTH EMPLOYEE -->
                    <td class="text-right">₱0.00</td> <!-- PHILHEALTH EMPLOYER -->
                    <td class="text-right">₱0.00</td> <!-- EC -->
                    <td class="text-right">₱0.00</td> <!-- CASH ADVANCE -->
                    <td class="text-right">₱0.00</td> <!-- AGEC COOP -->
                    <td class="text-right">₱0.00</td> <!-- CFI LOAN -->
                    <td class="text-right">₱0.00</td> <!-- LBP LOAN -->
                    <td class="text-right">₱0.00</td> <!-- 1ST VALLEY BANK -->
                    <td class="text-right">₱0.00</td> <!-- DBP LOAN -->
                    <td class="text-right">₱0.00</td> <!-- PAG-IBIG LOAN -->
                    <td class="text-right">₱0.00</td> <!-- GSIS PLREG -->
                    <td class="text-right">₱0.00</td> <!-- GSIS MPL -->
                    <td class="text-right">₱0.00</td> <!-- GSIS MPL LITE -->
                    <td class="text-right">₱<?= number_format($group_totals['total_deductions'], 2) ?></td>
                    <td class="text-right">₱<?= number_format($group_totals['net_pay'], 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <tr class="grand-total">
                    <td class="text-left"><strong>GRAND TOTAL</strong></td>
                    <td></td>
                    <td class="text-right"><strong>₱<?= number_format($grand_totals['basic_pay'], 2) ?></strong></td>
                    <td class="text-right"><strong>₱<?= number_format($grand_totals['gross_pay'], 2) ?></strong></td>
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- WITH HOLDING TAX -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- LIFE/RETIREMENT EMPLOYEE -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- LIFE/RETIREMENT EMPLOYER -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- PHILHEALTH EMPLOYEE -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- PHILHEALTH EMPLOYER -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- EC -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- CASH ADVANCE -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- AGEC COOP -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- CFI LOAN -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- LBP LOAN -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- 1ST VALLEY BANK -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- DBP LOAN -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- PAG-IBIG LOAN -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- GSIS PLREG -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- GSIS MPL -->
                    <td class="text-right"><strong>₱0.00</strong></td> <!-- GSIS MPL LITE -->
                    <td class="text-right"><strong>₱<?= number_format($grand_totals['total_deductions'], 2) ?></strong></td>
                    <td class="text-right"><strong>₱<?= number_format($grand_totals['net_pay'], 2) ?></strong></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Footer with Dynamic Signatures -->
    <div class="footer">
        <?php 
        $signature_labels = ['Prepared by:', 'Reviewed by:', 'Approved by:'];
        for ($i = 0; $i < count($signatories); $i++): 
            $signatory = $signatories[$i];
        ?>
        <div class="signature-block">
            <div class="signature-line">
                <?php if (!empty($signatory['signature_image'])): ?>
                    <img src="uploads/signatures/<?= htmlspecialchars($signatory['signature_image']) ?>" 
                         alt="Signature" class="signature-image">
                <?php endif; ?>
            </div>
            <p><strong><?= isset($signature_labels[$i]) ? $signature_labels[$i] : 'Approved by:' ?></strong></p>
            <p><?= htmlspecialchars($signatory['signatory_name']) ?></p>
            <p><em><?= htmlspecialchars($signatory['designation']) ?></em></p>
        </div>
        <?php endfor; ?>
    </div>

    <div class="print-info">
        Printed on: <?= htmlspecialchars($print_date) ?>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.addEventListener('load', function() {
            // Uncomment the line below if you want auto-print
            // window.print();
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>