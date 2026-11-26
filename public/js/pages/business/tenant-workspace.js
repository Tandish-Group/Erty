(() => {
    'use strict';
    const text = (tag, value, className) => {
        const element = document.createElement(tag);
        element.textContent = value;
        if (className) element.className = className;
        return element;
    };

    window.TenantOnboarding = {
        attach(root, form, stepper) {
            const next = root.querySelector('[data-kt-stepper-action="next"]');
            const previous = root.querySelector('[data-kt-stepper-action="previous"]');
            const submit = root.querySelector('[data-kt-stepper-action="submit"]');
            const errorBox = form.querySelector('[data-onboard-error]');
            const confirmation = form.querySelector('[data-onboard-confirm]');
            const titles = ['Tenant', 'Property & unit', 'Agreement', 'Billing', 'Documents & parking', 'Review'];
            let busy = false, dirty = false, saved = false;
            const agent = form.querySelector('#assigned_agent');
            if (agent && window.jQuery?.fn.select2) {
                const select = window.jQuery(agent);
                if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
                select.select2({
                    width: '100%', placeholder: 'Search agents', allowClear: true,
                    ajax: {
                        url: agent.dataset.ajaxUrl, dataType: 'json', delay: 250,
                        data: params => ({ q: params.term || '', page: params.page || 1 }),
                        processResults: (data, params) => ({ results: data.results || [], pagination: { more: (params.page || 1) * 10 < (data.total || 0) } })
                    }
                });
            }
            const value = name => {
                const fields = Array.from(form.elements).filter(field => field.name === name);
                if (!fields.length) return '';
                if (fields[0].type === 'radio') return fields.find(field => field.checked)?.value || '';
                return fields[0].value;
            };
            const selected = name => {
                const field = Array.from(form.elements).find(field => field.name === name);
                return field?.tagName === 'SELECT' ? Array.from(field.selectedOptions).filter(option => option.value).map(option => option.textContent.trim()).join(', ') : value(name);
            };
            ['unit_number', 'bedrooms', 'bathrooms', 'sq_area'].forEach(name => {
                const field = form.elements.namedItem(name);
                if (field) field.closest('.fv-row')?.setAttribute('hidden', '');
            });
            const error = message => {
                errorBox.textContent = message;
                errorBox.hidden = false;
                errorBox.focus();
            };
            const periodAmount = () => {
                const amount = Number(value('monthly_payment')) * ({ Monthly: 1, Quarterly: 3, Annually: 12 }[value('payment_interval')] || 1);
                return value('monthly_payment') === '' ? 'Enter monthly rent' : `${value('currency')} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            };
            const serviceChargeAmount = () => {
                const months = { Monthly: 1, Quarterly: 3, Annually: 12 }[value('payment_interval')] || 1;
                const amount = Number(value('service_charge_per_sq_ft') || 0) * Number(value('occupied_sq_area') || 0) * months;
                return `${value('currency')} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (invoiced separately)`;
            };
            const billingStart = () => {
                if (!value('start_date')) return 'Choose a start date';
                const date = new Date(`${value('start_date')}T12:00:00Z`);
                const days = value('listing_type') === 'Commercial Unit' ? Number(value('build_out_period') || 0) : 0;
                if (!Number.isFinite(date.getTime()) || !Number.isFinite(days)) return 'Check the start date and build-out period';
                date.setUTCDate(date.getUTCDate() + days);
                if (!Number.isFinite(date.getTime())) return 'Check the build-out period';
                return date.toISOString().slice(0, 10);
            };
            const billingPreview = () => {
                const panel = form.querySelector('[data-billing-preview]');
                const period = { Monthly: 'month', Quarterly: 'quarter', Annually: 'year' }[value('payment_interval')] || 'billing period';
                panel.replaceChildren(text('strong', `${periodAmount()} per ${period}`), text('p', `Billing starts: ${billingStart()}. The security deposit is a requirement, not a recorded payment.`));
                const start = value('start_date');
                if (start) {
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    const date = new Date(`${start}T00:00:00`);
                    if (date < today) panel.append(text('p', 'A past start date creates historical billing periods up to today. Review these in Billing after creation.'));
                    if (date > today) panel.append(text('p', 'Creating this tenancy reserves the selected space immediately, including when the start date is in the future.'));
                }
            };
            form.addEventListener('input', billingPreview);
            form.addEventListener('change', billingPreview);
            if (window.jQuery) window.jQuery(form).on('change.tenantBillingPreview', 'select', billingPreview);
            billingPreview();
            const review = () => {
                const container = form.querySelector('[data-onboard-review]');
                container.replaceChildren();
                const existing = value('tenant_option') === 'existing';
                const business = value('tenant_type') === 'Business';
                const commercial = value('listing_type') === 'Commercial Unit';
                const money = name => value(name) === '' ? 'Not entered' : `${value('currency')} ${Number(value(name)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                const documents = Array.from(form.querySelector('[name="documents[]"]')?.files || []).map(file => file.name).join(', ');
                const groups = [
                    ['Tenant', 1, [
                        ['Profile', existing ? 'Existing tenant' : 'New tenant'], ['Type', value('tenant_type')],
                        ['Name', existing ? document.getElementById('preview-name')?.textContent : value(business ? 'business_name' : 'tenant_name')],
                        ['Email', existing ? document.getElementById('preview-email')?.textContent : value(business ? 'business_email' : 'tenant_email')],
                        ['Phone', existing ? document.getElementById('preview-phone')?.textContent : value(business ? 'business_phone' : 'tenant_phone')]
                    ]],
                    ['Property & unit', 2, [
                        ['Property', selected('complex')], ['Property type', selected('listing_type')],
                        ['Unit', value('listing_type') === 'Commercial Unit' ? selected('commercial_unit_id') : selected('residential_unit_id') || value('unit_number') || 'Whole property'],
                        ...(value('listing_type') === 'Commercial Unit' ? [['Occupied area', `${value('occupied_sq_area')} sq ft`]] : [['Occupants', value('number_of_occupants')], ['Pets', value('has_pets') === '1' ? value('pet_details') || 'Yes' : 'No']])
                    ]],
                    ['Agreement', 3, [
                        ['Agreement', value('agreement_type').replaceAll('_', ' ')], ['Starts', value('start_date')], ['Ends', value('end_date') || 'Open-ended'],
                        ['Assigned agent', selected('assigned_agent_id') || 'Unassigned']
                    ]],
                    ['Billing', 4, [
                        ['Monthly rent', money('monthly_payment')], ['Payment interval', selected('payment_interval')], ['Amount per billing period', periodAmount()],
                        ...(commercial && Number(value('service_charge_per_sq_ft') || 0) > 0 ? [['Service charge per billing period', serviceChargeAmount()]] : []),
                        ['Security deposit required', money('security_deposit')],
                        ['Billing starts', billingStart()],
                        ...(value('payment_interval') === 'Quarterly' ? [['Quarterly timing', selected('tenancy_quarterly_payment_type')]] : []),
                        ['Grace period', `${value('grace_period') || '0'} days`],
                        ...(commercial ? [['Build-out period', `${value('build_out_period') || '0'} days`]] : []),
                        ['Late fee', money('late_fee_amount')], ['Daily penalty', money('penalty_amount')],
                    ]],
                    ['Documents & parking', 5, [
                        ['Documents', documents || 'None attached'], ['Legal files', selected('legal_files[]') || 'None selected'],
                        ['Parking', selected('parking_slot[]') || 'None selected'], ['Notes', value('notes') || 'None']
                    ]]
                ];
                groups.forEach(([title, step, rows]) => {
                    const section = text('section', '', 'tenant-review-section');
                    const heading = text('div', '', 'tenant-review-heading');
                    const edit = text('button', 'Edit', 'btn btn-sm btn-light');
                    edit.type = 'button'; edit.setAttribute('aria-label', `Edit ${title.toLowerCase()}`);
                    edit.addEventListener('click', () => { if (!busy) { stepper.goTo(step); changed(); } });
                    heading.append(text('h3', title), edit);
                    const list = document.createElement('dl');
                    rows.forEach(([label, content]) => {
                        const row = document.createElement('div');
                        row.append(text('dt', label), text('dd', content || 'Not entered'));
                        list.append(row);
                    });
                    section.append(heading, list); container.append(section);
                });
            };
            const changed = () => {
                const step = stepper.getCurrentStepIndex();
                if (step === 3 && window.applyAgreementFieldVisibility) window.applyAgreementFieldVisibility(value('listing_type'));
                next.classList.toggle('d-none', step === 6);
                submit.classList.toggle('d-none', step !== 6);
                previous.classList.toggle('d-none', step === 1);
                root.querySelectorAll('[data-kt-stepper-element="nav"]').forEach((item, index) => {
                    if (index + 1 === step) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
                });
                document.querySelector('[data-onboard-progress-label]').textContent = `Step ${step} of 6 · ${titles[step - 1]}`;
                document.querySelector('[data-onboard-progress]').value = step;
                if (step === 4) billingPreview();
                if (step === 6) review();
                const content = form.querySelectorAll('[data-kt-stepper-element="content"]')[step - 1];
                content.setAttribute('tabindex', '-1');
                content.focus({ preventScroll: true });
                document.querySelector('[data-onboard-progress-label]').scrollIntoView({ block: 'start', behavior: 'smooth' });
            };
            const validate = async step => {
                form.querySelectorAll('[aria-invalid="true"]').forEach(field => field.removeAttribute('aria-invalid'));
                const issues = [];
                const check = (name, message, valid) => { if (!valid) issues.push({ name, message }); };
                const required = (name, label) => check(name, `${label} is required.`, value(name).trim() !== '');
                const nonnegative = (name, label, integer = false) => check(name, `${label} must be zero or a positive ${integer ? 'whole number' : 'number'}.`, value(name) === '' || (Number.isFinite(Number(value(name))) && Number(value(name)) >= 0 && (!integer || Number.isInteger(Number(value(name))))));
                const commercial = value('listing_type') === 'Commercial Unit';
                if (step === 1) {
                    required('tenant_type', 'Tenant type');
                    if (value('tenant_option') === 'existing') {
                        required('existing_tenant_id', 'An existing tenant');
                        check('existing_tenant_id', 'This tenant is blacklisted. Review their profile before onboarding.', document.getElementById('preview-status')?.textContent.trim() !== 'Blacklisted');
                    } else {
                        const business = value('tenant_type') === 'Business';
                        const fields = business ? { business_name: 'Business name', business_registration: 'Registration number', business_contact_person: 'Contact person', business_email: 'Business email', business_phone: 'Business phone' } : { tenant_name: 'Full name', tenant_email: 'Email', tenant_phone: 'Phone', tenant_id_number: 'ID or passport number' };
                        Object.entries(fields).forEach(([name, label]) => required(name, label));
                        const email = business ? 'business_email' : 'tenant_email';
                        check(email, 'Enter a valid email address.', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value(email)));
                        if (!business && (value('emergency_contact_name') || value('emergency_contact_phone'))) {
                            required('emergency_contact_name', 'Emergency contact name'); required('emergency_contact_phone', 'Emergency contact phone');
                        }
                    }
                }
                if (step === 2) {
                    required('listing_type', 'Property type'); required('complex', 'Property');
                    if (commercial) {
                        required('commercial_unit_id', 'Commercial unit');
                        check('occupied_sq_area', 'Enter the area this tenancy will occupy.', Number(value('occupied_sq_area')) > 0);
                        const area = form.elements.namedItem('occupied_sq_area');
                        if (area?.max) check('occupied_sq_area', 'The requested area exceeds the available space.', Number(value('occupied_sq_area')) <= Number(area.max));
                        if (area?.min) check('occupied_sq_area', `This unit requires at least ${area.min} sq ft.`, Number(value('occupied_sq_area')) >= Number(area.min));
                        check('rate_per_sq_ft', 'Enter a positive rent rate per sq ft.', Number(value('rate_per_sq_ft')) > 0);
                        nonnegative('service_charge_per_sq_ft', 'Service charge rate');
                    } else {
                        const state = form.querySelector('[data-unit-availability]')?.dataset.state;
                        check('complex', 'Wait for unit availability, or select the property again if it could not load.', state === 'required' || state === 'standalone');
                        if (state === 'required') required('residential_unit_id', 'An available residential unit');
                        check('number_of_occupants', 'Enter at least one occupant.', Number.isInteger(Number(value('number_of_occupants'))) && Number(value('number_of_occupants')) >= 1);
                        if (value('has_pets') === '1') required('pet_details', 'Pet details');
                    }
                }
                if (step === 3) {
                    required('agreement_type', 'Agreement type'); required('start_date', 'Start date');
                    check('end_date', 'The end date must be after the start date.', !value('end_date') || value('end_date') > value('start_date'));
                }
                if (step === 4) {
                    required('currency', 'Currency'); required('monthly_payment', 'Monthly rent'); required('payment_interval', 'Payment interval');
                    ['monthly_payment', 'security_deposit', 'late_fee_amount', 'penalty_amount'].forEach(name => nonnegative(name, name.replaceAll('_', ' ')));
                    nonnegative('grace_period', 'Grace period', true); nonnegative('build_out_period', 'Build-out period', true);
                    if (value('payment_interval') === 'Quarterly') required('tenancy_quarterly_payment_type', 'Quarterly payment timing');
                    if (commercial && value('end_date')) check('build_out_period', 'Billing must start before the agreement ends.', billingStart() < value('end_date'));
                }
                if (step === 5) {
                    Array.from(form.querySelector('[name="documents[]"]')?.files || []).forEach(file => {
                        check('documents[]', `${file.name}: use PDF, Word, JPG or PNG files.`, /\.(pdf|docx?|jpe?g|png)$/i.test(file.name));
                        check('documents[]', `${file.name}: the maximum size is 10 MB per file.`, file.size <= 10 * 1024 * 1024);
                    });
                }
                if (issues.length) {
                    stepper.goTo(step); changed();
                    issues.forEach(issue => Array.from(form.elements).filter(field => field.name === issue.name).forEach(field => field.setAttribute('aria-invalid', 'true')));
                    error(issues.map(issue => issue.message).join(' '));
                    return false;
                }
                return true;
            };
            stepper.on('kt.stepper.changed', changed);
            stepper.on('kt.stepper.next', async () => {
                if (busy) return;
                busy = true; next.disabled = true;
                try {
                    if (await validate(stepper.getCurrentStepIndex())) {
                        errorBox.hidden = true; stepper.goNext();
                    }
                } finally { busy = false; next.disabled = false; }
            });
            stepper.on('kt.stepper.previous', () => {
                if (busy) return;
                errorBox.hidden = true; stepper.goPrevious();
            });
            form.addEventListener('input', event => { dirty = true; if (event.target !== confirmation) confirmation.checked = false; });
            form.addEventListener('change', event => { dirty = true; if (event.target !== confirmation) confirmation.checked = false; });
            // Select2 emits jQuery events which do not always reach native change listeners.
            if (window.jQuery) window.jQuery(form).on('change.tenantOnboarding', 'select', () => { dirty = true; confirmation.checked = false; });
            window.addEventListener('beforeunload', event => {
                if (dirty && !saved) { event.preventDefault(); event.returnValue = ''; }
            });
            const save = async event => {
                event.preventDefault();
                if (busy || saved) return;
                if (stepper.getCurrentStepIndex() !== 6) { next.click(); return; }
                if (!confirmation.checked) { error('Confirm that you have reviewed the tenancy details before creating it.'); return; }
                busy = true;
                [next, previous, submit].forEach(button => { button.disabled = true; });
                submit.setAttribute('data-kt-indicator', 'on');
                try {
                    for (let step = 1; step <= 5; step++) if (!await validate(step)) return;
                    errorBox.hidden = true;
                    const data = new FormData(form);
                    const existing = value('tenant_option') === 'existing';
                    const business = value('tenant_type') === 'Business';
                    form.querySelectorAll('.new-tenant-fields input, .new-tenant-fields select, .new-tenant-fields textarea').forEach(field => {
                        if (existing || (business ? field.closest('.individual-fields') : field.closest('.business-fields'))) data.delete(field.name);
                    });
                    if (!existing) data.delete('existing_tenant_id');
                    const commercial = value('listing_type') === 'Commercial Unit';
                    (commercial ? ['residential_unit_id', 'unit_number', 'bedrooms', 'bathrooms', 'sq_area', 'furnishing'] : ['commercial_unit_id', 'occupied_sq_area', 'specific_unit_number', 'rate_per_sq_ft', 'service_charge_per_sq_ft']).forEach(name => data.delete(name));
                    const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
                    const result = await response.json();
                    if (!response.ok || result.status !== 200) {
                        const messages = Object.values(result.errors || {}).flat();
                        const name = Object.keys(result.errors || {})[0];
                        const field = name && Array.from(form.elements).find(element => element.name === name || element.name === `${name}[]`);
                        if (field) {
                            const contents = Array.from(form.querySelectorAll('[data-kt-stepper-element="content"]'));
                            const index = contents.indexOf(field.closest('[data-kt-stepper-element="content"]'));
                            if (index >= 0) { stepper.goTo(index + 1); changed(); }
                        }
                        throw new Error(messages.join(' ') || result.message || 'The tenancy could not be created. Please try again.');
                    }
                    saved = true; dirty = false;
                    const panel = document.querySelector('[data-onboard-success]');
                    panel.replaceChildren(text('span', 'Tenancy created', 'tenant-eyebrow'), text('h2', 'Ready for the next step'), text('p', result.message || 'The tenancy, lease and initial billing have been created.'));
                    const actions = text('div', '', 'tenant-heading-actions');
                    const ref = result.tenancy_ref;
                    if (ref) {
                        const link = text('a', 'View tenancy', 'btn btn-primary');
                        link.href = `/tenant_management/tenancy/${encodeURIComponent(ref)}/view`; actions.append(link);
                    }
                    const leaseId = result.encrypted_lease_id || result.encryped_lease_id;
                    if (leaseId) {
                        const link = text('a', 'Review lease terms', 'btn btn-light');
                        link.href = `/tenant_management/lease/edit/${encodeURIComponent(leaseId)}`; actions.append(link);
                    }
                    const dashboard = text('a', 'Tenant dashboard', 'btn btn-light'); dashboard.href = '/tenant_management'; actions.append(dashboard);
                    panel.append(actions); panel.hidden = false;
                    document.querySelector('[data-onboard-shell]').hidden = true; panel.focus();
                } catch (failure) {
                    error(failure instanceof SyntaxError ? 'The server returned an unexpected response. Your entries have been kept.' : failure.message || 'The connection failed. Your entries have been kept.');
                } finally {
                    busy = false;
                    [next, previous, submit].forEach(button => { button.disabled = saved; });
                    submit.removeAttribute('data-kt-indicator');
                }
            };
            submit.addEventListener('click', save);
            form.addEventListener('submit', save);
            changed();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const dashboard = document.querySelector('[data-tenant-dashboard]');
        if (!dashboard) return;
        const tabs = Array.from(dashboard.querySelectorAll('[data-bs-toggle="tab"]'));
        tabs.forEach(tab => tab.addEventListener('shown.bs.tab', () => {
            history.replaceState(null, '', `${location.pathname}${location.search}${tab.dataset.bsTarget}`);
            if (window.jQuery?.fn.dataTable) window.jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }));
        const active = tabs.find(tab => tab.dataset.bsTarget === location.hash);
        if (active && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(active).show();
        const button = dashboard.querySelector('[data-tenant-refresh]');
        button.addEventListener('click', async () => {
            if (button.disabled) return;
            button.disabled = true; button.textContent = 'Refreshing…';
            const notice = dashboard.querySelector('[data-tenant-feedback]');
            notice.hidden = true;
            try {
                const response = await fetch('/tenant_management', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Could not refresh. Please try again.');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const selectors = ['[data-tenant-dashboard-summary]', '[data-tenant-recent]'];
                if (selectors.some(selector => !page.querySelector(selector))) throw new Error('Could not refresh. Your session may have expired.');
                selectors.forEach(selector => dashboard.querySelector(selector).replaceWith(page.querySelector(selector)));
                ['#kt_table_leases', '#kt_table_complex_occupancy'].forEach(selector => {
                    if (window.jQuery?.fn.dataTable?.isDataTable(selector)) window.jQuery(selector).DataTable().ajax.reload(null, false);
                });
                notice.classList.remove('is-error'); notice.textContent = 'Summary and recent tenancies updated. Lease and occupancy tables are refreshing.';
            } catch (failure) { notice.classList.add('is-error'); notice.textContent = failure.message; }
            finally { notice.hidden = false; button.disabled = false; button.textContent = 'Refresh data'; }
        });
    });
})();
