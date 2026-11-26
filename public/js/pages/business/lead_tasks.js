$(document).ready(function() {
    // Initialize Select2 for all select elements in modals
    if ($.fn.select2) {
        $('.modal select').each(function() {
            $(this).select2({
                dropdownParent: $(this).closest('.modal'),
                placeholder: 'Select an option',
                allowClear: false,
                width: '100%'
            });
        });
    }
    
    // Store task ID when modal opens
    $('.complete-task-btn').on('click', function() {
        var taskId = $(this).data('task-id');
        var modal = $($(this).data('bs-target'));
        modal.find('form').data('task-id', taskId);
    });

    // Re-initialize Select2 when modal is shown (in case of dynamic content)
    $('.modal').on('shown.bs.modal', function() {
        if ($.fn.select2) {
            $(this).find('select:not(.select2-hidden-accessible)').each(function() {
                $(this).select2({
                    dropdownParent: $(this).closest('.modal'),
                    placeholder: 'Select an option',
                    allowClear: false,
                    width: '100%'
                });
            });
        }
    });

    // Destroy Select2 when modal is hidden to prevent issues
    $('.modal').on('hidden.bs.modal', function() {
        $(this).find('select.select2-hidden-accessible').each(function() {
            $(this).select2('destroy');
        });
    });
    
    // Handle task completion form submissions
    $('.task-completion-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var taskId = form.data('task-id');
        var formData = form.serializeArray();
        var completionData = {};
        
        // Convert form data to object
        $.each(formData, function(i, field) {
            completionData[field.name] = field.value;
        });
        
        // Submit via AJAX
        $.ajax({
            url: `/agents/tasks/${taskId}/complete`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                completion_notes: JSON.stringify(completionData),
                completion_data: completionData
            },
            success: function(response) {
                // Close modal
                form.closest('.modal').modal('hide');
                
                // Show success message
                Swal.fire({
                    title: 'Success!',
                    text: 'Task completed successfully',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Reload page to show updated task status
                    location.reload();
                });
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to complete task: ' + (xhr.responseJSON?.message || 'Unknown error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
    
    // Handle start task
    $('.start-task').on('click', function() {
        var taskId = $(this).data('task-id');
        
        $.ajax({
            url: `/agents/tasks/${taskId}/start`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                toastr.success('Task started successfully');
                setTimeout(() => location.reload(), 1000);
            },
            error: function(xhr) {
                toastr.error('Failed to start task');
            }
        });
    });
});