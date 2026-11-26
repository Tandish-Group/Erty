(() => {
    'use strict';
    let opening = false;
    const modalElement = () => document.getElementById('property-facility-modal');
    const panel = () => document.querySelector('[data-facilities-panel]');
    function notice(element, text, error = false) {
        element.hidden = false;
        element.className = `facility-form-message ${error ? 'is-error' : 'is-success'}`;
        element.setAttribute('role', error ? 'alert' : 'status');
        element.textContent = text;
    }
    function fields(form) {
        const type = form.elements.amenity_type.value;
        form.querySelectorAll('[data-facility-fields]').forEach(group => {
            group.hidden = group.dataset.facilityFields !== type;
            group.disabled = group.hidden;
        });
        form.querySelector('[data-facility-details]').hidden = !type;
        form.elements.amenity_currency.required = form.elements.annual_cost.value !== '';
    }
    function reset(form) {
        form.reset();
        form.action = form.dataset.createUrl;
        form.elements._method.value = 'POST';
        form.dataset.dirty = 'false';
        form.dataset.saved = 'false';
        form.querySelectorAll('[data-legacy-option]').forEach(option => option.remove());
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        form.querySelector('[data-facility-message]').hidden = true;
        form.querySelector('[data-facility-submit]').textContent = 'Save facility';
        fields(form);
    }
    async function json(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', ...options.headers } });
        if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Your session may have expired. Refresh the page before trying again.');
        const result = await response.json();
        if (!response.ok || !result.success) {
            const error = new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The change could not be saved. Please try again.');
            error.fields = result.errors || {};
            throw error;
        }
        return result;
    }
    async function refresh(text) {
        const current = panel();
        const response = await fetch(current.dataset.facilityRefresh, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error('Unable to refresh facilities.');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const fresh = page.querySelector('[data-facilities-panel]');
        if (!fresh) throw new Error('Unable to refresh facilities.');
        const filter = current.querySelector('[data-facility-filter]')?.value;
        const collapsed = new Set([...current.querySelectorAll('[data-facility-group]')].filter(group => group.querySelector('[data-facility-toggle]').getAttribute('aria-expanded') === 'false').map(group => group.dataset.facilityGroup));
        current.replaceWith(fresh);
        fresh.querySelectorAll('[data-facility-group]').forEach(group => {
            if (collapsed.has(group.dataset.facilityGroup)) expandGroup(group, false);
        });
        const select = fresh.querySelector('[data-facility-filter]');
        if (select && [...select.options].some(option => option.value === filter)) {
            select.value = filter;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        document.querySelector('#property-tab-amenities .property-tab-count')?.replaceChildren(document.createTextNode(fresh.dataset.facilityCount));
        const summary = page.querySelector('[data-property-summary]');
        if (summary) document.querySelector('[data-property-summary]')?.replaceWith(summary);
        document.querySelectorAll('[data-readiness-warning]').forEach(warning => { warning.hidden = false; });
        notice(fresh.querySelector('[data-facility-notice]'), text);
    }
    document.addEventListener('click', async event => {
        const add = event.target.closest('[data-facility-add]');
        const edit = event.target.closest('[data-facility-edit]');
        if (!(add || edit) || !modalElement() || !window.bootstrap) return;
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (opening) return;
        opening = true;
        const form = modalElement().querySelector('form');
        const button = edit || add;
        button.setAttribute('aria-busy', 'true');
        try {
            const data = edit ? (await json(edit.dataset.facilityEdit)).amenity : null;
            reset(form);
            if (data) {
                form.action = edit.dataset.facilityUpdate;
                form.elements._method.value = 'PUT';
                const values = { ...data, amenity_currency: data.currency, annual_cost: data.annual_price };
                for (const [key, value] of Object.entries(values)) {
                    const input = form.elements.namedItem(key);
                    if (!input || value === null || typeof value === 'object') continue;
                    if (input.tagName === 'SELECT' && ![...input.options].some(option => option.value === String(value))) {
                        const option = new Option(value, value); option.dataset.legacyOption = ''; input.add(option);
                    }
                    input.value = value;
                }
                fields(form);
            }
            document.getElementById('facility-modal-title').textContent = data ? 'Edit facility' : 'Add facility';
            bootstrap.Modal.getOrCreateInstance(modalElement()).show();
        } catch (error) { notice(panel().querySelector('[data-facility-notice]'), error.message, true); }
        finally { opening = false; button.removeAttribute('aria-busy'); }
    });
    ['input', 'change'].forEach(type => document.addEventListener(type, event => {
        const form = event.target.closest('[data-facility-form]');
        if (form) { form.dataset.dirty = 'true'; fields(form); }
    }));
    function expandGroup(group, expanded) {
        group.querySelector('[data-facility-toggle]').setAttribute('aria-expanded', String(expanded));
        group.querySelectorAll('[data-facility-record]').forEach(row => { row.hidden = !expanded; });
    }
    document.addEventListener('click', event => {
        const toggle = event.target.closest('[data-facility-toggle]');
        if (toggle) expandGroup(toggle.closest('[data-facility-group]'), toggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-facility-filter]')) return;
        panel().querySelectorAll('[data-facility-group]').forEach(group => { group.hidden = !!event.target.value && group.dataset.facilityGroup !== event.target.value; });
    });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-facility-form]')) return;
        event.preventDefault();
        if (form.dataset.busy === 'true' || form.dataset.saved === 'true') return;
        const payload = new FormData(form);
        const controls = [...form.querySelectorAll('input, select, textarea, button')].filter(input => !input.disabled);
        controls.forEach(input => { input.disabled = true; });
        form.dataset.busy = 'true'; form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        const message = form.querySelector('[data-facility-message]');
        notice(message, 'Saving facility…');
        let saved = false;
        try {
            const result = await json(form.action, { method: 'POST', body: payload });
            saved = true; form.dataset.saved = 'true'; form.dataset.dirty = 'false';
            if (form.closest('.modal')) {
                // Closing is allowed once the save has completed, even while the tab refreshes.
                form.dataset.busy = 'false';
                bootstrap.Modal.getInstance(modalElement()).hide();
                await refresh(result.message);
            } else {
                reset(form);
                notice(message, 'Facility saved. Add another below or return to facilities.');
            }
        } catch (error) {
            for (const key of Object.keys(error.fields || {})) form.elements.namedItem(key)?.setAttribute('aria-invalid', 'true');
            if (saved && panel()) notice(panel().querySelector('[data-facility-notice]'), 'The facility was saved, but the tab could not refresh. Reload to see the saved record; do not add it again.', true);
            else { notice(message, error.message, true); message.tabIndex = -1; message.focus(); }
        } finally {
            controls.forEach(input => { input.disabled = false; }); fields(form);
            form.dataset.busy = 'false'; form.removeAttribute('aria-busy');
        }
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-facility-delete]');
        if (!button || button.disabled) return;
        if (!window.confirm(`Remove ${button.dataset.facilityLabel} from this property? This cannot be undone.`)) return;
        button.disabled = true;
        let removed = false;
        try {
            const payload = new FormData();
            payload.set('_token', document.querySelector('[data-facility-form] [name="_token"]').value);
            payload.set('amenity_id', button.dataset.facilityDelete);
            const result = await json(button.dataset.facilityDeleteUrl, { method: 'POST', body: payload });
            removed = true;
            await refresh(result.message);
        } catch (error) {
            notice(panel().querySelector('[data-facility-notice]'), removed ? 'The facility was removed, but the tab could not refresh. Reload to see the updated list.' : error.message, true);
            if (!removed) button.disabled = false;
        }
    });
    function init() {
        document.querySelectorAll('[data-facility-form]').forEach(fields);
        modalElement()?.addEventListener('hide.bs.modal', event => {
            const form = modalElement().querySelector('form');
            if (form.dataset.busy === 'true' || (form.dataset.dirty === 'true' && !window.confirm('Discard the unsaved facility changes?'))) event.preventDefault();
        });
        modalElement()?.addEventListener('hidden.bs.modal', () => { modalElement().querySelector('form').dataset.dirty = 'false'; });
        modalElement()?.addEventListener('shown.bs.modal', () => modalElement().querySelector('[name="amenity_type"]').focus());
    }
    window.addEventListener('beforeunload', event => {
        if ([...document.querySelectorAll('[data-facility-form]')].some(form => form.dataset.dirty === 'true')) { event.preventDefault(); event.returnValue = ''; }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
