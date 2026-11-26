"use strict";

var documentsTable;

// Suppress default DataTables error alerts
$.fn.dataTable.ext.errMode = 'none';

$(document).ready(function () {
    // Initialize DataTable
    documentsTable = $('#documentsTable').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        responsive: true,
        ajax: {
            url: '/customer_relationship/document-center/data',
            type: 'GET',
            data: function (d) {
                d.source_type = $('#sourceFilter').val();
                d.category = $('#categoryFilter').val();
                d.date_from = $('#dateFrom').val();
                d.date_to = $('#dateTo').val();
            },
            dataSrc: function (json) {
                return json.data || [];
            },
            error: function (xhr, error, thrown) {
                console.error('Document Center AJAX error:', error, thrown);
                Swal.fire({
                    text: 'Failed to load documents. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        columns: [
            {
                data: 'name',
                render: function (data, type, row) {
                    var icon = getFileIcon(row.mime_type);
                    var statusBadge = row.exists
                        ? ''
                        : '<span class="badge badge-light-danger ms-2 fs-9">Missing</span>';

                    return '<div class="d-flex align-items-center">' +
                        '<div class="symbol symbol-35px me-3">' +
                            '<span class="symbol-label bg-light-' + icon.color + '">' +
                                '<i class="ki-duotone ' + icon.icon + ' fs-2 text-' + icon.color + '">' +
                                    '<span class="path1"></span><span class="path2"></span>' +
                                '</i>' +
                            '</span>' +
                        '</div>' +
                        '<div class="d-flex flex-column">' +
                            '<span class="text-gray-800 fw-bold text-truncate" style="max-width: 250px;" title="' + (data || '') + '">' + (data || 'Unknown') + '</span>' +
                            '<span class="text-muted fs-8">' + (row.related_ref || '') + statusBadge + '</span>' +
                        '</div>' +
                    '</div>';
                }
            },
            {
                data: 'source',
                render: function (data, type, row) {
                    var badgeClass = getSourceBadgeClass(row.source_type);
                    return '<span class="badge badge-light-' + badgeClass + ' fw-bold">' + (data || 'Unknown') + '</span>';
                }
            },
            {
                data: 'category',
                render: function (data) {
                    return '<span class="text-gray-700 fw-semibold">' + (data || 'N/A') + '</span>';
                }
            },
            {
                data: 'related_to',
                render: function (data) {
                    return '<span class="text-gray-800">' + (data || '-') + '</span>';
                }
            },
            {
                data: 'file_size_formatted',
                render: function (data) {
                    return '<span class="text-muted">' + (data || 'N/A') + '</span>';
                }
            },
            {
                data: 'uploaded_at_formatted',
                render: function (data) {
                    return '<span class="text-muted">' + (data || 'N/A') + '</span>';
                }
            },
            {
                data: null,
                className: 'text-end',
                orderable: false,
                render: function (data, type, row) {
                    var actions = '<div class="d-flex justify-content-end gap-1">';

                    // Preview button
                    actions += '<button type="button" class="btn btn-sm btn-icon btn-light-primary" ' +
                        'onclick=\'previewDocument(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\' ' +
                        'title="Preview">' +
                        '<i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>' +
                        '</button>';

                    // Download button
                    if (row.exists && row.url) {
                        actions += '<a href="' + row.url + '" class="btn btn-sm btn-icon btn-light-success" ' +
                            'target="_blank" download title="Download">' +
                            '<i class="ki-duotone ki-cloud-download fs-4"><span class="path1"></span><span class="path2"></span></i>' +
                            '</a>';
                    } else {
                        actions += '<button class="btn btn-sm btn-icon btn-light" disabled title="File not available">' +
                            '<i class="ki-duotone ki-cloud-download fs-4 text-muted"><span class="path1"></span><span class="path2"></span></i>' +
                            '</button>';
                    }

                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[5, 'desc']], // Sort by date descending
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: '<div class="text-center py-10">' +
                '<i class="ki-duotone ki-folder fs-5x text-muted mb-5"><span class="path1"></span><span class="path2"></span></i>' +
                '<p class="text-muted fs-5 fw-semibold">No documents found</p>' +
                '<p class="text-muted fs-7">Documents from tenant onboarding, leases and agents will appear here</p>' +
            '</div>',
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>'
        }
    });

    // Load stats via AJAX to stay in sync with actual data
    loadStats();

    // Filter change handlers
    $('#sourceFilter, #categoryFilter').on('change', function () {
        documentsTable.ajax.reload();
    });

    $('#dateFrom, #dateTo').on('change', function () {
        documentsTable.ajax.reload();
    });

    // Reset filters
    $('#resetFilters').on('click', function () {
        $('#sourceFilter').val('all');
        $('#categoryFilter').val('all');
        $('#dateFrom').val('');
        $('#dateTo').val('');
        documentsTable.ajax.reload();
    });
});

/**
 * Filter documents by source (called from stat cards).
 */
function filterBySource(sourceType) {
    event.preventDefault();
    if (sourceType === 'all') {
        $('#sourceFilter').val('all');
    } else {
        $('#sourceFilter').val(sourceType);
    }
    documentsTable.ajax.reload();

    // Scroll to table
    $('html, body').animate({
        scrollTop: $('#documentsTable').closest('.card').offset().top - 80
    }, 300);
}

/**
 * Preview a document in the modal.
 */
function previewDocument(doc) {
    $('#previewModalTitle').text(doc.name || 'Document Preview');
    $('#previewSource').text(doc.source || '-');
    $('#previewCategory').text(doc.category || '-');
    $('#previewRelatedTo').text(doc.related_to || '-');
    $('#previewSize').text(doc.file_size_formatted || '-');
    $('#previewDate').text(doc.uploaded_at_formatted || '-');
    $('#previewType').text(doc.mime_type || '-');

    var previewHtml = '';

    if (!doc.exists || !doc.url) {
        previewHtml = '<div class="py-10">' +
            '<i class="ki-duotone ki-information-4 fs-5x text-warning mb-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>' +
            '<p class="text-muted fs-5">File is not available for preview</p>' +
        '</div>';
        $('#previewDownloadBtn').addClass('disabled').attr('href', '#');
    } else if (doc.mime_type && doc.mime_type.startsWith('image/')) {
        previewHtml = '<img src="' + doc.url + '" class="img-fluid rounded" style="max-height: 500px;" alt="' + doc.name + '">';
        $('#previewDownloadBtn').removeClass('disabled').attr('href', doc.url);
    } else if (doc.mime_type === 'application/pdf') {
        previewHtml = '<iframe src="' + doc.url + '" style="width: 100%; height: 500px; border: none;" title="PDF Preview"></iframe>';
        $('#previewDownloadBtn').removeClass('disabled').attr('href', doc.url);
    } else {
        previewHtml = '<div class="py-10">' +
            '<i class="ki-duotone ki-file fs-5x text-muted mb-5"><span class="path1"></span><span class="path2"></span></i>' +
            '<p class="text-muted fs-5">Preview not available for this file type</p>' +
            '<p class="text-muted fs-7">' + (doc.mime_type || 'Unknown type') + '</p>' +
        '</div>';
        $('#previewDownloadBtn').removeClass('disabled').attr('href', doc.url);
    }

    $('#previewContent').html(previewHtml);
    $('#documentPreviewModal').modal('show');
}

/**
 * Load stats from the server (uses same fetcher logic as DataTable).
 */
function loadStats() {
    $.ajax({
        url: '/customer_relationship/document-center/stats',
        type: 'GET',
        success: function (response) {
            if (response.status === 200 && response.stats) {
                var s = response.stats;
                $('#stat-total').text(s.total);
                $('#stat-tenant').text(s.tenant_onboarding || 0);
                $('#stat-legal').text(s.tenant_legal || 0);
                $('#stat-agent').text(s.agent);
            }
        },
        error: function () {
            $('#stat-total, #stat-tenant, #stat-legal, #stat-agent').text('--');
        }
    });
}

/**
 * Get icon and color based on MIME type.
 */
function getFileIcon(mimeType) {
    if (!mimeType) return { icon: 'ki-file', color: 'gray-500' };

    if (mimeType.includes('pdf')) return { icon: 'ki-file-added', color: 'danger' };
    if (mimeType.includes('image')) return { icon: 'ki-picture', color: 'info' };
    if (mimeType.includes('word') || mimeType.includes('document')) return { icon: 'ki-document', color: 'primary' };
    if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) return { icon: 'ki-chart-simple-2', color: 'success' };
    if (mimeType.includes('text')) return { icon: 'ki-notepad', color: 'warning' };

    return { icon: 'ki-file', color: 'gray-600' };
}

/**
 * Get badge class based on source type.
 */
function getSourceBadgeClass(sourceType) {
    switch (sourceType) {
        case 'tenant_onboarding': return 'success';
        case 'tenant_legal': return 'primary';
        case 'agent': return 'info';
        default: return 'secondary';
    }
}
