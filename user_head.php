<!DOCTYPE html>
<html lang="en">
<head>
  <title>HRIS</title>
  <!-- [Meta] -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Mantis is made using Bootstrap 5 design framework.">
  <meta name="keywords" content="HRIS, Dashboard, Bootstrap 5, Admin Template">
  <meta name="author" content="CodedThemes">

  <!-- [Favicon] icon -->
  <link rel="icon" href="assets/images/logo.webp" type="image/x-icon">

  <!-- [Icons] -->
  <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
  <link rel="stylesheet" href="assets/fonts/feather.css">
  <link rel="stylesheet" href="assets/fonts/fontawesome.css">
  <link rel="stylesheet" href="assets/fonts/material.css">

  <!-- [Styles] -->
  <link rel="stylesheet" href="assets/css/style.css" id="main-style-link">
  <link rel="stylesheet" href="assets/css/style-preset.css">

  <!-- [DataTables Styles] -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">


  <style>
    /* Preloader logo animation */
    @keyframes pulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.1); opacity: 0.8; }
      100% { transform: scale(1); opacity: 1; }
    }
  
  body {
    font-family: "Times New Roman", Times, serif;
  }


    /* Loader styling */
    .loader-bg {
      position: fixed;
      inset: 0;
      background-color: #ffffff;
      z-index: 9999;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .loader-bg img {
      height: 150px;
      animation: pulse 1.5s infinite;
    }
/* Custom DataTables styling for Mantis theme */
.dataTables_wrapper {
    font-family: "Times New Roman", Times, serif;
 
}

.dataTables_wrapper .dataTables_length select {
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    margin-left: 0.5rem;
    background-color: #fff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.dataTables_wrapper .dataTables_filter input:focus {
    border-color: #3b82f6;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    color: #6b7280;
    font-size: 0.875rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.5rem 0.75rem;
    margin-left: 0.125rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    color: #374151;
    background-color: #fff;
    text-decoration: none;
    transition: all 0.15s ease-in-out;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background-color: #f3f4f6;
    border-color: #d1d5db;
    color: #1f2937;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.table-responsive {
    border-radius: 0.5rem;
    overflow: hidden;
}

.table thead th {
  background-color: #3EB489;

    border-bottom: 2px solid #292a2cff;
    color:whitesmoke;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    padding: 1rem 0.75rem;
}
 
.table tbody td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
    border-top: 1px solid #e2e8f0;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

.dataTables_wrapper .row {
    margin: 0;
}

.dataTables_wrapper .col-md-6,
.dataTables_wrapper .col-sm-5,
.dataTables_wrapper .col-sm-7 {
    padding: 0;
}

.card {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    border: 2px solid #e2e8f0;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solidrgb(17, 19, 22);
    padding: 1.25rem;
}

.card-body {
    padding: 1.25rem;
}

/* Custom search and length menu styling */
.dataTables_length label,
.dataTables_filter label {
    display: flex;
    align-items: center;
    font-weight: 500;
    color:rgb(10, 11, 12);
}

.dt-search-box {
    position: relative;
    display: inline-block;
}

.dt-search-box::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    background-repeat: no-repeat;
    background-size: contain;
    z-index: 1;
}

.dt-search-input {
    padding-left: 2.5rem !important;
}

/* Custom styling for same row layout */
.dt-top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.dt-bottom-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
}

/* Enhanced responsive design for DataTables */
@media (max-width: 992px) {
    .dt-top-controls,
    .dt-bottom-controls {
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
    }
    
    .dt-top-controls > div,
    .dt-bottom-controls > div {
        width: 100%;
        text-align: center;
    }
    
    .dataTables_filter {
        margin-top: 0.5rem;
    }
}

@media (max-width: 768px) {
    /* Stack table controls vertically on mobile */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 0.75rem;
    }
    
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 0.5rem;
        text-align: center;
    }
    
    /* Make pagination buttons more mobile-friendly */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.375rem 0.5rem;
        margin: 0.125rem;
        font-size: 0.875rem;
    }
    
    /* Adjust search input for mobile */
    .dataTables_filter input {
        width: 100%;
        max-width: 300px;
    }
    
    /* Mobile table styling */
    .table-responsive {
        border: 1px solidrgb(88, 90, 92);
        border-radius: 0.5rem;
    }
    
    .table thead th {
        font-size: 0.75rem;
        padding: 0.75rem 0.5rem;
    }
    
    .table tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
    }
    
    /* Mobile-specific button styling */
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    /* Adjust badge and avatar sizes for mobile */
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .avtar {
        width: 2rem;
        height: 2rem;
    }
    
    .avtar i {
        font-size: 0.875rem;
    }
}

@media (max-width: 576px) {
    /* Extra small devices adjustments */
    .card-header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .card-header > div:first-child {
        width: 100%;
    }
    
    .card-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    /* Very small screen pagination */
    .dataTables_wrapper .dataTables_paginate {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .dataTables_wrapper .dataTables_paginate .pagination {
        flex-wrap: nowrap;
        min-width: max-content;
    }
    
    /* Hide some pagination buttons on very small screens */
    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):not(.previous):not(.next) {
        display: none;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        position: relative;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current::before {
        content: 'Page ';
        font-size: 0.75rem;
    }
}

/* Responsive table enhancements */
@media (max-width: 768px) {
    .table-responsive table {
        min-width: 600px; /* Ensure minimum width for horizontal scroll */
    }
    
    /* Hide less important columns on smaller screens */
    .table th:first-child,
    .table td:first-child {
        position: sticky;
        left: 0;
        background-color: #fff;
        z-index: 10;
    }
    
    .table thead th:first-child {
        background-color:rgb(7, 113, 139);
    }
    
    .table tbody tr:hover td:first-child {
        background-color:rgb(48, 50, 51);
    }
}

/* Enhanced responsive behavior for DataTables elements */
.dataTables_wrapper .dataTables_length select {
    min-width: 80px;
}

.dataTables_wrapper .dataTables_filter input {
    min-width: 150px;
}

@media (max-width: 480px) {
    .dataTables_wrapper .dataTables_length select {
        min-width: 70px;
        font-size: 0.875rem;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        min-width: 120px;
        font-size: 0.875rem;
    }
}
    /* [You can keep your existing custom DataTable CSS here or link it separately] */
    @media print {
  thead {
    background-color:rgb(7, 113, 139) !important;
    color: white !important;
  }
  table thead th {
    background-color:rgb(7, 113, 139) !important;
    color: white !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
@media print {
  .no-print {
    display: none !important;
  }
}

  </style>
</head>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
  <!-- [ Pre-loader with logo ] start -->
  <div class="loader-bg">
    <img src="assets/images/logo.webp" alt="Loading...">
  </div>


  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Bootstrap & Mantis Plugins -->
  <script src="assets/js/plugins/popper.min.js"></script>
  <script src="assets/js/plugins/simplebar.min.js"></script>
  <script src="assets/js/plugins/bootstrap.min.js"></script>
  <script src="assets/js/fonts/custom-font.js"></script>
  <script src="assets/js/pcoded.js"></script>
  <script src="assets/js/plugins/feather.min.js"></script>

  <!-- Dashboard Specific JS -->
  <script src="assets/js/plugins/apexcharts.min.js"></script>
  <script src="assets/js/pages/dashboard-analytics.js"></script>

  <!-- Layout Setup -->
  <script>layout_change('light');</script>
  <script>change_box_container('false');</script>
  <script>layout_rtl_change('false');</script>
  <script>preset_change("preset-1");</script>
  <script>font_change("Public-Sans");</script>

  <!-- Hide Preloader on Page Load -->
  <script>
    window.addEventListener("load", function () {
      const loader = document.querySelector('.loader-bg');
      if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => loader.style.display = 'none', 300);
      }
    });
  </script>
  
</body>
</html>
