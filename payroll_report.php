<?php
session_start();
include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch all employees for dropdown
$employees_query = "SELECT employee_id, CONCAT(last_name, ', ', first_name, 
                    CASE WHEN middle_name IS NOT NULL AND middle_name != '' 
                    THEN CONCAT(' ', middle_name) ELSE '' END) AS name 
                    FROM employees WHERE status='active' ORDER BY last_name";
$employees = mysqli_query($conn, $employees_query);

// Fetch all active payroll groups
$payroll_groups_query = "SELECT id, group_name FROM payroll_groups WHERE is_active = 1 ORDER BY group_name";
$payroll_groups = mysqli_query($conn, $payroll_groups_query);

// Fetch all payroll records for cutoff selection
$payroll_periods_query = "SELECT payroll_id, CONCAT(DATE_FORMAT(cutoff_start_date, '%M %d, %Y'), ' - ', DATE_FORMAT(cutoff_end_date, '%M %d, %Y')) AS period, payroll_date, cutoff_start_date, cutoff_end_date 
                         FROM payroll WHERE status IN ('Finalized', 'Processed') ORDER BY cutoff_start_date DESC";
$payroll_periods = mysqli_query($conn, $payroll_periods_query);
$payroll_periods_count = mysqli_num_rows($payroll_periods);

// Check for query errors
if (!$employees || !$payroll_groups || !$payroll_periods) {
    die("Error fetching data: " . mysqli_error($conn));
}

$display_rows = [];
$search_performed = false;
$no_results = false;
$cutoff_start_date = '';
$cutoff_end_date = '';
$payroll_date = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_performed = true;
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id'] ?? '');
    $payroll_group_id = mysqli_real_escape_string($conn, $_POST['payroll_group_id'] ?? '');
    $payroll_id = mysqli_real_escape_string($conn, $_POST['payroll_id'] ?? '');

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

    // Updated query to fetch payroll details
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
        COALESCE(pd.total_deductions, 0) AS tax_deduction,
        COALESCE(pd.total_deductions, 0) AS pagibig_deduction
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

    while ($row = mysqli_fetch_assoc($result)) {
        $row['basic_pay'] = floatval($row['basic_pay']);
        $row['gross_pay'] = floatval($row['gross_pay']);
        $row['total_deductions'] = floatval($row['total_deductions']);
        $row['net_pay'] = floatval($row['net_pay']);
        $row['tax_deduction'] = floatval($row['tax_deduction']);
        $row['pagibig_deduction'] = floatval($row['pagibig_deduction']);
        $display_rows[] = $row;
    }

    if (empty($display_rows)) {
        $no_results = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Payroll Summary Report</title>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css">
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body, table { font-size: 8pt !important; }
            #payrollTable { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        }

        #payrollTable {
            table-layout: auto !important;
            width: 100% !important;
            margin: 0 auto;
        }

        #payrollTable th {
            font-weight: bold;
            font-size: 10px;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            height: 40px;
            padding: 4px !important;
            white-space: nowrap;
        }

        #payrollTable td {
            font-size: 11px !important;
            text-align: center;
            vertical-align: middle;
            height: 35px;
            padding: 3px !important;
            white-space: nowrap;
        }

        .dataTables_wrapper { overflow-x: auto; }
        .card-body { padding: 0.75rem; }
        .dt-buttons .btn { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        .c-name { min-width: 150px; }
        .c-design { min-width: 120px; }
        .c-rate, .c-small { min-width: 70px; }
        .group-header { background-color: #e3f2fd !important; font-weight: bold; color: #1976d2; }
        .group-total { background-color: #f3e5f5 !important; font-weight: bold; color: #7b1fa2; }
        .grand-total { background-color: #e8f5e8 !important; font-weight: bold; color: #2e7d32; font-size: 12px !important; }
        .no-results-card { max-width: 500px; margin: 50px auto; padding: 30px; text-align: center; border: 1px solid #dee2e6; border-radius: 5px; background-color: #f8f9fa; }
        .no-results-icon { font-size: 50px; color: #6c757d; margin-bottom: 20px; }
        .filter-card { margin-bottom: 20px; }
        .results-card { margin-top: 20px; }
        .no-print { display: block; }
        .alert { margin: 20px auto; max-width: 500px; }
    </style>
</head>
<body>
<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Payroll Summary</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item">Payroll</li>
                            <li class="breadcrumb-item">Payroll Summary</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($payroll_periods_count == 0): ?>
        <center>
            <div class="alert alert-warning">
                <h4 class="alert-heading">No Payroll Periods Available</h4>
                <p>No finalized or processed payroll periods found. Please create or finalize a payroll period to generate a summary.</p>
            </div>
        </center>
        <?php else: ?>
        <div class="card filter-card no-print">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i> Payroll Summary Filter
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3" id="payrollFilterForm">
                    <div class="col-md-4">
                        <label for="employee_id" class="form-label">
                            <i class="fas fa-user me-1"></i> Employee
                        </label>
                        <select name="employee_id" id="employee_id" class="form-select">
                            <option value="">-- All Employees --</option>
                            <?php 
                            mysqli_data_seek($employees, 0);
                            while ($emp = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?= $emp['employee_id'] ?>" <?= (isset($_POST['employee_id']) && $_POST['employee_id'] == $emp['employee_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="payroll_group_id" class="form-label">
                            <i class="fas fa-users me-1"></i> Payroll Group
                        </label>
                        <select name="payroll_group_id" id="payroll_group_id" class="form-select">
                            <option value="">-- All Groups --</option>
                            <?php 
                            mysqli_data_seek($payroll_groups, 0);
                            while ($group = mysqli_fetch_assoc($payroll_groups)): ?>
                                <option value="<?= $group['id'] ?>" <?= (isset($_POST['payroll_group_id']) && $_POST['payroll_group_id'] == $group['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($group['group_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="payroll_id" class="form-label">
                            <i class="fas fa-calendar-alt me-1"></i> Payroll Period
                        </label>
                        <select name="payroll_id" id="payroll_id" class="form-select" required>
                            <option value="">-- Select Payroll Period --</option>
                            <?php 
                            mysqli_data_seek($payroll_periods, 0);
                            while ($period = mysqli_fetch_assoc($payroll_periods)): ?>
                                <option value="<?= $period['payroll_id'] ?>" <?= (isset($_POST['payroll_id']) && $_POST['payroll_id'] == $period['payroll_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($period['period']) ?> (Pay Date: <?= date('M d, Y', strtotime($period['payroll_date'])) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Generate Summary
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i> Clear Filter
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($search_performed && $no_results): ?>
        <center>
            <div class="no-results-card">
                <div class="no-results-icon"><i class="fas fa-search"></i></div>
                <h4 class="text-muted">No Records Found</h4>
                <p class="text-muted">No payroll records match the selected filters. Try adjusting your filters and search again.</p>
            </div>
        </center>
        <?php endif; ?>

        <?php if (!empty($display_rows)): ?>
        <div class="card results-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i> MUNICIPALITY OF ALCUINSAR REGULAR PAYROLL for 
                    <?= date('M d, Y', strtotime($cutoff_start_date)) ?> - <?= date('M d, Y', strtotime($cutoff_end_date)) ?> 
                    (Pay Date: <?= date('M d, Y', strtotime($payroll_date)) ?>) (<?= count($display_rows) ?> Records)
                </h5>
                <div class="btn-group float-end no-print">
                    <button class="btn btn-success" onclick="openPrintPage()">
                        <i class="fas fa-print me-2"></i> Print Summary
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="payrollTable">
                        <thead class="table-light">
                            <tr>
                                <th class="c-name">NAME OF EMPLOYEE</th>
                                <th class="c-design">DESIGNATION</th>
                                <th class="c-rate">MONTHLY RATE</th>
                                <th class="c-small">GROSS PAY</th>
                                <th class="c-small">ADCOM</th>
                                <th class="c-small">RA</th>
                                <th class="c-small">TA</th>
                                <th class="c-small">GROSS PAY</th>
                                <th class="c-small">W/HOLDING TAX</th>
                                <th class="c-small">PERSONAL SHARE</th>
                                <th class="c-small">GOV'T SHARE</th>
                                <th class="c-small">REFUND LBP LOAN</th>
                                <th class="c-small">AGEC COOP</th>
                                <th class="c-small">CFI LOAN</th>
                                <th class="c-small">LBN LOAN</th>
                                <th class="c-small">1ST VALLEY BANK</th>
                                <th class="c-small">DBP LOAN</th>
                                <th class="c-small">PAG-IBIG LOAN</th>
                                <th class="c-small">EMERGENCY LOAN</th>
                                <th class="c-small">GSIS MPL</th>
                                <th class="c-small">GSIS CONSO LOAN</th>
                                <th class="c-small">TOTAL DEDUCTIONS</th>
                                <th class="c-small">TOTAL NET PAY</th>
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
                                'tax_deduction' => 0.0,
                                'pagibig_deduction' => 0.0
                            ];
                            $grand_totals = $group_totals;

                            foreach ($display_rows as $row): 
                                if ($row['payroll_group'] != $current_group) {
                                    if ($current_group != '') {
                                        echo '<tr class="group-total">';
                                        echo '<td class="text-end"><strong>Group Total - ' . htmlspecialchars($current_group) . '</strong></td>';
                                        echo '<td></td><td></td>';
                                        echo '<td>₱'.number_format($group_totals['basic_pay'], 2).'</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱'.number_format($group_totals['gross_pay'], 2).'</td>';
                                        echo '<td>₱'.number_format($group_totals['tax_deduction'], 2).'</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱'.number_format($group_totals['pagibig_deduction'], 2).'</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱0.00</td>';
                                        echo '<td>₱'.number_format($group_totals['total_deductions'], 2).'</td>';
                                        echo '<td>₱'.number_format($group_totals['net_pay'], 2).'</td>';
                                        echo '</tr>';
                                        $group_totals = array_map(function() { return 0.0; }, $group_totals);
                                    }
                                    $current_group = $row['payroll_group'];
                                }
                                
                                $group_totals['basic_pay'] += floatval($row['basic_pay']);
                                $group_totals['gross_pay'] += floatval($row['gross_pay']);
                                $group_totals['total_deductions'] += floatval($row['total_deductions']);
                                $group_totals['net_pay'] += floatval($row['net_pay']);
                                $group_totals['tax_deduction'] += floatval($row['tax_deduction']);
                                $group_totals['pagibig_deduction'] += floatval($row['pagibig_deduction']);
                                
                                $grand_totals['basic_pay'] += floatval($row['basic_pay']);
                                $grand_totals['gross_pay'] += floatval($row['gross_pay']);
                                $grand_totals['total_deductions'] += floatval($row['total_deductions']);
                                $grand_totals['net_pay'] += floatval($row['net_pay']);
                                $grand_totals['tax_deduction'] += floatval($row['tax_deduction']);
                                $grand_totals['pagibig_deduction'] += floatval($row['pagibig_deduction']);
                            ?>
                            <tr>
                                <td style="text-align: left;"><?= htmlspecialchars($row['name']) ?></td>
                                <td style="text-align: left;"><?= htmlspecialchars($row['department']) ?></td>
                                <td>₱<?= number_format($row['basic_pay'], 2) ?></td>
                                <td>₱<?= number_format($row['gross_pay'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($row['gross_pay'], 2) ?></td>
                                <td>₱<?= number_format($row['tax_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($row['pagibig_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($row['total_deductions'], 2) ?></td>
                                <td>₱<?= number_format($row['net_pay'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if ($current_group != ''): ?>
                            <tr class="group-total">
                                <td class="text-end"><strong>Group Total - <?= htmlspecialchars($current_group) ?></strong></td>
                                <td></td><td></td>
                                <td>₱<?= number_format($group_totals['basic_pay'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($group_totals['gross_pay'], 2) ?></td>
                                <td>₱<?= number_format($group_totals['tax_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($group_totals['pagibig_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($group_totals['total_deductions'], 2) ?></td>
                                <td>₱<?= number_format($group_totals['net_pay'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="grand-total">
                                <td class="text-end"><strong>GRAND TOTAL</strong></td>
                                <td></td><td></td>
                                <td>₱<?= number_format($grand_totals['basic_pay'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($grand_totals['gross_pay'], 2) ?></td>
                                <td>₱<?= number_format($grand_totals['tax_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($grand_totals['pagibig_deduction'], 2) ?></td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱0.00</td>
                                <td>₱<?= number_format($grand_totals['total_deductions'], 2) ?></td>
                                <td>₱<?= number_format($grand_totals['net_pay'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
// Pass PHP variables to JavaScript
const cutoffStartDate = '<?= $cutoff_start_date ?>';
const cutoffEndDate = '<?= $cutoff_end_date ?>';

$(document).ready(function () {
    if ($('#payrollTable').length && $('#payrollTable tbody tr').length > 0) {
        const table = $('#payrollTable').DataTable({
            lengthMenu: [[-1], ["All"]],
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            responsive: false,
            scrollX: true,
            fixedHeader: { header: true, footer: false },
            autoWidth: true,
            columnDefs: [
                { width: "auto", targets: "_all" },
                { targets: [0, 1], className: 'text-start' },
                { targets: '_all', className: 'text-center' }
            ],
            dom: "<'row mb-3'<'col-md-6 d-flex align-items-center'><'col-md-6 d-flex justify-content-end align-items-center'B>>" +
                 "<'row'<'col-sm-12'tr>>",
            language: {
                paginate: {
                    previous: '<i class="fas fa-chevron-left"></i>',
                    next: '<i class="fas fa-chevron-right"></i>'
                },
                info: "Showing _TOTAL_ records",
                infoEmpty: "Showing 0 records",
                lengthMenu: "Show _MENU_ records per page",
                zeroRecords: "No matching records found",
                emptyTable: "No data available in table"
            },
            initComplete: function() { this.api().columns.adjust(); }
        });

        $(window).on('resize', function() { table.columns.adjust(); });
    }

    // Form validation
    $('#payrollFilterForm').on('submit', function(e) {
        if (!$('#payroll_id').val()) {
            e.preventDefault();
            alert('Please select a payroll period.');
        }
    });
});

function openPrintPage() {
    if ($('#payrollTable tbody tr').length === 0) {
        alert('No data available to print. Please generate a payroll summary first.');
        return;
    }
    
    var employee_id = document.getElementById('employee_id').value;
    var payroll_group_id = document.getElementById('payroll_group_id').value;
    var payroll_id = document.getElementById('payroll_id').value;
    
    if (!payroll_id) {
        alert('Payroll period is required for printing.');
        return;
    }
    
    // Check if Job Order group is selected
    var selectedGroupText = $('#payroll_group_id option:selected').text().toLowerCase();
    var isJobOrder = selectedGroupText.includes('job order') || selectedGroupText.includes('jo');
    
    // If no specific group is selected, check if the data contains job order employees
    if (!payroll_group_id) {
        var hasJobOrder = false;
        var hasRegular = false;
        
        $('#payrollTable tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.includes('job order') || rowText.includes('jo')) {
                hasJobOrder = true;
            } else if (!$(this).hasClass('group-total') && !$(this).hasClass('grand-total')) {
                hasRegular = true;
            }
        });
        
        // If both types exist, ask user which to print
        if (hasJobOrder && hasRegular) {
            var choice = confirm("Both Regular and Job Order employees found.\n\nClick OK for Regular Payroll\nClick Cancel for Job Order Payroll");
            isJobOrder = !choice;
        } else if (hasJobOrder) {
            isJobOrder = true;
        }
    }
    
    // Ensure cutoffStartDate and cutoffEndDate are properly defined and parsed
    if (typeof cutoffStartDate === 'undefined' || typeof cutoffEndDate === 'undefined') {
        alert('Payroll period dates are not properly loaded. Please refresh and try again.');
        return;
    }
    
    // Parse dates more reliably
    const startDate = new Date(cutoffStartDate);
    const endDate = new Date(cutoffEndDate);
    
    // Validate dates
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
        alert('Invalid payroll period dates. Please check the selected payroll period.');
        return;
    }
    
    const startDay = startDate.getDate();
    const endDay = endDate.getDate();
    const startMonth = startDate.getMonth();
    const endMonth = endDate.getMonth();
    const startYear = startDate.getFullYear();
    const endYear = endDate.getFullYear();
    
    console.log('Date Analysis:', {
        startDate: cutoffStartDate,
        endDate: cutoffEndDate,
        startDay: startDay,
        endDay: endDay,
        startMonth: startMonth,
        endMonth: endMonth
    });
    
    // Improved date range logic for job orders
    let hasFirstHalf = false;
    let hasSecondHalf = false;
    let isFullMonth = false;
    
    // Check if this spans the full month (1-31 or close to it)
    if (startDay === 1 && (endDay >= 28 && endDay <= 31)) {
        isFullMonth = true;
        hasFirstHalf = true;
        hasSecondHalf = true;
    } else {
        // Determine which halves are covered
        hasFirstHalf = (startDay <= 15);
        hasSecondHalf = (endDay >= 16);
        
        // Special case: if start is after 15 but end is before 16, it's second half only
        if (startDay >= 16 && endDay < 16) {
            hasFirstHalf = false;
            hasSecondHalf = true;
        }
        
        // Cross-month periods (rare but possible)
        if (startMonth !== endMonth || startYear !== endYear) {
            hasFirstHalf = true;
            hasSecondHalf = true;
        }
    }
    
    console.log('Print Decision:', {
        isFullMonth: isFullMonth,
        hasFirstHalf: hasFirstHalf,
        hasSecondHalf: hasSecondHalf,
        isJobOrder: isJobOrder
    });
    
    // Function to open print page
    function openPrintTab(url, fields) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.target = '_blank';
        form.style.display = 'none';
        
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    const fields = {
        'employee_id': employee_id,
        'payroll_group_id': payroll_group_id,
        'payroll_id': payroll_id,
        'print_mode': '1',
        'cutoff_start': cutoffStartDate,
        'cutoff_end': cutoffEndDate
    };
    
    // Choose the appropriate print file based on payroll type
    var printFile;
    
    if (isJobOrder) {
        // For Job Order, always use single comprehensive print page
        printFile = 'job_order_print.php'; // Use your existing job order print file
        console.log('Printing Job Order Payroll - Single Page');
    } else {
        // Regular payroll logic - still uses split prints
        var printFiles = [];
        if (hasFirstHalf) {
            printFiles.push('payroll_print_1_15.php');
        }
        if (hasSecondHalf) {
            printFiles.push('payroll_print_16_31.php');
        }
        
        if (printFiles.length === 0) {
            alert('No valid date range for printing. Please check the payroll period.');
            return;
        }
        
        // Open multiple files for regular payroll
        printFiles.forEach((file, index) => {
            setTimeout(() => {
                console.log(`Opening print file: ${file}`);
                openPrintTab(file, fields);
            }, index * 100);
        });
        
        console.log('Printing Regular Payroll - Multiple Pages');
        return; // Exit early for regular payroll
    }
    
    // Open single print page for Job Order
    if (!hasFirstHalf && !hasSecondHalf) {
        alert('No valid date range for printing. Please check the payroll period.');
        return;
    }
    
    console.log(`Opening single Job Order print file: ${printFile}`);
    openPrintTab(printFile, fields);
}

// Helper function to format date for display
function formatDateRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        // Same month
        return `${start.toLocaleDateString('en-US', {month: 'long', day: 'numeric'})} - ${end.toLocaleDateString('en-US', options)}`;
    } else {
        // Different months
        return `${start.toLocaleDateString('en-US', options)} - ${end.toLocaleDateString('en-US', options)}`;
    }
}

// Additional helper function for debugging date issues
function debugDateInfo() {
    console.log('Debug Date Info:', {
        cutoffStartDate: typeof cutoffStartDate !== 'undefined' ? cutoffStartDate : 'UNDEFINED',
        cutoffEndDate: typeof cutoffEndDate !== 'undefined' ? cutoffEndDate : 'UNDEFINED',
        startDateParsed: typeof cutoffStartDate !== 'undefined' ? new Date(cutoffStartDate) : 'N/A',
        endDateParsed: typeof cutoffEndDate !== 'undefined' ? new Date(cutoffEndDate) : 'N/A'
    });
}
</script>

</body>
</html>