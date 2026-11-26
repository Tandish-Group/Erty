document.addEventListener('DOMContentLoaded', function() {
    // Toggle follow-up date section
    document.getElementById('requires_followup')?.addEventListener('change', function() {
        const followupSection = document.getElementById('followup_date_section');
        if (this.checked) {
            followupSection.classList.remove('d-none');
        } else {
            followupSection.classList.add('d-none');
        }
    });
});

// Handle status update form
$(document).on('submit', '#kt_modal_update_status_form', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitButton = form.find('button[type="submit"]');
    const leadId = form.find('input[name="lead_id"]').val();
    
    // Set loading state
    submitButton.attr('data-kt-indicator', 'on');
    submitButton.prop('disabled', true);
    
    $.ajax({
        url: `/customer_relationship/lead_management/${leadId}/update-status`,
        method: 'POST',
        data: form.serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            Swal.fire({
                text: "Lead status updated successfully!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            }).then(function() {
                location.reload();
            });
        },
        error: function(xhr) {
            Swal.fire({
                text: xhr.responseJSON?.message || "Error updating status",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        },
        complete: function() {
            submitButton.removeAttr('data-kt-indicator');
            submitButton.prop('disabled', false);
        }
    });
});

// Handle add interaction form
$(document).on('submit', '#kt_modal_add_interaction_form', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitButton = form.find('button[type="submit"]');
    const leadId = form.find('input[name="lead_id"]').val();
    
    // Set loading state
    submitButton.attr('data-kt-indicator', 'on');
    submitButton.prop('disabled', true);
    
    $.ajax({
        url: `/customer_relationship/lead_management/${leadId}/add-interaction`,
        method: 'POST',
        data: form.serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            Swal.fire({
                text: "Interaction added successfully!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            }).then(function() {
                location.reload();
            });
        },
        error: function(xhr) {
            Swal.fire({
                text: xhr.responseJSON?.message || "Error adding interaction",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        },
        complete: function() {
            submitButton.removeAttr('data-kt-indicator');
            submitButton.prop('disabled', false);
        }
    });
});