$(document).ready(function() {
    // Function to setup floor interaction
    function setupFloorInteraction() {
        const floorRadios = $('input[type="radio"][name="floors"]');
        const customFloorsInput = $('input[name="custom_floors"]');
        
        // When a floor radio is selected, clear custom floors input
        floorRadios.on('change', function() {
            if (this.checked) {
                customFloorsInput.val('');
            }
        });
        
        // When custom floors input is focused or typed in, deselect all floor radios
        customFloorsInput.on('focus input keyup', function() {
            if ($(this).val().trim() !== '') {
                floorRadios.prop('checked', false);
            }
        });
    }

    // Initialize floor interaction
    setupFloorInteraction();
    
    // Load complex info on page load if complex_id exists
    const complexId = $('input[name="complex_id"]').val();
    if (complexId) {
        fetchComplexInfo(complexId);
    }

    // Handle form submission
    $('#submit_commercial_unit_edit').on('click', function(e) {
        e.preventDefault();
        
        const button = this;
        const form = $('#kt_commercial_unit_edit_form');
        
        // Validation
        let isValid = true;
        let errorMessages = [];
        
        // Required field validation
        const unitNumber = $('input[name="unit_number"]').val();
        const unitSqFt = $('input[name="unit_sq_ft"]').val();
        const maxSubdivisions = $('input[name="max_subdivisions"]').val();
        const minSqFt = $('input[name="min_sq_ft"]').val();
        const status = $('input[name="status"]:checked').val();
        
        // Floor validation (either radio or custom floor)
        const selectedFloor = $('input[name="floors"]:checked').val();
        const customFloor = $('input[name="custom_floors"]').val();
        
        // Check required fields
        if (!unitNumber || unitNumber.trim() === '') {
            isValid = false;
            errorMessages.push('Unit Number/ID is required');
        }
        
        if (!unitSqFt || parseFloat(unitSqFt) <= 0) {
            isValid = false;
            errorMessages.push('Unit Square Footage is required and must be greater than 0');
        }
        
        if (maxSubdivisions === '' || parseInt(maxSubdivisions) < 0) {
            isValid = false;
            errorMessages.push('Maximum Subdivisions is required and must be 0 or greater');
        }
        
        if (!minSqFt || parseFloat(minSqFt) <= 0) {
            isValid = false;
            errorMessages.push('Minimum Square Footage is required and must be greater than 0');
        }
        
        if (!selectedFloor && (!customFloor || customFloor.trim() === '')) {
            isValid = false;
            errorMessages.push('Please select a floor or enter a custom floor');
        }
        
        if (!status) {
            isValid = false;
            errorMessages.push('Unit Status is required');
        }
        
        // Additional validation: min_sq_ft should not be greater than unit_sq_ft
        if (unitSqFt && minSqFt && parseFloat(minSqFt) > parseFloat(unitSqFt)) {
            isValid = false;
            errorMessages.push('Minimum Square Footage cannot be greater than Unit Square Footage');
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
        
        // Submit form
        form.submit();
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
            <p class="text-muted fs-7 mb-0">Complex Reference: <span class="badge badge-light-primary">${complex.ref_code || 'N/A'}</span></p>
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
                    <td class="text-center"><span class="badge badge-light-dark">${unit.unit_count}</span></td>
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
