/**
 * Team & roles: invite and manage team members, and edit role permissions.
 */
// Runs after the layout's scripts (Bootstrap, SweetAlert) have loaded.
document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('[data-team-page]');
    if (!page) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const template = document.getElementById('permission-matrix-template');

    async function request(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success === false) {
            throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The request could not be completed.');
        }
        return result;
    }

    function notify(text, icon = 'success') {
        return window.Swal ? Swal.fire({ text, icon, confirmButtonText: 'OK' }) : Promise.resolve(alert(text));
    }

    function mountMatrix(container, permissions) {
        container.innerHTML = '';
        container.appendChild(template.content.cloneNode(true));
        setMatrix(container, permissions || {});
        // Every action needs its area's "View": ticking one ticks View; unticking View clears the area.
        container.addEventListener('change', event => {
            const box = event.target.closest('[data-permission]');
            if (!box) return;
            const group = box.dataset.permission;
            const boxes = [...container.querySelectorAll(`[data-permission="${group}"]`)];
            const view = boxes.find(b => b.value === 'view');
            if (box.checked && box.hasAttribute('data-needs-view') && view && !view.disabled) view.checked = true;
            if (!box.checked && box === view) boxes.forEach(b => { if (!b.disabled) b.checked = false; });
            updatePreview(container);
        });
    }

    function setMatrix(container, permissions) {
        container.querySelectorAll('[data-permission]').forEach(box => {
            box.checked = (permissions[box.dataset.permission] || []).includes(box.value);
        });
        updatePreview(container);
    }

    // Which menu items the ticked permissions put in the person's menu, per section and in total.
    function updatePreview(container) {
        const held = new Set([...container.querySelectorAll('[data-permission]:checked')].map(b => `${b.dataset.permission}.${b.value}`));
        const names = [];
        container.querySelectorAll('[data-perm-section]').forEach(section => {
            const shows = JSON.parse(section.dataset.shows || '[]').some(right => held.has(right));
            section.classList.toggle('is-in-menu', shows);
            const badge = section.querySelector('[data-section-badge]');
            if (badge) badge.textContent = shows ? 'In menu' : 'Not in menu';
            if (shows) names.push(section.querySelector('header strong').textContent.trim());
        });
        const preview = container.querySelector('[data-menu-preview]');
        if (preview) preview.textContent = names.length ? names.join(' · ') : 'nothing but their own profile';
    }

    // The server normalises { group: { action: "on" } } against the permission catalogue.
    function readMatrix(container) {
        const permissions = {};
        container.querySelectorAll('[data-permission]:checked').forEach(box => {
            (permissions[box.dataset.permission] ||= {})[box.value] = 'on';
        });
        return permissions;
    }

    function showError(form, message) {
        const box = form.querySelector('[data-form-error]');
        box.textContent = message;
        box.classList.toggle('d-none', !message);
    }

    // ── Team members ────────────────────────────────────────────
    const memberModalEl = document.getElementById('member-modal');
    const memberForm = memberModalEl?.querySelector('[data-member-form]');
    const memberModal = memberModalEl ? new bootstrap.Modal(memberModalEl) : null;
    const memberMatrix = memberForm?.querySelector('[data-member-matrix]');
    const customToggle = memberForm?.querySelector('[data-custom-toggle]');
    const agentMode = memberForm?.querySelector('[data-agent-mode]');
    const agentCurrent = memberForm?.querySelector('[data-agent-current]');
    const agentLinkBox = memberForm?.querySelector('[data-agent-link]');
    const agentSearch = memberForm?.querySelector('[data-agent-search]');
    const propertiesField = memberForm?.querySelector('[data-properties-field]');
    const roleSelect = memberForm?.querySelector('[name="business_role_id"]');

    customToggle?.addEventListener('change', () => { memberMatrix.hidden = !customToggle.checked; });

    // Tell the user whether the chosen role is limited to the properties ticked here.
    function updateScopeHelp() {
        const help = propertiesField?.querySelector('[data-scope-help]');
        if (!help) return;
        const scope = roleSelect?.selectedOptions[0]?.dataset.scope;
        help.textContent = scope === 'assigned'
            ? 'This role only sees the properties selected here.'
            : (scope ? 'This role sees every property; selected ones are theirs to manage.' : '');
    }
    roleSelect?.addEventListener('change', updateScopeHelp);

    // Property picker: searched on the server, so it works for portfolios of any size.
    const propertySelect = memberForm?.querySelector('[data-properties-select]');
    const propertyCount = memberForm?.querySelector('[data-properties-count]');
    function updatePropertyCount() {
        if (!propertyCount || !propertySelect) return;
        const n = propertySelect.selectedOptions.length;
        propertyCount.textContent = n ? `(${n} selected)` : '';
    }
    if (propertySelect && window.jQuery && jQuery.fn.select2) {
        jQuery(propertySelect).select2({
            dropdownParent: jQuery(memberModalEl),
            width: '100%',
            placeholder: propertySelect.dataset.placeholder,
            minimumInputLength: 0,
            ajax: {
                url: propertySelect.dataset.url,
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term || '', page: params.page || 1 }),
                processResults: data => data,
            },
            templateResult: item => {
                if (!item.id) return item.text;
                const row = document.createElement('div');
                const title = document.createElement('div'); title.textContent = item.text; row.appendChild(title);
                if (item.detail) { const d = document.createElement('div'); d.className = 'text-muted fs-8'; d.textContent = item.detail; row.appendChild(d); }
                return row;
            },
        }).on('change', updatePropertyCount);
    }
    // CRM agent profile: keep / stop for current agents; create / link for everyone else.
    if (agentSearch && window.jQuery && jQuery.fn.select2) {
        jQuery(agentSearch).select2({
            dropdownParent: jQuery(memberModalEl), width: '100%', placeholder: agentSearch.dataset.placeholder,
            ajax: { url: agentSearch.dataset.url, dataType: 'json', delay: 250,
                data: params => ({ q: params.term || '', page: params.page || 1, unlinked: 1 }),
                processResults: data => ({ results: data.results || [], pagination: { more: !!(data.pagination && data.pagination.more) } }) },
        });
    }
    function setAgent(member) {
        if (!agentMode) return;
        const agent = member?.agent;
        const options = agent && agent.active
            ? [['', 'Keep as agent'], ['none', 'Stop working as an agent']]
            : [['', 'Not an agent'], ['create', agent ? 'Reactivate their agent profile' : 'Create a new agent profile'], ['link', 'Link an existing agent']];
        agentMode.replaceChildren(...options.map(([value, label]) => new Option(label, value)));
        agentCurrent.hidden = !agent;
        agentCurrent.textContent = agent ? `Linked to ${agent.name}${agent.active ? '' : ' (inactive)'}` : '';
        if (agentSearch) {
            agentSearch.innerHTML = '';
            if (member?.suggested_agent) agentSearch.appendChild(new Option(member.suggested_agent.name, member.suggested_agent.id, true, true));
            if (window.jQuery) jQuery(agentSearch).trigger('change');
        }
        // A matching unlinked agent (same email) is the likely choice.
        if (!agent && member?.suggested_agent) agentMode.value = 'link';
        agentLinkBox.hidden = agentMode.value !== 'link';
    }
    agentMode?.addEventListener('change', () => { agentLinkBox.hidden = agentMode.value !== 'link'; });

    function setProperties(list) {
        if (!propertySelect) return;
        propertySelect.innerHTML = '';
        (list || []).forEach(p => propertySelect.appendChild(new Option(p.title, p.id, true, true)));
        if (window.jQuery) jQuery(propertySelect).trigger('change'); else updatePropertyCount();
    }

    page.addEventListener('click', event => {
        const opener = event.target.closest('[data-open-member]');
        if (!opener || !memberForm) return;
        const member = opener.dataset.member ? JSON.parse(opener.dataset.member) : null;
        memberForm.reset();
        showError(memberForm, '');
        memberForm.elements.id.value = member?.id || '';
        memberForm.elements.first_name.value = member?.first_name || '';
        memberForm.elements.last_name.value = member?.last_name || '';
        memberForm.elements.email.value = member?.email || '';
        memberForm.elements.mobile.value = member?.mobile || '';
        memberForm.elements.email.readOnly = !!member;
        memberForm.querySelector('[data-email-help]').textContent = member ? 'The email address cannot be changed here.' : "They'll get a link to set their password.";
        memberForm.elements.business_role_id.value = member?.business_role_id || '';
        customToggle.checked = !!(member && member.permissions);
        setProperties(member?.complexes || []);
        setAgent(member);
        updateScopeHelp();
        mountMatrix(memberMatrix, member?.permissions || {});
        memberMatrix.hidden = !customToggle.checked;
        memberModalEl.querySelector('.modal-title').textContent = member ? 'Edit team member' : 'Invite team member';
        memberForm.querySelector('[data-submit]').textContent = member ? 'Save changes' : 'Send invitation';
        memberModal.show();
    });

    memberForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!memberForm.checkValidity()) { memberForm.reportValidity(); return; }
        const id = memberForm.elements.id.value;
        const payload = {
            first_name: memberForm.elements.first_name.value,
            last_name: memberForm.elements.last_name.value,
            email: memberForm.elements.email.value,
            mobile: memberForm.elements.mobile.value,
            business_role_id: Number(memberForm.elements.business_role_id.value),
            custom_permissions: customToggle.checked,
            permissions: customToggle.checked ? readMatrix(memberMatrix) : {},
        };
        // Only send what this person may change (the server checks the permissions again).
        if (propertySelect) payload.complex_ids = [...propertySelect.selectedOptions].map(option => Number(option.value));
        if (agentMode && agentMode.value) {
            payload.agent_mode = agentMode.value;
            if (agentMode.value === 'link') {
                payload.agent_id = Number(agentSearch?.value || 0);
                if (!payload.agent_id) { showError(memberForm, 'Choose the agent to link.'); return; }
            }
        }
        const submit = memberForm.querySelector('[data-submit]');
        submit.disabled = true;
        showError(memberForm, '');
        try {
            const result = await request(id ? `/business_account/team/${id}` : '/business_account/team/invite', id ? 'PUT' : 'POST', payload);
            memberModal.hide();
            await notify(result.message);
            location.reload();
        } catch (error) {
            showError(memberForm, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    page.addEventListener('click', async event => {
        const button = event.target.closest('[data-member-action]');
        if (!button) return;
        const { memberAction: action, id, name } = button.dataset;
        const confirmations = {
            suspend: `Suspend ${name}? They will be signed out and cannot sign in until reactivated.`,
            destroy: `Remove the invitation for ${name}?`,
        };
        if (confirmations[action] && window.Swal) {
            const answer = await Swal.fire({ text: confirmations[action], icon: 'warning', showCancelButton: true, confirmButtonText: action === 'destroy' ? 'Remove' : 'Suspend' });
            if (!answer.isConfirmed) return;
        }
        button.disabled = true;
        try {
            const url = action === 'destroy' ? `/business_account/team/${id}` : `/business_account/team/${id}/${action}`;
            const result = await request(url, action === 'destroy' ? 'DELETE' : 'POST');
            await notify(result.message);
            location.reload();
        } catch (error) {
            notify(error.message, 'error');
            button.disabled = false;
        }
    });

    // ── Roles ───────────────────────────────────────────────────
    const roleModalEl = document.getElementById('role-modal');
    const roleForm = roleModalEl?.querySelector('[data-role-form]');
    const roleModal = roleModalEl ? new bootstrap.Modal(roleModalEl) : null;
    const roleMatrix = roleForm?.querySelector('[data-role-matrix]');
    const templateSelect = roleForm?.querySelector('#role-template');

    page.addEventListener('click', event => {
        const opener = event.target.closest('[data-open-role]');
        if (!opener || !roleForm) return;
        const role = opener.dataset.role ? JSON.parse(opener.dataset.role) : null;
        roleForm.reset();
        showError(roleForm, '');
        roleForm.elements.id.value = role?.id || '';
        roleForm.elements.name.value = role?.name || '';
        roleForm.elements.description.value = role?.description || '';
        roleForm.elements.color.value = role?.color || 'primary';
        if (roleForm.elements.department) roleForm.elements.department.value = role?.department || '';
        const scopeSelect = roleForm.elements.property_scope;
        scopeSelect.value = role?.property_scope || (scopeSelect.querySelector('option[value="all"]').disabled ? 'assigned' : 'all');
        roleForm.querySelector('[data-template-field]').hidden = !!role;
        mountMatrix(roleMatrix, role?.permissions || {});
        roleModalEl.querySelector('.modal-title').textContent = role ? `Edit ${role.name}` : 'New role';
        roleModal.show();
    });

    templateSelect?.addEventListener('change', async () => {
        if (!templateSelect.value) { setMatrix(roleMatrix, {}); return; }
        try {
            const template = await request(`/business-roles/template/${encodeURIComponent(templateSelect.value)}`, 'GET');
            if (!roleForm.elements.name.value) roleForm.elements.name.value = template.display_name || '';
            if (!roleForm.elements.description.value) roleForm.elements.description.value = template.description || '';
            setMatrix(roleMatrix, template.permissions || {});
        } catch (error) {
            showError(roleForm, error.message);
        }
    });

    roleForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!roleForm.checkValidity()) { roleForm.reportValidity(); return; }
        const id = roleForm.elements.id.value;
        const payload = {
            name: roleForm.elements.name.value,
            description: roleForm.elements.description.value,
            color: roleForm.elements.color.value,
            permissions: readMatrix(roleMatrix),
            property_scope: roleForm.elements.property_scope.value,
            department: roleForm.elements.department ? roleForm.elements.department.value : undefined,
        };
        const submit = roleForm.querySelector('[data-submit]');
        submit.disabled = true;
        showError(roleForm, '');
        try {
            await request(id ? `/business-roles/${id}` : '/business-roles/store', id ? 'PUT' : 'POST', payload);
            roleModal.hide();
            await notify(id ? 'Role updated. People with this role get the new access on their next page load.' : 'Role created.');
            history.replaceState(null, '', location.pathname + '#team-roles');
            location.reload();
        } catch (error) {
            showError(roleForm, error.message);
        } finally {
            submit.disabled = false;
        }
    });

    page.addEventListener('click', async event => {
        const button = event.target.closest('[data-role-action]');
        if (!button) return;
        const { roleAction: action, id, name } = button.dataset;
        if (action === 'delete' && window.Swal) {
            const answer = await Swal.fire({ text: `Delete the ${name} role?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
            if (!answer.isConfirmed) return;
        }
        button.disabled = true;
        try {
            const result = await request(`/business-roles/${id}${action === 'toggle' ? '/toggle' : ''}`, action === 'toggle' ? 'PATCH' : 'DELETE');
            await notify(result.message || 'Role updated.');
            history.replaceState(null, '', location.pathname + '#team-roles');
            location.reload();
        } catch (error) {
            notify(error.message, 'error');
            button.disabled = false;
        }
    });

    // Open the Roles tab when linked directly.
    if (location.hash === '#team-roles') {
        const tab = document.querySelector('a[href="#team-roles"]');
        if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
    }
});

// Team members: search and filter the list (name, email, role; department; status).
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('[data-members-table]');
    if (!table) return;
    const search = document.querySelector('[data-team-search]');
    const filters = [...document.querySelectorAll('[data-team-filter]')];
    const count = document.querySelector('[data-team-count]');
    const empty = table.querySelector('[data-team-empty]');
    const apply = () => {
        const q = (search?.value || '').trim().toLowerCase();
        const want = Object.fromEntries(filters.map(f => [f.dataset.teamFilter, f.value]));
        let shown = 0;
        table.querySelectorAll('tbody tr[data-member-row]').forEach(row => {
            const match = (!q || (row.dataset.search || '').includes(q))
                && (!want.department || row.dataset.department === want.department)
                && (!want.status || row.dataset.status === want.status);
            row.hidden = !match;
            if (match) shown++;
        });
        if (empty) empty.hidden = shown > 0;
        if (count) count.textContent = shown + (shown === 1 ? ' person' : ' people');
    };
    search?.addEventListener('input', apply);
    filters.forEach(f => f.addEventListener('change', apply));
});

