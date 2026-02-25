<?php
session_start();
include 'connection.php';
include 'head.php';
include 'sidebar.php';
include 'header.php';

// Fetch all departments for the dropdown
$departments_query = "SELECT department_id, name FROM departments ORDER BY name";
$departments = mysqli_query($conn, $departments_query);

if (!$departments) {
    die("Error fetching departments: " . mysqli_error($conn));
}

// Fetch all employees for the dropdown
$employees_query = "SELECT e.employee_id, e.department_id, CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS name, d.name AS department_name FROM employees e JOIN departments d ON e.department_id = d.department_id ORDER BY e.first_name, e.last_name";
$employees = mysqli_query($conn, $employees_query);

if (!$employees) {
    die("Error fetching employees: " . mysqli_error($conn));
}
?>
<style>
    .filter-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: white;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }
    .select2-container--default .select2-selection--multiple {
        min-height: 100px;
        border: 1px solid #0f0f0fff;
    }

    /* Make selected items' text black */
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        color: white !important;
         background-color: green !important;
        
    }

    /* Make search field text inside Select2 black */
    .select2-container--default .select2-selection--multiple .select2-search__field {
        color: black !important;
    }
    
    .select2-container--default .select2-selection--single {
        border: 1px solid #0f0f0fff;
    }
    
    .employee-controls {
        position: relative;
    }
    
    .checkbox-controls {
        margin-top: 5px;
        text-align: right;
         color: black !important;
    }
    
    .checkbox-controls .form-check {
        display: inline-block;
        margin-left: 10px;
    }
</style>


<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Daily Time Record Generator</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Report</li>
              <li class="breadcrumb-item">Daily Time Record Generator</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card filter-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-calendar-alt me-3"></i>
                Daily Time Record Generator
            </h5>
        </div>
        <div class="card-body">
            <form action="generate_dtr.php" method="POST" class="row g-3" target="_blank">
                <div class="col-md-3">
                    <label for="department_filter" class="form-label">
                        <i class="fas fa-building me-1"></i> Filter by Department
                    </label>
                    <select id="department_filter" class="form-select select2-single">
                        <option value="">All Departments</option>
                        <?php 
                        mysqli_data_seek($departments, 0); // Reset pointer
                        while ($dept = mysqli_fetch_assoc($departments)): 
                        ?>
                            <option value="<?= $dept['department_id'] ?>">
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted">Filter employees by department first</small>
                </div>
                <div class="col-md-3">
                    <label for="employee_id" class="form-label">
                        <i class="fas fa-user me-1"></i> Select Employee(s)
                    </label>
                    <div class="employee-controls">
                        <select name="employee_id[]" id="employee_id" class="form-select select2" multiple="multiple" required>
                            <?php 
                            mysqli_data_seek($employees, 0); // Reset pointer
                            while ($emp = mysqli_fetch_assoc($employees)): 
                            ?>
                                <option value="<?= $emp['employee_id'] ?>" data-department="<?= $emp['department_id'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <div class="checkbox-controls">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_all">
                                <label class="form-check-label" for="select_all">
                                    Select All
                                </label>
                            </div>
                          <!--  <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_department">
                                <label class="form-check-label" for="select_department">
                                    Select Dept
                                </label>
                            </div>-->
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">
                        <i class="fas fa-calendar-alt me-1"></i> Start Date
                    </label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">
                        <i class="fas fa-calendar-alt me-1"></i> End Date
                    </label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required>
                </div>
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-alt me-2"></i> Generate DTR Forms
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">
                        <i class="fas fa-times me-2"></i> Clear Form
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Information Card -->
    <div class="card info-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-info-circle me-2"></i> Instructions
            </h5>
        </div>
        <div class="card-body">
            <p><strong>How to generate Daily Time Records:</strong></p>
            <ul>
                <li>Optionally filter by department to narrow down employee list</li>
                <li>Select one or more employees from the dropdown list</li>
                <li>Or click "Select All" to generate for everyone</li>
                <li>Or click "Select Dept" to select all employees in the filtered department</li>
                <li>Choose the start and end dates for the period you want</li>
                <li>Click "Generate DTR Forms" – it will open in a new tab</li>
                <li>Each employee's DTR will be generated on separate pages</li>
                <li>You can print all at once or save as PDF from that tab</li>
            </ul>
            <p><strong>Note:</strong> The forms will include all attendance records for the selected employees within the specified date range.</p>
        </div>
    </div>
  </div>
</div>

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: "Select Employee(s)",
        allowClear: true
    });
    
    $('.select2-single').select2({
        placeholder: "All Departments",
        allowClear: true
    });
    
    // Set default date range (current month)
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    if (!startDate.value) {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        startDate.value = firstDay.toISOString().split('T')[0];
        endDate.value = lastDay.toISOString().split('T')[0];
    }
    
    // Department filter functionality
    $('#department_filter').change(function() {
        const selectedDepartment = $(this).val();
        const employeeSelect = $('#employee_id');
        
        // Clear current selection first
        employeeSelect.val(null).trigger('change');
        
        if (selectedDepartment) {
            // Auto-select all employees from the selected department
            const selectedEmployees = [];
            employeeSelect.find('option').each(function() {
                const optionDepartment = $(this).data('department');
                
                if (optionDepartment == selectedDepartment) {
                    selectedEmployees.push($(this).val());
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Set the selected employees
            employeeSelect.val(selectedEmployees).trigger('change');
        } else {
            // Show all options when no department is selected
            employeeSelect.find('option').show();
        }
        
        // Refresh Select2 to update the display
        employeeSelect.select2({
            placeholder: "Select Employee(s)",
            allowClear: true
        });
        
        // Update the "Select All in Department" checkbox visibility
        updateSelectDepartmentCheckbox();
    });
    
    // Select all employees checkbox
    $('#select_all').change(function() {
        if($(this).is(':checked')) {
            // Uncheck department-specific select
            $('#select_department').prop('checked', false);
            
            // Select all visible options
            $('#employee_id option:visible').prop('selected', true);
            $('#employee_id').trigger('change');
        } else {
            $('#employee_id option').prop('selected', false);
            $('#employee_id').trigger('change');
        }
    });
    
    // Select all employees in department checkbox
    $('#select_department').change(function() {
        if($(this).is(':checked')) {
            // Uncheck select all
            $('#select_all').prop('checked', false);
            
            const selectedDepartment = $('#department_filter').val();
            
            if (selectedDepartment) {
                // Select only employees from the selected department
                $('#employee_id option').each(function() {
                    if ($(this).data('department') == selectedDepartment) {
                        $(this).prop('selected', true);
                    } else {
                        $(this).prop('selected', false);
                    }
                });
                $('#employee_id').trigger('change');
            }
        } else {
            $('#employee_id option').prop('selected', false);
            $('#employee_id').trigger('change');
        }
    });
    
    // Function to update the visibility of "Select All in Department" checkbox
    function updateSelectDepartmentCheckbox() {
        const selectedDepartment = $('#department_filter').val();
        const selectDepartmentCheckbox = $('#select_department').closest('.form-check');
        
        if (selectedDepartment) {
            selectDepartmentCheckbox.show();
        } else {
            selectDepartmentCheckbox.hide();
            $('#select_department').prop('checked', false);
        }
    }
    
    // Initialize the checkbox visibility
    updateSelectDepartmentCheckbox();
    
    // Clear selections when employee dropdown changes manually
    $('#employee_id').change(function() {
        // If manual selection, uncheck both checkboxes
        setTimeout(function() {
            if (!$('#select_all').is(':focus') && !$('#select_department').is(':focus')) {
                $('#select_all').prop('checked', false);
                $('#select_department').prop('checked', false);
            }
        }, 100);
    });
});
</script>