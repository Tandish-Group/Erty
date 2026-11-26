/**
 * Service charges: add, edit and delete recurring facility charges for a property.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form_service_charge');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const facilityLabels = {};

    async function request(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success === false) {
            const errors = Object.values(result.errors || {}).flat();
            throw new Error(errors.join(' ') || result.message || 'The request could not be completed.');
        }
        return result;
    }

    if (form) {
        const complexSelect = form.querySelector('#service_complex');
        const facilitySelect = form.querySelector('#complex_amenitiy');
        const help = form.querySelector('[data-facility-help]');
        const error = form.querySelector('[data-charge-error]');
        const cancel = form.querySelector('[data-charge-cancel]');
        const title = document.querySelector('[data-charge-title]');
        const submit = form.querySelector('#kt_button_submit_service_charge');

        async function loadFacilities(complexId, selectedId) {
            facilitySelect.innerHTML = '<option value="">Loading…</option>';
            facilitySelect.disabled = true;
            if (!complexId) {
                facilitySelect.innerHTML = '<option value="">Choose a property first</option>';
                return;
            }
            try {
                const data = await request(`/complex-amenities/${encodeURIComponent(complexId)}/get-amenities?all=1`, 'GET');
                const facilities = data.amenities || [];
                facilitySelect.innerHTML = facilities.length ? '<option value="">Choose a facility</option>' : '<option value="">No facilities recorded for this property</option>';
                const counts = {};
                facilities.forEach(facility => { counts[facility.amenity_type] = (counts[facility.amenity_type] || 0) + 1; });
                const seen = {};
                facilities.forEach(facility => {
                    seen[facility.amenity_type] = (seen[facility.amenity_type] || 0) + 1;
                    const base = facility.amenity_type.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
                    const option = new Option(counts[facility.amenity_type] > 1 ? `${base} ${seen[facility.amenity_type]}` : base, facility.id);
                    facilityLabels[facility.id] = option.text;
                    facilitySelect.add(option);
                });
                facilitySelect.disabled = !facilities.length;
                if (selectedId) facilitySelect.value = String(selectedId);
                help.textContent = facilities.length
                    ? 'One charge per facility. Facilities are added on the property\'s Facilities tab.'
                    : 'Add a facility on the property\'s Facilities tab first.';
            } catch (e) {
                facilitySelect.innerHTML = '<option value="">Facilities could not be loaded</option>';
                help.textContent = e.message;
            }
        }

        function resetForm() {
            form.reset();
            form.elements.charge_id.value = '';
            title.textContent = 'Add a service charge';
            cancel.classList.add('d-none');
            error.classList.add('d-none');
            loadFacilities(complexSelect.value);
        }

        complexSelect.addEventListener('change', () => loadFacilities(complexSelect.value));
        cancel.addEventListener('click', resetForm);
        if (complexSelect.value) loadFacilities(complexSelect.value);

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-edit-charge]');
            if (!button) return;
            const charge = JSON.parse(button.dataset.editCharge);
            form.elements.charge_id.value = charge.id;
            complexSelect.value = String(charge.complex_id);
            form.elements.payment_interval.value = charge.payment_interval;
            form.elements.charge.value = charge.charge;
            title.textContent = 'Edit service charge';
            cancel.classList.remove('d-none');
            error.classList.add('d-none');
            loadFacilities(charge.complex_id, charge.amenity_id);
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            const id = form.elements.charge_id.value;
            const payload = {
                complex_id: Number(complexSelect.value),
                amenity_id: Number(facilitySelect.value),
                payment_interval: form.elements.payment_interval.value,
                charge: Number(form.elements.charge.value),
            };
            submit.disabled = true;
            error.classList.add('d-none');
            try {
                const result = await request(id ? `/service_charge/${id}` : '/service_charge', id ? 'PUT' : 'POST', payload);
                await Swal.fire({ text: result.message, icon: 'success', confirmButtonText: 'OK' });
                window.location.reload();
            } catch (e) {
                error.textContent = e.message;
                error.classList.remove('d-none');
            } finally {
                submit.disabled = false;
            }
        });
    }

    document.addEventListener('click', async event => {
        const button = event.target.closest('.delete-service-charge');
        if (!button) return;
        event.preventDefault();
        const confirmed = await Swal.fire({
            title: 'Delete this service charge?',
            text: 'Tenants will no longer be billed for it on new invoices. The property returns to setup review.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33',
        });
        if (!confirmed.isConfirmed) return;
        try {
            const result = await request(`/service-charge/${button.dataset.id}`, 'DELETE');
            await Swal.fire({ text: result.message, icon: 'success', confirmButtonText: 'OK' });
            window.location.reload();
        } catch (e) {
            Swal.fire({ text: e.message, icon: 'error', confirmButtonText: 'OK' });
        }
    });
});
