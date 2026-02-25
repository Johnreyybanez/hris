<?php
session_start();
include 'connection.php';

// ADD PAYROLL GROUP
if (isset($_POST['add_payroll_group'])) {
    $group_name = $_POST['group_name'];
    $description = $_POST['description'];
    $salary_type = $_POST['salary_type'];
    $payroll_frequency = $_POST['payroll_frequency'];
    $cutoff_start_date = $_POST['cutoff_start_date'];
    $cutoff_end_date = $_POST['cutoff_end_date'];
    $release_day = $_POST['release_day'];
    $include_late = isset($_POST['include_late']) ? 1 : 0;
    $include_undertime = isset($_POST['include_undertime']) ? 1 : 0;
    $include_absent = isset($_POST['include_absent']) ? 1 : 0;
    $overtime_multiplier = $_POST['overtime_multiplier'] ?: 1.25;
    $night_diff_multiplier = $_POST['night_diff_multiplier'] ?: 1.10;
    $restday_multiplier = $_POST['restday_multiplier'] ?: 1.30;
    $holiday_regular_multiplier = $_POST['holiday_regular_multiplier'] ?: 2.00;
    $holiday_special_multiplier = $_POST['holiday_special_multiplier'] ?: 1.30;
    $notes = $_POST['notes'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $insert = mysqli_query($conn, "INSERT INTO payroll_groups (
        group_name, description, salary_type, payroll_frequency, cutoff_start_date,
        cutoff_end_date, release_day, include_late, include_undertime, include_absent,
        overtime_multiplier, night_diff_multiplier, restday_multiplier,
        holiday_regular_multiplier, holiday_special_multiplier, notes, is_active
    ) VALUES (
        '$group_name', '$description', '$salary_type', '$payroll_frequency', '$cutoff_start_date',
        '$cutoff_end_date', '$release_day', $include_late, $include_undertime, $include_absent,
        $overtime_multiplier, $night_diff_multiplier, $restday_multiplier,
        $holiday_regular_multiplier, $holiday_special_multiplier, '$notes', $is_active
    )");

    $_SESSION['success'] = $insert ? 'Payroll group added successfully!' : 'Failed to add payroll group.';
    header("Location: payroll_groups.php");
    exit();
}

// UPDATE PAYROLL GROUP
if (isset($_POST['update_payroll_group'])) {
    $id = $_POST['payroll_group_id'];
    $group_name = $_POST['group_name'];
    $description = $_POST['description'];
    $salary_type = $_POST['salary_type'];
    $payroll_frequency = $_POST['payroll_frequency'];
    $cutoff_start_date = $_POST['cutoff_start_date'];
    $cutoff_end_date = $_POST['cutoff_end_date'];
    $release_day = $_POST['release_day'];
    $include_late = isset($_POST['include_late']) ? 1 : 0;
    $include_undertime = isset($_POST['include_undertime']) ? 1 : 0;
    $include_absent = isset($_POST['include_absent']) ? 1 : 0;
    $overtime_multiplier = $_POST['overtime_multiplier'] ?: 1.25;
    $night_diff_multiplier = $_POST['night_diff_multiplier'] ?: 1.10;
    $restday_multiplier = $_POST['restday_multiplier'] ?: 1.30;
    $holiday_regular_multiplier = $_POST['holiday_regular_multiplier'] ?: 2.00;
    $holiday_special_multiplier = $_POST['holiday_special_multiplier'] ?: 1.30;
    $notes = $_POST['notes'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $update = mysqli_query($conn, "UPDATE payroll_groups SET
        group_name='$group_name',
        description='$description',
        salary_type='$salary_type',
        payroll_frequency='$payroll_frequency',
        cutoff_start_date='$cutoff_start_date',
        cutoff_end_date='$cutoff_end_date',
        release_day='$release_day',
        include_late=$include_late,
        include_undertime=$include_undertime,
        include_absent=$include_absent,
        overtime_multiplier=$overtime_multiplier,
        night_diff_multiplier=$night_diff_multiplier,
        restday_multiplier=$restday_multiplier,
        holiday_regular_multiplier=$holiday_regular_multiplier,
        holiday_special_multiplier=$holiday_special_multiplier,
        notes='$notes',
        is_active=$is_active
        WHERE id=$id
    ");

    $_SESSION['success'] = $update ? 'Payroll group updated successfully!' : 'Failed to update payroll group.';
    header("Location: payroll_groups.php");
    exit();
}

// DELETE PAYROLL GROUP
if (isset($_POST['delete_payroll_group'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM payroll_groups WHERE id=$id");
    $_SESSION['success'] = $delete ? 'Payroll group deleted successfully!' : 'Failed to delete payroll group.';
    header("Location: payroll_groups.php");
    exit();
}
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<!-- Main Content -->
<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Payroll Group Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Setup</li>
              <li class="breadcrumb-item active" aria-current="page">Payroll Groups</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Payroll Groups</h5>
              <small class="text-muted">Manage employee payroll groupings and configurations</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Payroll Group
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-center w-100" id="payrollTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Group Name</th>
                    <th>Salary Type</th>
                    <th>Frequency</th>
                    <th>Cutoff Period</th>
                    <th>Release Day</th>
                    <th>OT Multiplier</th>
                    <th>Night Diff</th>
                    <th>Active</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $result = mysqli_query($conn, "SELECT * FROM payroll_groups ORDER BY group_name");
                  while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><span class="badge bg-dark"><?= $row['id']; ?></span></td>
                    <td><?= htmlspecialchars($row['group_name']); ?></td>
                    <td><span class="badge bg-info"><?= $row['salary_type']; ?></span></td>
                    <td><?= $row['payroll_frequency']; ?></td>
                    <td><?= date('M d', strtotime($row['cutoff_start_date'])) . ' - ' . date('M d', strtotime($row['cutoff_end_date'])); ?></td>
                    <td><?= $row['release_day']; ?></td>
                    <td><?= $row['overtime_multiplier']; ?>x</td>
                    <td><?= $row['night_diff_multiplier']; ?>x</td>
                    <td><?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></td>
                    <td class="text-center">
                      <div class="btn-group gap-1" role="group">
                        <button class="btn btn-sm btn-outline-warning editBtn"
                          data-id="<?= $row['id']; ?>"
                          data-group_name="<?= htmlspecialchars($row['group_name']); ?>"
                          data-description="<?= htmlspecialchars($row['description']); ?>"
                          data-salary_type="<?= $row['salary_type']; ?>"
                          data-payroll_frequency="<?= $row['payroll_frequency']; ?>"
                          data-cutoff_start_date="<?= $row['cutoff_start_date']; ?>"
                          data-cutoff_end_date="<?= $row['cutoff_end_date']; ?>"
                          data-release_day="<?= htmlspecialchars($row['release_day']); ?>"
                          data-include_late="<?= $row['include_late']; ?>"
                          data-include_undertime="<?= $row['include_undertime']; ?>"
                          data-include_absent="<?= $row['include_absent']; ?>"
                          data-overtime_multiplier="<?= $row['overtime_multiplier']; ?>"
                          data-night_diff_multiplier="<?= $row['night_diff_multiplier']; ?>"
                          data-restday_multiplier="<?= $row['restday_multiplier']; ?>"
                          data-holiday_regular_multiplier="<?= $row['holiday_regular_multiplier']; ?>"
                          data-holiday_special_multiplier="<?= $row['holiday_special_multiplier']; ?>"
                          data-notes="<?= htmlspecialchars($row['notes']); ?>"
                          data-active="<?= $row['is_active']; ?>"
                          title="Edit"><i class="ti ti-edit"></i></button>
                        <button class="btn btn-sm btn-outline-info viewBtn"
                          data-id="<?= $row['id']; ?>"
                          data-group_name="<?= htmlspecialchars($row['group_name']); ?>"
                          data-description="<?= htmlspecialchars($row['description']); ?>"
                          data-salary_type="<?= $row['salary_type']; ?>"
                          data-payroll_frequency="<?= $row['payroll_frequency']; ?>"
                          data-cutoff_start_date="<?= $row['cutoff_start_date']; ?>"
                          data-cutoff_end_date="<?= $row['cutoff_end_date']; ?>"
                          data-release_day="<?= htmlspecialchars($row['release_day']); ?>"
                          data-include_late="<?= $row['include_late']; ?>"
                          data-include_undertime="<?= $row['include_undertime']; ?>"
                          data-include_absent="<?= $row['include_absent']; ?>"
                          data-overtime_multiplier="<?= $row['overtime_multiplier']; ?>"
                          data-night_diff_multiplier="<?= $row['night_diff_multiplier']; ?>"
                          data-restday_multiplier="<?= $row['restday_multiplier']; ?>"
                          data-holiday_regular_multiplier="<?= $row['holiday_regular_multiplier']; ?>"
                          data-holiday_special_multiplier="<?= $row['holiday_special_multiplier']; ?>"
                          data-notes="<?= htmlspecialchars($row['notes']); ?>"
                          data-active="<?= $row['is_active']; ?>"
                          title="View"><i class="ti ti-eye"></i></button>
                        <button class="btn btn-sm btn-outline-danger deleteBtn"
                          data-id="<?= $row['id']; ?>"
                          title="Delete"><i class="ti ti-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-eye me-2"></i>View Payroll Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Group Name</label>
              <input type="text" id="view_group_name" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea id="view_description" class="form-control" readonly></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Salary Type</label>
              <input type="text" id="view_salary_type" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Payroll Frequency</label>
              <input type="text" id="view_payroll_frequency" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff Start Date</label>
              <input type="text" id="view_cutoff_start_date" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff End Date</label>
              <input type="text" id="view_cutoff_end_date" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Release Day</label>
              <input type="text" id="view_release_day" class="form-control" readonly>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Include Late</label>
              <input type="text" id="view_include_late" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Include Undertime</label>
              <input type="text" id="view_include_undertime" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Include Absent</label>
              <input type="text" id="view_include_absent" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Overtime Multiplier</label>
              <input type="text" id="view_overtime_multiplier" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Night Differential Multiplier</label>
              <input type="text" id="view_night_diff_multiplier" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Rest Day Multiplier</label>
              <input type="text" id="view_restday_multiplier" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Regular Multiplier</label>
              <input type="text" id="view_holiday_regular_multiplier" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Special Multiplier</label>
              <input type="text" id="view_holiday_special_multiplier" class="form-control" readonly>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea id="view_notes" class="form-control" readonly></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Active</label>
          <input type="text" id="view_is_active" class="form-control" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Payroll Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Group Name</label>
              <input type="text" name="group_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Salary Type</label>
              <select name="salary_type" class="form-select" required>
                <option value="">Select Salary Type</option>
                <option value="Monthly">Monthly</option>
                <option value="Semi-monthly">Semi-monthly</option>
                <option value="Daily">Daily</option>
                <option value="Hourly">Hourly</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Payroll Frequency</label>
              <select name="payroll_frequency" class="form-select" required>
                <option value="">Select Frequency</option>
                <option value="Weekly">Weekly</option>
                <option value="Biweekly">Biweekly</option>
                <option value="Semimonthly">Semimonthly</option>
                <option value="Monthly">Monthly</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff Start Date</label>
              <input type="date" name="cutoff_start_date" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff End Date</label>
              <input type="date" name="cutoff_end_date" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Release Day</label>
              <input type="text" name="release_day" class="form-control" placeholder="e.g., 15th of every month" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Overtime Multiplier</label>
              <input type="number" step="0.01" name="overtime_multiplier" class="form-control" value="1.25">
            </div>
            <div class="mb-3">
              <label class="form-label">Night Differential Multiplier</label>
              <input type="number" step="0.01" name="night_diff_multiplier" class="form-control" value="1.10">
            </div>
            <div class="mb-3">
              <label class="form-label">Rest Day Multiplier</label>
              <input type="number" step="0.01" name="restday_multiplier" class="form-control" value="1.30">
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Regular Multiplier</label>
              <input type="number" step="0.01" name="holiday_regular_multiplier" class="form-control" value="2.00">
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Special Multiplier</label>
              <input type="number" step="0.01" name="holiday_special_multiplier" class="form-control" value="1.30">
            </div>
            <div class="mb-3">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_late" id="add_include_late" checked>
                <label class="form-check-label" for="add_include_late">Include Late Deductions</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_undertime" id="add_include_undertime" checked>
                <label class="form-check-label" for="add_include_undertime">Include Undertime Deductions</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_absent" id="add_include_absent" checked>
                <label class="form-check-label" for="add_include_absent">Include Absent Deductions</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" checked>
                <label class="form-check-label" for="add_is_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" placeholder="Additional notes or configurations"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_payroll_group" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Payroll Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="payroll_group_id" id="edit_id">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Group Name</label>
              <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" id="edit_description" class="form-control"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Salary Type</label>
              <select name="salary_type" id="edit_salary_type" class="form-select" required>
                <option value="">Select Salary Type</option>
                <option value="Monthly">Monthly</option>
                <option value="Semi-monthly">Semi-monthly</option>
                <option value="Daily">Daily</option>
                <option value="Hourly">Hourly</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Payroll Frequency</label>
              <select name="payroll_frequency" id="edit_payroll_frequency" class="form-select" required>
                <option value="">Select Frequency</option>
                <option value="Weekly">Weekly</option>
                <option value="Biweekly">Biweekly</option>
                <option value="Semimonthly">Semimonthly</option>
                <option value="Monthly">Monthly</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff Start Date</label>
              <input type="date" name="cutoff_start_date" id="edit_cutoff_start_date" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Cutoff End Date</label>
              <input type="date" name="cutoff_end_date" id="edit_cutoff_end_date" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Release Day</label>
              <input type="text" name="release_day" id="edit_release_day" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Overtime Multiplier</label>
              <input type="number" step="0.01" name="overtime_multiplier" id="edit_overtime_multiplier" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Night Differential Multiplier</label>
              <input type="number" step="0.01" name="night_diff_multiplier" id="edit_night_diff_multiplier" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Rest Day Multiplier</label>
              <input type="number" step="0.01" name="restday_multiplier" id="edit_restday_multiplier" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Regular Multiplier</label>
              <input type="number" step="0.01" name="holiday_regular_multiplier" id="edit_holiday_regular_multiplier" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Holiday Special Multiplier</label>
              <input type="number" step="0.01" name="holiday_special_multiplier" id="edit_holiday_special_multiplier" class="form-control">
            </div>
            <div class="mb-3">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_late" id="edit_include_late">
                <label class="form-check-label" for="edit_include_late">Include Late Deductions</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_undertime" id="edit_include_undertime">
                <label class="form-check-label" for="edit_include_undertime">Include Undertime Deductions</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="include_absent" id="edit_include_absent">
                <label class="form-check-label" for="edit_include_absent">Include Absent Deductions</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                <label class="form-check-label" for="edit_is_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" id="edit_notes" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_payroll_group" class="btn btn-warning"><i class="ti ti-device-floppy me-1"></i>Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_payroll_group" value="1">
</form>

<script>
$(document).ready(function () {
  const table = $('#payrollTable').DataTable({
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      lengthMenu: 'Show _MENU_ entries',
      search: '',
      searchPlaceholder: 'Search payroll groups...',
      info: 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: 'Showing 0 to 0 of 0 entries',
      infoFiltered: '(filtered from _MAX_ total entries)',
      zeroRecords: 'No matching payroll groups found',
      emptyTable: 'No payroll groups available',
      paginate: {
        first: 'First',
        last: 'Last', 
        next: 'Next',
        previous: 'Previous'
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, width: '80px', className: 'text-center' },
      { targets: -1, orderable: false, className: 'text-center' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActionButtons();
    }
  });

  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5 dt-search-input');
  }
  
  function bindActionButtons() {
    $('.editBtn').off().on('click', function () {
      $('#edit_id').val($(this).data('id'));
      $('#edit_group_name').val($(this).data('group_name'));
      $('#edit_description').val($(this).data('description'));
      $('#edit_salary_type').val($(this).data('salary_type'));
      $('#edit_payroll_frequency').val($(this).data('payroll_frequency'));
      $('#edit_cutoff_start_date').val($(this).data('cutoff_start_date'));
      $('#edit_cutoff_end_date').val($(this).data('cutoff_end_date'));
      $('#edit_release_day').val($(this).data('release_day'));
      $('#edit_overtime_multiplier').val($(this).data('overtime_multiplier'));
      $('#edit_night_diff_multiplier').val($(this).data('night_diff_multiplier'));
      $('#edit_restday_multiplier').val($(this).data('restday_multiplier'));
      $('#edit_holiday_regular_multiplier').val($(this).data('holiday_regular_multiplier'));
      $('#edit_holiday_special_multiplier').val($(this).data('holiday_special_multiplier'));
      $('#edit_notes').val($(this).data('notes'));
      $('#edit_include_late').prop('checked', $(this).data('include_late') == 1);
      $('#edit_include_undertime').prop('checked', $(this).data('include_undertime') == 1);
      $('#edit_include_absent').prop('checked', $(this).data('include_absent') == 1);
      $('#edit_is_active').prop('checked', $(this).data('active') == 1);
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    $('.viewBtn').off().on('click', function () {
      $('#view_group_name').val($(this).data('group_name'));
      $('#view_description').val($(this).data('description'));
      $('#view_salary_type').val($(this).data('salary_type'));
      $('#view_payroll_frequency').val($(this).data('payroll_frequency'));
      $('#view_cutoff_start_date').val($(this).data('cutoff_start_date'));
      $('#view_cutoff_end_date').val($(this).data('cutoff_end_date'));
      $('#view_release_day').val($(this).data('release_day'));
      $('#view_overtime_multiplier').val($(this).data('overtime_multiplier') + 'x');
      $('#view_night_diff_multiplier').val($(this).data('night_diff_multiplier') + 'x');
      $('#view_restday_multiplier').val($(this).data('restday_multiplier') + 'x');
      $('#view_holiday_regular_multiplier').val($(this).data('holiday_regular_multiplier') + 'x');
      $('#view_holiday_special_multiplier').val($(this).data('holiday_special_multiplier') + 'x');
      $('#view_notes').val($(this).data('notes'));
      $('#view_include_late').val($(this).data('include_late') == 1 ? 'Yes' : 'No');
      $('#view_include_undertime').val($(this).data('include_undertime') == 1 ? 'Yes' : 'No');
      $('#view_include_absent').val($(this).data('include_absent') == 1 ? 'Yes' : 'No');
      $('#view_is_active').val($(this).data('active') == 1 ? 'Yes' : 'No');
      new bootstrap.Modal(document.getElementById('viewModal')).show();
    });

    $('.deleteBtn').off().on('click', function () {
      const id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          $('#delete_id').val(id);
          $('#deleteForm').submit();
        }
      });
    });
  }

  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success']; ?>',
    timer: 3000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end'
  });
  <?php unset($_SESSION['success']); endif; ?>
});
</script>
</body>
</html>