<?php
session_start();
include 'connection.php';

$employee_id = $_SESSION['user_id'];

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_document'])) {
    $doc_name = mysqli_real_escape_string($conn, $_POST['document_name']);
    $doc_type = mysqli_real_escape_string($conn, $_POST['document_type']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $file_path = '';

    if (!empty($_FILES['document_file']['name'])) {
        $upload_dir = '../uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['document_file']['name']);
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
            $_SESSION['error'] = 'File upload failed.';
            header("Location: user_documents.php");
            exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO EmployeeDocuments (employee_id, document_name, document_type, file_path, remarks, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $employee_id, $doc_name, $doc_type, $file_path, $remarks);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Document uploaded successfully!';
    } else {
        $_SESSION['error'] = 'Upload failed.';
    }
    $stmt->close();

    header("Location: user_documents.php");
    exit;
}

// Fetch uploaded documents
$documents_q = mysqli_query($conn, "SELECT * FROM EmployeeDocuments WHERE employee_id = '$employee_id' ORDER BY uploaded_at DESC");
?>

<?php include 'user_head.php'; ?>
<?php include 'user/sidebar.php'; ?>
<?php include 'user_header.php'; ?>

<div class="pc-container">
    <div class="pc-content">
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Employee Documents</h5>
                        <small class="text-muted">Upload and view your personal documents</small>
                    </div>
                    <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#documentModal">
                        <i class="bi bi-plus-circle me-1"></i> Upload Document
                    </button>
                </div>

                <!-- Upload Modal -->
                <div class="modal fade" id="documentModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <form method="POST" enctype="multipart/form-data" class="modal-content">
                            <input type="hidden" name="save_document" value="1">
                            <div class="modal-header">
                                <h5 class="modal-title">Upload Document</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Document Name</label>
                                    <input type="text" name="document_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Document Type</label>
                                    <input type="text" name="document_type" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">File Upload</label>
                                    <input type="file" name="document_file" class="form-control" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Upload</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="table-responsive mt-3">
                    <table id="documentTable" class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Remarks</th>
                                <th>Uploaded At</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($documents_q)) : ?>
                                <tr>
                                    <td><a href="<?= $row['file_path'] ?>" target="_blank"><?= htmlspecialchars($row['document_name']) ?></a></td>
                                    <td><?= htmlspecialchars($row['document_type']) ?: '—' ?></td>
                                    <td><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($row['uploaded_at'])) ?></td>
                                    <td class="text-center">
                                        <a href="<?= $row['file_path'] ?>" target="_blank" title="Download">
                                            <i class="bi bi-download" style="color: black; font-size: 1.2rem;"></i>
                                        </a>
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

<!-- SweetAlert + DataTables Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<?php if (isset($_SESSION['success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= $_SESSION['success'] ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?= $_SESSION['error'] ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
});
</script>
<?php unset($_SESSION['error']); endif; ?>

<script>
$(document).ready(function () {
    const table = $('#documentTable').DataTable({
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search documents...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ documents",
            infoEmpty: "No documents available",
            zeroRecords: "No matching documents found"
        },
        dom:
            "<'row mb-3'<'col-md-6'l><'col-md-6 text-end'<'search-wrapper'>>>"+
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>"
    });

    // Build modern Bootstrap search input group with icon
    setTimeout(() => {
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0 rounded-start">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="search" class="form-control border-start-0 rounded-end" placeholder="Search documents..." id="dt-search-input">
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        // Link search input to DataTable search
        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
        });
    }, 0);
});
</script>
<style>
  #dt-search-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: #ced4da !important; /* default border */
  }
</style>
