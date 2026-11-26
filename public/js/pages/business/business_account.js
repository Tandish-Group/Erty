// ─────────────────────────────────────────────────────────────────────────────
// Payment Methods Management
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

    var pmModal     = document.getElementById('kt_modal_payment_method');
    var pmBootstrap = pmModal ? new bootstrap.Modal(pmModal) : null;

    // Open modal for new method
    ['btn_add_payment_method', 'btn_add_pm_empty'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', function () {
                openPmModal(null);
            });
        }
    });

    // Method type change → show/hide relevant field groups (Select2-compatible)
    $('#pm_method_type').on('change', function () {
        switchPmFields($(this).val() || '');
    });

    // Save button
    var pmSaveBtn = document.getElementById('pm_save_btn');
    if (pmSaveBtn) {
        pmSaveBtn.addEventListener('click', function () {
            savePm();
        });
    }

    // Select2: initialize when modal opens, destroy when it closes
    if (pmModal) {
        pmModal.addEventListener('shown.bs.modal', function () {
            if (!$('#pm_method_type').data('select2')) {
                $('#pm_method_type').select2({
                    placeholder: '— select type —',
                    allowClear: true,
                    dropdownParent: $(pmModal),
                });
            }
        });
        pmModal.addEventListener('hidden.bs.modal', function () {
            if ($('#pm_method_type').data('select2')) {
                $('#pm_method_type').select2('destroy');
            }
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    function openPmModal(method) {
        // Reset form
        document.getElementById('pm_editing_id').value = '';
        // Reset the underlying select; also update Select2 display if already init'd
        document.getElementById('pm_method_type').value = '';
        if ($('#pm_method_type').data('select2')) { $('#pm_method_type').trigger('change'); }
        document.getElementById('pm_label').value = '';
        document.getElementById('pm_is_primary').checked = false;
        document.querySelectorAll('.pm-type-fields').forEach(function (el) { el.classList.add('d-none'); });
        clearPmInputs();

        if (method) {
            document.getElementById('pm_editing_id').value   = method.id;
            // Set native value; Select2 will read it on shown.bs.modal init
            document.getElementById('pm_method_type').value  = method.method_type;
            if ($('#pm_method_type').data('select2')) { $('#pm_method_type').trigger('change'); }
            document.getElementById('pm_label').value        = method.label;
            document.getElementById('pm_is_primary').checked = method.is_primary;
            switchPmFields(method.method_type);
            fillPmInputs(method.method_type, method.details || {});
            document.getElementById('pm_modal_title').textContent = 'Edit Payment Method';
        } else {
            document.getElementById('pm_modal_title').textContent = 'Add Payment Method';
        }

        // Hide validation errors
        ['pm_type_error', 'pm_label_error'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('d-none');
        });

        if (pmBootstrap) pmBootstrap.show();
    }

    function switchPmFields(type) {
        document.querySelectorAll('.pm-type-fields').forEach(function (el) {
            el.classList.add('d-none');
        });
        var target = document.getElementById('pm_fields_' + type);
        if (target) target.classList.remove('d-none');
    }

    function clearPmInputs() {
        ['pm_paybill_number','pm_paybill_account_format',
         'pm_till_number','pm_till_account_name',
         'pm_sendmoney_phone','pm_sendmoney_name',
         'pm_bank_name','pm_bank_account_name','pm_bank_account_number','pm_bank_branch','pm_bank_swift',
         'pm_cash_instructions'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    function fillPmInputs(type, d) {
        if (type === 'mpesa_paybill') {
            setVal('pm_paybill_number',         d.paybill_number || '');
            setVal('pm_paybill_account_format', d.account_number_format || '');
        } else if (type === 'mpesa_till') {
            setVal('pm_till_number',      d.till_number || '');
            setVal('pm_till_account_name',d.account_name || '');
        } else if (type === 'mpesa_send_money') {
            setVal('pm_sendmoney_phone', d.phone_number || '');
            setVal('pm_sendmoney_name',  d.name || '');
        } else if (type === 'bank_transfer') {
            setVal('pm_bank_name',           d.bank_name || '');
            setVal('pm_bank_account_name',   d.account_name || '');
            setVal('pm_bank_account_number', d.account_number || '');
            setVal('pm_bank_branch',         d.branch || '');
            setVal('pm_bank_swift',          d.swift || '');
        } else if (type === 'cash') {
            setVal('pm_cash_instructions', d.instructions || '');
        }
    }

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = val;
    }

    function getVal(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function buildDetails(type) {
        if (type === 'mpesa_paybill')   return { paybill_number: getVal('pm_paybill_number'), account_number_format: getVal('pm_paybill_account_format') };
        if (type === 'mpesa_till')      return { till_number: getVal('pm_till_number'), account_name: getVal('pm_till_account_name') };
        if (type === 'mpesa_send_money')return { phone_number: getVal('pm_sendmoney_phone'), name: getVal('pm_sendmoney_name') };
        if (type === 'bank_transfer')   return { bank_name: getVal('pm_bank_name'), account_name: getVal('pm_bank_account_name'), account_number: getVal('pm_bank_account_number'), branch: getVal('pm_bank_branch'), swift: getVal('pm_bank_swift') };
        if (type === 'cash')            return { instructions: getVal('pm_cash_instructions') };
        return {};
    }

    function savePm() {
        var type      = document.getElementById('pm_method_type').value;
        var label     = document.getElementById('pm_label').value.trim();
        var isPrimary = document.getElementById('pm_is_primary').checked;
        var editingId = document.getElementById('pm_editing_id').value;
        var valid     = true;

        // Validate
        var typeErr  = document.getElementById('pm_type_error');
        var labelErr = document.getElementById('pm_label_error');
        if (!type)  { if (typeErr)  typeErr.classList.remove('d-none');  valid = false; } else { if (typeErr)  typeErr.classList.add('d-none'); }
        if (!label) { if (labelErr) labelErr.classList.remove('d-none'); valid = false; } else { if (labelErr) labelErr.classList.add('d-none'); }

        // Type-specific required field validation
        if (type === 'mpesa_paybill' && !getVal('pm_paybill_number'))    { valid = false; toast('Please enter the Paybill number.', 'warning'); }
        if (type === 'mpesa_till' && !getVal('pm_till_number'))           { valid = false; toast('Please enter the Till number.', 'warning'); }
        if (type === 'mpesa_send_money' && !getVal('pm_sendmoney_phone')) { valid = false; toast('Please enter the M-Pesa phone number.', 'warning'); }
        if (type === 'bank_transfer')  {
            if (!getVal('pm_bank_name') || !getVal('pm_bank_account_name') || !getVal('pm_bank_account_number')) {
                valid = false; toast('Please enter the bank name, account name and account number.', 'warning');
            }
        }

        if (!valid) return;

        // Show saving indicator
        var saveBtn = document.getElementById('pm_save_btn');
        saveBtn.querySelector('.indicator-label').classList.add('d-none');
        saveBtn.querySelector('.indicator-progress').classList.remove('d-none');
        saveBtn.disabled = true;

        var token   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var details = buildDetails(type);
        var isEdit  = !!editingId;

        var url    = isEdit ? '/payment-methods/' + editingId + '/update' : '/payment-methods/store';
        var method = isEdit ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ method_type: type, label: label, details: details, is_primary: isPrimary }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 200) {
                if (pmBootstrap) pmBootstrap.hide();
                loadPmList();
                toast(data.message || 'Saved successfully.', 'success');
            } else {
                toast(data.message || 'Failed to save.', 'error');
            }
        })
        .catch(function () { toast('Request failed. Please try again.', 'error'); })
        .finally(function () {
            saveBtn.querySelector('.indicator-label').classList.remove('d-none');
            saveBtn.querySelector('.indicator-progress').classList.add('d-none');
            saveBtn.disabled = false;
        });
    }

    // ─── List ─────────────────────────────────────────────────────────────

    function loadPmList() {
        var loading = document.getElementById('pm_loading');
        var empty   = document.getElementById('pm_empty');
        var wrapper = document.getElementById('pm_table_wrapper');

        if (loading) loading.classList.remove('d-none');
        if (empty)   empty.classList.add('d-none');
        if (wrapper) wrapper.classList.add('d-none');

        fetch('/payment-methods/list')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (loading) loading.classList.add('d-none');

                if (!res.data || res.data.length === 0) {
                    if (empty) empty.classList.remove('d-none');
                    return;
                }

                if (wrapper) wrapper.classList.remove('d-none');
                renderPmRows(res.data);
            })
            .catch(function () {
                if (loading) loading.classList.add('d-none');
                if (empty) {
                    empty.classList.remove('d-none');
                    empty.querySelector('p').textContent = 'Failed to load payment methods.';
                }
            });
    }

    function renderPmRows(methods) {
        var tbody = document.getElementById('pm_table_body');
        if (!tbody) return;

        var typeIcons = {
            mpesa_paybill:    '<i class="fab fa-google-pay fs-3 text-success me-2"></i>',
            mpesa_till:       '<i class="fas fa-store fs-3 text-success me-2"></i>',
            mpesa_send_money: '<i class="fas fa-mobile-alt fs-3 text-success me-2"></i>',
            bank_transfer:    '<i class="fas fa-university fs-3 text-primary me-2"></i>',
            cash:             '<i class="fas fa-money-bill-wave fs-3 text-warning me-2"></i>',
        };

        tbody.innerHTML = methods.map(function (m) {
            var primaryBadge = m.is_primary ? '<span class="badge badge-light-warning ms-2">Primary</span>' : '';
            var statusBadge  = m.is_active
                ? '<span class="badge badge-light-success">Active</span>'
                : '<span class="badge badge-light-danger">Inactive</span>';
            var icon = typeIcons[m.method_type] || '<i class="fas fa-credit-card fs-3 text-muted me-2"></i>';

            var detailLines = buildDetailLines(m.method_type, m.details || {});

            return '<tr>' +
                '<td class="ps-4">' +
                    '<div class="d-flex align-items-center">' +
                        icon +
                        '<div>' +
                            '<div class="fw-bolder text-gray-800">' + escHtml(m.label) + primaryBadge + '</div>' +
                            '<div class="text-muted fs-7">' + escHtml(m.type_label) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td><div class="text-muted fs-7">' + detailLines + '</div></td>' +
                '<td class="text-center">' + statusBadge + '</td>' +
                '<td class="text-end pe-4">' +
                    '<div class="d-flex justify-content-end gap-2">' +
                        (!m.is_primary ? '<button class="btn btn-sm btn-light-warning" onclick="pmSetPrimary(' + m.id + ')" title="Set as primary"><i class="fas fa-star fs-7"></i></button>' : '') +
                        '<button class="btn btn-sm btn-light-info" onclick="pmToggle(' + m.id + ')" title="' + (m.is_active ? 'Disable' : 'Enable') + '"><i class="fas fa-' + (m.is_active ? 'toggle-on' : 'toggle-off') + ' fs-7"></i></button>' +
                        '<button class="btn btn-sm btn-light-primary" onclick="pmEdit(' + m.id + ')" title="Edit"><i class="fas fa-pen fs-7"></i></button>' +
                        '<button class="btn btn-sm btn-light-danger" onclick="pmDelete(' + m.id + ')" title="Delete"><i class="fas fa-trash fs-7"></i></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');

        // Cache for edit
        window._pmData = methods;
    }

    function buildDetailLines(type, d) {
        if (type === 'mpesa_paybill')    return 'Paybill: <strong>' + escHtml(d.paybill_number || '—') + '</strong>' + (d.account_number_format ? ' &nbsp;|&nbsp; Account format: ' + escHtml(d.account_number_format) : '');
        if (type === 'mpesa_till')       return 'Till: <strong>' + escHtml(d.till_number || '—') + '</strong>' + (d.account_name ? ' &nbsp;|&nbsp; ' + escHtml(d.account_name) : '');
        if (type === 'mpesa_send_money') return 'Phone: <strong>' + escHtml(d.phone_number || '—') + '</strong>' + (d.name ? ' &nbsp;|&nbsp; ' + escHtml(d.name) : '');
        if (type === 'bank_transfer')    return escHtml(d.bank_name || '') + ' &nbsp;|&nbsp; <strong>' + escHtml(d.account_number || '—') + '</strong>' + (d.account_name ? '<br>' + escHtml(d.account_name) : '') + (d.branch ? ' &nbsp;|&nbsp; Branch: ' + escHtml(d.branch) : '');
        if (type === 'cash')             return escHtml(d.instructions || '—');
        return '—';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function toast(msg, icon) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ text: msg, icon: icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        }
    }

    // Expose action functions globally (called from rendered HTML)
    window.pmEdit = function (id) {
        var method = (window._pmData || []).find(function (m) { return m.id === id; });
        if (method) openPmModal(method);
    };

    window.pmDelete = function (id) {
        Swal.fire({
            text: 'Remove this payment method?',
            icon: 'warning',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Yes, remove',
            cancelButtonText: 'Cancel',
            customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light ms-2' }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('/payment-methods/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 200) { loadPmList(); toast(data.message, 'success'); }
                else toast(data.message || 'Failed to remove.', 'error');
            })
            .catch(function () { toast('Request failed.', 'error'); });
        });
    };

    window.pmSetPrimary = function (id) {
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        fetch('/payment-methods/' + id + '/primary', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 200) { loadPmList(); toast(data.message, 'success'); }
            else toast(data.message || 'Failed.', 'error');
        })
        .catch(function () { toast('Request failed.', 'error'); });
    };

    window.pmToggle = function (id) {
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        fetch('/payment-methods/' + id + '/toggle', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 200) { loadPmList(); toast(data.message, 'success'); }
            else toast(data.message || 'Failed.', 'error');
        })
        .catch(function () { toast('Request failed.', 'error'); });
    };

    // Kick off on page load
    if (document.getElementById('kt_payment_methods_card')) {
        loadPmList();
    }
});
