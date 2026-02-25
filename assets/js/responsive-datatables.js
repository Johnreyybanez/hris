/**
 * Enhanced Responsive DataTables JavaScript for HRIS System
 * Provides consistent responsive behavior across all data tables
 */

// Global DataTables configuration
$.extend(true, $.fn.dataTable.defaults, {
    responsive: {
        details: {
            display: $.fn.dataTable.Responsive.display.childRowImmediate,
            type: 'column',
            renderer: function (api, rowIdx, columns) {
                var data = $.map(columns, function (col, i) {
                    return col.hidden ?
                        '<li data-dtr-index="' + col.columnIndex + '" data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                        '<span class="dtr-title">' +
                        col.title + ':' +
                        '</span> ' +
                        '<span class="dtr-data">' +
                        col.data +
                        '</span>' +
                        '</li>' :
                        '';
                }).join('');

                return data ? $('<ul class="dtr-details"/>').append(data) : false;
            }
        },
        breakpoints: [
            { name: 'desktop', width: Infinity },
            { name: 'tablet-l', width: 1024 },
            { name: 'tablet-p', width: 768 },
            { name: 'mobile-l', width: 480 },
            { name: 'mobile-p', width: 320 }
        ]
    },
    language: {
        search: "_INPUT_",
        searchPlaceholder: "Search records...",
        lengthMenu: "Show _MENU_ entries",
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        infoEmpty: "No records available",
        infoFiltered: "(filtered from _MAX_ total entries)",
        zeroRecords: "No matching records found",
        emptyTable: "No data available in table",
        paginate: {
            first: "First",
            last: "Last",
            next: "Next",
            previous: "Previous"
        },
        processing: "Processing..."
    },
    dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
    pageLength: 10,
    processing: true,
    autoWidth: false,
    columnDefs: [
        {
            targets: '_all',
            className: 'align-middle'
        }
    ]
});

/**
 * Initialize responsive DataTable with enhanced features
 * @param {string} tableId - The ID of the table element
 * @param {object} options - Additional DataTable options
 * @returns {object} DataTable instance
 */
function initResponsiveDataTable(tableId, options = {}) {
    const defaultOptions = {
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.childRowImmediate,
                type: 'column',
                renderer: function (api, rowIdx, columns) {
                    var data = $.map(columns, function (col, i) {
                        return col.hidden ?
                            '<li data-dtr-index="' + col.columnIndex + '" data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                            '<span class="dtr-title">' +
                            col.title + ':' +
                            '</span> ' +
                            '<span class="dtr-data">' +
                            col.data +
                            '</span>' +
                            '</li>' :
                            '';
                    }).join('');

                    return data ? $('<ul class="dtr-details"/>').append(data) : false;
                }
            }
        },
        columnDefs: [
            {
                targets: '_all',
                className: 'align-middle'
            }
        ]
    };

    // Merge options
    const finalOptions = $.extend(true, {}, defaultOptions, options);

    // Initialize DataTable
    const table = $(tableId).DataTable(finalOptions);

    // Add responsive recalculation on window resize
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            table.columns.adjust().responsive.recalc();
        }, 250);
    });

    // Initialize tooltips after table draw
    table.on('draw', function() {
        $('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').tooltip();
    });

    // Add loading state management
    table.on('processing.dt', function(e, settings, processing) {
        if (processing) {
            $(tableId + '_wrapper').addClass('dt-processing');
        } else {
            $(tableId + '_wrapper').removeClass('dt-processing');
        }
    });

    return table;
}

/**
 * Enhanced delete functionality with SweetAlert
 * @param {string} buttonSelector - Selector for delete buttons
 * @param {object} options - Configuration options
 */
function initDeleteFunctionality(buttonSelector, options = {}) {
    const defaults = {
        title: 'Are you sure?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        successTitle: 'Deleted!',
        successText: 'Record has been deleted successfully.',
        errorTitle: 'Error!',
        errorText: 'An error occurred while deleting the record.',
        loadingTitle: 'Deleting...',
        loadingText: 'Please wait while we delete the record.',
        idAttribute: 'data-id',
        ajaxUrl: window.location.href,
        ajaxData: function(id) {
            return { delete_id: id, action: 'delete' };
        },
        onSuccess: function(response, row, table) {
            table.row(row).remove().draw();
        }
    };

    const config = $.extend(true, {}, defaults, options);

    $(document).on('click', buttonSelector, function(e) {
        e.preventDefault();
        
        const button = $(this);
        const id = button.attr(config.idAttribute);
        const row = button.closest('tr');
        const table = $('#' + row.closest('table').attr('id')).DataTable();

        // Check if button is disabled
        if (button.prop('disabled') || button.hasClass('disabled')) {
            return false;
        }

        Swal.fire({
            title: config.title,
            text: typeof config.text === 'function' ? config.text(id) : config.text,
            icon: config.icon,
            showCancelButton: true,
            confirmButtonColor: config.confirmButtonColor,
            cancelButtonColor: config.cancelButtonColor,
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: config.cancelButtonText
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: config.loadingTitle,
                    text: config.loadingText,
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // AJAX delete request
                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: config.ajaxData(id),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            config.onSuccess(response, row, table);
                            
                            Swal.fire({
                                title: config.successTitle,
                                text: response.message || config.successText,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: config.errorTitle,
                                text: response.message || config.errorText,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        Swal.fire({
                            title: config.errorTitle,
                            text: config.errorText,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });
}

/**
 * Initialize modal edit functionality
 * @param {string} buttonSelector - Selector for edit buttons
 * @param {string} modalId - ID of the modal
 * @param {object} options - Configuration options
 */
function initEditFunctionality(buttonSelector, modalId, options = {}) {
    const defaults = {
        dataAttribute: 'data-record',
        titleSelector: '.modal-title',
        formSelector: 'form',
        actionFieldSelector: 'input[name="action"]',
        idFieldSelector: 'input[name="id"]',
        editTitle: 'Edit Record',
        addTitle: 'Add Record',
        onPopulate: function(data, modal) {
            // Default population logic
            Object.keys(data).forEach(key => {
                const field = modal.find(`[name="${key}"]`);
                if (field.length) {
                    if (field.is('select')) {
                        field.val(data[key]).trigger('change');
                    } else if (field.is(':checkbox')) {
                        field.prop('checked', data[key] == 1);
                    } else if (field.is(':radio')) {
                        field.filter(`[value="${data[key]}"]`).prop('checked', true);
                    } else {
                        field.val(data[key]);
                    }
                }
            });
        },
        onReset: function(modal) {
            const form = modal.find(this.formSelector)[0];
            if (form) form.reset();
            
            modal.find('.is-invalid').removeClass('is-invalid');
            modal.find('.invalid-feedback').remove();
            modal.find(this.actionFieldSelector).val('add');
            modal.find(this.idFieldSelector).val('');
        }
    };

    const config = $.extend(true, {}, defaults, options);

    // Edit button click handler
    $(document).on('click', buttonSelector, function(e) {
        e.preventDefault();
        
        const button = $(this);
        const modal = $(modalId);
        
        try {
            const dataStr = button.attr(config.dataAttribute);
            const data = dataStr ? JSON.parse(dataStr) : {};
            
            // Show modal
            const modalInstance = new bootstrap.Modal(modal[0]);
            modalInstance.show();
            
            // Update modal title and form action
            modal.find(config.titleSelector).text(config.editTitle);
            modal.find(config.actionFieldSelector).val('edit');
            
            // Populate form fields
            config.onPopulate(data, modal);
            
        } catch (error) {
            console.error('Error parsing edit data:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to load record data for editing.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });

    // Add button functionality (reset modal)
    $(document).on('click', '[data-bs-target="' + modalId + '"]', function(e) {
        if (!$(this).hasClass('btn-edit') && !$(this).is(buttonSelector)) {
            const modal = $(modalId);
            
            setTimeout(() => {
                config.onReset.call(config, modal);
                modal.find(config.titleSelector).text(config.addTitle);
            }, 100);
        }
    });

    // Modal hidden event
    $(modalId).on('hidden.bs.modal', function() {
        config.onReset.call(config, $(this));
    });
}

/**
 * Initialize custom filters for DataTables
 * @param {object} table - DataTable instance
 * @param {object} filters - Filter configuration
 */
function initCustomFilters(table, filters = {}) {
    // Clear existing custom search functions
    $.fn.dataTable.ext.search = [];

    Object.keys(filters).forEach(filterId => {
        const filterConfig = filters[filterId];
        
        $(filterId).on('change', function() {
            const selectedValue = $(this).val();
            
            // Remove existing filter for this ID
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                return fn.name !== filterId;
            });
            
            if (selectedValue !== '') {
                // Add new filter
                const filterFunction = function(settings, data, dataIndex) {
                    if (settings.nTable.id !== table.table().node().id.replace('#', '')) {
                        return true;
                    }
                    
                    if (filterConfig.type === 'column') {
                        return data[filterConfig.columnIndex].includes(selectedValue);
                    } else if (filterConfig.type === 'attribute') {
                        const row = $(settings.nTable).find('tbody tr').eq(dataIndex);
                        const attributeValue = row.attr(filterConfig.attribute);
                        return attributeValue === selectedValue;
                    } else if (filterConfig.type === 'custom' && typeof filterConfig.filter === 'function') {
                        return filterConfig.filter(settings, data, dataIndex, selectedValue);
                    }
                    
                    return true;
                };
                
                filterFunction.name = filterId;
                $.fn.dataTable.ext.search.push(filterFunction);
            }
            
            table.draw();
        });
    });
}

/**
 * Format time display in 12-hour format
 * @param {HTMLElement} input - Time input element
 * @param {string} displayId - ID of display element
 */
function formatTimeDisplay(input, displayId) {
    if (input.value) {
        try {
            const time = new Date(`2000-01-01T${input.value}`);
            const formatted = time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
            const displayElement = document.getElementById(displayId);
            if (displayElement) {
                displayElement.textContent = formatted;
                displayElement.classList.add('time-display');
            }
        } catch (error) {
            console.error('Error formatting time:', error);
            const displayElement = document.getElementById(displayId);
            if (displayElement) {
                displayElement.textContent = 'Invalid time';
                displayElement.classList.remove('time-display');
            }
        }
    } else {
        const displayElement = document.getElementById(displayId);
        if (displayElement) {
            displayElement.textContent = '12-hour format';
            displayElement.classList.remove('time-display');
        }
    }
}

/**
 * Show toast notification
 * @param {string} icon - Icon type (success, error, warning, info)
 * @param {string} message - Message to display
 * @param {number} timer - Auto-close timer in milliseconds
 */
function showToast(icon, message, timer = 3000) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: timer,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: icon,
        title: message
    });
}

/**
 * Initialize character counter for textarea
 * @param {string} selector - Textarea selector
 * @param {number} maxLength - Maximum character length
 */
function initCharacterCounter(selector, maxLength) {
    $(document).on('input', selector, function() {
        const textarea = $(this);
        const currentLength = textarea.val().length;
        const remaining = maxLength - currentLength;
        
        let counterText = `${remaining} characters remaining`;
        let counterClass = 'text-muted';
        
        if (remaining < 0) {
            counterText = `${Math.abs(remaining)} characters over limit`;
            counterClass = 'text-danger';
            textarea.addClass('is-invalid');
        } else if (remaining < 50) {
            counterClass = 'text-warning';
            textarea.removeClass('is-invalid');
        } else {
            textarea.removeClass('is-invalid');
        }
        
        // Update or create counter display
        let counter = textarea.siblings('.character-counter');
        if (counter.length === 0) {
            counter = $('<div class="character-counter form-text"></div>');
            textarea.after(counter);
        }
        
        counter.text(counterText)
               .removeClass('text-muted text-warning text-danger')
               .addClass(counterClass);
    });
}

/**
 * Auto-dismiss alerts after specified time
 * @param {number} delay - Delay in milliseconds
 */
function autoDismissAlerts(delay = 5000) {
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, delay);
}

/**
 * Initialize all responsive features for a page
 * @param {object} config - Page configuration
 */
function initResponsivePage(config = {}) {
    const defaults = {
        tableId: '#dataTable',
        tableOptions: {},
        deleteButton: '.btn-delete',
        deleteOptions: {},
        editButton: '.btn-edit',
        editModal: '#editModal',
        editOptions: {},
        filters: {},
        characterCounters: {},
        autoDismissAlerts: true
    };

    const pageConfig = $.extend(true, {}, defaults, config);

    // Initialize DataTable
    const table = initResponsiveDataTable(pageConfig.tableId, pageConfig.tableOptions);

    // Initialize delete functionality
    if (pageConfig.deleteButton) {
        initDeleteFunctionality(pageConfig.deleteButton, pageConfig.deleteOptions);
    }

    // Initialize edit functionality
    if (pageConfig.editButton && pageConfig.editModal) {
        initEditFunctionality(pageConfig.editButton, pageConfig.editModal, pageConfig.editOptions);
    }

    // Initialize custom filters
    if (Object.keys(pageConfig.filters).length > 0) {
        initCustomFilters(table, pageConfig.filters);
    }

    // Initialize character counters
    Object.keys(pageConfig.characterCounters).forEach(selector => {
        initCharacterCounter(selector, pageConfig.characterCounters[selector]);
    });

    // Auto-dismiss alerts
    if (pageConfig.autoDismissAlerts) {
        autoDismissAlerts();
    }

    return table;
}

// Initialize tooltips on document ready
$(document).ready(function() {
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').tooltip();
    
    // Reinitialize tooltips when content changes
    $(document).on('DOMNodeInserted', function() {
        $('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').tooltip();
    });
});

// Export functions for global use
window.ResponsiveDataTables = {
    init: initResponsiveDataTable,
    initDelete: initDeleteFunctionality,
    initEdit: initEditFunctionality,
    initFilters: initCustomFilters,
    initPage: initResponsivePage,
    formatTime: formatTimeDisplay,
    showToast: showToast,
    initCharCounter: initCharacterCounter,
    autoDismissAlerts: autoDismissAlerts
};