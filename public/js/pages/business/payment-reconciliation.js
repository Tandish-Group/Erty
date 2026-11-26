/**
 * Payment reconciliation: attach uploaded proof of payment (receipts) to recorded tenant payments.
 * Step 1 picks a receipt, step 2 picks the payment, the match bar confirms the pair.
 * Depends on window.RECONCILIATION_URLS and window.CSRF_TOKEN (layouts/scripts/scripts.blade.php).
 */
(function ($) {
    'use strict';

    const URLS = window.RECONCILIATION_URLS || {};
    const CSRF = window.CSRF_TOKEN || $('meta[name="csrf-token"]').attr('content');

    const state = { receipt: null, payment: null, page: 1, filters: {} };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function toast(message, icon) {
        Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }

    function errorMessage(xhr, fallback) {
        const errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) return Object.values(errors).flat().join(' ');
        return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
    }

    /* ── Selection and the match bar ─────────────────────────────────── */

    function renderMatchBar() {
        const bar = document.getElementById('reconciliation-action-bar');
        bar.hidden = !state.receipt && !state.payment;
        $('#selected-receipt-name').text(state.receipt ? state.receipt.name : 'Choose a receipt').toggleClass('is-missing', !state.receipt);
        $('#selected-payment-ref').text(state.payment ? state.payment.label : 'Choose a payment').toggleClass('is-missing', !state.payment);
        $('#btn-match').prop('disabled', !(state.receipt && state.payment));
    }

    function selectReceipt(id, name) {
        state.receipt = state.receipt && state.receipt.id === id ? null : { id: id, name: name };
        $('.recon-file').each(function () {
            const on = !!state.receipt && $(this).data('id') === state.receipt.id;
            $(this).toggleClass('is-selected', on).find('.recon-file-pick').attr('aria-pressed', on);
        });
        renderMatchBar();
    }

    function selectPayment(id, label) {
        state.payment = state.payment && state.payment.id === id ? null : { id: id, label: label };
        $('.recon-row').each(function () {
            const on = !!state.payment && $(this).data('id') === state.payment.id;
            $(this).toggleClass('is-selected', on).attr('aria-selected', on);
        });
        renderMatchBar();
    }

    function clearSelection() {
        state.receipt = null;
        state.payment = null;
        $('.recon-file').removeClass('is-selected').find('.recon-file-pick').attr('aria-pressed', false);
        $('.recon-row').removeClass('is-selected').attr('aria-selected', false);
        renderMatchBar();
    }

    /* ── Step 1: receipts ─────────────────────────────────────────────── */

    function fileThumb(r) {
        if (r.is_image) return '<span class="recon-file-thumb"><img src="' + esc(r.url) + '" alt=""></span>';
        const icon = r.mime_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file';
        return '<span class="recon-file-thumb"><i class="fas ' + icon + '" aria-hidden="true"></i></span>';
    }

    function loadReceipts() {
        const list = $('#staged-receipts-list');
        if (!list.length) return;
        $.get(URLS.staged_receipts)
            .done(function (res) {
                const rows = res.data || [];
                $('#staged-count-badge').text(rows.length);
                $('#stat-staged-receipts').text(rows.length);
                if (state.receipt && !rows.some((r) => r.id === state.receipt.id)) state.receipt = null;

                if (!rows.length) {
                    list.html('<li class="recon-empty"><i class="fas fa-inbox" aria-hidden="true"></i>No receipts waiting. Upload the proof you received from tenants above.</li>');
                    renderMatchBar();
                    return;
                }
                list.html(rows.map(function (r) {
                    const on = state.receipt && state.receipt.id === r.id;
                    return '<li class="recon-file' + (on ? ' is-selected' : '') + '" data-id="' + r.id + '">'
                        + '<button type="button" class="recon-file-pick" aria-pressed="' + !!on + '" data-name="' + esc(r.original_filename) + '">'
                        + fileThumb(r)
                        + '<span class="min-w-0"><span class="recon-file-name" title="' + esc(r.original_filename) + '">' + esc(r.original_filename) + '</span>'
                        + '<span class="recon-file-meta">' + esc(r.file_size) + ' · ' + esc(r.uploaded_at) + '</span></span>'
                        + '</button>'
                        + '<span class="recon-file-actions">'
                        + '<a href="' + esc(r.url) + '" target="_blank" rel="noopener" class="btn btn-icon btn-sm btn-light" title="Open ' + esc(r.original_filename) + '"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">Open</span></a>'
                        + '<button type="button" class="btn btn-icon btn-sm btn-light btn-delete-staged" data-id="' + r.id + '" title="Delete ' + esc(r.original_filename) + '"><i class="fas fa-trash" aria-hidden="true"></i><span class="visually-hidden">Delete</span></button>'
                        + '</span></li>';
                }).join(''));
                renderMatchBar();
            })
            .fail(function () { list.html('<li class="recon-empty">The receipts could not be loaded. Refresh the page to try again.</li>'); });
    }

    const uploadQueue = [];
    let uploading = false;

    function queueUploads(files) {
        $.each(files, function (_, file) { uploadQueue.push(file); });
        if (!uploading) uploadNext();
    }

    function uploadNext() {
        const file = uploadQueue.shift();
        const area = $('#upload-progress-area');
        if (!file) {
            uploading = false;
            area.addClass('d-none');
            loadReceipts();
            return;
        }
        uploading = true;
        const data = new FormData();
        data.append('receipt', file);
        data.append('_token', CSRF);
        $('#upload-progress-label').text('Uploading ' + file.name + (uploadQueue.length ? ' (' + uploadQueue.length + ' more)' : ''));
        $('#upload-progress-bar').css('width', '0%');
        $('#upload-progress-pct').text('0%');
        area.removeClass('d-none');

        $.ajax({
            url: URLS.upload, type: 'POST', data: data, processData: false, contentType: false,
            xhr: function () {
                const xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) return;
                    const pct = Math.round((e.loaded / e.total) * 100);
                    $('#upload-progress-bar').css('width', pct + '%');
                    $('#upload-progress-pct').text(pct + '%');
                });
                return xhr;
            },
        })
            .done(function () { toast(file.name + ' uploaded.'); })
            .fail(function (xhr) { toast(file.name + ': ' + errorMessage(xhr, 'upload failed.'), 'error'); })
            .always(uploadNext);
    }

    /* ── Step 2: payments ─────────────────────────────────────────────── */

    function loadPayments(page) {
        state.page = page || 1;
        const tbody = $('#unmatched-payments-tbody');
        tbody.html('<tr><td colspan="6" class="recon-loading"><span class="spinner-border spinner-border-sm" role="status"></span> Loading payments…</td></tr>');

        $.get(URLS.unmatched_payments, $.extend({ page: state.page }, state.filters))
            .done(function (res) {
                const rows = res.data || [];
                const meta = res.meta || { total: rows.length, page: 1, last_page: 1 };
                const filtered = Object.values(state.filters).some(Boolean);
                if (!filtered) $('#stat-unmatched-payments').text(meta.total.toLocaleString());

                if (!rows.length) {
                    tbody.html('<tr><td colspan="6" class="recon-empty"><i class="fas fa-check-circle" aria-hidden="true"></i>'
                        + (filtered ? 'No payments match these filters.' : 'Every recorded payment has proof attached.') + '</td></tr>');
                    $('#payments-pager').addClass('d-none');
                    return;
                }

                tbody.html(rows.map(function (p) {
                    const on = state.payment && state.payment.id === p.id;
                    const label = p.tenant_name + ' · ' + p.amount + ' · ' + p.payment_date;
                    return '<tr class="recon-row' + (on ? ' is-selected' : '') + '" data-id="' + p.id + '" data-label="' + esc(label) + '" tabindex="0" aria-selected="' + !!on + '">'
                        + '<td><span class="recon-radio" aria-hidden="true"></span></td>'
                        + '<td class="text-nowrap">' + esc(p.payment_date) + '</td>'
                        + '<td><strong>' + esc(p.tenant_name) + '</strong><small>' + esc(p.tenancy_ref) + '</small></td>'
                        + '<td>' + (p.invoice_number ? esc(p.invoice_number) : '<span class="text-muted">Not linked</span>') + '</td>'
                        + '<td>' + esc(p.payment_method) + (p.transaction_reference ? '<small>' + esc(p.transaction_reference) + '</small>' : '') + '</td>'
                        + '<td class="text-end recon-amount">' + esc(p.amount) + '</td>'
                        + '</tr>';
                }).join(''));

                $('#payments-pager').toggleClass('d-none', meta.last_page <= 1);
                $('#payments-range').text(meta.from + '–' + meta.to + ' of ' + meta.total.toLocaleString());
                $('#payments-prev').prop('disabled', meta.page <= 1);
                $('#payments-next').prop('disabled', meta.page >= meta.last_page);
            })
            .fail(function (xhr) {
                tbody.html('<tr><td colspan="6" class="recon-empty">' + esc(errorMessage(xhr, 'The payments could not be loaded.')) + '</td></tr>');
            });
    }

    /* ── Recent matches ───────────────────────────────────────────────── */

    function loadRecent() {
        $.get(URLS.recent_matches)
            .done(function (res) {
                const rows = res.data || [];
                $('#stat-recent-matches').text(rows.length);
                const tbody = $('#recent-matches-tbody');
                if (!rows.length) {
                    tbody.html('<tr><td colspan="7" class="recon-empty">No receipts matched in the last 30 days.</td></tr>');
                    return;
                }
                tbody.html(rows.map(function (r) {
                    return '<tr>'
                        + '<td class="text-nowrap">' + esc(r.matched_at) + '</td>'
                        + '<td><strong>' + esc(r.original_filename) + '</strong></td>'
                        + '<td>' + esc(r.tenancy_ref) + '</td>'
                        + '<td class="text-nowrap">' + esc(r.payment_date) + '</td>'
                        + '<td>' + esc(r.payment_method) + (r.transaction_reference && r.transaction_reference !== '—' ? '<small>' + esc(r.transaction_reference) + '</small>' : '') + '</td>'
                        + '<td class="text-end recon-amount">' + esc(r.amount) + '</td>'
                        + '<td class="text-end"><button type="button" class="btn btn-sm btn-light btn-unmatch" data-payment-id="' + r.payment_id + '">Undo match</button></td>'
                        + '</tr>';
                }).join(''));
            })
            .fail(function () { $('#recent-matches-tbody').html('<tr><td colspan="7" class="recon-empty">Recent matches could not be loaded.</td></tr>'); });
    }

    /* ── Actions ──────────────────────────────────────────────────────── */

    function doMatch() {
        if (!state.receipt || !state.payment) return;
        Swal.fire({
            title: 'Match this receipt?',
            html: '<strong>' + esc(state.receipt.name) + '</strong><br>will be attached to<br><strong>' + esc(state.payment.label) + '</strong>',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Match', cancelButtonText: 'Cancel', confirmButtonColor: '#087d84',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.post(URLS.match, { _token: CSRF, staged_receipt_id: state.receipt.id, payment_id: state.payment.id })
                .done(function () {
                    toast('Receipt attached to the payment.');
                    clearSelection();
                    loadReceipts();
                    loadPayments(state.page);
                    loadRecent();
                })
                .fail(function (xhr) { toast(errorMessage(xhr, 'The match failed.'), 'error'); });
        });
    }

    function doUnmatch(paymentId) {
        Swal.fire({
            title: 'Undo this match?',
            text: 'The receipt is detached from the payment and goes back to the waiting list.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Undo match', cancelButtonText: 'Cancel', confirmButtonColor: '#c2453b',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.post(URLS.unmatch.replace('__ID__', paymentId), { _token: CSRF })
                .done(function () {
                    toast('Match undone.');
                    loadReceipts();
                    loadPayments(state.page);
                    loadRecent();
                })
                .fail(function (xhr) { toast(errorMessage(xhr, 'The match could not be undone.'), 'error'); });
        });
    }

    function deleteReceipt(id, name) {
        Swal.fire({
            title: 'Delete this receipt?', text: name + ' will be deleted permanently.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#c2453b',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: URLS.delete_receipt.replace('__ID__', id), type: 'DELETE', data: { _token: CSRF } })
                .done(function () {
                    toast('Receipt deleted.');
                    if (state.receipt && state.receipt.id === id) state.receipt = null;
                    loadReceipts();
                })
                .fail(function (xhr) { toast(errorMessage(xhr, 'The receipt could not be deleted.'), 'error'); });
        });
    }

    function readFilters() {
        state.filters = {
            tenant_search: $.trim($('#filter-tenant-search').val() || ''),
            date_from: $('#filter-date-from').val() || '',
            date_to: $('#filter-date-to').val() || '',
        };
        $('#btn-reset-filters').toggleClass('d-none', !Object.values(state.filters).some(Boolean));
    }

    /* ── Wiring ───────────────────────────────────────────────────────── */

    $(function () {
        if (!document.getElementById('unmatched-payments-table')) return;

        loadReceipts();
        loadPayments(1);
        loadRecent();
        renderMatchBar();

        const dropzone = $('#receipt-dropzone');
        const input = $('#receipt-file-input');
        dropzone.on('click', function () { input.trigger('click'); });
        dropzone.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.trigger('click'); }
        });
        dropzone.on('dragover', function (e) { e.preventDefault(); dropzone.addClass('is-dragover'); });
        dropzone.on('dragleave dragend', function () { dropzone.removeClass('is-dragover'); });
        dropzone.on('drop', function (e) {
            e.preventDefault();
            dropzone.removeClass('is-dragover');
            queueUploads(e.originalEvent.dataTransfer.files);
        });
        input.on('click', function (e) { e.stopPropagation(); });
        input.on('change', function () { queueUploads(this.files); $(this).val(''); });

        $(document).on('click', '.recon-file-pick', function () {
            selectReceipt($(this).closest('.recon-file').data('id'), $(this).data('name'));
        });
        $(document).on('click', '.btn-delete-staged', function () {
            const item = $(this).closest('.recon-file');
            deleteReceipt($(this).data('id'), item.find('.recon-file-pick').data('name'));
        });

        $(document).on('click', '.recon-row', function () { selectPayment($(this).data('id'), $(this).data('label')); });
        $(document).on('keydown', '.recon-row', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectPayment($(this).data('id'), $(this).data('label')); }
        });

        $('#btn-match').on('click', doMatch);
        $('#btn-clear-selection').on('click', clearSelection);
        $(document).on('click', '.btn-unmatch', function () { doUnmatch($(this).data('payment-id')); });

        $('#recon-filters').on('submit', function (e) { e.preventDefault(); readFilters(); loadPayments(1); });
        $('#btn-reset-filters').on('click', function () {
            $('#recon-filters')[0].reset();
            readFilters();
            loadPayments(1);
        });
        $('#payments-prev').on('click', function () { loadPayments(state.page - 1); });
        $('#payments-next').on('click', function () { loadPayments(state.page + 1); });
    });
})(jQuery);
