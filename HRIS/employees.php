<?php
session_start();
include 'connection.php';

// ADD EMPLOYEE
if (isset($_POST['add_employee'])) {
    $employee_number = $_POST['employee_number'];
    $biometric_id = $_POST['biometric_id'];
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $middle = $_POST['middle_name'];
    $birthdate = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $civil = $_POST['civil_status'];
    $contact = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $hire = $_POST['hire_date'];
    $regular = $_POST['date_regular'];
    $ended = $_POST['date_ended'];
    $total_years_service = $_POST['total_years_service'];
    $status = $_POST['status'];
    $department_id = $_POST['department_id'];
    $shift_id = $_POST['shift_id'];

    $photo_path = '';

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photo_name = basename($_FILES['photo']['name']);
        $target_dir = "uploads/documents/";
        $target_path = $target_dir . time() . '_' . $photo_name;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_path = $target_path;
        }
    }

    $insert = mysqli_query($conn, "INSERT INTO employees (
      employee_number, biometric_id, first_name, last_name, middle_name,
      birth_date, gender, civil_status, phone, email, address,
      hire_date, date_regular, date_ended, total_years_service,
      photo_path, status, department_id, shift_id
  ) VALUES (
      '$employee_number', '$biometric_id', '$first', '$last', '$middle',
      '$birthdate', '$gender', '$civil', '$contact', '$email', '$address',
      '$hire', '$regular', '$ended', '$total_years_service',
      '$photo_path', '$status', '$department_id', '$shift_id'
  )");
  

    $_SESSION['success'] = $insert ? 'Employee added successfully!' : 'Failed to add employee.';
    header("Location: employees.php");
    exit();
}

// DELETE EMPLOYEE
if (isset($_POST['delete_employee'])) {
    $id = $_POST['delete_id'];
    $delete = mysqli_query($conn, "DELETE FROM employees WHERE employee_id=$id");
    $_SESSION['success'] = $delete ? 'Employee deleted successfully!' : 'Failed to delete employee.';
    header("Location: employees.php");
    exit();
}
?>

<?php include 'head.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'header.php'; ?>

<div class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Employee Management</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">HR</li>
              <li class="breadcrumb-item">Employees</li>
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
              <h5 class="mb-0">Employees</h5>
              <small class="text-muted">Manage employee records</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="ti ti-plus me-1"></i>Add Employee
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center w-100" id="employeeTable">
              <thead>
  <tr>
    <th>Photo</th>
    <th>Emp. No.</th>
    <th>Biometric ID</th>
    <th>Employee Name</th>
    <th>Actions</th>
  </tr>
</thead>
<tbody>
<?php
  $result = mysqli_query($conn, "SELECT * FROM employees");
  while ($row = mysqli_fetch_assoc($result)): ?>
  <tr>
    <td>
      <?php if ($row['photo_path']): ?>
        <img src="<?= htmlspecialchars($row['photo_path']) ?>" alt="Photo" style="height:40px; border-radius:4px;">
      <?php else: ?>
        <span class="text-muted">No Photo</span>
      <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($row['employee_number']) ?></td>
    <td><?= htmlspecialchars(string: $row['biometric_id']) ?></td>
    <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['first_name']) ?> <?= htmlspecialchars($row['middle_name']) ?></td>
    <td>
      <div class="d-flex justify-content-center align-items-center">
        <div class="btn-group gap-1">
          <button class="btn btn-sm btn-outline-warning editBtn" data-id="<?= $row['employee_id'] ?>">
            <i class="ti ti-edit"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['employee_id'] ?>">
            <i class="ti ti-trash"></i>
          </button>
        </div>
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

<!-- Add Modal -->
<?php include 'modals/add_modals.php'; ?>

<!-- Hidden Delete Form -->
<form method="POST" id="deleteForm">
  <input type="hidden" name="delete_id" id="delete_id">
  <input type="hidden" name="delete_employee" value="1">
</form>
<script>
$(document).ready(function () {
  // Initialize DataTable
  const table = $('#employeeTable').DataTable({
  
    responsive: true,
    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search employees...",
      zeroRecords: "No matching records found",
      emptyTable: "No employees available",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "Showing 0 to 0 of 0 entries",
      paginate: {
        first: "First", last: "Last", next: "Next", previous: "Previous"
      }
    },
    dom:
      "<'dt-top-controls'<'d-flex align-items-center'l><'dt-search-box position-relative'f>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'dt-bottom-controls'<'d-flex align-items-center'i><'d-flex align-items-center'p>>",
    columnDefs: [
      { targets: 0, className: 'text-center align-middle', width: '80px' },
      { targets: -1, orderable: false, className: 'text-center align-middle' }
    ],
    order: [[1, 'asc']],
    drawCallback: function () {
      bindActions();
    }
  });

  // Add icon inside the search box
  const searchBox = $('.dataTables_filter');
  if (searchBox.length && searchBox.find('.ti-search').length === 0) {
    searchBox.addClass('position-relative');
    searchBox.prepend('<i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>');
    searchBox.find('input').addClass('form-control ps-5');
  }

  // Bind Edit and Delete buttons
  function bindActions() {
    $('.editBtn').off('click').on('click', function () {
      const id = $(this).data('id');
      window.open('edit_employee.php?id=' + id, '_blank');
    });

    $('.deleteBtn').off('click').on('click', function () {
      const id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?',
        text: "This will permanently delete the employee.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          $('#delete_id').val(id);
          $('#deleteForm').submit();
        }
      });
    });
  }

  // SweetAlert Notifications
  <?php if (isset($_SESSION['success'])): ?>
  Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $_SESSION['success']; ?>',
    toast: true,
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false
  });
  <?php unset($_SESSION['success']); endif; ?>
});

<?php if (isset($_SESSION['updated'])): ?>
Swal.fire({
 icon: 'success',
title: '<?= $_SESSION['updated']; ?>',
toast: true,
position: 'top-end',
timer: 3000,
showConfirmButton: false
});
 <?php unset($_SESSION['updated']); endif; ?>

</script>
