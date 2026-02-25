
<!DOCTYPE html>
<html lang="en">
<head>
  <title>HRIS | Dashboard</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Mantis is made using Bootstrap 5 design framework.">
  <meta name="keywords" content="HRIS, Dashboard, Bootstrap 5, Admin Template">
  <meta name="author" content="CodedThemes">

  <link rel="icon" href="Hris.png" type="image/x-icon">

  <!-- Icons -->
  <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css">
  <link rel="stylesheet" href="assets/fonts/feather.css">
  <link rel="stylesheet" href="assets/fonts/fontawesome.css">
  <link rel="stylesheet" href="assets/fonts/material.css">

  <!-- Styles -->
  <link rel="stylesheet" href="assets/css/style.css" id="main-style-link">
  <link rel="stylesheet" href="assets/css/style-preset.css">

  <!-- DataTables Styles -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

  <style>
    @keyframes pulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.1); opacity: 0.8; }
      100% { transform: scale(1); opacity: 1; }
    }
  
  body {
    font-family: "Times New Roman", Times, serif;

}
/* Fullscreen loader background */
.loader-bg {
  position: fixed;
  inset: 0;
  background-color: #ffffff;
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
}
.loader-bg {
  transition: opacity 1s ease;
}

/* Uiverse Loader */
.loader {
  position: relative;
  width: 5em;   /* increased from 2.5em */
  height: 5em;  /* increased from 2.5em */
  transform: rotate(165deg);
}

.loader:before,
.loader:after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  display: block;
  width: 1em;     /* increased from 0.5em */
  height: 1em;    /* increased from 0.5em */
  border-radius: 0.5em;  /* half of width/height */
  transform: translate(-50%, -50%);
}

.loader:before {
  animation: before8 2s infinite;
}

.loader:after {
  animation: after6 2s infinite;
}

/* Animations remain the same, but shadows will scale proportionally if needed */
@keyframes before8 {
  0% {
    width: 1em;
    box-shadow: 2em -1em rgba(225, 20, 98, 0.75),
                -2em 1em rgba(111, 202, 220, 0.75);
  }
  35% {
    width: 5em;
    box-shadow: 0 -1em rgba(225, 20, 98, 0.75),
                0 1em rgba(111, 202, 220, 0.75);
  }
  70% {
    width: 1em;
    box-shadow: -2em -1em rgba(225, 20, 98, 0.75),
                2em 1em rgba(111, 202, 220, 0.75);
  }
  100% {
    box-shadow: 2em -1em rgba(225, 20, 98, 0.75),
                -2em 1em rgba(111, 202, 220, 0.75);
  }
}

@keyframes after6 {
  0% {
    height: 1em;
    box-shadow: 1em 2em rgba(61, 184, 143, 0.75),
                -1em -2em rgba(233, 169, 32, 0.75);
  }
  35% {
    height: 5em;
    box-shadow: 1em 0 rgba(61, 184, 143, 0.75),
                -1em 0 rgba(233, 169, 32, 0.75);
  }
  70% {
    height: 1em;
    box-shadow: 1em -2em rgba(61, 184, 143, 0.75),
                -1em 2em rgba(233, 169, 32, 0.75);
  }
  100% {
    box-shadow: 1em 2em rgba(61, 184, 143, 0.75),
                -1em -2em rgba(233, 169, 32, 0.75);
  }
}


    /* DataTables styling */
    .dataTables_wrapper {
      font-family: "Times New Roman", Times, serif;
    }

.dataTables_wrapper .dataTables_length select {
   
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
   
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    margin-left: 0.5rem;
    background-color: #fff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.dataTables_wrapper .dataTables_filter input:focus {
    border-color: #535355ff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(85, 85, 87, 0.25);
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
    background-color: #323333ff;
    border-color: #3c3d3dff;
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
      background-color: #16c216ff;
      border-bottom: 2px solid #292a2cff;
      color: rgba(5, 5, 5, 1);
      font-weight: 600;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.025em;
      padding: 1rem 0.75rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .table tbody td {
      padding: 0.875rem 0.75rem;
      vertical-align: middle;
      border-top: 1px solid #fcf8f8ff;
    }

    .table tbody tr {
      transition: all 0.2s ease-in-out;
    }

    .table tbody tr:hover {
      background-color: #bcbec0ff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transform: scale(1.001);
    }

    .card {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      border: 2px solid #e2e8f0;
      border-radius: 0.5rem;
      transition: box-shadow 0.3s ease-in-out;
    }

    .card:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .card-header {
      background-color: #fff;
      border-bottom: 1px solid rgb(17, 19, 22);
      padding: 1.25rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .card-body {
      padding: 1.25rem;
    }

    .dataTables_length label,
    .dataTables_filter label {
      display: flex;
      align-items: center;
      font-weight: 500;
      color: rgb(10, 11, 12);
    }

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

    /* Buttons and badges with shadows */
    .btn {
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      transition: all 0.2s ease-in-out;
    }

    .btn:hover {
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
      transform: translateY(-1px);
    }

    .badge {
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .avtar {
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
        background-color: #fffefeff;
        z-index: 10;
    }
    
    .table thead th:first-child {
        background-color: #16c216ff;
    }
    
    .table tbody tr:hover td:first-child {
        background-color:rgba(8, 8, 8, 1);
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
.page-header-title h5 {
    color: #080808ff;   /* makes the title white */
    
    font-weight: bold; /* makes the text bold */
}


.breadcrumb .breadcrumb-item {
    color: #0e0d0dff; /* makes breadcrumb items white */
}

.breadcrumb .breadcrumb-item a {
    color: #080808ff; /* makes links white */
    text-decoration: none; /* optional: remove underline */
}

.breadcrumb .breadcrumb-item a:hover {
    color: #020202ff; /* optional: change color on hover */
}
  /* [You can keep your existing custom DataTable CSS here or link it separately] */
 /* Add shadow to all Bootstrap buttons */
.btn {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2); /* soft shadow */
    transition: all 0.3s ease; /* smooth hover effect */
}

/* Optional: increase shadow on hover */
.btn:hover {
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
}

  </style>
</head>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
  <!-- Pre-loader start -->
<div class="loader-bg">
  <div class="loader"></div>
</div>
<!-- Pre-loader end -->
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
      // Wait 30 seconds (30000 ms) before starting the fade
      setTimeout(() => {
        loader.style.opacity = '0';
        // Wait 1 second for the fade effect before hiding completely
        setTimeout(() => loader.style.display = 'none', 1000);
      }, 50000); // 30000ms = 30 seconds
    }
  });
</script>

  
</body>
</html>
