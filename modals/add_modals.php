<form method="POST" enctype="multipart/form-data">
  <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body row g-3">
          <!-- Basic Info -->
          <div class="col-md-6">
            <label>Employee No.</label>
            <input type="text" name="employee_number" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>Biometric ID</label>
            <input type="text" name="biometric_id" class="form-control">
          </div>
          <div class="col-md-4">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label>Middle Name</label>
            <input type="text" name="middle_name" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label>Birth Date</label>
            <input type="date" name="birth_date" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Gender</label>
            <select name="gender" class="form-select">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="col-md-4">
            <label>Civil Status</label>
            <select name="civil_status" class="form-select">
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Divorced">Divorced</option>
              <option value="Widowed">Widowed</option>
            </select>
          </div>
          <div class="col-md-6">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <div class="col-md-12">
            <label>Address</label>
            <textarea name="address" class="form-control"></textarea>
          </div>

          <!-- Employment Dates -->
          <div class="col-md-4">
            <label>Date Hired</label>
           <input type="date" name="hire_date" id="hire_date" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Date Regular</label>
            <input type="date" name="date_regular" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Date Ended</label>
            <input type="date" name="date_ended" class="form-control">
          </div>

          <div class="col-md-6">
            <label>Total Years of Service</label>
            <input type="number" step="0.01" name="total_years_service" class="form-control">
          </div>

          <div class="col-md-6">
            <label>Status</label>
            <select name="status" class="form-select">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>

          <!-- Department -->
          <div class="col-md-6">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-select" required>
              <option value="">Select Department</option>
              <?php
              $departments = mysqli_query($conn, "SELECT department_id, name FROM departments ORDER BY name ASC");
              while ($dept = mysqli_fetch_assoc($departments)): ?>
                <option value="<?= $dept['department_id']; ?>">
                  <?= htmlspecialchars($dept['name']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Shift -->
          <div class="col-md-6">
            <label class="form-label">Shift</label>
            <select name="shift_id" class="form-select" required>
              <option value="">Select Shift</option>
              <?php
              $shifts = mysqli_query($conn, "SELECT shift_id, shift_name FROM shifts ORDER BY shift_name ASC");
              while ($shift = mysqli_fetch_assoc($shifts)): ?>
                <option value="<?= $shift['shift_id']; ?>">
                  <?= htmlspecialchars($shift['shift_name']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Designation -->
          <div class="col-md-6">
            <label class="form-label">Designation</label>
            <select name="designation_id" class="form-select" required>
              <option value="">Select Designation</option>
              <?php
              $designations = mysqli_query($conn, "SELECT designation_id, title, level FROM designations ORDER BY title ASC");
              while ($des = mysqli_fetch_assoc($designations)): ?>
                <option value="<?= $des['designation_id']; ?>">
                  <?= htmlspecialchars($des['title'] . ($des['level'] ? ' - ' . $des['level'] : '')); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Employment Type -->
          <div class="col-md-6">
            <label class="form-label">Employment Type</label>
            <select name="employmenttype_id" class="form-select" required>
              <option value="">Select Employment Type</option>
              <?php
              $employment_types = mysqli_query($conn, "SELECT type_id, name FROM employmenttypes ORDER BY name ASC");
              while ($emp = mysqli_fetch_assoc($employment_types)): ?>
                <option value="<?= $emp['type_id']; ?>">
                  <?= htmlspecialchars($emp['name']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Payroll Information Section -->
          <div class="col-12">
            <hr class="my-3">
            <h6 class="text-primary mb-3">
              <i class="ti ti-currency-dollar me-1"></i>Payroll Information
            </h6>
          </div>

        <!-- Salary Type -->
        <div class="col-md-4">
          <label class="form-label">Salary Type <span class="text-danger">*</span></label>
          <select name="salary_type" class="form-select" required>
            <option value="">Select Type</option>
            <option value="Monthly" selected>Monthly</option>
            <option value="Semi-Monthly">Semi-Monthly</option>
            <option value="Daily">Daily</option>
            <option value="Hourly">Hourly</option>
          </select>
        </div>


          <!-- Employee Rate -->
          <div class="col-md-4">
            <label class="form-label">Employee Rate <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">₱</span>
              <input type="number" name="employee_rates" class="form-control" step="0.01" min="0" required>
            </div>
            <small class="text-muted" id="rateHelpText">Enter the rate based on salary type</small>
          </div>

          <!-- Tax Status -->
          <div class="col-md-4">
            <label class="form-label">Tax Status <span class="text-danger">*</span></label>
            <select name="tax_status" class="form-select" required>
              <option value="">Select Status</option>
              <option value="Single" selected>Single</option>
              <option value="Married">Married</option>
              <option value="Head of Family">Head of Family</option>
            </select>
          </div>

          <!-- Payroll Group -->
          <div class="col-md-6">
            <label class="form-label">Payroll Group</label>
            <select name="payroll_group_id" class="form-select">
              <option value="">Select Group (Optional)</option>
              <?php
              // Check if payroll_groups table exists and get data
              $payroll_check = mysqli_query($conn, "SHOW TABLES LIKE 'payroll_groups'");
              if ($payroll_check && mysqli_num_rows($payroll_check) > 0) {
                  $payroll_result = mysqli_query($conn, "SELECT * FROM payroll_groups WHERE is_active = 1 ORDER BY group_name");
                  if ($payroll_result && mysqli_num_rows($payroll_result) > 0) {
                      while ($payroll = mysqli_fetch_assoc($payroll_result)) {
                          echo "<option value='" . $payroll['id'] . "'>" . htmlspecialchars($payroll['group_name']) . "</option>";
                      }
                  }
              } else {
                  // If table doesn't exist, show default options
                  echo "<option value='1'>Regular Employees</option>";
                  echo "<option value='2'>Contractual</option>";
                  echo "<option value='3'>Part-time</option>";
              }
              ?>
            </select>
            <small class="text-muted">Optional grouping for payroll processing</small>
          </div>

          <!-- Tax Deduction -->
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_tax_deducted" id="is_tax_deducted" checked>
              <label class="form-check-label" for="is_tax_deducted">
                <strong>Subject to Tax Deduction</strong>
              </label>
              <small class="d-block text-muted">Check if employee is subject to income tax deductions</small>
            </div>
          </div>

          <!-- Photo Upload -->
          <div class="col-md-12">
            <hr class="my-2">
            <label>Photo</label>
            <input type="file" name="photo" class="form-control" accept="image/*">
            <small class="text-muted">Optional. Maximum file size: 2MB</small>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="submit" name="add_employee" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
// Add form validation and salary type change handler
document.addEventListener('DOMContentLoaded', function() {
    const salaryTypeSelect = document.querySelector('select[name="salary_type"]');
    const employeeRateInput = document.querySelector('input[name="employee_rates"]');
    const rateHelpText = document.getElementById('rateHelpText');
    
    if (salaryTypeSelect && employeeRateInput) {
        salaryTypeSelect.addEventListener('change', function() {
            const salaryType = this.value;
            
            switch(salaryType) {
                case 'Monthly':
                    if (rateHelpText) rateHelpText.textContent = 'Enter monthly salary amount (e.g. 25000.00)';
                    employeeRateInput.placeholder = 'Monthly salary';
                    break;
                case 'Daily':
                    if (rateHelpText) rateHelpText.textContent = 'Enter daily wage rate (e.g. 500.00)';
                    employeeRateInput.placeholder = 'Daily rate';
                    break;
                case 'Hourly':
                    if (rateHelpText) rateHelpText.textContent = 'Enter hourly wage rate (e.g. 62.50)';
                    employeeRateInput.placeholder = 'Hourly rate';
                    break;
                default:
                    if (rateHelpText) rateHelpText.textContent = 'Enter the rate based on salary type';
                    employeeRateInput.placeholder = '';
            }
        });
        
        // Trigger change event on page load
        salaryTypeSelect.dispatchEvent(new Event('change'));
    }
    
    // Form validation
    const addEmployeeForm = document.querySelector('form[method="POST"]');
    if (addEmployeeForm) {
        addEmployeeForm.addEventListener('submit', function(e) {
            const requiredFields = addEmployeeForm.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate employee rate is greater than 0
            const rateField = document.querySelector('input[name="employee_rates"]');
            if (rateField && parseFloat(rateField.value) <= 0) {
                rateField.classList.add('is-invalid');
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = rateField;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                
                // Show SweetAlert if available, otherwise use regular alert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fill in all required fields correctly.',
                    });
                } else {
                    alert('Please fill in all required fields correctly.');
                }
            }
        });
        
        // Remove invalid class when user starts typing
        const inputs = addEmployeeForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>