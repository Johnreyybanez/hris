<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'];

// Handle Add/Edit/Delete Official Business
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_ob'])) {
        $ob_id = $_POST['ob_id'] ?? null;
        $date = $_POST['date'];
        $time_from = $_POST['time_from'];
        $time_to = $_POST['time_to'];
        $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);

        if ($ob_id) {
            $stmt = $conn->prepare("UPDATE EmployeeOfficialBusiness SET date=?, time_from=?, time_to=?, purpose=?, location=? WHERE ob_id=? AND status='Pending'");
            $stmt->bind_param("sssssi", $date, $time_from, $time_to, $purpose, $location, $ob_id);
            $stmt->execute();
            $_SESSION['ob_success'] = 'Official Business updated!';
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO EmployeeOfficialBusiness (employee_id, date, time_from, time_to, purpose, location, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->bind_param("isssss", $employee_id, $date, $time_from, $time_to, $purpose, $location);
            $stmt->execute();
            $_SESSION['ob_success'] = 'Official Business request submitted!';
            $stmt->close();
        }
    }

    if (isset($_POST['delete_ob'])) {
        $ob_id = $_POST['delete_ob'];
        $stmt = $conn->prepare("DELETE FROM EmployeeOfficialBusiness WHERE ob_id = ? AND status='Pending'");
        $stmt->bind_param("i", $ob_id);
        $stmt->execute();
        $_SESSION['ob_success'] = $stmt->affected_rows > 0 ? 'Request deleted successfully.' : 'Delete failed — already approved/rejected.';
        $stmt->close();
    }

    header("Location: user_ob_request.php");
    exit;
}

$ob_q = mysqli_query($conn, "SELECT * FROM EmployeeOfficialBusiness WHERE employee_id = '$employee_id' ORDER BY requested_at DESC");

include 'user_head.php';
include 'user/sidebar.php';
include 'user_header.php';
?>

<div class="pc-container">
  <div class="pc-content">
    <div class="card card-body shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Official Business Requests</h5>
        <button class="btn btn-dark btn-sm" onclick="clearOBForm()" data-bs-toggle="modal" data-bs-target="#obModal">
          <i class="bi bi-plus-circle me-1"></i> Add Official Business
        </button>
      </div>

      <div class="table-responsive mt-3">
        <table id="obTable" class="table table-bordered table-striped align-middle">
          <thead class="table-dark text-center">
            <tr>
              <th>Date</th>
              <th>Time From</th>
              <th>Time To</th>
              <th>Purpose</th>
              <th>Location</th>
              <th>Status</th>
              <th>Requested At</th>
              <th>Approved At</th>
              <th>Remarks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($ob_q)) : ?>
              <tr>
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td class="text-center"><?= date('h:i A', strtotime($row['time_from'])) ?></td>
                <td class="text-center"><?= date('h:i A', strtotime($row['time_to'])) ?></td>
                <td><?= htmlspecialchars($row['purpose']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td class="text-center">
                  <?php
                    $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'][$row['status']] ?? 'secondary';
                    echo "<span class='badge bg-$badge'>{$row['status']}</span>";
                  ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($row['requested_at'])) ?></td>
                <td><?= $row['approved_at'] ? date('M d, Y h:i A', strtotime($row['approved_at'])) : '—' ?></td>
                <td><?= htmlspecialchars($row['approval_remarks'] ?? '—') ?></td>
                <td class="text-center">
                  <?php if ($row['status'] === 'Pending') : ?>
                    <button class="btn btn-sm btn-primary me-1" onclick='editOB(<?= json_encode($row) ?>)'>
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="delete_ob" value="<?= $row['ob_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this request?')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php else : ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="obModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="ob_id">
      <div class="modal-header">
        <h5 class="modal-title">Official Business Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Time From</label>
          <input type="time" name="time_from" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Time To</label>
          <input type="time" name="time_to" class="form-control" required>
        </div>
        <div class="mb-3"><label class="form-label">Purpose</label>
          <textarea name="purpose" class="form-control" required></textarea>
        </div>
        <div class="mb-3"><label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="submit_ob" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTable Init -->
<script>
$(document).ready(function () {
  const table = $('#obTable').DataTable({
    lengthMenu: [5, 10, 25, 50],
    pageLength: 10,
    language: {
      search: "",
      searchPlaceholder: "Search official business...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      zeroRecords: "No matching requests found",
    },
    dom:
      "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>"+
      "<'row'<'col-sm-12'tr>>" +
      "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
  });

  setTimeout(() => {
    $('.search-wrapper').html(`
      <div class="input-group w-auto ms-auto" style="max-width:300px;">
        <span class="input-group-text bg-white border-end-0 rounded-start">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search..." id="dt-search-input">
      </div>
    `);
    $('#dt-search-input').on('keyup', function () {
      table.search(this.value).draw();
    });
  }, 0);
});

</script>

<!-- OB Functions -->
<script>
function editOB(data) {
  if (data.status !== 'Pending') {
    Swal.fire({
      icon: 'info',
      title: 'Not Editable',
      text: 'Only pending requests can be edited.'
    });
    return;
  }

  const form = document.querySelector('#obModal form');
  form.querySelector('[name="ob_id"]').value = data.ob_id;
  form.querySelector('[name="date"]').value = data.date;
  form.querySelector('[name="time_from"]').value = data.time_from;
  form.querySelector('[name="time_to"]').value = data.time_to;
  form.querySelector('[name="purpose"]').value = data.purpose;
  form.querySelector('[name="location"]').value = data.location;
  new bootstrap.Modal(document.getElementById('obModal')).show();
}

function clearOBForm() {
  const form = document.querySelector('#obModal form');
  form.reset();
  form.querySelector('[name="ob_id"]').value = '';
}

$('#obModal').on('hidden.bs.modal', function () {
  clearOBForm();
});
</script>

<!-- SweetAlert Feedback -->
<?php if (isset($_SESSION['ob_success'])) : ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $_SESSION['ob_success'] ?>',
  toast: true,
  position: 'top-end',
  timer: 3000,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['ob_success']); endif; ?>
