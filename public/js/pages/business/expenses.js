document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('expense_tbody')) return;

    // ── Constants ──────────────────────────────────────────────────────────
    const URLS  = window.EXPENSE_URLS;
    const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content;

    let currentPage  = 1;
    let totalPages   = 1;
    let editingId    = null;   // null = add, int = edit
    let unitRequest = 0;

    // ── Date pickers ───────────────────────────────────────────────────────
    const now          = new Date();
    const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastOfMonth  = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    flatpickr('#filter_date_from', { dateFormat: 'Y-m-d', defaultDate: firstOfMonth });
    flatpickr('#filter_date_to',   { dateFormat: 'Y-m-d', defaultDate: lastOfMonth  });
    const fpModalDate = flatpickr('#modal_date', { dateFormat: 'Y-m-d', defaultDate: now });

    // ── Select2 for modal selects (with correct dropdownParent) ─────────────
    if (typeof $ !== 'undefined' && $.fn.select2) {
        const $modal = $('#expense_modal');
        $('#modal_complex').select2({
            placeholder: 'Search for a complex…',
            allowClear:  true,
            dropdownParent: $modal,
        });
        $('#modal_type').select2({
            placeholder: '— Uncategorised —',
            allowClear:  true,
            dropdownParent: $modal,
        });
        $('#modal_currency').select2({
            minimumResultsForSearch: Infinity,
            dropdownParent: $modal,
        });
    }

    // ── Pre-select complex from query-string (linked from complex card) ────
    const preComplex = window.PRESELECT_COMPLEX;
    if (preComplex) {
        const fcEl = document.getElementById('filter_complex');
        if (fcEl) fcEl.value = String(preComplex);
        // Notify Select2 after it initialises (deferred to next tick)
        if (typeof $ !== 'undefined') {
            setTimeout(function () {
                $('#filter_complex').trigger('change.select2');
            }, 0);
        }
    }

    // ── Load button ────────────────────────────────────────────────────────
    document.getElementById('btn_load').addEventListener('click', function () {
        currentPage = 1;
        loadExpenses();
    });

    // Preserve the currently selected property when moving to the full add page.
    document.getElementById('btn_add_expense')?.addEventListener('click', function () {
        const complexId = document.getElementById('filter_complex').value;
        if (complexId) this.href = `${this.href.split('?')[0]}?complex_id=${encodeURIComponent(complexId)}`;
    });

    // ── Scope radio → show/hide unit row ──────────────────────────────────
    document.querySelectorAll('input[name="modal_scope"]').forEach(r => {
        r.addEventListener('change', onScopeChange);
    });

    // ── Complex change → reload units ────────────────────────────────────
    if (typeof $ !== 'undefined') {
        $('#modal_complex').on('change', function () {
            const scope   = getSelectedScope();
            const isMulti = (editingId === null);
            if (scope !== 'complex') loadUnits($(this).val(), scope, isMulti);
        });
    } else {
        document.getElementById('modal_complex').addEventListener('change', function () {
            const scope   = getSelectedScope();
            const isMulti = (editingId === null);
            if (scope !== 'complex') loadUnits(this.value, scope, isMulti);
        });
    }

    // ── Pagination ────────────────────────────────────────────────────────
    document.getElementById('btn_prev').addEventListener('click', function () {
        if (currentPage > 1) { currentPage--; loadExpenses(); }
    });
    document.getElementById('btn_next').addEventListener('click', function () {
        if (currentPage < totalPages) { currentPage++; loadExpenses(); }
    });

    // ── Save expense ──────────────────────────────────────────────────────
    document.getElementById('btn_save_expense').addEventListener('click', saveExpense);

    // ─────────────────────────────────────────────────────────────────────
    // LOAD EXPENSES
    // ─────────────────────────────────────────────────────────────────────

    function loadExpenses() {
        const btn = document.getElementById('btn_load');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Loading…';

        const params = new URLSearchParams({
            complex_id: document.getElementById('filter_complex').value,
            scope:      document.getElementById('filter_scope').value,
            date_from:  document.getElementById('filter_date_from').value,
            date_to:    document.getElementById('filter_date_to').value,
            page:       currentPage,
            category: document.getElementById('filter_category').value,
            q: document.getElementById('filter_q').value,
            unit_q: document.getElementById('filter_unit_q').value,
            workflow_status: document.getElementById('filter_workflow_status').value,
        });

        const exportLink = document.getElementById('export_ledger');
        if (exportLink) exportLink.href = `/complex-expenses/export-ledger?${params}`;
        fetch(`${URLS.data}?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i> Load Expenses';

            if (res.status !== 200) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load expenses.' });
                return;
            }

            renderSummary(res.data.summary);
            renderTable(res.data.expenses);
            renderPagination(res.data.pagination);

            document.getElementById('summary_cards').style.setProperty('display', 'flex', 'important');
            document.getElementById('results_card').style.display = '';
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i> Load Expenses';
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to load expenses. Please try again.' });
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────────────

    function renderSummary(s) {
        document.getElementById('stat_total_amount').textContent = (s.currency_totals || []).map(item => `${item.currency} ${fmt(item.total)}`).join(' · ') || 'No expenses';
        document.getElementById('stat_complex_count').textContent   = s.complex_count;
        document.getElementById('stat_residential_count').textContent = s.residential_count;
        document.getElementById('stat_commercial_count').textContent = s.commercial_count;
    }

    function renderTable(expenses) {
        const tbody = document.getElementById('expense_tbody');
        tbody.innerHTML = '';

        if (!expenses.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="expense-empty-state">No expenses match these filters. Try changing your search criteria.</td></tr>';
            return;
        }

        expenses.forEach(e => {
            const scopeBadge = {
                complex:          '<span class="badge badge-light-primary">Whole Complex</span>',
                residential_unit: '<span class="badge badge-light-success">Residential Unit</span>',
                commercial_unit:  '<span class="badge badge-light-warning">Commercial Unit</span>',
            }[e.scope] || '<span class="badge badge-light">—</span>';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="expense-property-cell"><strong>${escHtml(e.complex_title)}</strong></td>
                <td class="expense-description-cell"><strong>${escHtml(e.description)}</strong><small>${escHtml(e.category || 'Uncategorised')}</small></td>
                <td class="expense-scope-cell">${scopeBadge}<small>${escHtml(e.unit_ref || (e.scope === 'complex' ? 'Whole property' : '—'))}</small></td>
                <td class="text-end expense-amount-cell"><strong>${escHtml(e.currency)} ${fmt(e.amount)}</strong></td>
                <td class="expense-date-cell">${escHtml(e.expense_date || '—')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-icon btn-light-primary btn-edit me-1"
                        data-expense='${escHtml(JSON.stringify(e)).replace(/'/g, "&#39;")}' title="Edit">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-danger btn-delete"
                        data-id="${e.id}" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            if (e.workflow_status) {
                const link = document.createElement('a');
                link.href = `${URLS.store}/${e.id}/workflow`;
                link.className = 'btn btn-sm btn-light-primary mb-2 expense-workflow-link';
                link.textContent = `${e.workflow_status} · ${e.payment_status} · Open workflow`;
                tr.lastElementChild.prepend(link);
                tr.querySelector('.btn-delete').hidden = true;
                if (!['unreviewed', 'draft', 'returned'].includes(e.workflow_status)) tr.querySelector('.btn-edit').hidden = true;
            }
            if (tbody.dataset.canEdit === '0') tr.querySelector('.btn-edit').remove();
            if (tbody.dataset.canDelete === '0') tr.querySelector('.btn-delete').remove();
        });

        // Edit listeners
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function () {
                openEditModal(JSON.parse(this.dataset.expense));
            });
        });

        // Delete listeners
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function () {
                confirmDelete(parseInt(this.dataset.id));
            });
        });
    }

    function renderPagination(p) {
        totalPages = p.pages || 1;
        const label = `Page ${p.page} of ${Math.max(1, p.pages)} (${p.total} record${p.total !== 1 ? 's' : ''})`;

        document.getElementById('pagination_label').textContent = label;
        document.getElementById('pagination_info').textContent  = label;
        document.getElementById('btn_prev').disabled = p.page <= 1;
        document.getElementById('btn_next').disabled = p.page >= p.pages;

        const paginationRow = document.getElementById('pagination_row');
        if (p.pages > 1) {
            paginationRow.style.setProperty('display', 'flex', 'important');
        } else {
            paginationRow.style.setProperty('display', 'none', 'important');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // MODAL — ADD / EDIT
    // ─────────────────────────────────────────────────────────────────────

    function openEditModal(e) {
        editingId = e.id;
        document.getElementById('allocation_row').hidden = true;
        document.getElementById('expenseModalLabel').querySelector('span').textContent = 'Edit Expense';
        document.getElementById('expense_id').value = e.id;

        // Single-select for edit mode
        const unitSelect = document.getElementById('modal_unit');
        unitSelect.removeAttribute('multiple');

        if (typeof $ !== 'undefined') {
            // Set the complex value using integer ID (matches server-rendered options)
            $('#modal_complex').val(String(e.complex_id)).trigger('change');
            $('#modal_type').val(e.expense_type_id ?? '').trigger('change');
            $('#modal_currency').val(e.currency || 'KES').trigger('change');
        }

        // Set scope radio
        document.querySelectorAll('input[name="modal_scope"]').forEach(r => {
            r.checked = r.value === e.scope;
        });

        document.getElementById('modal_description').value = e.description;
        document.getElementById('modal_amount').value      = e.amount;
        fpModalDate.setDate(e.expense_date);

        // Load units then pre-select
        if (e.scope !== 'complex') {
            loadUnits(e.complex_id, e.scope, false, function () {
                const unitId = e.scope === 'residential_unit'
                    ? e.residential_unit_id
                    : e.commercial_unit_id;
                if (typeof $ !== 'undefined') {
                    $('#modal_unit').val(unitId ?? '').trigger('change');
                } else {
                    document.getElementById('modal_unit').value = unitId ?? '';
                }
            });
            document.getElementById('unit_row').style.display = '';
            document.getElementById('unit_label').textContent =
                e.scope === 'residential_unit' ? 'Residential Unit' : 'Commercial Unit';
            document.getElementById('unit_hint').textContent = 'Editing applies to this single unit.';
        } else {
            document.getElementById('unit_row').style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('expense_modal')).show();
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCOPE CHANGE → show/hide unit selector
    // ─────────────────────────────────────────────────────────────────────

    function getSelectedScope() {
        return document.querySelector('input[name="modal_scope"]:checked')?.value ?? 'complex';
    }

    function onScopeChange() {
        const scope     = getSelectedScope();
        const unitRow   = document.getElementById('unit_row');
        const unitLabel = document.getElementById('unit_label');

        if (scope === 'complex') {
            unitRow.style.display = 'none';
            return;
        }

        unitLabel.textContent = scope === 'residential_unit' ? 'Residential Unit' : 'Commercial Unit';
        unitRow.style.display = '';

        const isMulti   = (editingId === null);  // multi-select only in add mode
        const complexId = document.getElementById('modal_complex').value;
        if (complexId) loadUnits(complexId, scope, isMulti);
    }

    function loadUnits(complexId, scope, isMulti, callback) {
        const requestId = ++unitRequest;
        if (!complexId) {
            document.getElementById('modal_unit').innerHTML = '';
            return;
        }

        const select = document.getElementById('modal_unit');
        select.innerHTML = '<option value="">Loading…</option>';
        select.disabled  = true;

        fetch(`${URLS.units}/${complexId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
        })
        .then(r => r.json())
        .then(res => {
            if (requestId !== unitRequest) return;
            select.innerHTML = '';
            select.disabled  = false;

            if (!isMulti) {
                const blank = document.createElement('option');
                blank.value       = '';
                blank.textContent = '— Select unit —';
                select.appendChild(blank);
            }

            const units = scope === 'residential_unit' ? res.residential : res.commercial;
            (units || []).forEach(u => {
                const opt = document.createElement('option');
                opt.value       = u.id;
                opt.textContent = u.label;
                select.appendChild(opt);
            });

            // (Re-)initialise Select2 on the unit dropdown with modal as dropdownParent
            if (typeof $ !== 'undefined' && $.fn.select2) {
                if ($('#modal_unit').hasClass('select2-hidden-accessible')) {
                    $('#modal_unit').select2('destroy');
                }
                if (isMulti) {
                    select.setAttribute('multiple', 'multiple');
                    $('#modal_unit').select2({
                        placeholder: '— Select one or more units —',
                        allowClear:  true,
                        multiple:    true,
                        dropdownParent: $('#expense_modal'),
                    });
                } else {
                    select.removeAttribute('multiple');
                    $('#modal_unit').select2({
                        placeholder: '— Select unit —',
                        allowClear:  true,
                        dropdownParent: $('#expense_modal'),
                    });
                }
            }

            if (typeof callback === 'function') callback();
        })
        .catch(() => {
            select.innerHTML = '<option value="">Failed to load units</option>';
            select.disabled  = false;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // SAVE (store / update)
    // ─────────────────────────────────────────────────────────────────────

    async function saveExpense() {
        const complexId   = document.getElementById('modal_complex').value;
        const scope       = getSelectedScope();
        const typeId      = document.getElementById('modal_type').value;
        const description = document.getElementById('modal_description').value.trim();
        const amount      = document.getElementById('modal_amount').value;
        const currency    = document.getElementById('modal_currency').value;
        const expenseDate = document.getElementById('modal_date').value;
        const isEdit      = !!editingId;

        if (!complexId || !description || !amount || !expenseDate) {
            Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Please fill in all required fields.' });
            return;
        }

        let payload;

        if (isEdit) {
            // Edit mode — single unit
            const unitId = document.getElementById('modal_unit').value;
            if (scope !== 'complex' && !unitId) {
                Swal.fire({ icon: 'warning', title: 'Select a unit', text: 'Please select a unit for this scope.' });
                return;
            }
            payload = {
                complex_id:          parseInt(complexId),
                scope:               scope,
                residential_unit_id: scope === 'residential_unit' ? (parseInt(unitId) || null) : null,
                commercial_unit_id:  scope === 'commercial_unit'  ? (parseInt(unitId) || null) : null,
                type:                typeId ? parseInt(typeId) : null,
                description:         description,
                amount:              parseFloat(amount),
                currency:            currency,
                expense_date:        expenseDate,
            };
        } else {
            // Add mode — collect selected unit IDs (may be multiple)
            const unitSelectEl  = document.getElementById('modal_unit');
            const selectedUnits = Array.from(unitSelectEl.selectedOptions)
                .map(o => parseInt(o.value))
                .filter(v => !isNaN(v) && v > 0);

            if (scope !== 'complex' && selectedUnits.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Select a unit', text: 'Please select at least one unit for this scope.' });
                return;
            }
            payload = {
                complex_id:           parseInt(complexId),
                scope:                scope,
                residential_unit_ids: scope === 'residential_unit' ? selectedUnits : null,
                commercial_unit_ids:  scope === 'commercial_unit'  ? selectedUnits : null,
                type:                 typeId ? parseInt(typeId) : null,
                description:          description,
                amount:               parseFloat(amount),
                currency:             currency,
                expense_date:         expenseDate,
            };
        }

        const btn = document.getElementById('btn_save_expense');
        if (btn.disabled) return;
        const allocation = document.getElementById('modal_allocation').value;
        const count = isEdit || scope === 'complex' ? 1 : (payload.residential_unit_ids || payload.commercial_unit_ids || []).length;
        const total = !isEdit && allocation === 'per_unit' ? Number(amount) * count : Number(amount);
        btn.disabled = true;
        const review = await Swal.fire({
            title: isEdit ? 'Confirm expense correction' : 'Review expense allocation',
            text: `${document.getElementById('modal_complex').selectedOptions[0]?.textContent}: ${currency} ${fmt(total)} total across ${count} record(s). ${count > 1 ? (allocation === 'split_total' ? 'The total is split equally; rounding differences are allocated to the first selected units.' : 'The entered amount is charged separately to each selected unit.') : ''} This records a cost, not payment or approval.`,
            icon: 'question', showCancelButton: true, confirmButtonText: 'Confirm & save', cancelButtonText: 'Back to edit'
        });
        if (!review.isConfirmed) { btn.disabled = false; return; }
        payload.confirmed = true;
        if (!isEdit) payload.allocation = allocation;
        btn.querySelector('.indicator-label').style.display   = 'none';
        btn.querySelector('.indicator-progress').style.display = '';
        btn.disabled = true;

        const url    = isEdit ? `${URLS.update}/${editingId}` : URLS.store;
        const method = isEdit ? 'PUT' : 'POST';

        fetch(url, {
            method:  method,
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     CSRF,
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(res => {
            btn.querySelector('.indicator-label').style.display   = '';
            btn.querySelector('.indicator-progress').style.display = 'none';
            btn.disabled = false;

            if (res.status === 200) {
                bootstrap.Modal.getInstance(document.getElementById('expense_modal')).hide();
                Swal.fire({ icon: 'success', title: 'Saved', text: res.message, timer: 2000, showConfirmButton: false });
                loadExpenses();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not save expense.' });
            }
        })
        .catch(() => {
            btn.querySelector('.indicator-label').style.display   = '';
            btn.querySelector('.indicator-progress').style.display = 'none';
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to save. Please try again.' });
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────

    function confirmDelete(id) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete Expense?',
            text: 'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#f1416c',
        }).then(result => {
            if (!result.isConfirmed) return;

            fetch(`${URLS.destroy}/${id}`, {
                method:  'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     CSRF,
                },
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 200) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1500, showConfirmButton: false });
                    loadExpenses();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not delete expense.' });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to delete. Please try again.' });
            });
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    function fmt(n) {
        return parseFloat(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = String(str ?? '');
        return d.innerHTML;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Auto-load if complex was pre-selected via query string
    // ─────────────────────────────────────────────────────────────────────
    loadExpenses();
});
