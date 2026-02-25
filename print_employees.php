<?php
session_start();
include 'connection.php';

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ✅ Order alphabetically by Last Name, First Name, Middle Name
$query = "SELECT * FROM employees 
          ORDER BY last_name ASC, first_name ASC, middle_name ASC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn) . "<br>Query: " . $query);
}

$total_employees = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee List - Print</title>
<link rel="icon" href="Hris.png" type="image/x-icon">
  <style>
    @media print {
      body {
        margin: 0;
        padding: 15px;
      }
      .no-print {
        display: none !important;
      }
      table {
        page-break-inside: auto;
      }
      tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      thead {
        display: table-header-group;
      }
    }

    body {
      font-family: "Times New Roman", Times, serif;
      margin: 20px;
      color: #333;
      font-size: 15px;
      font-weight: bold;
    }

    .header {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 3px solid #333;
      padding-bottom: 15px;
    }

    .header h1 {
      margin: 0;
      font-size: 30px;
      color: #2c3e50;
      font-weight: bold;
    }

    .header h2 {
      margin: 5px 0;
      font-size: 18px;
      color: #555;
      font-weight: bold;
    }

    .print-info {
      text-align: right;
      font-size: 14px;
      color: #444;
      margin-bottom: 20px;
      font-weight: bold;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 15px;
      font-weight: bold;
    }

    th, td {
      border: 1px solid #000;
      padding: 10px;
      text-align: left;
    }

    th {
      background-color: #2c3e50;
      color: white;
      text-align: center;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .status-active {
      color: #28a745;
      font-weight: bold;
    }

    .status-inactive, .status-resigned, .status-terminated {
      color: #dc3545;
      font-weight: bold;
    }

    .text-center {
      text-align: center;
    }

    .btn-print {
      background-color: #007bff;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 20px;
    }

    .btn-print:hover {
      background-color: #0056b3;
    }

    .btn-back {
      background-color: #6c757d;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      margin-left: 10px;
      text-decoration: none;
      display: inline-block;
      font-weight: bold;
    }

    .btn-back:hover {
      background-color: #5a6268;
    }

    .summary {
      margin-top: 30px;
      padding: 15px;
      background-color: #f8f9fa;
      border-left: 4px solid #2c3e50;
      font-size: 15px;
      font-weight: bold;
    }

    .summary h3 {
      margin-top: 0;
      color: #2c3e50;
      font-weight: bold;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
      margin-top: 10px;
    }

    .footer {
      margin-top: 40px;
      text-align: center;
      font-size: 13px;
      color: #555;
      border-top: 1px solid #000;
      padding-top: 10px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="btn-print" onclick="window.print()">
      🖨️ Print Employee List
    </button>
    <a href="employees.php" class="btn-back">← Back to Employees</a>
  </div>

  <div class="header">
    <h1>EMPLOYEE LIST</h1>
    <h2>Complete Employee Directory</h2>
  </div>

  <div class="print-info">
    <strong>Date Generated:</strong> <?= date('F d, Y h:i A') ?><br>
    <strong>Total Employees:</strong> <?= $total_employees ?>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 10%;">Emp. No.</th>
        <th style="width: 10%;">Bio ID</th>
        <th style="width: 30%;">Employee Name</th>
        <th style="width: 15%;">Hire Date</th>
        <th style="width: 15%;">Salary Type</th>
        <th style="width: 15%;">Rate</th>
        <th style="width: 15%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      if ($result && $total_employees > 0):
        $active_count = 0;
        $inactive_count = 0;
        $resigned_count = 0;
        $terminated_count = 0;
        $total_rates = 0;
        
        while ($row = mysqli_fetch_assoc($result)): 
          switch ($row['status']) {
            case 'Active': $active_count++; break;
            case 'Inactive': $inactive_count++; break;
            case 'Resigned': $resigned_count++; break;
            case 'Terminated': $terminated_count++; break;
          }

          $hire_display = 'N/A';
          $hire_raw = $row['hire_date'];
          if (!empty($hire_raw) && $hire_raw !== '0000-00-00') {
            $timestamp = strtotime($hire_raw);
            if ($timestamp) {
              $hire_display = date('M d, Y', $timestamp);
            }
          }

          $full_name = trim(
            htmlspecialchars($row['last_name'] ?? '') . ', ' . 
            htmlspecialchars($row['first_name'] ?? '') . ' ' . 
            htmlspecialchars($row['middle_name'] ?? '')
          );

          $rate = floatval($row['employee_rates'] ?? 0);
          $total_rates += $rate;
          $rate_display = number_format($rate, 2);
          $status_class = 'status-' . strtolower($row['status'] ?? 'inactive');
      ?>
      <tr>
        <td class="text-center"><?= htmlspecialchars($row['employee_number'] ?? 'N/A') ?></td>
        <td class="text-center"><?= htmlspecialchars($row['biometric_id'] ?? 'N/A') ?></td>
        <td><?= $full_name ?></td>
        <td class="text-center"><?= $hire_display ?></td>
        <td class="text-center"><?= htmlspecialchars($row['salary_type'] ?? 'Monthly') ?></td>
        <td class="text-center">₱<?= $rate_display ?></td>
        <td class="text-center <?= $status_class ?>"><?= htmlspecialchars($row['status'] ?? 'Inactive') ?></td>
      </tr>
      <?php endwhile; else: ?>
      <tr>
        <td colspan="7" class="text-center">No employees found</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($result && $total_employees > 0): ?>
  <div class="summary">
    <h3>Summary Report</h3>
    <div class="summary-grid">
      <div>
        <p><strong>📊 Total Employees:</strong> <?= $total_employees ?></p>
        <p><strong>✅ Active Employees:</strong> <?= $active_count ?></p>
        <p><strong>⏸️ Inactive Employees:</strong> <?= $inactive_count ?></p>
      </div>
      <div>
        <p><strong>👋 Resigned Employees:</strong> <?= $resigned_count ?></p>
        <p><strong>❌ Terminated Employees:</strong> <?= $terminated_count ?></p>
        <p><strong>💰 Total Monthly Payroll:</strong> ₱<?= number_format($total_rates, 2) ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="footer">
    <p>This is a computer-generated document. No signature is required.</p>
    <p>Generated from Employee Management System | <?= date('Y') ?></p>
  </div>
</body>
</html>
<?php
if ($result) {
    mysqli_free_result($result);
}
mysqli_close($conn);
?>
