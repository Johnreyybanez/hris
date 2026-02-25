<?php
session_start();
include 'connection.php';

// Get form data from session
if (!isset($_SESSION['print_form_data'])) {
    header("Location: payrollslip.php");
    exit();
}

$form_data = $_SESSION['print_form_data'];
$employee_id = mysqli_real_escape_string($conn, $form_data['employee_id']);
$payroll_group_id = mysqli_real_escape_string($conn, $form_data['payroll_group_id']);
$payroll_id = mysqli_real_escape_string($conn, $form_data['payroll_id']);

// Clear the session data
unset($_SESSION['print_form_data']);

if (empty($payroll_id)) {
    die("❌ Error: Please select a payroll period.");
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

// Format the period for display
$period = date('F d', strtotime($cutoff_start_date)) . ' - ' . date('F d, Y', strtotime($cutoff_end_date));

// Query to fetch payroll details with deductions
$query = "
SELECT 
    e.employee_id,
    e.biometric_id,
    CONCAT(e.last_name, ', ', e.first_name, 
        CASE WHEN e.middle_name IS NOT NULL AND middle_name != '' 
        THEN CONCAT(' ', e.middle_name) ELSE '' END) AS name,
    COALESCE(pg.group_name, 'No Group') AS payroll_group,
    COALESCE(d.name, 'No Department') AS department,
    COALESCE(e.employee_rates, 0) AS basic_pay,
    COALESCE(pd.gross_pay, e.employee_rates, 0) AS gross_pay,
    COALESCE(pd.total_deductions, 0) AS total_deductions,
    COALESCE(pd.net_pay, e.employee_rates, 0) AS net_pay,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 1 THEN pded.amount ELSE 0 END), 0) AS tax_deduction,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 2 THEN pded.amount ELSE 0 END), 0) AS pagibig_deduction,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 3 THEN pded.amount ELSE 0 END), 0) AS adcom,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 4 THEN pded.amount ELSE 0 END), 0) AS ra,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 5 THEN pded.amount ELSE 0 END), 0) AS ta,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 6 THEN pded.amount ELSE 0 END), 0) AS lbp_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 7 THEN pded.amount ELSE 0 END), 0) AS agec_coop,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 8 THEN pded.amount ELSE 0 END), 0) AS cfi_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 9 THEN pded.amount ELSE 0 END), 0) AS lbn_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 10 THEN pded.amount ELSE 0 END), 0) AS first_valley_bank,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 11 THEN pded.amount ELSE 0 END), 0) AS dbp_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 12 THEN pded.amount ELSE 0 END), 0) AS pagibig_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 13 THEN pded.amount ELSE 0 END), 0) AS emergency_loan,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 14 THEN pded.amount ELSE 0 END), 0) AS gsis_mpl,
    COALESCE(SUM(CASE WHEN pded.deduction_type_id = 15 THEN pded.amount ELSE 0 END), 0) AS gsis_conso_loan
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN payroll_groups pg ON e.payroll_group_id = pg.id
LEFT JOIN payroll_details pd ON e.employee_id = pd.employee_id AND pd.payroll_id = '$payroll_id'
LEFT JOIN payroll p ON pd.payroll_id = p.payroll_id
LEFT JOIN payroll_deductions pded ON pd.payroll_detail_id = pded.payroll_detail_id
WHERE e.status = 'active'
";

if (!empty($employee_id)) {
    $query .= " AND e.employee_id = '$employee_id'";
}

if (!empty($payroll_group_id)) {
    $query .= " AND e.payroll_group_id = '$payroll_group_id'";
}

$query .= " GROUP BY e.employee_id, pd.payroll_detail_id ORDER BY pg.group_name, e.last_name ASC, e.first_name ASC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$display_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    foreach ($row as $k => $v) {
        if (is_numeric($v)) {
            $row[$k] = floatval($v);
        }
    }
    $display_rows[] = $row;
}

if (empty($display_rows)) {
    echo '<div style="text-align: center; padding: 50px; font-size: 18px; color: #666;">';
    echo '<h3>No Records Found</h3>';
    echo '<p>No payroll records match the selected filters.</p>';
    echo '<a href="payrollslip.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">Back to Payroll Summary</a>';
    echo '</div>';
    exit();
}

// FIXED: Fetch signatory details for "Prepared by" - updated query and file path handling
$signatory_query = "SELECT signatory_name, designation, signature_image 
                    FROM signatory_settings 
                    WHERE LOWER(TRIM(designation)) LIKE '%payroll%maker%' AND is_active = 1 
                    ORDER BY id DESC 
                    LIMIT 1";
$signatory_result = mysqli_query($conn, $signatory_query);

// Debug: Check if query executed successfully
if (!$signatory_result) {
    die("Error fetching signatory data: " . mysqli_error($conn));
}

$signatory_data = mysqli_fetch_assoc($signatory_result);
$signatory_name = $signatory_data ? htmlspecialchars($signatory_data['signatory_name']) : 'N/A';
$signatory_designation = $signatory_data && !empty($signatory_data['designation']) ? htmlspecialchars($signatory_data['designation']) : 'PAYROLL MAKER';

// FIXED: Handle signature image path properly - Updated to check uploads/signatures directory
$signatory_image = '';
if ($signatory_data && !empty($signatory_data['signature_image'])) {
    $signature_filename = $signatory_data['signature_image'];
    
    // Clean up filename to remove any path separators for security
    $signature_filename = basename($signature_filename);
    
    // Try different possible paths where the signature might be stored
    $possible_paths = [
        'uploads/signatures/' . $signature_filename,  // Primary path: uploads/signatures/
        $signature_filename, // Direct path (if full path is stored)
        'uploads/' . $signature_filename, // If stored in uploads folder
        'signatures/' . $signature_filename, // If stored in signatures folder
        '../uploads/signatures/' . $signature_filename, // If script is in subdirectory
        './uploads/signatures/' . $signature_filename, // Explicit current directory
    ];
    
    // Check each possible path
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $signatory_image = $path;
            break;
        }
    }
    
    // Additional debugging info (remove in production)
    if (empty($signatory_image)) {
        // Log or display which paths were checked for debugging
        error_log("Signature image not found. Checked paths: " . implode(', ', $possible_paths));
    }
}

// Debug information (remove this in production)
/*
echo "<!-- DEBUG INFO:
Signatory Name: " . ($signatory_data ? $signatory_data['signatory_name'] : 'Not found') . "
Signature Filename: " . ($signatory_data ? $signatory_data['signature_image'] : 'Not found') . "
Final Image Path: " . $signatory_image . "
File exists: " . (file_exists($signatory_image) ? 'Yes' : 'No') . "
Current working directory: " . getcwd() . "
-->";
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
<title>Payroll Slip</title>
<style>
    body { font-family: "Times New Roman", Times, serif; font-size: 14px; margin: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #000; padding: 5px; text-align: right; }
    th { text-align: center; }
    .left { text-align: left; }
    .page-break { page-break-before: always; }
    @media print { .no-print { display: none !important; } }
    .signature-container { 
        height: 60px; 
        margin-bottom: 10px; 
        display: flex; 
        align-items: center; 
        justify-content: flex-start; 
    }
    .signature-image { 
        max-height: 50px; 
        max-width: 200px; 
        object-fit: contain; 
    }
    .signature-placeholder { 
        font-style: italic; 
        color: #666; 
        font-size: 12px; 
    }
</style>
</head>
<body>
<div class="no-print" style="text-align:center; margin-bottom:20px;">
    <button onclick="window.print()" style="background:#28a745; color:#fff; padding:10px 20px; border:none; border-radius:5px;">🖨️ Print Payroll Slips</button>
    <button onclick="window.location.href='payrollslip.php'" style="background:#6c757d; color:#fff; padding:10px 20px; border:none; border-radius:5px;">← Back to Form</button>
</div>

<?php foreach ($display_rows as $index => $row): ?>
    <?php if ($index > 0): ?><div class="page-break"></div><?php endif; ?>

<div style="display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 20px;">
    <!-- Logo aligned to the left -->
    <img src="logo.png" alt="Logo" style="height: 80px; position: absolute; left: 0; top: 50%; transform: translateY(-50%);">

        <div style="text-align: center;">
            <h2 style="margin: 0;">MUNICIPALITY OF ALOGUINSAN</h2>
            <h3 style="margin: 0;">POBLACION, ALOGUINSAN, CEBU</h3>
            <h3 style="margin: 0;">Pay Slip for the Month of <?= htmlspecialchars($period) ?></h3>
        </div>
    </div>

    <!-- EMPLOYEE INFO -->
    <div style="margin-bottom: 10px;">
        <strong>Name:</strong> <?= htmlspecialchars($row['name']) ?><br>
        <strong>Emp. No.:</strong> <?= htmlspecialchars($row['employee_id']) ?><br>
        <strong>Designation:</strong> <?= htmlspecialchars($row['department']) ?>
    </div>

    <!-- PAYROLL TABLE -->
    <table>
        <tr>
            <th>DESCRIPTION</th><th>AMOUNT</th><th>DEDUCTIONS</th><th>AMOUNT</th>
        </tr>
        <tr>
            <td class="left">MONTHLY RATE</td><td><?= number_format($row['basic_pay'], 2) ?></td>
            <td class="left">WITHHOLDING TAX</td><td><?= number_format($row['tax_deduction'], 2) ?></td>
        </tr>
        <tr>
            <td class="left">GROSS PAY</td><td><?= number_format($row['gross_pay'], 2) ?></td>
            <td class="left">PAGIBIG CONTRIBUTION</td><td><?= number_format($row['pagibig_deduction'], 2) ?></td>
        </tr>
        <tr>
            <td class="left">ADCOM</td><td><?= number_format($row['adcom'], 2) ?></td>
            <td class="left">AGEC COOP</td><td><?= number_format($row['agec_coop'], 2) ?></td>
        </tr>
        <tr>
            <td class="left">RATA (RA + TA)</td><td><?= number_format($row['ra'] + $row['ta'], 2) ?></td>
            <td class="left">LBP LOAN</td><td><?= number_format($row['lbp_loan'], 2) ?></td>
        </tr>
        <tr><td></td><td></td><td class="left">1st VALLEY BANK</td><td><?= number_format($row['first_valley_bank'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">DBP LOAN</td><td><?= number_format($row['dbp_loan'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">PAGIBIG LOAN</td><td><?= number_format($row['pagibig_loan'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">EMERGENCY LOAN</td><td><?= number_format($row['emergency_loan'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">GSIS MPL LOAN</td><td><?= number_format($row['gsis_mpl'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">GSIS CONSO LOAN</td><td><?= number_format($row['gsis_conso_loan'], 2) ?></td></tr>
        <tr><td></td><td></td><td class="left">LBP REFUND LOAN</td><td><?= number_format($row['lbn_loan'], 2) ?></td></tr>
        <tr>
            <td class="left"><strong>TOTAL GROSS PAY</strong></td>
            <td><strong><?= number_format($row['gross_pay'] + $row['adcom'] + $row['ra'] + $row['ta'], 2) ?></strong></td>
            <td class="left"><strong>TOTAL DEDUCTIONS</strong></td>
            <td><strong><?= number_format($row['total_deductions'], 2) ?></strong></td>
        </tr>
        <tr>
            <td></td><td></td>
            <td class="left"><strong>NET SALARY</strong></td>
            <td><strong><?= number_format($row['net_pay'], 2) ?></strong></td>
        </tr>
        <tr>
            <td class="left" colspan="4"><strong>Amount in words:</strong></td>
        </tr>
    </table>

    <center><p><em>Note: This is a system generated pay slip, does not require seal and signature.</em></p></center>

    <!-- SIGNATURES -->
    <div style="margin-top: 40px; display: flex; justify-content: space-between;">
        <div>
            <strong>Prepared by:</strong><br><br>
            <div class="signature-container">
                <?php if ($signatory_image && file_exists($signatory_image) && is_readable($signatory_image)): ?>
                    <img src="<?= htmlspecialchars($signatory_image) ?>" alt="Signature of <?= $signatory_name ?>" class="signature-image">
                <?php else: ?>
                    <div class="signature-placeholder">
                        <?php if (!empty($signatory_data['signature_image'])): ?>
                            [Signature file not found in uploads/signatures/: <?= htmlspecialchars(basename($signatory_data['signature_image'])) ?>]
                        <?php else: ?>
                            [No signature image available]
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <u><?= $signatory_name ?></u><br>
            <?= $signatory_designation ?>
        </div>
    </div>
<?php endforeach; ?>
</body>
</html>