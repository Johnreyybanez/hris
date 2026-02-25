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

// Fetch signatory settings for different designations
$signatory_query = "SELECT * FROM signatory_settings WHERE is_active = 1 ORDER BY id DESC";
$signatory_result = mysqli_query($conn, $signatory_query);

if (!$signatory_result) {
    die("Error fetching signatory data: " . mysqli_error($conn));
}

// Initialize signatory variables with hardcoded designations only
$mayor_name = '';  // No default name - fetch from database
$mayor_designation = 'Municipal Mayor';      // Fixed designation
$mayor_signature = '';

$treasurer_name = '';  // No default name - fetch from database
$treasurer_designation = 'Municipal Treasurer'; // Fixed designation
$treasurer_signature = '';

// Process signatory data - match based on exact designation from database
while ($signatory = mysqli_fetch_assoc($signatory_result)) {
    $db_designation = trim($signatory['designation']);
    $name = htmlspecialchars($signatory['signatory_name']);
    $signature_file = $signatory['signature_image'];
    
    // Process signature image path
    $signature_path = '';
    if (!empty($signature_file)) {
        $signature_filename = basename($signature_file);
        $possible_paths = [
            'uploads/signatures/' . $signature_filename,
            $signature_file,
            'uploads/' . $signature_filename,
            'signatures/' . $signature_filename,
            '../uploads/signatures/' . $signature_filename,
            './uploads/signatures/' . $signature_filename,
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $signature_path = $path;
                break;
            }
        }
    }
    
    // Match based on exact designation or partial match
    if (stripos($db_designation, 'Municipal Mayor') !== false || stripos($db_designation, 'Mayor') !== false) {
        $mayor_name = $name;
        $mayor_signature = $signature_path;
    } elseif (stripos($db_designation, 'Municipal Treasurer') !== false || stripos($db_designation, 'Treasurer') !== false) {
        $treasurer_name = $name;
        $treasurer_signature = $signature_path;
    }
}

// Query employees
$query = "
SELECT 
    e.employee_id,
    e.biometric_id,
    CONCAT(e.last_name, ', ', e.first_name, 
        CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != '' 
        THEN CONCAT(' ', e.middle_name) ELSE '' END) AS name,
    COALESCE(pg.group_name, 'No Group') AS payroll_group,
    COALESCE(d.name, 'No Department') AS department,
    COALESCE(e.employee_rates, 0) AS daily_rate,
    COALESCE(pd.gross_pay, 0) AS gross_pay,
    COALESCE(pd.total_deductions, 0) AS total_deductions,
    COALESCE(pd.net_pay, 0) AS net_pay,
    COALESCE(pd.remarks, '') AS remarks,
    CASE 
        WHEN e.employee_rates > 0 AND pd.gross_pay > 0 
        THEN ROUND(pd.gross_pay / e.employee_rates, 0)
        ELSE 0 
    END AS days_worked
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN payroll_groups pg ON e.payroll_group_id = pg.id
LEFT JOIN payroll_details pd ON e.employee_id = pd.employee_id AND pd.payroll_id = '$payroll_id'
WHERE e.status = 'active' 
AND pg.group_name LIKE '%Job Order%'";

if (!empty($employee_id)) {
    $query .= " AND e.employee_id = '$employee_id'";
}

if (!empty($payroll_group_id)) {
    $query .= " AND e.payroll_group_id = '$payroll_group_id'";
}

$query .= " ORDER BY e.last_name ASC, e.first_name ASC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$display_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['daily_rate'] = floatval($row['daily_rate']);
    $row['gross_pay'] = floatval($row['gross_pay']);
    $row['total_deductions'] = floatval($row['total_deductions']);
    $row['net_pay'] = floatval($row['net_pay']);
    $row['days_worked'] = intval($row['days_worked']);
    $display_rows[] = $row;
}

$no_results = empty($display_rows);

// Calculate totals
$total_gross = 0;
$total_deductions = 0;
$total_net = 0;

foreach ($display_rows as $row) {
    $total_gross += $row['gross_pay'];
    $total_deductions += $row['total_deductions'];
    $total_net += $row['net_pay'];
}

// Format pay period
$pay_period = date('F j', strtotime($cutoff_start_date)) . '-' . date('j, Y', strtotime($cutoff_end_date));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Job Order Payroll</title>
<style>
    body {
        font-family:"Times New Roman", Times, serif;
        font-size: 12px;
        margin: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-top: 15px;
    }
    th, td {
        border: 1px solid black;
        padding: 4px;
        text-align: center;
        vertical-align: middle;
    }
    th {
        background: #f0f0f0;
        font-weight: bold;
    }
    .align-left {
        text-align: left;
        padding-left: 8px;
    }
    .total-row {
        font-weight: bold;
        background-color: #f9f9f9;
    }
    .signature {
        margin-top: 40px;
        width: 100%;
        text-align: center;
        font-size: 12px;
    }
    .signature td {
        border: none;
        padding-top: 40px;
        padding-left: 20px;
        padding-right: 20px;
    }
    .signature-image {
        max-height: 50px;
        max-width: 180px;
        object-fit: contain;
        margin-bottom: 5px;
    }
    @media print {
        .no-print {
            display: none !important;
        }
    }
    .print-button {
        position: fixed;
        top: 10px;
        right: 10px;
        background: #007cba;
        color: white;
        border: none;
        padding: 10px 20px;
        cursor: pointer;
        border-radius: 3px;
        font-size: 12px;
        z-index: 1000;
    }
</style>
</head>
<body>

<button class="print-button no-print" onclick="window.print();">🖨️ Print</button>

<h1 style="text-align:center;">JOB ORDER PAYROLL</h1>
<span style="display: inline-block; width: 100%; text-align: center; "><strong><?= $treasurer_name ?></strong></span>
<hr style="margin: 0px auto; width: 200px;">
<h5 style="text-align:center; margin-top: 7px;">NAME OF STORE</h5>

<div style="text-align:right; margin-bottom:0px; margin-right: 100px; margin-top: -30px;">
    For the Period of &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($pay_period) ?>
</div>

<hr style="margin-right: 100px; width: 100px; margin-left: auto; margin-top: -2px;">
<h4 style="text-align:right; margin-right: 100px; margin-top: -5px;">LGU - Aloguinsan</h4>
<hr style="margin-right: 100px; width: 100px; margin-left: auto; margin-top: -15px;">
<h5 style="text-align:right; margin-bottom:5px; margin-right: 100px; margin-top: -5px;">(NAME OF BUSINESS)</h5>
<p style="text-align: left;margin-top: -50px;">
    WE HEREBY ACKNOWLEDGE to have received from <br><br>
    the sum specified opposite our respective name, as full compensation for our service rendered.
</p>

<?php if ($no_results): ?>
    <p style="text-align:center; color: red; font-weight: bold;">No Job Order Records Found</p>
<?php else: ?>
<table>
    <thead>
    <tr>
        <th rowspan="3">No.</th>
        <th rowspan="3">Name of Employee</th>
        <th rowspan="3">Days of Work</th>
        <th rowspan="3">Rate</th>
        <th rowspan="3">Total Regular Wage</th>
        <th colspan="2">UNDERTIME</th>
        <th colspan="2">OVERTIME</th>
        <th rowspan="3">Total Amount</th>
        <th rowspan="3">SSS CONT</th>
        <th rowspan="3">PAG-IBIG CONT.</th>
        <th rowspan="3">CFI LOAN</th>
        <th rowspan="3">AGECC COOP</th>
        <th rowspan="3">Net Amount Paid</th>
        <th rowspan="3">CTC NO.</th>
    </tr>
    <tr>
        <th colspan="2">Regular Day</th>
        <th colspan="2">Regular Day</th>
    </tr>
    <tr>
        <th>Minutes</th>
        <th>Amount</th>
        <th>Hrs.</th>
        <th>Amount</th>
    </tr>
</thead>
<tbody>
    <?php 
    $counter = 1;
    foreach ($display_rows as $row): ?>
    <tr>
        <td><?= $counter ?></td>
        <td class="align-left"><?= htmlspecialchars($row['name']) ?></td>
        <td><?= $row['days_worked'] ?></td>
        <td><?= number_format($row['daily_rate'], 2) ?></td>
        <td><?= number_format($row['gross_pay'], 2) ?></td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
        <td><?= number_format($row['gross_pay'], 2) ?></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td><?= number_format($row['net_pay'], 2) ?></td>
        <td><?= htmlspecialchars($row['biometric_id']) ?></td>
    </tr>
    <?php 
    $counter++;
    endforeach; ?>
    <tr class="total-row">
        <td colspan="4" class="align-left"><strong>TOTAL</strong></td>
        <td><strong><?= number_format($total_gross, 2) ?></strong></td>
        <td colspan="2">-</td>
        <td colspan="2">-</td>
        <td><strong><?= number_format($total_gross, 2) ?></strong></td>
        <td colspan="4"></td>
        <td><strong><?= number_format($total_net, 2) ?></strong></td>
        <td></td>
    </tr>
</tbody>

</table>
<p style="margin-top:20px; text-align:center;">
    I HEREBY CERTIFY that I have personally paid in cash to each employee whose name appears in the above payroll the set opposite name.
</p>
<p style="text-align:center;">
    The amount paid in this payroll is Php &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <span style="display:inline-block; min-width:150px; border-bottom:1px solid #000; text-align:center;">
        <strong><?= number_format($total_net, 2) ?></strong>
    </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;  , including their overtime pay.
</p>

<table class="signature" style="width:100%; margin-top:0px;">
    <tr>
        <!-- Approved for Payment aligned at bottom left -->
        <td style="vertical-align:bottom; text-align:left;">
            APPROVED FOR PAYMENT:
        </td>

        <!-- Spacer column -->
        <td style="width:100px;"></td>

        <!-- Empty cell to align with Treasurer -->
        <td></td>
    </tr>
    <tr>
        <!-- Municipal Mayor with signature line -->
        <td style="padding-top:40px; text-align:center;">
            <?php if ($mayor_signature && file_exists($mayor_signature) && is_readable($mayor_signature)): ?>
                <img src="<?= htmlspecialchars($mayor_signature) ?>" alt="Signature of <?= $mayor_name ?>" class="signature-image"><br>
            <?php endif; ?>
            <strong><?= $mayor_name ?></strong><br>
            <div style="border-top:1px solid #000; width:40%; margin:auto;"></div>
            <?= $mayor_designation ?>
        </td>

        <td></td>

        <!-- Municipal Treasurer with signature line -->
        <td style="padding-top:40px; text-align:center;">
            <?php if ($treasurer_signature && file_exists($treasurer_signature) && is_readable($treasurer_signature)): ?>
                <img src="<?= htmlspecialchars($treasurer_signature) ?>" alt="Signature of <?= $treasurer_name ?>" class="signature-image"><br>
            <?php endif; ?>
            <strong><?= $treasurer_name ?></strong><br>
            <div style="border-top:1px solid #000; width:50%; margin:auto;"></div>
            <?= $treasurer_designation ?>
        </td>
    </tr>
</table>

<?php endif; ?>
</body>
</html>