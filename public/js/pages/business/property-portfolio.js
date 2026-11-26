(() => {
    'use strict';
    const maps = new Map();
    const dirty = new Set();
    let navigating = false;
    const tileOptions = { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' };
    const valid = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
    const number = input => input.value.trim() === '' ? NaN : Number(input.value);
    const root = () => document.querySelector('[data-property-flow]');
    const hasChanges = () => [...dirty].some(form => form.isConnected);
    function message(container, text, error = false) {
        let box = container.querySelector('[data-live-message]');
        if (!box) {
            box = document.createElement('div');
            box.dataset.liveMessage = '';
            container.prepend(box);
        }
        box.className = `property-live-message alert alert-${error ? 'danger' : 'success'}`;
        box.setAttribute('role', error ? 'alert' : 'status');
        box.textContent = text;
        return box;
    }
    function initializeMaps(scope = document) {
        for (const [element, entry] of maps) {
            if (!element.isConnected) { entry.observer?.disconnect(); entry.map.remove(); maps.delete(element); }
        }
        scope.querySelectorAll('[data-location-picker], [data-property-map]').forEach(element => {
            if (maps.has(element)) return;
            const picker = element.hasAttribute('data-location-picker');
            const canvas = picker ? element.querySelector('[data-location-map]') : element;
            const status = picker ? element.querySelector('[data-location-status]') : null;
            if (!window.L) {
                if (status) status.textContent = 'The map could not load. You can still enter coordinates below.';
                else canvas.textContent = 'Map unavailable. The property address is shown below.';
                return;
            }
            const lat = picker ? element.querySelector('[data-coordinate="latitude"]') : null;
            const lng = picker ? element.querySelector('[data-coordinate="longitude"]') : null;
            const initialLat = picker ? number(lat) : (element.dataset.lat === '' ? NaN : Number(element.dataset.lat));
            const initialLng = picker ? number(lng) : (element.dataset.long === '' ? NaN : Number(element.dataset.long));
            const selected = valid(initialLat, initialLng);
            const map = L.map(canvas, { scrollWheelZoom: false }).setView(selected ? [initialLat, initialLng] : [-1.286389, 36.817223], selected ? 17 : 6);
            const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', tileOptions).addTo(map);
            tiles.on('tileerror', () => { if (status) status.textContent = 'Map tiles are unavailable. Retry later or enter exact coordinates below.'; });
            let marker;
            function pin(latitude, longitude, changed = false) {
                if (!valid(latitude, longitude)) return;
                if (!marker) {
                    marker = L.marker([latitude, longitude], { draggable: picker, keyboard: true, title: 'Property location', icon: L.divIcon({ className: '', html: '<div class="property-pin"></div>', iconSize: [24, 30], iconAnchor: [12, 30] }) }).addTo(map);
                    if (picker) marker.on('dragend', () => { const point = marker.getLatLng().wrap(); pin(point.lat, point.lng, true); });
                } else marker.setLatLng([latitude, longitude]);
                if (picker) {
                    if (changed) {
                        lat.value = latitude.toFixed(6); lng.value = longitude.toFixed(6);
                        lat.dispatchEvent(new Event('input', { bubbles: true }));
                        lng.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    status.textContent = changed || element.dataset.locationSaved !== 'true' ? 'Location selected. Save the property to keep this pin.' : 'Saved location. Click the map or drag the pin to change it.';
                }
            }
            if (selected) pin(initialLat, initialLng);
            if (picker) {
                map.on('click', event => { const point = event.latlng.wrap(); pin(point.lat, point.lng, true); });
                [lat, lng].forEach(input => input.addEventListener('change', () => {
                    if (valid(number(lat), number(lng))) { pin(number(lat), number(lng)); map.setView([number(lat), number(lng)], 17); status.textContent = 'Coordinates updated. Save the property to keep this location.'; }
                    else { if (marker) { map.removeLayer(marker); marker = null; } status.textContent = 'Enter both coordinates within the allowed ranges.'; }
                }));
                element.querySelector('[data-locate]').addEventListener('click', function () {
                    if (!navigator.geolocation) { status.textContent = 'Location is unavailable in this browser. Place the pin on the map.'; return; }
                    this.disabled = true; status.textContent = 'Finding your current location…';
                    navigator.geolocation.getCurrentPosition(position => {
                        this.disabled = false;
                        if (!element.isConnected) return;
                        pin(position.coords.latitude, position.coords.longitude, true);
                        map.setView([position.coords.latitude, position.coords.longitude], 18);
                        status.textContent = 'Check that this is the property entrance, then save the location.';
                    }, () => { this.disabled = false; status.textContent = 'Your location could not be accessed. Click the property on the map or enter coordinates.'; }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                });
            }
            const observer = window.ResizeObserver ? new ResizeObserver(() => { if (canvas.clientWidth && canvas.clientHeight) map.invalidateSize(); }) : null;
            observer?.observe(canvas);
            maps.set(element, { map, observer });
        });
    }
    async function fetchPage(url) {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('The page could not be updated. Try again.');
        return { document: new DOMParser().parseFromString(await response.text(), 'text/html'), url: response.url };
    }
    function replacePart(page, selector) {
        const current = document.querySelector(selector), fresh = page.querySelector(selector);
        if (!current || !fresh) throw new Error('The updated page is unavailable. Refresh to continue.');
        current.replaceWith(fresh);
        return fresh;
    }
    async function navigate(url, { replace = false, savedMessage = '', selector = '[data-property-flow]', pop = false } = {}) {
        if (navigating) return;
        const current = document.querySelector(selector);
        if (!current) { location.assign(url); return; }
        navigating = true; current.setAttribute('aria-busy', 'true');
        try {
            const page = await fetchPage(url);
            if (!page.document.querySelector(selector)) {
                // Full reload: the prefetch consumed the server's flash, so carry the message across.
                if (savedMessage) { try { sessionStorage.setItem('erty_flash', savedMessage); } catch (e) {} }
                location.assign(page.url); return;
            }
            const updated = replacePart(page.document, selector);
            dirty.clear(); initializeMaps();
            const stepLink = updated.querySelector('.property-step-nav [aria-current="step"]');
            if (stepLink && innerWidth < 992) stepLink.parentElement.scrollLeft = Math.max(0, stepLink.offsetLeft - stepLink.parentElement.offsetLeft);
            if (!pop) history[replace ? 'replaceState' : 'pushState']({ propertyLive: true, selector }, '', url);
            if (savedMessage) message(updated, savedMessage);
            if (selector === '[data-property-flow]') {
                const heading = updated.querySelector('h1, h2');
                if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }); }
                updated.scrollIntoView({ block: 'start', behavior: 'instant' });
            }
        } finally { navigating = false; current.removeAttribute('aria-busy'); }
    }
    function showPropertyTab(hash) {
        const tab = [...document.querySelectorAll('.property-tab-navigation [data-bs-toggle="tab"]')].find(link => link.getAttribute('href') === hash);
        if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-property-tab]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        showPropertyTab(link.getAttribute('href'));
        document.querySelector('.property-tab-navigation')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
    document.addEventListener('change', event => {
        if (event.target.matches('[data-property-tab-select]')) showPropertyTab(event.target.value);
    });
    document.addEventListener('shown.bs.tab', event => {
        if (!event.target.closest('.property-tab-navigation')) return;
        const hash = event.target.getAttribute('href');
        const select = document.querySelector('[data-property-tab-select]');
        if (select) select.value = hash;
        history.replaceState(history.state, '', location.pathname + location.search + hash);
        for (const { map } of maps.values()) map.invalidateSize();
    });
    document.addEventListener('input', event => {
        const form = event.target.closest('form[data-live-form]');
        if (!form) return;
        dirty.add(form);
        const state = form.querySelector('[data-save-state]');
        if (state) state.textContent = 'Unsaved changes';
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('form[data-live-form]');
        if (form) dirty.add(form);
        if (event.target.name === 'decision') {
            const note = form?.querySelector('[name="note"]');
            if (note) note.required = event.target.value === 'deferred';
        }
    });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (form.matches('[data-live-filter]')) {
            event.preventDefault();
            const url = new URL(form.action); url.search = new URLSearchParams(new FormData(form)).toString();
            try { await navigate(url.href, { selector: form.dataset.liveTarget || '[data-portfolio-results]' }); } catch (error) { message(form, error.message, true); }
            return;
        }
        if (!form.matches('[data-live-form]')) return;
        event.preventDefault();
        if (form.dataset.saving === 'true' || navigating) return;
        if ([...dirty].some(other => other !== form && other.isConnected)) {
            message(form, 'Save your other changes on this page before continuing.', true); return;
        }
        const payload = new FormData(form);
        const controls = [...form.querySelectorAll('input, select, textarea, button')].filter(control => !control.disabled);
        controls.forEach(control => { control.disabled = true; });
        form.dataset.saving = 'true'; form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        message(form, 'Saving…');
        let saved = false;
        try {
            const response = await fetch(form.action, { method: 'POST', body: payload, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Your session may have expired. Refresh before trying again.');
            const result = await response.json();
            if (!response.ok || !result.success) {
                for (const field of Object.keys(result.errors || {})) {
                    const input = form.elements.namedItem(field);
                    if (input?.setAttribute) input.setAttribute('aria-invalid', 'true');
                }
                throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The changes could not be saved.');
            }
            saved = true; dirty.delete(form);
            if (form.hasAttribute('data-refresh-details')) {
                const page = await fetchPage(location.href);
                // Update only the property summary, overview and focused editor; keep existing tabs and other tools mounted.
                for (const selector of ['[data-property-summary]', '[data-property-overview]', '[data-property-editor]']) replacePart(page.document, selector);
                initializeMaps();
                document.querySelectorAll('[data-readiness-warning]').forEach(warning => { warning.hidden = false; });
                message(document.querySelector('[data-property-editor]'), result.message);
            } else {
                await navigate(form.dataset.saveStay ? location.href : result.redirect, { replace: !!form.dataset.saveStay, savedMessage: result.message });
            }
        } catch (error) {
            message(form, saved ? 'Your changes were saved, but the display could not refresh. Reload this page to see them; do not submit again.' : error.message, true);
            if (saved) { form.dataset.saving = 'true'; }
        } finally {
            if (!saved || !form.isConnected) controls.forEach(control => { control.disabled = false; });
            if (!saved) form.dataset.saving = 'false';
            form.removeAttribute('aria-busy');
        }
    });
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.href === location.href || link.getAttribute('href').startsWith('#')) return;
        const flowLink = link.closest('[data-property-flow]') && (link.matches('[data-flow-link]') || link.closest('.pagination'));
        const portfolioLink = link.matches('[data-portfolio-link]');
        const inventoryLink = link.closest('[data-inventory-results]') && (link.matches('[data-inventory-link]') || link.closest('.pagination'));
        const inventorySelector = inventoryLink ? '#' + link.closest('[data-inventory-results]').id : null;
        if (hasChanges() && !window.confirm('Leave this page and discard unsaved changes?')) { event.preventDefault(); return; }
        if (!flowLink && !portfolioLink && !inventoryLink) return;
        event.preventDefault();
        try { await navigate(url.href, { selector: inventorySelector || (portfolioLink ? '[data-portfolio-results]' : '[data-property-flow]') }); }
        catch (error) { message(document.querySelector(inventorySelector || '[data-property-flow], [data-portfolio-results]'), error.message, true); }
    });
    window.addEventListener('beforeunload', event => { if (hasChanges()) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('popstate', async event => {
        if (!root() && !document.querySelector('[data-portfolio-results], [data-inventory-results]')) return;
        // Native reload restores the correct server page if there are unsaved edits or the live region changed.
        if (hasChanges()) { location.reload(); return; }
        if (!event.state?.selector && document.querySelector('[data-inventory-results]')) { location.reload(); return; }
        try { await navigate(location.href, { pop: true, selector: event.state?.selector || (root() ? '[data-property-flow]' : '[data-portfolio-results]') }); }
        catch { location.reload(); }
    });
    function init() { initializeMaps(); showPropertyTab(location.hash); if (root() || document.querySelector('[data-portfolio-results]')) history.replaceState({ propertyLive: true, selector: root() ? '[data-property-flow]' : '[data-portfolio-results]' }, '', location.href); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
