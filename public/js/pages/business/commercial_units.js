$(document).ready(function() {

    $('[id*=units-drag-drop-area]').each(function(){
        
        var uppy = new Uppy.Core({
            allowMultipleUploadBatches: true,
            restrictions: {
                maxFileSize: 100000000,
                maxNumberOfFiles: 1,
                minNumberOfFiles: 1,
                allowedFileTypes: ['.xls', '.xlsx']
            }
        })
        .use(Uppy.Dashboard, {
            inline: true,
            target: "#"+$(this).attr('id')+"",
            note: 'Use the sample document as reference before uploading.',
            proudlyDisplayPoweredByUppy: false
        })
        .use(Uppy.XHRUpload, {
            endpoint: '/upload/units',
            formData: true,
            bundle: true,
            fieldName: 'file',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            getResponseError: (responseText) => {
                try { return new Error(JSON.parse(responseText).message || 'The import failed.'); }
                catch (e) { return new Error('The import failed. Check the file and try again.'); }
            },
        });

        uppy.on('upload-success', (file, response) => {
            Swal.fire({
                text: (response.body && response.body.message) || 'Commercial spaces imported.',
                icon: "success",
                buttonsStyling: !1,
                confirmButtonText: "Ok, got it!",
                customClass: { confirmButton: "btn btn-light" }
            }).then(() => window.location.reload());
        });

        uppy.on('upload-error', (file, error) => {
            Swal.fire({
                text: error.message,
                icon: "error",
                buttonsStyling: !1,
                confirmButtonText: "Fix the file",
                customClass: { confirmButton: "btn btn-light" }
            });
        });

    });

    // Initialize Select2 for complex selection (if on create page)
    if ($('#complex_select').length) {
        $('#complex_select').select2();
        
        // Handle complex selection change
        $('#complex_select').on('change', function() {
            const complexId = $(this).val();
            
            if (complexId) {
                // Fetch complex information
                fetchComplexInfo(complexId);
            } else {
                // Hide the complex breakdown if no complex selected
                hideCommercialUnitsComplexBreakdown();
            }
        });
        
        // Initialize the repeater
        let commercialSpaceCounter = 0;
        $('#kt_commercial_units_repeater').repeater({
            initEmpty: false,
            
            show: function () {
                // Each added space gets its own radio ids (labels otherwise point at the first space)
                // and starts as Available, like the first space.
                const item = this;
                const suffix = '_' + (++commercialSpaceCounter);
                $(item).find('input[type="radio"][id]').each(function () {
                    const oldId = this.id.replace(/_\d+$/, '');
                    $(item).find('label[for="' + this.id + '"]').attr('for', oldId + suffix);
                    this.id = oldId + suffix;
                });
                $(item).find('input[type="radio"][name*="[status]"][value="Available"]').prop('checked', true);
                $(this).slideDown();
                setupFloorInteraction(this);
            },
            
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            },
        
            ready: function() {
                // Setup floor interaction for existing items
                $('#kt_commercial_units_repeater [data-repeater-item]').each(function() {
                    setupFloorInteraction(this);
                });
            }
        });
    }

    // Function to setup floor interaction within each repeater item
    function setupFloorInteraction(item) {
        // Use more flexible selectors that account for FormRepeater's name changes
        const floorRadios = $(item).find('input[type="radio"][name*="floors"]');
        const customFloorsInput = $(item).find('input[name*="custom_floors"]');
        
        
        // When a floor radio is selected, clear custom floors input
        floorRadios.on('change', function() {
            if (this.checked) {
                customFloorsInput.val('');
            }
        });
        
        // When custom floors input is focused or typed in, deselect all floor radios
        customFloorsInput.on('focus input keyup', function() {
            console.log('Custom floors input event:', $(this).val());
            if ($(this).val().trim() !== '') {
                floorRadios.prop('checked', false);
            }
        });
    }
    
    // Submit commercial units
    $('#submit_commercial_units').on('click', function() {
        const button = this; // Get the actual DOM element
        const form = $('#kt_commercial_units_form');
        
        // Basic validation
        const complexSelect = $('#complex_select').val();
        if (!complexSelect) {
            Swal.fire({
                text: "Choose a property before adding commercial spaces.",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-light"
                }
            });
            return;
        }
        
        // Validate all repeater items
        let isValid = true;
        let errorMessages = [];
        
        $('#kt_commercial_units_repeater [data-repeater-item]').each(function(index) {
            const itemNumber = index + 1;
            const item = $(this);
            
            // Required field validation
            const unitNumber = item.find('input[name*="unit_number"]').val();
            const unitSqFt = item.find('input[name*="unit_sq_ft"]').val();
            const maxSubdivisions = item.find('input[name*="max_subdivisions"]').val();
            const minSqFt = item.find('input[name*="min_sq_ft"]').val();
            const status = item.find('input[name*="status"]:checked').val();
            
            // Floor validation (either radio or custom floor)
            const selectedFloor = item.find('input[name*="floors"]:checked').val();
            const customFloor = item.find('input[name*="custom_floors"]').val();
            
            // Check required fields
            if (!unitNumber || unitNumber.trim() === '') {
                isValid = false;
                errorMessages.push(`Space ${itemNumber}: Space number / reference is required`);
            }
            
            if (!unitSqFt || parseFloat(unitSqFt) <= 0) {
                isValid = false;
                errorMessages.push(`Space ${itemNumber}: Total area (sq ft) is required and must be greater than 0`);
            }
            
            if (maxSubdivisions === '' || parseInt(maxSubdivisions) < 0) {
                isValid = false;
                errorMessages.push(`Space ${itemNumber}: Maximum subdivisions is required and must be 0 or greater`);
            }
            
            if (!minSqFt || parseFloat(minSqFt) <= 0) {
                isValid = false;
                errorMessages.push(`Space ${itemNumber}: Minimum subdivision area (sq ft) is required and must be greater than 0`);
            }
            
            if (!selectedFloor && (!customFloor || customFloor.trim() === '')) {
                isValid = false;
                errorMessages.push(`Unit ${itemNumber}: Please select a floor or enter a custom floor`);
            }
            
            if (!status) {
                isValid = false;
                errorMessages.push(`Unit ${itemNumber}: Unit Status is required`);
            }
            
            // Additional validation: min_sq_ft should not be greater than unit_sq_ft
            if (unitSqFt && minSqFt && parseFloat(minSqFt) > parseFloat(unitSqFt)) {
                isValid = false;
                errorMessages.push(`Unit ${itemNumber}: Minimum Square Footage cannot be greater than Unit Square Footage`);
            }
        });
        
        // Check if at least one unit exists
        if ($('#kt_commercial_units_repeater [data-repeater-item]').length === 0) {
            isValid = false;
            errorMessages.push('Please add at least one commercial unit');
        }
        
        // Show validation errors
        if (!isValid) {
            const errorText = errorMessages.length > 3 
                ? errorMessages.slice(0, 3).join('<br>') + `<br>... and ${errorMessages.length - 3} more errors`
                : errorMessages.join('<br>');
                
            Swal.fire({
                title: "Validation Errors",
                html: errorText,
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-light"
                }
            });
            return;
        }
        
        // Activate indicator
        button.setAttribute("data-kt-indicator", "on");
        
        // Get form data
        const formData = new FormData(form[0]);
        
        // Log form data for debugging (remove in production)
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        
        // AJAX submission
        fetch('/commercial-units', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Remove indicator
            button.removeAttribute("data-kt-indicator");
            
            if (data.success) {
                Swal.fire({
                    text: "Commercial spaces saved to the property's inventory.",
                    icon: "success",
                    buttonsStyling: false,
                    confirmButtonText: "Ok, got it!",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                }).then(() => {
                    // Redirect to index page
                    window.location.href = data.redirect || '/commercial-units';
                });
            } else {
                let errorMessage = "Something went wrong. Please try again.";
                
                if (data.errors) {
                    // Handle validation errors from server
                    const serverErrors = Object.values(data.errors).flat();
                    errorMessage = serverErrors.length > 3 
                        ? serverErrors.slice(0, 3).join('<br>') + `<br>... and ${serverErrors.length - 3} more errors`
                        : serverErrors.join('<br>');
                } else if (data.message) {
                    errorMessage = data.message;
                }
                
                Swal.fire({
                    title: "Error",
                    html: errorMessage,
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok, got it!",
                    customClass: {
                        confirmButton: "btn btn-light"
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Remove indicator on error
            button.removeAttribute("data-kt-indicator");
            Swal.fire({
                text: "Something went wrong. Please try again.",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-light"
                }
            });
        });
    });
});


// Function to fetch and display complex information
function fetchComplexInfo(complexId) {
    $.ajax({
        url: '/complexes/' + complexId + '/info',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                displayCommercialUnitsComplexBreakdown(response.complex);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching complex info:', error);
        }
    });
}

// Function to display complex breakdown
function displayCommercialUnitsComplexBreakdown(complex) {
    const container = $('#commercial_units_complex_container');
    const infoDiv = $('#commercial_units_complex_info');
    
    // Clear existing content
    infoDiv.empty();
    
    // Build the complex information display
    let html = `
        <div class="col-12 mb-3">
            <h5 class="text-primary fw-bold mb-2">${complex.title}</h5>
            <p class="text-muted fs-7 mb-0">Property reference: <span class="badge badge-light-primary">${complex.ref_code || 'N/A'}</span></p>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                <span class="fw-bold">Location:</span>
            </div>
            <p class="text-muted ms-4">${complex.county || 'N/A'}${complex.sub_county ? ', ' + complex.sub_county : ''}</p>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-building text-primary me-2"></i>
                <span class="fw-bold">Type:</span>
            </div>
            <p class="text-muted ms-4">${complex.type || 'N/A'}</p>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-cube text-primary me-2"></i>
                <span class="fw-bold">Total Planned:</span>
            </div>
            <p class="text-muted ms-4">${complex.total_planned_units || 0} units</p>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-check-circle text-success me-2"></i>
                <span class="fw-bold">Created:</span>
            </div>
            <p class="text-muted ms-4">${complex.created_units_count || 0} units</p>
        </div>
    `;
    
    // Add unit specifications if available
    if (complex.unit_specifications && complex.unit_specifications.length > 0) {
        html += `
            <div class="col-12 mt-4">
                <h6 class="text-dark fw-bold mb-3">Unit Specifications:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>Type</th>
                                <th>Unit Details</th>
                                <th>Count</th>
                                <th>Size (sq ft)</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        complex.unit_specifications.forEach(function(unit) {
            const typeClass = unit.unit_category === 'apartment' ? 'info' : 'primary';
            
            html += `
                <tr>
                    <td><span class="badge badge-light-${typeClass}">${unit.unit_type_display}</span></td>
                    <td>${unit.display_name}</td>
                    <td><span class="badge badge-light-dark">${unit.unit_count}</span></td>
                    <td>${unit.sq_ft || 'N/A'}</td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
    
    infoDiv.html(html);
    container.slideDown(300);
}

// Function to hide complex breakdown
function hideCommercialUnitsComplexBreakdown() {
    $('#commercial_units_complex_container').slideUp(300, function() {
        $('#commercial_units_complex_info').empty();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const autoClickButton = document.getElementById('auto-click-button');
    if (autoClickButton) {
        autoClickButton.click();
    }
});
