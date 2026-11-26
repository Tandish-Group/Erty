document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('expense_create_form');
    if (!form) return;

    const property = document.getElementById('create_complex');
    const unitWrap = document.getElementById('create_unit_wrap');
    const unitSelect = document.getElementById('create_units');
    const allocationWrap = document.getElementById('create_allocation_wrap');
    const allocationHintWrap = document.getElementById('create_allocation_hint_wrap');
    const allocation = document.getElementById('create_allocation');
    const saveButton = document.getElementById('create_save_expense');
    let unitRequest = 0;

    if (typeof flatpickr === 'function') {
        flatpickr('#create_date', { dateFormat: 'Y-m-d', defaultDate: new Date() });
    }

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#create_complex, #create_category, #create_currency').select2({ width: '100%', minimumResultsForSearch: 8 });
        $('#create_units').select2({ width: '100%', placeholder: 'Select one or more units', closeOnSelect: false });
    }

    function scopeValue() {
        return document.querySelector('input[name="create_scope"]:checked')?.value || 'complex';
    }

    function setScope(scope) {
        document.querySelectorAll('.expense-scope-option').forEach(label => {
            label.classList.toggle('is-selected', label.querySelector('input').value === scope);
        });
        const isUnitScope = scope !== 'complex';
        unitWrap.hidden = !isUnitScope;
        allocationWrap.hidden = !isUnitScope;
        allocationHintWrap.hidden = !isUnitScope;
        document.getElementById('create_unit_label').textContent = scope === 'residential_unit' ? 'Residential units' : 'Commercial units';
        if (isUnitScope && property.value) loadUnits();
        if (!isUnitScope) clearUnits();
        updateAllocationHint();
    }

    function clearUnits() {
        unitRequest++;
        unitSelect.innerHTML = '';
        unitSelect.disabled = true;
        if (typeof $ !== 'undefined' && $('#create_units').hasClass('select2-hidden-accessible')) {
            $('#create_units').val(null).trigger('change');
        }
    }

    function loadUnits() {
        const complexId = property.value;
        const scope = scopeValue();
        if (!complexId || scope === 'complex') return clearUnits();

        const requestId = ++unitRequest;
        unitSelect.disabled = true;
        unitSelect.innerHTML = '<option>Loading units…</option>';
        if (typeof $ !== 'undefined' && $('#create_units').hasClass('select2-hidden-accessible')) $('#create_units').trigger('change.select2');

        fetch(`${window.EXPENSE_URLS.units}/${encodeURIComponent(complexId)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(response => response.json())
        .then(result => {
            if (requestId !== unitRequest) return;
            const units = scope === 'residential_unit' ? result.residential : result.commercial;
            unitSelect.innerHTML = '';
            (units || []).forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = unit.label;
                unitSelect.appendChild(option);
            });
            unitSelect.disabled = !(units || []).length;
            if (typeof $ !== 'undefined' && $('#create_units').hasClass('select2-hidden-accessible')) $('#create_units').val(null).trigger('change');
            if (!(units || []).length) {
                Swal.fire({ icon: 'info', title: 'No units available', text: 'This property has no units in the selected scope.' });
            }
        })
        .catch(() => {
            if (requestId !== unitRequest) return;
            unitSelect.innerHTML = '';
            unitSelect.disabled = true;
            Swal.fire({ icon: 'error', title: 'Could not load units', text: 'Please try selecting the property again.' });
        });
    }

    function updateAllocationHint() {
        const hint = document.querySelector('.expense-allocation-hint');
        if (!hint) return;
        hint.textContent = allocation.value === 'split_total'
            ? 'The entered amount is divided equally across selected units.'
            : 'The entered amount is recorded separately for each selected unit.';
    }

    property.addEventListener('change', function () {
        if (scopeValue() !== 'complex') loadUnits();
    });
    document.querySelectorAll('input[name="create_scope"]').forEach(input => input.addEventListener('change', () => setScope(scopeValue())));
    allocation.addEventListener('change', updateAllocationHint);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity() || saveButton.disabled) return;

        const scope = scopeValue();
        const selectedUnits = Array.from(unitSelect.selectedOptions).map(option => Number(option.value)).filter(Number.isFinite);
        if (scope !== 'complex' && !selectedUnits.length) {
            Swal.fire({ icon: 'warning', title: 'Select units', text: 'Choose at least one unit for this expense.' });
            return;
        }

        const amount = Number(document.getElementById('create_amount').value);
        const allocationMode = scope === 'complex' ? 'split_total' : allocation.value;
        const count = scope === 'complex' ? 1 : selectedUnits.length;
        const total = scope !== 'complex' && allocationMode === 'per_unit' ? amount * count : amount;
        const currency = document.getElementById('create_currency').value;
        const propertyName = property.selectedOptions[0]?.textContent?.trim() || 'Selected property';
        const confirmed = await Swal.fire({
            title: 'Review expense',
            text: `${propertyName}: ${currency} ${formatAmount(total)} total across ${count} record${count === 1 ? '' : 's'}. ${count > 1 ? (allocationMode === 'split_total' ? 'The total will be split equally.' : 'This amount will be charged to each selected unit.') : ''} This records a cost, not a payment or approval.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm and save',
            cancelButtonText: 'Back to edit',
        });
        if (!confirmed.isConfirmed) return;

        const payload = {
            confirmed: true,
            allocation: allocationMode,
            complex_id: Number(property.value),
            scope,
            residential_unit_ids: scope === 'residential_unit' ? selectedUnits : null,
            commercial_unit_ids: scope === 'commercial_unit' ? selectedUnits : null,
            type: document.getElementById('create_category').value || null,
            description: document.getElementById('create_description').value.trim(),
            amount,
            currency,
            expense_date: document.getElementById('create_date').value,
        };

        setSaving(true);
        fetch(window.EXPENSE_URLS.store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(payload),
        })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Could not save the expense.');
            return result;
        })
        .then(result => {
            if (result.status !== 200) throw new Error(result.message || 'Could not save the expense.');
            Swal.fire({ icon: 'success', title: 'Expense recorded', text: result.message, confirmButtonText: 'View ledger' })
                .then(() => window.location.assign(form.dataset.ledgerUrl));
        })
        .catch(error => {
            setSaving(false);
            Swal.fire({ icon: 'error', title: 'Could not save expense', text: error.message || 'Please check the details and try again.' });
        });
    });

    function setSaving(saving) {
        saveButton.disabled = saving;
        saveButton.querySelector('.indicator-label').hidden = saving;
        saveButton.querySelector('.indicator-progress').hidden = !saving;
    }

    function formatAmount(value) {
        return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    setScope(scopeValue());
});
