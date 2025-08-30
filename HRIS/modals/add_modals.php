<form method="POST" enctype="multipart/form-data">
  <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
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
            <input type="text" name="civil_status" class="form-control">
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
          <div class="col-md-4">
            <label>Date Hired</label>
            <input type="date" name="date_hired" class="form-control">
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
          <div class="row">
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
          </div>


          <div class="col-md-12">
            <label>Photo</label>
            <input type="file" name="photo" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="add_employee" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</form>