/**
 * Legal documents: folder filter and search, new/rename folder, upload (one request per file), move and delete.
 * The page is server-rendered; changes reload it.
 */
(function () {
    'use strict';

    const page = document.querySelector('.legal-library');
    if (!page) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const reload = () => window.location.reload();

    async function send(url, method, body) {
        const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (!(body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, { method: method, headers: headers, body: body instanceof FormData ? body : JSON.stringify(body || {}) });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || (data.status && data.status >= 400)) {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : null;
            throw new Error(errors || data.message || 'Something went wrong. Please try again.');
        }
        return data;
    }

    function setError(form, message) {
        const box = form.querySelector('[data-form-error]');
        if (box) box.textContent = message || '';
    }

    function busy(form, on) {
        form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = on; });
    }

    /* ── Folder filter and search ─────────────────────────────── */

    const rows = Array.from(page.querySelectorAll('[data-document]'));
    const emptyRow = page.querySelector('[data-documents-empty]');
    const title = page.querySelector('[data-folder-title]');
    const search = document.getElementById('legal-search');
    let folder = '';

    function applyFilter() {
        const term = (search ? search.value : '').trim().toLowerCase();
        let shown = 0;
        rows.forEach((row) => {
            const visible = (!folder || row.dataset.folderId === folder) && (!term || row.dataset.search.includes(term));
            row.hidden = !visible;
            if (visible) shown++;
        });
        if (emptyRow) {
            emptyRow.hidden = shown > 0;
            const text = emptyRow.querySelector('[data-empty-text]');
            if (text && rows.length) text.textContent = term ? 'No documents match your search.' : 'This folder is empty. Upload documents into it.';
        }
    }

    page.querySelectorAll('[data-folder-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            folder = button.dataset.folderFilter;
            page.querySelectorAll('[data-folder-filter]').forEach((b) => {
                const on = b === button;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on);
            });
            if (title) title.textContent = button.querySelector('span').textContent;
            applyFilter();
        });
    });
    if (search) search.addEventListener('input', applyFilter);

    /* ── New folder / rename ──────────────────────────────────── */

    const folderModal = document.getElementById('legal-folder-modal');
    const folderForm = document.getElementById('legal-folder-form');
    if (folderModal && folderForm) {
        folderModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const renaming = trigger && trigger.dataset.folderMode === 'rename';
            folderForm.dataset.mode = renaming ? 'rename' : 'create';
            folderForm.previous.value = renaming ? trigger.dataset.folderName : '';
            folderForm.name.value = renaming ? trigger.dataset.folderName : '';
            document.getElementById('legal-folder-title').textContent = renaming ? 'Rename folder' : 'New folder';
            folderForm.querySelector('[data-submit-label]').textContent = renaming ? 'Rename' : 'Create folder';
            setError(folderForm, '');
        });
        folderModal.addEventListener('shown.bs.modal', () => folderForm.name.focus());
        folderForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            setError(folderForm, '');
            busy(folderForm, true);
            try {
                if (folderForm.dataset.mode === 'rename') {
                    await send('/update/legal_folder', 'POST', { combinedObject: { previousFolderName: folderForm.previous.value, NewFolderName: folderForm.name.value } });
                } else {
                    await send('/legal', 'POST', { value: folderForm.name.value });
                }
                reload();
            } catch (e) {
                setError(folderForm, e.message);
                busy(folderForm, false);
            }
        });
    }

    /* ── Upload ───────────────────────────────────────────────── */

    const uploadModal = document.getElementById('legal-upload-modal');
    const uploadForm = document.getElementById('legal-upload-form');
    if (uploadModal && uploadForm) {
        const list = uploadForm.querySelector('[data-upload-list]');
        uploadModal.addEventListener('show.bs.modal', () => {
            uploadForm.reset();
            list.innerHTML = '';
            setError(uploadForm, '');
            if (folder) uploadForm.folder.value = folder;
        });
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            setError(uploadForm, '');
            const files = Array.from(uploadForm.files.files);
            if (!files.length) return;
            busy(uploadForm, true);
            list.innerHTML = '';
            let failed = 0;
            for (const file of files) {
                const item = document.createElement('li');
                item.textContent = file.name + ': uploading…';
                list.appendChild(item);
                const data = new FormData();
                data.append('file', file);
                data.append('values', JSON.stringify({ folder: uploadForm.folder.value, listing_type: uploadForm.type.value }));
                try {
                    await send('/upload/legal_document', 'POST', data);
                    item.textContent = file.name + ': uploaded';
                    item.className = 'is-done';
                } catch (e) {
                    failed++;
                    item.textContent = file.name + ': ' + e.message;
                    item.className = 'is-failed';
                }
            }
            if (!failed) {
                reload();
            } else {
                setError(uploadForm, failed === files.length ? 'Nothing was uploaded.' : 'Some files were not uploaded. The rest are saved; close this window to see them.');
                busy(uploadForm, false);
                uploadModal.addEventListener('hidden.bs.modal', reload, { once: true });
            }
        });
    }

    /* ── Move ─────────────────────────────────────────────────── */

    const moveModal = document.getElementById('legal-move-modal');
    const moveForm = document.getElementById('legal-move-form');
    if (moveModal && moveForm) {
        moveModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            moveForm.file_id.value = trigger.dataset.moveId;
            moveForm.querySelector('[data-move-label]').textContent = trigger.dataset.moveName;
            Array.from(moveForm.folder.options).forEach((option) => { option.disabled = option.value === trigger.dataset.moveFolder; });
            const firstOther = Array.from(moveForm.folder.options).find((option) => !option.disabled);
            if (firstOther) moveForm.folder.value = firstOther.value;
            setError(moveForm, '');
        });
        moveForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            busy(moveForm, true);
            try {
                await send('/move/legal_document', 'POST', { values: { file_id: moveForm.file_id.value, move_to_folder: moveForm.folder.value } });
                reload();
            } catch (e) {
                setError(moveForm, e.message);
                busy(moveForm, false);
            }
        });
    }

    /* ── Delete ───────────────────────────────────────────────── */

    function confirmDelete(title, text) {
        return Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel', confirmButtonColor: '#c2453b' })
            .then((result) => result.isConfirmed);
    }

    function failed(e) {
        Swal.fire({ icon: 'error', title: 'Not deleted', text: e.message });
    }

    page.addEventListener('click', async (event) => {
        const doc = event.target.closest('[data-delete-document]');
        if (doc) {
            const name = doc.dataset.deleteDocument;
            if (!(await confirmDelete('Delete this document?', name + ' will be deleted permanently.'))) return;
            try { await send('/delete/legal_document/' + encodeURIComponent(name), 'DELETE'); reload(); } catch (e) { failed(e); }
            return;
        }
        const folderButton = event.target.closest('[data-delete-folder]');
        if (folderButton) {
            const count = Number(folderButton.dataset.folderCount);
            const text = count ? 'The folder and its ' + count + ' document' + (count === 1 ? '' : 's') + ' will be deleted permanently.' : 'The empty folder will be deleted.';
            if (!(await confirmDelete('Delete "' + folderButton.dataset.folderName + '"?', text))) return;
            try { await send('/legal/' + folderButton.dataset.deleteFolder, 'DELETE'); reload(); } catch (e) { failed(e); }
        }
    });
})();
