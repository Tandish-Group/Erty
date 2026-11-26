document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM Content Loaded - Starting table initialization');
    
    // Wait for jQuery and DataTables to be available
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.dataTable === 'undefined') {
        console.error('jQuery or DataTables not loaded');
        return;
    }

    // State per tableId
    const tables = ['tenant-mapping-table', 'payment-mapping-table', 'notice-mapping-table'];
    const selectedIdsMap = {}; // tableId -> Map()
    const selectAllPagesMap = {}; // tableId -> bool

    // Configuration for each table with their specific detail row prefix
    const tableConfigs = {
        'tenant-mapping-table': 'details-',
        'payment-mapping-table': 'payment-details-',
        'notice-mapping-table': 'notice-details-'
    };

    // Button mapping - MOVED TO TOP TO AVOID REFERENCE ERROR
    const tableBtnMap = {
        'tenant-mapping-table': {
            confirm: 'tenant-confirm-import-btn',
            cancel: 'tenant-cancel-import-btn'
        },
        'payment-mapping-table': {
            confirm: 'payment-confirm-import-btn',
            cancel: 'payment-cancel-import-btn'
        },
        'notice-mapping-table': {
            confirm: 'notice-confirm-import-btn',
            cancel: 'notice-cancel-import-btn'
        }
    };
    
    // Helper function to check if a checkbox is selectable
    function isCheckboxSelectable(checkbox) {
        return !checkbox.disabled && !checkbox.hasAttribute('disabled');
    }
    
    // Show/hide confirm and cancel buttons
    function updateButtonsVisibility(tableId) {
        const btnIds = tableBtnMap[tableId];
        if (!btnIds) return;

        const confirmBtn = document.getElementById(btnIds.confirm);
        const cancelBtn = document.getElementById(btnIds.cancel);
        if (!confirmBtn || !cancelBtn) return;

        const map = selectedIdsMap[tableId] || new Map();
        const allPages = selectAllPagesMap[tableId] || false;
        const visible = allPages || (map.size > 0);

        if (visible) {
            confirmBtn.classList.remove('d-none');
            cancelBtn.classList.remove('d-none');
        } else {
            confirmBtn.classList.add('d-none');
            cancelBtn.classList.add('d-none');
        }
    }

    // Update master checkbox
    function updateMasterCheckboxForTable(tableEl) {
        const tableId = tableEl.id;
        const masterCheckboxId = tableId.replace('-mapping-table', '-bulk-select-all');
        const master = document.getElementById(masterCheckboxId);
        
        if (!master) return;
        
        // Only count selectable checkboxes
        const rowChecks = Array.from(tableEl.querySelectorAll('tbody tr:not(.collapse) .row-select'))
            .filter(cb => isCheckboxSelectable(cb));
        const checked = rowChecks.filter(cb => cb.checked).length;
        
        if (checked === 0) {
            master.indeterminate = false;
            master.checked = false;
        } else if (checked === rowChecks.length) {
            master.indeterminate = false;
            master.checked = true;
        } else {
            master.indeterminate = true;
        }
    }

    // Refresh row checkboxes
    function refreshRowCheckboxesForTable(tableEl) {
        const tableId = tableEl.id;
        const map = selectedIdsMap[tableId] || new Map();
        
        tableEl.querySelectorAll('tbody tr:not(.collapse) .row-select').forEach(function (cb) {
            const id = String(cb.dataset.id ?? '');
            if (isCheckboxSelectable(cb)) {
                cb.checked = map.has(id);
            }
        });
        
        updateMasterCheckboxForTable(tableEl);
        updateButtonsVisibility(tableId);
    }

    // Get selected ID batch mapping
    function getSelectedIdBatchMap(tableEl, tableId) {
        const map = selectedIdsMap[tableId] || new Map();
        const idBatchMap = {};
        map.forEach((batchId, id) => {
            idBatchMap[id] = batchId;
        });
        return idBatchMap;
    }

    // Initialize each table separately
    tables.forEach(function(tableId) {
        const tableEl = document.getElementById(tableId);
        if (!tableEl) {
            console.log('Table not found:', tableId);
            return;
        }
        
        console.log('Initializing table:', tableId);
        
        const detailPrefix = tableConfigs[tableId];
        selectedIdsMap[tableId] = new Map();
        selectAllPagesMap[tableId] = false;

        try {
            // Check if DataTable is already initialized
            if ($.fn.dataTable.isDataTable('#' + tableId)) {
                console.log('Table already initialized:', tableId);
                $('#' + tableId).DataTable().destroy();
            }

            // Store and remove detail rows before DataTable initialization
            const collapsibleRows = [];
            const $table = $('#' + tableId);
            
            $table.find('tbody tr.collapse').each(function(index) {
                const $row = $(this);
                const $prevRow = $row.prev('tr:not(.collapse)');
                const originalIndex = $prevRow.index();
                
                collapsibleRows.push({
                    originalIndex: originalIndex,
                    id: $row.attr('id'),
                    html: $row[0].outerHTML
                });
                
                $row.remove();
            });
            
            console.log('Stored', collapsibleRows.length, 'collapsible rows for', tableId);

            // Initialize DataTable with proper configuration
            const dataTable = $table.DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                pageLength: 10,
                lengthChange: false,
                autoWidth: false,
                responsive: false,
                destroy: true,
                columnDefs: [
                    { 
                        orderable: false, 
                        targets: [0, -1], // checkbox and details columns
                        className: 'no-select'
                    }
                ],
                language: {
                    emptyTable: "No data available in table",
                    zeroRecords: "No matching records found"
                },
                drawCallback: function(settings) {
                    console.log('DataTable draw callback for', tableId);
                    
                    // Re-add detail rows after each draw
                    const api = this.api();
                    
                    api.rows({page: 'current'}).every(function(rowIdx, tableLoop, rowLoop) {
                        const rowNode = this.node();
                        const $rowNode = $(rowNode);
                        
                        // Find matching detail row based on the original data index
                        const dataIndex = this.index();
                        const detailRow = collapsibleRows.find(cr => cr.originalIndex === dataIndex);
                        
                        if (detailRow) {
                            // Create new detail row with updated ID
                            const newId = detailPrefix + rowLoop;
                            const $newDetailRow = $(detailRow.html);
                            $newDetailRow.attr('id', newId);
                            
                            // Update the View button to target the new ID
                            $rowNode.find('[data-bs-toggle="collapse"]').attr('data-bs-target', '#' + newId);
                            
                            // Insert after the current row
                            $rowNode.after($newDetailRow);
                        }
                    });
                    
                    // Refresh checkbox states
                    refreshRowCheckboxesForTable(tableEl);
                }
            });

            // Handle pagination, search, and sort events
            $table.on('page.dt search.dt order.dt', function() {
                // Close all open detail rows when table changes
                setTimeout(function() {
                    $table.closest('.card-body').find('tr.collapse.show').removeClass('show');
                }, 100);
            });

            console.log('DataTable initialized successfully for', tableId);

        } catch (error) {
            console.error('Error initializing DataTable for', tableId, ':', error);
        }
    });

    // Event delegation for checkboxes and master checkboxes
    document.addEventListener('click', function(e) {
        const target = e.target;
        
        // Handle row checkbox clicks
        if (target && target.classList.contains('row-select') && !target.closest('tr.collapse')) {
            // Skip if checkbox is disabled
            if (!isCheckboxSelectable(target)) {
                e.preventDefault();
                return false;
            }

            const tableEl = target.closest('table');
            if (!tableEl || !tables.includes(tableEl.id)) return;
            
            const tableId = tableEl.id;
            const id = String(target.dataset.id ?? '');
            const map = selectedIdsMap[tableId];
            
            if (target.checked) {
                map.set(id, target.dataset.batchId ?? '');
            } else {
                map.delete(id);
                selectAllPagesMap[tableId] = false;
            }

            updateMasterCheckboxForTable(tableEl);
            updateButtonsVisibility(tableId);
        }
        
        // Handle master checkbox clicks
        if (target && target.type === 'checkbox' && 
            (target.id === 'tenant-bulk-select-all' || 
             target.id === 'payment-bulk-select-all' || 
             target.id === 'notice-bulk-select-all')) {
            
            const tableId = target.id.replace('-bulk-select-all', '-mapping-table');
            const tableEl = document.getElementById(tableId);
            if (!tableEl) return;
            
            const checked = target.checked;
            const dataTable = $('#' + tableId).DataTable();
            
            if (checked) {
                dataTable.rows({ search: 'applied' }).every(function () {
                    const rowNode = this.node();
                    const checkbox = rowNode.querySelector('.row-select');
                    if (checkbox && isCheckboxSelectable(checkbox)) {
                        const id = String(checkbox.dataset.id ?? '');
                        const batchId = checkbox.dataset.batchId ?? '';
                        if (id) {
                            selectedIdsMap[tableId].set(id, batchId);
                            checkbox.checked = true;
                        }
                    }
                });
                selectAllPagesMap[tableId] = true;
            } else {
                dataTable.rows({ search: 'applied' }).every(function () {
                    const rowNode = this.node();
                    const checkbox = rowNode.querySelector('.row-select');
                    if (checkbox && isCheckboxSelectable(checkbox)) {
                        const id = String(checkbox.dataset.id ?? '');
                        if (id) {
                            selectedIdsMap[tableId].delete(id);
                            checkbox.checked = false;
                        }
                    }
                });
                selectAllPagesMap[tableId] = false;
            }

            updateButtonsVisibility(tableId);
        }
    });

    // Add select all pages buttons
    tables.forEach(function(tableId) {
        const tableEl = document.getElementById(tableId);
        if (!tableEl) return;
        
        const masterCheckboxId = tableId.replace('-mapping-table', '-bulk-select-all');
        const masterCheckbox = document.getElementById(masterCheckboxId);
        if (!masterCheckbox) return;
        
        const masterTh = masterCheckbox.closest('th');
        if (masterTh) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-link btn-sm p-0 ms-2';
            btn.title = 'Select all valid rows in this batch across all pages';
            masterTh.appendChild(btn);

            btn.addEventListener('click', function() {
                selectAllPagesMap[tableId] = true;
                
                const dataTable = $('#' + tableId).DataTable();
                let selectedCount = 0;
                let skippedCount = 0;
                
                dataTable.rows({ search: 'applied' }).every(function() {
                    const rowNode = this.node();
                    const checkbox = rowNode.querySelector('.row-select');
                    if (checkbox) {
                        if (isCheckboxSelectable(checkbox)) {
                            const id = String(checkbox.dataset.id ?? '');
                            const batchId = checkbox.dataset.batchId ?? '';
                            if (id) {
                                selectedIdsMap[tableId].set(id, batchId);
                                checkbox.checked = true;
                                selectedCount++;
                            }
                        } else {
                            skippedCount++;
                        }
                    }
                });
                
                // Update button text to show what was selected
                if (skippedCount > 0) {
                    btn.textContent = `${selectedCount} selected (${skippedCount} invalid skipped)`;
                    btn.classList.add('text-warning');
                } else {
                    btn.textContent = `All ${selectedCount} selected`;
                    btn.classList.add('text-success');
                }
                btn.disabled = true;
                
                updateMasterCheckboxForTable(tableEl);
                updateButtonsVisibility(tableId);

                // Show info message if some were skipped
                if (skippedCount > 0 && window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Selection Complete',
                        html: `
                            <div class="text-start">
                                <p><strong>${selectedCount} valid records selected</strong></p>
                                <p class="text-warning">${skippedCount} invalid records were skipped and cannot be imported.</p>
                                <p class="text-muted small">Invalid records typically have missing or incorrect invoice references.</p>
                            </div>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#198754',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });

    // Initialize button visibility
    tables.forEach(function(tableId) {
        updateButtonsVisibility(tableId);
    });

    // Button event handlers (confirm and cancel)
    const mapping = [
        { btnId: 'tenant-confirm-import-btn', tableId: 'tenant-mapping-table', formSelector: '#bulk-action-form' },
        { btnId: 'payment-confirm-import-btn', tableId: 'payment-mapping-table', formSelector: '#payment-bulk-action-form' },
        { btnId: 'notice-confirm-import-btn', tableId: 'notice-mapping-table', formSelector: '#notice-bulk-action-form' },
    ];

    mapping.forEach(entry => {
        const btn = document.getElementById(entry.btnId);
        if (!btn) return;
    
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const tableId = entry.tableId;
            const map = selectedIdsMap[tableId] || new Map();
            const allPages = selectAllPagesMap[tableId] || false;
            const tableEl = document.getElementById(tableId);
            const idBatchMap = getSelectedIdBatchMap(tableEl, tableId);

            if (!allPages && map.size < 1) {
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No selection',
                        text: 'Please select at least one valid row or click "Select All Valid" for this table.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Please select at least one valid row or click "Select All Valid" for this table.');
                }
                return;
            }
    
            let type = '';
            if (tableId === 'tenant-mapping-table') type = 'tenant';
            else if (tableId === 'payment-mapping-table') type = 'payment';
            else if (tableId === 'notice-mapping-table') type = 'notice';

            // Get count for confirmation message
            const selectedCount = allPages ? 'all valid records' : `${map.size} record${map.size !== 1 ? 's' : ''}`;
    
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm Import',
                html: `
                    <div class="text-start">
                        <p><strong>You are about to confirm and import ${selectedCount} for ${type} data.</strong></p>
                        <p class="text-muted mb-2">This action will:</p>
                        <ul class="text-muted">
                            <li>Move the selected data from temporary storage to the main database</li>
                            <li>Make the data live and available in the system</li>
                            <li>Cannot be easily undone</li>
                        </ul>
                        <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Please ensure the data is correct before proceeding.</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Yes, Import ${selectedCount}`,
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: {
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // User confirmed, proceed with the import
                    btn.setAttribute("data-kt-indicator", "on");

                    const payload = {
                        selected_ids: Object.keys(idBatchMap),
                        id_batch_map: idBatchMap,
                        select_all_pages: allPages ? 1 : 0,
                        table: tableId,
                        type: type
                    };

                    fetch(`/tenant_management/bulk-upload/confirm`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(res => res.json())
                    .then(json => {
                        btn.removeAttribute("data-kt-indicator");
                        if (json.status === 200) {
                            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                            const failed = (json.result && json.result.failed) || 0;
                            Swal.fire({
                                icon: failed ? 'warning' : 'success',
                                title: failed ? 'Partly imported' : 'Imported',
                                html: `
                                    <div class="text-start">
                                        <p><strong>${esc(json.message || 'Bulk action completed.')}</strong></p>
                                        ${failed ? '<p class="text-muted">Rows that were not imported stay on this page with the reason.</p>' : ''}
                                    </div>
                                `,
                                confirmButtonText: 'Continue',
                                confirmButtonColor: '#198754',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Import Failed',
                                html: `
                                    <div class="text-start">
                                        <p><strong>The import operation failed.</strong></p>
                                        <p class="text-danger">${String(json.message || 'Bulk action failed.').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))}</p>
                                        <p class="text-muted">Please check the data and try again.</p>
                                    </div>
                                `,
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        }
                    })
                    .catch(err => {
                        btn.removeAttribute("data-kt-indicator");
                        console.error('Request failed:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            html: `
                                <div class="text-start">
                                    <p><strong>Could not complete bulk action.</strong></p>
                                    <p class="text-danger">There was a network or server error.</p>
                                    <p class="text-muted">Please check your connection and try again.</p>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    });
                } else {
                    btn.removeAttribute("data-kt-indicator");
                }
                // If user cancels, do nothing (dialog closes automatically)
            });
        });
    });

    // Cancel button handlers
    const cancelMapping = [
        { btnId: 'tenant-cancel-import-btn', tableId: 'tenant-mapping-table', formSelector: '#bulk-action-form' },
        { btnId: 'payment-cancel-import-btn', tableId: 'payment-mapping-table', formSelector: '#payment-bulk-action-form' },
        { btnId: 'notice-cancel-import-btn', tableId: 'notice-mapping-table', formSelector: '#notice-bulk-action-form' },
    ];
    
    cancelMapping.forEach(entry => {
        const btn = document.getElementById(entry.btnId);
        if (!btn) return;
    
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const tableId = entry.tableId;
            const map = selectedIdsMap[tableId] || new Map();
            const allPages = selectAllPagesMap[tableId] || false;
            const tableEl = document.getElementById(tableId);
            const idBatchMap = getSelectedIdBatchMap(tableEl, tableId);
        
            // Check if there are any selections first
            if (!allPages && map.size < 1) {
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No selection',
                        text: 'Please select at least one row or click "Select All Valid" for this table.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Please select at least one row or click "Select All Valid" for this table.');
                }
                return;
            }
        
            let type = '';
            if (tableId === 'tenant-mapping-table') type = 'tenant';
            else if (tableId === 'payment-mapping-table') type = 'payment';
            else if (tableId === 'notice-mapping-table') type = 'notice';
        
            // Get count for confirmation message
            const selectedCount = allPages ? 'all records' : `${map.size} record${map.size !== 1 ? 's' : ''}`;
        
            // Show confirmation dialog for cancellation
            Swal.fire({
                title: 'Confirm Cancellation',
                html: `
                    <div class="text-start">
                        <p><strong>You are about to cancel and delete ${selectedCount} for ${type} data.</strong></p>
                        <p class="text-muted mb-2">This action will:</p>
                        <ul class="text-muted">
                            <li>Permanently delete the selected data from temporary storage</li>
                            <li>Remove all traces of this imported data</li>
                            <li>Cannot be undone - you'll need to re-upload the file</li>
                        </ul>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This will permanently delete the selected data!</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: `Yes, Delete ${selectedCount}`,
                cancelButtonText: 'Keep Data',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // User confirmed, proceed with the cancellation
                    btn.setAttribute("data-kt-indicator", "on");
        
                    const payload = {
                        selected_ids: Object.keys(idBatchMap),
                        id_batch_map: idBatchMap,
                        select_all_pages: allPages ? 1 : 0,
                        table: tableId,
                        type: type
                    };
        
                    fetch(`/tenant_management/bulk-upload/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(res => res.json())
                    .then(json => {
                        btn.removeAttribute("data-kt-indicator");
                        if (json.status === 200) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Data Deleted Successfully',
                                html: `
                                    <div class="text-start">
                                        <p><strong>${json.message || 'Bulk action canceled.'}</strong></p>
                                        <p class="text-muted">The selected ${type} data has been permanently removed from temporary storage.</p>
                                        <p class="text-info"><i class="fas fa-info-circle"></i> You can upload a new file if needed.</p>
                                    </div>
                                `,
                                confirmButtonText: 'Continue',
                                confirmButtonColor: '#198754',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Deletion Failed',
                                html: `
                                    <div class="text-start">
                                        <p><strong>The deletion operation failed.</strong></p>
                                        <p class="text-danger">${json.message || 'Bulk cancel action failed.'}</p>
                                        <p class="text-muted">Please try again or contact support if the problem persists.</p>
                                    </div>
                                `,
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        }
                    })
                    .catch(err => {
                        btn.removeAttribute("data-kt-indicator");
                        console.error('Cancel request failed:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            html: `
                                <div class="text-start">
                                    <p><strong>Could not complete bulk cancel action.</strong></p>
                                    <p class="text-danger">There was a network or server error.</p>
                                    <p class="text-muted">Please check your connection and try again.</p>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    });
                } else {
                    btn.removeAttribute("data-kt-indicator");
                }
                // If user cancels, do nothing (dialog closes automatically)
            });
        });
    });

    // Debug exposure
    window.__tenantBulkSelectedIdsMap = selectedIdsMap;
    window.__tenantBulkSelectAllPagesMap = selectAllPagesMap;
    
    console.log('Tenant bulk upload script initialized successfully');
});