"use strict";

// Account settings → Storage: how much file storage the business uses (informational; Erty does not cap storage).

$(document).ready(function () {
    if (!$('#kt_storage_management').length) return;
    loadStorageOverview();
    $('#btnRecalculate').on('click', recalculateStorage);
});

function loadStorageOverview() {
    $.ajax({
        url: '/storage/overview',
        type: 'GET',
        success: function (response) {
            if (response.status === 200 && response.data) {
                $('#storageUsedText').text(response.data.used_formatted);
                renderBreakdown(response.data.breakdown);
            }
        },
        error: function () {
            $('#storageUsedText').text('Could not load storage usage');
        }
    });
}

function renderBreakdown(breakdown) {
    var container = $('#storageBreakdownContainer');
    container.empty();

    if (!breakdown || breakdown.length === 0) {
        container.html('<div class="text-center text-muted py-5">No storage data available</div>');
        return;
    }

    breakdown.forEach(function (item) {
        var col = $('<div class="col"></div>');
        var card = $('<div class="border border-dashed border-gray-300 rounded py-4 px-5 text-center"></div>');
        card.append('<i class="ki-duotone ' + item.icon + ' fs-2x text-' + item.color + ' mb-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>');
        card.append($('<div class="fs-5 fw-bolder text-gray-800"></div>').text(item.size_formatted));
        card.append($('<div class="text-muted fs-7 fw-semibold"></div>').text(item.label));
        container.append(col.append(card));
    });
}

function recalculateStorage() {
    var $btn = $('#btnRecalculate');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Syncing...');

    $.ajax({
        url: '/storage/recalculate',
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function (response) {
            if (response.status === 200) {
                Swal.fire({ text: 'Storage usage has been recalculated: ' + response.storage_used_formatted, icon: 'success', confirmButtonText: 'OK' });
                loadStorageOverview();
            }
        },
        error: function () {
            Swal.fire({ text: 'Failed to recalculate storage usage. Please try again.', icon: 'error', confirmButtonText: 'OK' });
        },
        complete: function () {
            $btn.prop('disabled', false).html('<i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i> Sync');
        }
    });
}
