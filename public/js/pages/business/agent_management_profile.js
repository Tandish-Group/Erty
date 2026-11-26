function updateAgentStatus(agentId, status) {
    Swal.fire({
        title: 'Update Agent Status?',
        text: `Are you sure you want to mark this agent as ${status.replace('_', ' ')}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, update it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // AJAX call to update agent status
            $.ajax({
                url: `/agents/${agentId}/status`,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    status: status
                },
                success: function(response) {
                    Swal.fire('Updated!', 'Agent status has been updated.', 'success').then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    Swal.fire('Error!', 'Failed to update agent status.', 'error');
                }
            });
        }
    });
}