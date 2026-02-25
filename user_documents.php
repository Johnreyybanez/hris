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
         <div style="
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        opacity: 0.04;
        z-index: 0;
        pointer-events: none;
    ">
        <img src="asset/images/logo.webp" alt="Company Logo" style="max-width: 600px;">
    </div>
    <!-- Custom Breadcrumb Style -->
<style>
    .breadcrumb {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        margin-left: 15px;
         margin-right: 15px;
    }
    .breadcrumb-item a {
        color:  #6c757d;
        text-decoration: none;
        font-weight: 500;
    }
    .breadcrumb-item a:hover {
        text-decoration: underline;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        content: "\f285"; /* Bootstrap chevron-right */
        font-family: "bootstrap-icons";
        color: #6c757d;
        font-size: 0.8rem;
    }
    .breadcrumb-item.active {
    color: #007bff; /* Bootstrap blue */
    
}
</style>

<!-- Breadcrumb -->
<ul class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="user_dashboard.php"><i class="bi bi-house-door-fill"></i> Home</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
       Documents
    </li>
</ul>
        <div class="container-fluid py-4">
            <div class="card card-body shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                       <h5 class="mb-1 fw-semibold text-dark">My Documents</h5>
        <small class="text-muted">View and download your uploaded documents</small>
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

    
    <div class="card-body px-3 py-2 bg-white text-dark"> 
    <div class="table-responsive">
        <table id="documentTable"  class="table table-bordered  "  >
            <thead class="table-danger text-center ">
                <tr>
                    <th class=" text-center">File Name</th>
                    <th class=" text-center">Type</th>
                    <th class=" text-center">Remarks</th>
                    <th class=" text-center">Uploaded At</th>
                   <th class=" text-center" >Download</th>

                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($documents_q)) : ?>
                    <tr style="background-color: white;"> <!-- ✅ Force white background -->
                        <td class="text-center">
                            <a href="<?= $row['file_path'] ?>" target="_blank" class="text-decoration-none text-dark ">
                                <?= htmlspecialchars($row['document_name']) ?>
                            </a>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($row['document_type']) ?: '—' ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['remarks']) ?: '—' ?></td>
                        <td class="text-center"><?= date('M d, Y h:i A', strtotime($row['uploaded_at'])) ?></td>
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



<!-- SweetAlert + DataTables Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<?php if (isset($_SESSION['success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = localStorage.getItem('dark-mode') === 'enabled';
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= $_SESSION['success'] ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        background: isDark ? '#1e1e1e' : '#fff',
        color: isDark ? '#f8f9fa' : '#000',
        customClass: {
            popup: isDark ? 'swal-dark' : ''
        }
    });
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = localStorage.getItem('dark-mode') === 'enabled';
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?= $_SESSION['error'] ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        background: isDark ? '#1e1e1e' : '#fff',
        color: isDark ? '#f8f9fa' : '#000',
        customClass: {
            popup: isDark ? 'swal-dark' : ''
        }
    });
});
</script>
<?php unset($_SESSION['error']); endif; ?>
<!-- Lottie Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.1/lottie.min.js"></script>

<script>
$(document).ready(function () {
    const table = $('#documentTable').DataTable({
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            search: "",
            searchPlaceholder: "Search documents...",
            lengthMenu: "",
            info: "Showing <strong>_START_</strong> to <strong>_END_</strong> of <strong>_TOTAL_</strong> documents",
            infoEmpty: "No documents available",
            zeroRecords: `
                <div style="text-align:center; padding:10px ;">
                    <div id="no-results-animation" style="width:120px; height:120px; margin:0 auto;"></div>
                    <p class="mt-2 mb-20 text-muted">No documents data</p>
                </div>
            `,
            paginate: {
                previous: `<i class="bi bi-chevron-left"></i>`,
                next: `<i class="bi bi-chevron-right"></i>`
            }
        },
        dom:
            "<'row mb-3 align-items-center'<'col-md-6'<'custom-length-wrapper'>>" +
            "<'col-md-6 text-end'<'search-wrapper'>>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row mt-3 align-items-center'<'col-sm-6'i><'col-sm-6 text-end'p>>"
    });

    // Show Lottie animation only when searching
    table.on('draw', function () {
        const searchValue = table.search().trim(); // Current search text
        if (searchValue.length > 0) { 
            setTimeout(() => {
                const container = document.getElementById('no-results-animation');
                if (container) {
                    lottie.loadAnimation({
                        container: container,
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: 'asset/images/nodatafound.json' // ✅ correct path to your JSON file
                    });
                }
            }, 50);
        }
    });

    setTimeout(() => {
        // Custom entries dropdown
        const customLength = `
            <div class="d-flex align-items-center gap-2">
                <label for="customLengthSelect" class="mb-0 text-dark">Show</label>
                <select id="customLengthSelect" class="form-select form-select-sm w-auto" style="border: none; box-shadow: none;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="text-dark">entries</span>
            </div>
        `;
        $('.custom-length-wrapper').html(customLength);

        $('#customLengthSelect').on('change', function () {
            table.page.len($(this).val()).draw();
        });

        // Custom search bar
        const customSearch = `
            <div class="input-group w-auto ms-auto" style="max-width: 200px; height: 36px;">
                <span class="input-group-text bg-white border-end-0 rounded-start px-2 py-1" style="height: 100%;">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input 
                    type="search" 
                    class="form-control border-start-0 rounded-end py-1" 
                    placeholder="Search documents..." 
                    id="dt-search-input" 
                    style="height: 100%;"
                >
            </div>
        `;
        $('.search-wrapper').html(customSearch);

        $('#dt-search-input').on('keyup', function () {
            table.search(this.value).draw();
        });

        // Cleanup pagination buttons
        $('body').on('draw.dt', function () {
            $('.dataTables_paginate .paginate_button')
                .removeClass()
                .addClass('text-dark')
                .css({
                    'border': 'none',
                    'background': 'none',
                    'border-radius': '0',
                    'padding': '4px',
                    'margin': '0 2px',
                    'box-shadow': 'none'
                });

            $('.dataTables_info')
                .removeClass()
                .addClass('text-muted small')
                .css({
                    'border': 'none',
                    'background': 'none',
                    'border-radius': '0',
                    'padding': '0',
                    'margin': '0',
                    'font-size': '13px'
                });
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
 #documentTable

{
    background-color: whitesmoke;
  border: 1px solid whitesmoke !important;
}

</style>
