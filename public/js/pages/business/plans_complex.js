$(document).ready(function() {
    var delete_images = document.querySelectorAll(".delete_image");
    var delete_selected_images = document.querySelector("#delete_selected_images");
    var dragDropArea = $('#drag-drop-area');
    var complex_id = dragDropArea.attr('class');

    var uppy = new Uppy.Core({
        allowMultipleUploadBatches: true,
        restrictions: {
            maxFileSize: 100000000,
            // maxNumberOfFiles: 8,
            minNumberOfFiles: 1,
            allowedFileTypes: ['image/*', '.jpg', '.jpeg', '.png']
        },
        meta: {
            complex_id: complex_id
        }
    })
    .use(Uppy.Dashboard, {
        inline: true,
        target: "#drag-drop-area"
    })
    .use(Uppy.XHRUpload, {
        endpoint: '/plans_complex',
        formData: true,
        bundle: true,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        metaFields: ['complex_id']
    });

    uppy.on('upload-success', (file, response) => {
        location.reload(); // reload the page
    });

    $("#selectButton").click(function() {
        var $inputs = $("input[type='checkbox']");
        var allChecked = $inputs.length === $inputs.filter(":checked").length;
        $inputs.prop("checked", !allChecked);
        toggleDeleteButtonVisibility();
    });
    
    $("input[type='checkbox']").click(function() {
        toggleDeleteButtonVisibility();
    });
    
    function toggleDeleteButtonVisibility() {
        var $deleteButton = $("#deleteButton");
        var anyChecked = $("input[type='checkbox']:checked").length > 0;
        if (anyChecked) {
            $deleteButton.show();
        } else {
            $deleteButton.hide();
        }
    }
    
    toggleDeleteButtonVisibility();

    delete_images.forEach(function(button) {
        button.addEventListener("click", function() {
            // Activate indicator
            button.setAttribute("data-kt-indicator", "on");

            // Disable indicator after 3 seconds
            setTimeout(function() {
            button.removeAttribute("data-kt-indicator");
            }, 3000);
        });
    });

    delete_selected_images?.addEventListener("click", function() {

        delete_selected_images.setAttribute("data-kt-indicator", "on");

        var checkedBoxes = $("input[type='checkbox']:checked");
        var values = checkedBoxes.map(function() {
            return this.value;
        }).get();

        var token = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: '/bulk/complex/plans/delete',
            type: 'POST',
            data: { '_token' : token, values},
            success: function(response) 
            {
                if(response.status == 200)
                    Swal.fire({
                        text: "Success, "+response.message,
                        icon: "success",
                        buttonsStyling: !1,
                        confirmButtonText: "Ok, got it!",
                        customClass: {
                            confirmButton: "btn btn-light"
                        }
                    }).then((function() {
                        delete_selected_images.removeAttribute("data-kt-indicator");
                        $('#delete-selected').modal('hide');
                        location.reload(); // reload the page
                    }));
            },
            error:function (e) {
                Swal.fire({
                    text: "Sorry, looks like there are some errors detected, please try again.",
                    icon: "error",
                    buttonsStyling: !1,
                    confirmButtonText: "Ok, got it!",
                    customClass: {
                        confirmButton: "btn btn-light"
                    }
                }).then((function() {
                    delete_selected_images.removeAttribute("data-kt-indicator");
                }));
            }
        });

    });
});
