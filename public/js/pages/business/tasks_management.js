$(document).ready(function() {
    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    // Function to format expanded row content
    function formatTaskDetails(tasks) {
        let html = '<div class="crm-task-details">';
        html += '<div class="table-responsive">';
        html += '<table class="table table-row-bordered table-row-gray-200 align-middle gs-2 gy-2">';
        html += '<thead><tr class="fw-bold text-muted bg-light">';
        html += '<th class="min-w-300px">Task</th>';
        html += '<th class="min-w-100px">Phase</th>';
        html += '<th class="min-w-100px">Status</th>';
        html += '<th class="min-w-120px">Due Date</th>';
        html += '<th class="min-w-100px text-end">Actions</th>';
        html += '</tr></thead><tbody>';
        
        tasks.forEach(function(task) {
            let statusBadge = `<span class="badge ${task.status_badge} badge-sm">${task.status_label}</span>`;
            let requiredBadge = task.is_required ? '<span class="badge badge-light-danger badge-sm ms-1">Required</span>' : '';
            let overdueBadge = task.is_overdue ? '<span class="badge badge-danger badge-sm ms-1">Overdue</span>' : '';
            let phaseBadge = `<span class="badge badge-light-primary badge-sm">${task.phase_label}</span>`;
            
            html += '<tr>';
            
            // Task title with badges
            html += '<td>';
            html += `<div class="d-flex flex-column">`;
            html += `<span class="text-dark fw-bold fs-6 mb-1">${escapeHtml(task.title)}</span>`;
            if (task.description) {
                html += `<span class="text-muted fs-7">${escapeHtml(task.description)}</span>`;
            }
            html += '</div>';
            html += '</td>';
            
            // Phase
            html += `<td>${phaseBadge}</td>`;
            
            // Status
            html += '<td>';
            html += statusBadge + requiredBadge + overdueBadge;
            html += '</td>';
            
            // Due date
            html += '<td>';
            if (task.due_date) {
                let dueDateClass = task.is_overdue ? 'text-danger fw-bold' : 'text-muted';
                html += `<span class="${dueDateClass}">${escapeHtml(task.due_date)}</span>`;
            } else {
                html += '<span class="text-muted">No deadline</span>';
            }
            html += '</td>';
            
            // Actions
            html += '<td class="text-end">';
            if (task.status === 'pending') {
                html += `<button type="button" data-id="${Number(task.id)}" class="btn btn-sm btn-light start-task me-1">Start</button>`;
            } else if (task.status === 'in_progress') {
                html += `<button type="button" data-id="${Number(task.id)}" class="btn btn-sm btn-primary complete-task me-1">Complete</button>`;
            }
            html += `<a href="/agents/tasks/${Number(task.id)}" class="btn btn-sm btn-light crm-task-view" title="View task">View</a>`;
            html += '</td>';
            
            html += '</tr>';
        });
        
        html += '</tbody></table></div></div>';
        return html;
    }

    var table = $('#tasks-table').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rt<"crm-task-dt-bottom"ip>',
        ajax: {
            url: '/agents/tasks/data',
            data: function(d) {
                d.status = $('#status-filter').val();
                d.phase = $('#phase-filter').val();
                if ($('#scope-filter').length) d.scope = $('#scope-filter').val();
                if ($('#agent-filter').length) d.agent_id = $('#agent-filter').val();
            },
            dataSrc: function(response) {
                const metrics = response.metrics || {};
                $('#task-metric-total').text(metrics.total_tasks ?? 0);
                $('#task-metric-pending').text(metrics.pending_tasks ?? 0);
                $('#task-metric-active').text(metrics.in_progress_tasks ?? 0);
                $('#task-metric-overdue').text(metrics.overdue_tasks ?? 0);
                return response.data || [];
            }
        },
        columns: [
            {
                className: 'dt-control',
                orderable: false,
                data: null,
                defaultContent: '<i class="las la-plus-circle"></i>'
            },
            { 
                data: 'lead', 
                name: 'lead.name',
                render: function(data, type, row) {
                    let leadDisplay = '';
                    let leadId = row.lead.encrypted_lead_id;
                    
                    if (row.lead.lead_type === 'Business') {
                        // Business lead: show company name or fallback to name
                        if (row.lead.company_name && row.lead.company_name.trim()) {
                            leadDisplay = `<strong>${escapeHtml(row.lead.company_name)}</strong>`;
                        } else if (row.lead.name && row.lead.name !== 'Unnamed Lead') {
                            leadDisplay = `<strong>${escapeHtml(row.lead.name)}</strong>`;
                        } else {
                            leadDisplay = '<strong class="text-muted">Unnamed Business Lead</strong>';
                        }
                        
                        // Show representative name if available
                        if (row.lead.representative_name && row.lead.representative_name.trim()) {
                            leadDisplay += `<br><span class="text-muted fs-7">Rep: ${escapeHtml(row.lead.representative_name)}</span>`;
                        }
                    } else {
                        // Individual lead: show name or fallback
                        if (row.lead.name && row.lead.name.trim() && row.lead.name !== 'Unnamed Lead') {
                            leadDisplay = `<strong>${escapeHtml(row.lead.name)}</strong>`;
                        } else if (row.lead.first_name || row.lead.last_name) {
                            leadDisplay = `<strong>${escapeHtml((row.lead.first_name || '') + ' ' + (row.lead.last_name || ''))}</strong>`;
                        } else {
                            leadDisplay = '<strong class="text-muted">Unnamed Lead</strong>';
                        }
                    }
                    
                    // Show email if available
                    if (row.lead.email && row.lead.email.trim()) {
                        leadDisplay += `<br><span class="text-muted fs-7"><i class="las la-envelope"></i> ${escapeHtml(row.lead.email)}</span>`;
                    }
                    
                    // Show phone if available
                    if (row.lead.phone && row.lead.phone.trim()) {
                        leadDisplay += `<br><span class="text-muted fs-7"><i class="las la-phone"></i> ${escapeHtml(row.lead.phone)}</span>`;
                    }
            
                    return '<div class="d-flex align-items-center">' +
                           '<div class="d-flex justify-content-start flex-column">' +
                           '<a href="/customer_relationship/lead_management/lead/' + leadId + '" class="text-dark fw-bold text-hover-primary fs-6">' + 
                           leadDisplay + 
                           '</a>' +
                           '</div></div>';
                }
            },
            {
                data: 'current_phase_label',
                name: 'current_phase',
                render: function(data, type, row) {
                    return `<span class="badge badge-light-info fs-7 fw-bold">${escapeHtml(data)}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `<div class="text-center">`+
                           `<span class="text-dark fw-bold fs-5">${row.completed_tasks}</span>`+
                           `<span class="text-muted fw-semibold fs-7"> / ${row.total_tasks}</span>`+
                           `</div>`;
                }
            },
            {
                data: 'progress_percentage',
                name: 'progress',
                render: function(data, type, row) {
                    let progress = Math.max(0, Math.min(100, Number(data) || 0));
                    return `<div class="d-flex align-items-center">`+
                           `<div class="progress h-8px w-100px me-3">`+
                           `<div class="progress-bar" role="progressbar" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100" style="width: ${progress}%"></div>`+
                           `</div>`+
                           `<span class="text-dark fw-bold fs-7">${progress}%</span>`+
                           `</div>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    let badges = '';
                    
                    // Overdue badge
                    if (row.tasks.some(t => t.is_overdue)) {
                        let overdueCount = row.tasks.filter(t => t.is_overdue).length;
                        badges += `<span class="badge badge-danger badge-sm me-1">${overdueCount} Overdue</span>`;
                    }
                    
                    // Pending badge
                    let pendingCount = row.tasks.filter(t => t.status === 'pending').length;
                    if (pendingCount > 0) {
                        badges += `<span class="badge badge-light-warning badge-sm me-1">${pendingCount} Pending</span>`;
                    }
                    
                    // In Progress badge
                    let inProgressCount = row.tasks.filter(t => t.status === 'in_progress').length;
                    if (inProgressCount > 0) {
                        badges += `<span class="badge badge-light-info badge-sm me-1">${inProgressCount} Active</span>`;
                    }

                    const blockedCount = row.tasks.filter(t => t.status === 'blocked').length;
                    if (blockedCount > 0) badges += `<span class="badge badge-light-danger badge-sm me-1">${blockedCount} Blocked</span>`;

                    const allComplete = row.tasks.length > 0 && row.tasks.every(t => ['completed', 'skipped', 'cancelled'].includes(t.status));
                    return badges || (allComplete
                        ? '<span class="badge badge-light-success badge-sm">All complete</span>'
                        : '<span class="badge badge-light-secondary badge-sm">No active tasks</span>');
                }
            }
        ],
        order: [],
        pageLength: 10,
        language: {
            emptyTable: "No tasks found"
        }
    });

    // Add event listener for opening and closing details
    $('#tasks-table tbody').on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        var icon = $(this).find('i');

        if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
            icon.removeClass('la-minus-circle').addClass('la-plus-circle');
        } else {
            // Open this row
            row.child(formatTaskDetails(row.data().tasks)).show();
            tr.addClass('shown');
            icon.removeClass('la-plus-circle').addClass('la-minus-circle');
        }
    });

    // Filter handlers
    $('#status-filter, #phase-filter').on('change', function() {
        $('.crm-task-metric').removeClass('is-active');
        table.ajax.reload();
    });

    // Switching between my tasks and every agent's tasks reloads the page so the heading and counts match.
    $('#scope-filter').on('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('scope', this.value);
        url.searchParams.delete('agent_id');
        window.location.assign(url.toString());
    });
    // One agent's tasks: reload so the counts and phase totals match.
    $('#agent-filter').on('change', function() {
        const url = new URL(window.location.href);
        if (this.value) url.searchParams.set('agent_id', this.value); else url.searchParams.delete('agent_id');
        window.location.assign(url.toString());
    });

    $('#task-search').on('input', function() {
        table.search(this.value).draw();
    });

    $('.crm-task-metric').on('click', function() {
        const status = String($(this).data('task-status') || '');
        $('#status-filter').val(status);
        $('.crm-task-metric').removeClass('is-active');
        $(this).addClass('is-active');
        table.ajax.reload();
    });

    // Start task handler
    $(document).on('click', '.start-task', function() {
        var taskId = $(this).data('id');
        $.ajax({
            url: '/agents/tasks/' + taskId + '/start',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                toastr.success('Task started successfully');
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error('Failed to start task');
            }
        });
    });

    // Complete task handler
    $(document).on('click', '.complete-task', function() {
        var taskId = $(this).data('id');
        Swal.fire({
            title: 'Complete Task',
            input: 'textarea',
            inputLabel: 'Completion Notes (optional)',
            inputPlaceholder: 'Enter any notes about task completion...',
            showCancelButton: true,
            confirmButtonText: 'Complete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/agents/tasks/' + taskId + '/complete',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        completion_notes: result.value
                    },
                    success: function(response) {
                        toastr.success('Task completed successfully');
                        table.ajax.reload();
                    },
                    error: function(xhr) {
                        toastr.error('Failed to complete task');
                    }
                });
            }
        });
    });
});
