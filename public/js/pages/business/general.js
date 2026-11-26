// The "Create ticket" support modal (#support), available on every business page: topics, a rich-text
// description, optional files, then a link to the new ticket under My support tickets.
$(document).ready(function () {
    var modal = document.getElementById('support');
    if (!modal) return;

    var $topic = $("select[name='support_topic']");
    var $custom = $('#custom_topic_container');
    var button = document.getElementById('kt_button_ticket');
    var editor = null;
    var uppy = null;
    var fileRules = { extensions: [], max_bytes: 10 * 1024 * 1024, max_files: 5 };

    function alertBox(text, icon) {
        return Swal.fire({ text: text, icon: icon || 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
    }

    // Topics and the file rules come from the server, so they always match what it accepts.
    $.get('/support').done(function (response) {
        (response.topics || []).forEach(function (t) {
            $topic.append($('<option>', { value: t.id, text: t.topic, 'data-is-other': t.is_other ? 'true' : 'false' }));
        });
        if (response.attachments) {
            fileRules = response.attachments;
            $('[data-support-file-note]').text('Up to ' + fileRules.max_files + ' files, ' + Math.round(fileRules.max_bytes / 1048576) + ' MB each: ' + fileRules.extensions.join(', ').replace(/\./g, '').toUpperCase() + '.');
        }
    });

    $topic.select2({ dropdownParent: $('#support') });
    $topic.on('change', function () {
        var isOther = $(this).find('option:selected').data('is-other') === true || $(this).find('option:selected').attr('data-is-other') === 'true';
        $custom.toggle(isOther);
        if (!isOther) $('#custom_topic_input').val('');
    });

    function initEditor() {
        var el = document.getElementById('support_editor');
        if (editor || !el || typeof ClassicEditor === 'undefined') return;
        ClassicEditor.create(el, {
            toolbar: { items: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', '|', 'blockQuote', 'undo', 'redo'], shouldNotGroupWhenFull: true }
        }).then(function (instance) { editor = instance; }).catch(function () { /* the plain textarea still works */ });
    }

    function initUppy() {
        if (uppy || !document.getElementById('support-uppy-dashboard') || typeof Uppy === 'undefined') return;
        uppy = new Uppy.Core({
            id: 'support-uppy',
            autoProceed: false,
            restrictions: { maxFileSize: fileRules.max_bytes, allowedFileTypes: fileRules.extensions.length ? fileRules.extensions : null, maxNumberOfFiles: fileRules.max_files }
        });
        uppy.use(Uppy.Dashboard, {
            target: '#support-uppy-dashboard', inline: true, height: 220, hideUploadButton: true,
            proudlyDisplayPoweredByUppy: false, note: 'Optional: screenshots or documents that show the problem.'
        });
        uppy.use(Uppy.XHRUpload, {
            endpoint: '/support-upload-files', formData: true, fieldName: 'files[]',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });
    }

    modal.addEventListener('shown.bs.modal', function () {
        initEditor();
        initUppy();
    });

    function reset() {
        $('#kt_modal_create_ticket')[0].reset();
        $topic.val('').trigger('change');
        if (editor) editor.setData('');
        if (uppy) uppy.cancelAll();
    }

    function busy(on) {
        if (!button) return;
        if (on) { button.setAttribute('data-kt-indicator', 'on'); button.disabled = true; }
        else { button.removeAttribute('data-kt-indicator'); button.disabled = false; }
    }

    function done(response, uploadNote) {
        busy(false);
        reset();
        bootstrap.Modal.getOrCreateInstance(modal).hide();
        Swal.fire({
            html: $('<div>').text(response.message + (uploadNote ? ' ' + uploadNote : '')).html(),
            icon: uploadNote ? 'warning' : 'success',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'View ticket',
            cancelButtonText: 'Close',
            customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' }
        }).then(function (result) {
            if (result.isConfirmed && response.url) window.location.href = response.url;
        });
    }

    $(button).on('click', function (e) {
        e.preventDefault();
        var description = editor ? editor.getData() : $('#support_editor').val();
        var values = {
            support_topic: $topic.val(),
            support_description: description,
            custom_topic: $('#custom_topic_input').val()
        };
        if (!values.support_topic) return alertBox('Choose a topic.');
        if (!$('<div>').html(description || '').text().trim()) return alertBox('Describe what you need help with.');
        if ($custom.is(':visible') && !values.custom_topic.trim()) return alertBox('Say what the topic is.');

        busy(true);
        $.ajax({
            url: '/support', type: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content'), values: values }
        }).done(function (response) {
            if (!uppy || !uppy.getFiles().length) return done(response);
            uppy.setMeta({ ticket_id: response.ticket_id });
            uppy.upload().then(function (result) {
                done(response, result.failed && result.failed.length ? 'Some files could not be added; you can add them from the ticket.' : '');
            }).catch(function () {
                done(response, 'The files could not be added; you can add them from the ticket.');
            });
        }).fail(function (xhr) {
            busy(false);
            var r = xhr.responseJSON || {};
            var first = r.errors ? Object.values(r.errors)[0] : null;
            alertBox((first && first[0]) || r.message || 'The ticket could not be sent. Please try again.');
        });
    });
});
