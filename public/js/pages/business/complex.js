$(document).ready(function() {
    const selects = document.getElementsByTagName('select');
    var input = document.getElementById("Search");

    $('.hidden').remove();

    input?.addEventListener('keyup', () => {
        var filter = input.value.toLowerCase();
        var nodes = document.getElementsByClassName('grid-item');

        for (i = 0; i < nodes.length; i++) {
            if (nodes[i].innerText.toLowerCase().includes(filter)) {
                nodes[i].style.opacity = "1";
                nodes[i].style.order = "0";
            } else {
                nodes[i].style.opacity = "0";
                nodes[i].style.order = "1";
            }
        }
    });
    
    $('#download-complexes').on('click', function(e) {
        // Show success message
        Swal.fire({
            title: 'Download Started',
            text: 'Your complexes export is being prepared. The download should start shortly.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    });

    $('[id*=complex-expense-drag-drop-area]').each(function(){

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
            endpoint: 'upload/complex_transactions',
            formData: true,
            bundle: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
        });
        uppy.on('upload-success', (file, response) => {
            response.body.data.forEach(function (item, index) {
                Swal.fire({
                    text: "Success, your document data has been uploaded.",
                    icon: "success",
                    buttonsStyling: !1,
                    confirmButtonText: "Ok, got it!",
                    customClass: {
                        confirmButton: "btn btn-light"
                    }
                });
            });

        });

    });
    
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
            endpoint: 'upload/units',
            formData: true,
            bundle: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
        });

        uppy.on('upload-success', (file, response) => {
            response.body.data.forEach(function (item, index) {
                Swal.fire({
                    text: "Success, your document data has been uploaded.",
                    icon: "success",
                    buttonsStyling: !1,
                    confirmButtonText: "Ok, got it!",
                    customClass: {
                        confirmButton: "btn btn-light"
                    }
                });
            });

        });

    });

    // Enhanced Bulk Upload Modal functionality
    let currentUploadType = 'complexes';

    // Initialize Select2 and setup default selection when modal is shown
    $('#kt_modal_upload_complexes').on('shown.bs.modal', function() {
        $('#upload_type_select').select2({
            dropdownParent: $(this)
        });
        
        // Trigger the setup for the pre-selected "complexes" option
        setupUploadForType('complexes');
    });

    // Upload type selection handler
    $('#upload_type_select').on('select2:select', function(e) {
        const selectedType = e.params.data.id;
        setupUploadForType(selectedType);
    });

    $('#upload_type_select').on('select2:clear', function() {
        resetUploadModal();
    });

    function setupUploadForType(uploadType) {
        currentUploadType = uploadType;
        
        // Show instructions and sample download
        $('#upload_instructions').removeClass('d-none');
        $('#sample_download_section').removeClass('d-none');
        $('#upload_area_container').removeClass('d-none');

        // Configure based on upload type
        if (uploadType === 'complexes') {
            setupComplexesUpload();
        } else if (uploadType === 'service_charges') {
            setupServiceChargesUpload();
        } else if (uploadType === 'units') {
            setupUnitsUpload();
        }
    }

    function setupComplexesUpload() {
        $('#instruction_text').text('Upload an Excel file containing complex data. Make sure your file follows the sample template format.');
        $('#sample_download_link').attr('href', '/download/complex-sample').text('Download Complex Sample');
        $('#file_help_text').text('Upload Excel file with complex data. Use the sample template as reference.');
    }

    function setupServiceChargesUpload() {
        $('#instruction_text').text('Upload an Excel file containing service charges data. The file should include complex references, amenity types, and charge amounts.');
        $('#sample_download_link').attr('href', '/download/service-charges-sample').text('Download Service Charges Sample');
        $('#file_help_text').text('Upload Excel file with service charges data. Use the sample template as reference.');
    }

    function setupUnitsUpload() {
        $('#instruction_text').text('Upload an Excel file containing units data. The file should include complex references and unit details.');
        $('#sample_download_link').attr('href', '/download/unit-sample').text('Download Units Sample');
        $('#file_help_text').text('Upload Excel file with units data. Use the sample template as reference.');
    }

    // Handle form submission
    $('#bulk_upload_form').on('submit', function(e) {
        e.preventDefault();
        
        const fileInput = $('#upload_file')[0];
        const submitBtn = $('#upload_submit_btn');
        
        // Validate file selection
        if (!fileInput.files.length) {
            Swal.fire({
                title: 'No File Selected',
                text: 'Please select a file to upload.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validate file type
        const file = fileInput.files[0];
        const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/i)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Please select a valid Excel file (.xlsx or .xls).',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading state
        submitBtn.attr('data-kt-indicator', 'on');
        submitBtn.prop('disabled', true);

        // Prepare form data
        const formData = new FormData(this);
        
        // Determine endpoint based on upload type
        let endpoint;
        switch(currentUploadType) {
            case 'complexes':
                endpoint = '/upload/complexes';
                break;
            case 'service_charges':
                endpoint = '/upload/service_charges';
                break;
            case 'units':
                endpoint = '/upload/units';
                break;
            default:
                endpoint = '/upload/complexes';
        }

        // Submit via AJAX
        $.ajax({
            url: endpoint,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Remove loading state
                submitBtn.removeAttr('data-kt-indicator');
                submitBtn.prop('disabled', false);

                if (response.success !== false) {
                    let message = 'Upload completed successfully!';
                    
                    if (currentUploadType === 'service_charges' && response.count) {
                        message = `Successfully processed ${response.count} service charge records!`;
                    } else if (response.message) {
                        message = response.message;
                    }

                    showUploadSuccess(message);
                    
                    // Reset form
                    $('#bulk_upload_form')[0].reset();
                    
                    // Reload page after short delay
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    throw new Error(response.message || 'Upload failed');
                }
            },
            error: function(xhr) {
                // Remove loading state
                submitBtn.removeAttr('data-kt-indicator');
                submitBtn.prop('disabled', false);

                let errorMessage = 'Upload failed. Please try again.';
                
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        const errors = Object.values(xhr.responseJSON.errors).flat();
                        errorMessage = errors.join(', ');
                    }
                }

                Swal.fire({
                    title: 'Upload Error',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    function showUploadSuccess(message) {
        $('#upload_status').removeClass('d-none alert-danger').addClass('alert-success');
        $('#upload_message').text(message);
        
        // Hide upload area and show success message
        $('#upload_area_container').addClass('d-none');
        
        // Also show SweetAlert
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    function resetUploadModal() {
        $('#upload_instructions').addClass('d-none');
        $('#sample_download_section').addClass('d-none');
        $('#upload_area_container').addClass('d-none');
        $('#upload_status').addClass('d-none');
        
        // Reset form
        $('#bulk_upload_form')[0].reset();
        $('#upload_submit_btn').removeAttr('data-kt-indicator').prop('disabled', false);
    }

    // Reset modal when closed
    $('#kt_modal_upload_complexes').on('hidden.bs.modal', function() {
        $('#upload_type_select').val('complexes').trigger('change');
        resetUploadModal();
    });

});

document.addEventListener('DOMContentLoaded', function() {

    // Service Charges Modal functionality
    $('#kt_modal_add_service_charges').on('shown.bs.modal', function () {
        // Initialize Select2 for all dropdowns
        $(this).find('select').select2({
            dropdownParent: $(this)
        });

        const serviceComplex = $('#service_complex');
        const amenityDropdown = $('#complex_amenitiy');
        const serviceChargeInput = $('#service_charge');
        const paymentIntervalSelect = $('#payment_interval');
        const submitButton = $('#kt_button_submit_service_charge');
        const previewCard = $('#charge_preview');
        const loadingIndicator = $('#amenity_loading');
        
        // Form validation and preview update
        function updateFormState() {
            const complexSelected = serviceComplex.val();
            const amenitySelected = amenityDropdown.val();
            const chargeAmount = serviceChargeInput.val();
            const intervalSelected = paymentIntervalSelect.val();
            
            // Enable/disable submit button
            const formValid = complexSelected && amenitySelected && chargeAmount && parseFloat(chargeAmount) > 0 && intervalSelected;
            submitButton.prop('disabled', !formValid);
            
            // Update preview
            if (formValid) {
                updatePreview();
                previewCard.removeClass('d-none');
            } else {
                previewCard.addClass('d-none');
            }
        }
        
        function updatePreview() {
            const complexText = serviceComplex.find('option:selected').text();
            const amenityText = amenityDropdown.find('option:selected').text();
            const chargeAmount = serviceChargeInput.val();
            const intervalText = paymentIntervalSelect.find('option:selected').text();
            
            $('#preview_complex').text(complexText || '-');
            $('#preview_amenity').text(amenityText || '-');
            $('#preview_amount').text(chargeAmount ? `KES ${parseFloat(chargeAmount).toLocaleString()}` : 'KES 0');
        }
        
        // Remove existing event listeners to prevent duplicates
        serviceComplex.off('select2:select select2:clear');
        amenityDropdown.off('select2:select select2:clear');
        serviceChargeInput.off('input');
        paymentIntervalSelect.off('select2:select select2:clear');
        
        // Complex selection handler
        serviceComplex.on('select2:select', function (e) {
            const selectedOption = e.params.data.element;
            const selectedComplexId = $(selectedOption).data('id');
            
            if (selectedComplexId) {
                // Reset dependent fields
                amenityDropdown.html('<option></option>').trigger('change');
                amenityDropdown.prop('disabled', true);
                loadingIndicator.removeClass('d-none');
                
                // Fetch amenities
                fetch(`/complex-amenities/${selectedComplexId}/get-amenities`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        loadingIndicator.addClass('d-none');
                        
                        if (data.success && data.amenities.length > 0) {
                            // Group amenities by type for better organization
                            const groupedAmenities = {};
                            data.amenities.forEach(amenity => {
                                if (!groupedAmenities[amenity.amenity_type]) {
                                    groupedAmenities[amenity.amenity_type] = [];
                                }
                                groupedAmenities[amenity.amenity_type].push(amenity);
                            });
                            
                            // Populate dropdown with grouped options
                            Object.keys(groupedAmenities).forEach(type => {
                                const displayName = type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                const amenities = groupedAmenities[type];
                                
                                if (amenities.length === 1) {
                                    // Single amenity of this type
                                    const option = new Option(displayName, amenities[0].id, false, false);
                                    amenityDropdown.append(option);
                                } else {
                                    // Multiple amenities of this type - create optgroup
                                    const optgroup = $(`<optgroup label="${displayName}"></optgroup>`);
                                    amenities.forEach((amenity, index) => {
                                        const optionText = `${displayName} ${index + 1}`;
                                        const option = new Option(optionText, amenity.id, false, false);
                                        optgroup.append(option);
                                    });
                                    amenityDropdown.append(optgroup);
                                }
                            });
                        } else {
                            const option = new Option('No amenities available', '', false, true);
                            option.disabled = true;
                            amenityDropdown.append(option);
                        }
                        
                        amenityDropdown.prop('disabled', false);
                        amenityDropdown.trigger('change');
                        updateFormState();
                    })
                    .catch(error => {
                        console.error('Error fetching amenities:', error);
                        loadingIndicator.addClass('d-none');
                        
                        amenityDropdown.html('<option>Error loading amenities</option>');
                        amenityDropdown.prop('disabled', false);
                        amenityDropdown.trigger('change');
                        
                        // Show error notification
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to load amenities. Please try again.',
                            icon: 'error',
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    });
            }
            updateFormState();
        });
        
        serviceComplex.on('select2:clear', function () {
            amenityDropdown.html('<option></option>').trigger('change');
            amenityDropdown.prop('disabled', true);
            updateFormState();
        });
        
        // Other form field handlers
        amenityDropdown.on('select2:select select2:clear', updateFormState);
        serviceChargeInput.on('input', updateFormState);
        paymentIntervalSelect.on('select2:select select2:clear', updateFormState);
        
        // Real-time charge formatting
        serviceChargeInput.on('input', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value) && value > 0) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });
    });

    // Enhanced form submission
    $('#kt_button_submit_service_charge').on('click', function() {
        const button = $(this);
        const form = $('#form_service_charge');

        // Show loading state
        button.attr('data-kt-indicator', 'on');
        button.prop('disabled', true);
        
        // Get and validate form data
        const complexSlug = $('#service_complex').val();
        const amenityId = $('#complex_amenitiy').val();
        const serviceCharge = parseFloat($('#service_charge').val());
        const paymentInterval = $('#payment_interval').val();
        
        // Client-side validation
        if (!complexSlug || !amenityId || !serviceCharge || serviceCharge <= 0 || !paymentInterval) {
            button.removeAttr('data-kt-indicator');
            button.prop('disabled', false);
            
            Swal.fire({
                title: 'Validation Error',
                text: 'Please fill in all fields correctly',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Prepare form data
        let values = $('#form_service_charge').serializeArray().reduce((map, input) => {
            let value;
            if (map.hasOwnProperty(input.name)) {
                value = Array.isArray(map[input.name]) ? map[input.name] : [map[input.name]];
                value.push(input.value);
            } else {
                value = input.value;
            }
            map[input.name] = value;
            return map;
        }, {});

        const token = $('meta[name="csrf-token"]').attr('content');
        
        // Submit via AJAX
        $.ajax({
            url: '/service_charge',
            type: 'POST',
            data: { '_token': token, values },
            success: function(response) {
                button.removeAttr('data-kt-indicator');
                button.prop('disabled', false);
                
                if(response.status == 200){
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Great!",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    }).then(() => {
                        $('#kt_modal_add_service_charges').modal('hide');
                        location.reload();
                    });
                }
            },
            error: function(xhr) {
                button.removeAttr('data-kt-indicator');
                button.prop('disabled', false);
                
                const errorMessage = xhr.responseJSON?.message || 'An error occurred. Please try again.';
                
                Swal.fire({
                    title: 'Error',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // Enhanced modal reset
    $('#kt_modal_add_service_charges').on('hidden.bs.modal', function () {
        $('#form_service_charge')[0].reset();
        $('#service_complex').val(null).trigger('change');
        $('#complex_amenitiy').html('<option></option>').trigger('change');
        $('#complex_amenitiy').prop('disabled', true);
        $('#charge_preview').addClass('d-none');
        $('#kt_button_submit_service_charge').prop('disabled', true);
        $('#service_charge').removeClass('is-valid is-invalid');
        $('#amenity_loading').addClass('d-none');
    });

    // ── Async complex stats ────────────────────────────────────────────────
    (function loadComplexStats() {
        var statsUrl = (window.COMPLEX_STATS_URL || '');
        if (!statsUrl) return;

        var cards = document.querySelectorAll('.complex-stats[data-complex-id]');
        if (!cards.length) return;

        var ids = Array.from(cards).map(function (el) { return el.dataset.complexId; });
        var query = ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');

        fetch(statsUrl + '?' + query, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
            var sumResUnits = 0, sumResOccupied = 0;
            var sumComUnits = 0, sumComOccupied = 0;
            var sumListings = 0;

            cards.forEach(function (card) {
                var id   = card.dataset.complexId;
                var stat = data[id];
                if (!stat) return;

                var totalEl         = card.querySelector('.stat-total-units');
                var occupiedEl      = card.querySelector('.stat-occupied');
                var listingEl       = card.querySelector('.stat-listings');
                var unitsLabelEl    = card.querySelector('.stat-units-label');
                var occupiedLabelEl = card.querySelector('.stat-occupied-label');

                var hasRes   = stat.residential_total > 0;
                var hasCom   = stat.commercial_total  > 0;
                var isHybrid = hasRes && hasCom;

                if (isHybrid) {
                    // Hybrid: col1 = residential count, col2 = commercial count
                    if (totalEl)         totalEl.textContent         = stat.residential_total;
                    if (occupiedEl)      occupiedEl.textContent      = stat.commercial_total;
                    if (unitsLabelEl)    unitsLabelEl.textContent    = 'Residential';
                    if (occupiedLabelEl) occupiedLabelEl.textContent = 'Commercial';
                    occupiedEl && occupiedEl.classList.replace('text-success', 'text-warning');
                } else if (hasCom) {
                    // Commercial-only
                    if (totalEl)         totalEl.textContent         = stat.commercial_total;
                    if (occupiedEl)      occupiedEl.textContent      = stat.commercial_occupied + ' / ' + stat.commercial_total;
                    if (unitsLabelEl)    unitsLabelEl.textContent    = 'Commmercial Units';
                    if (occupiedLabelEl) occupiedLabelEl.textContent = 'Occupied';
                } else {
                    // Residential-only
                    if (totalEl)         totalEl.textContent         = stat.residential_total;
                    if (occupiedEl)      occupiedEl.textContent      = stat.residential_occupied + ' / ' + stat.residential_total;
                    if (unitsLabelEl)    unitsLabelEl.textContent    = 'Residential Units';
                    if (occupiedLabelEl) occupiedLabelEl.textContent = 'Occupied';
                }

                if (listingEl) listingEl.textContent = stat.total_listings;

                sumResUnits    += stat.residential_total;
                sumResOccupied += stat.residential_occupied;
                sumComUnits    += stat.commercial_total;
                sumComOccupied += stat.commercial_occupied;
                sumListings    += stat.total_listings;
            });

            // Populate summary bar
            var elResUnits    = document.getElementById('summary-res-units');
            var elResOccupied = document.getElementById('summary-res-occupied');
            var elComUnits    = document.getElementById('summary-com-units');
            var elComOccupied = document.getElementById('summary-com-occupied');
            var elListings    = document.getElementById('summary-listings');

            if (elResUnits)    elResUnits.textContent    = sumResUnits;
            if (elResOccupied) elResOccupied.textContent = sumResOccupied;
            if (elComUnits)    elComUnits.textContent    = sumComUnits;
            if (elComOccupied) elComOccupied.textContent = sumComOccupied;
            if (elListings)    elListings.textContent    = sumListings;
        })
        .catch(function () { /* silently ignore — stats are cosmetic */ });
    })();
    // ── End complex stats ──────────────────────────────────────────────────

});

// Open the Complex Feature Guide modal from the help panel
window.openComplexFeatureGuide = function() {
    if (typeof closeHelpPanel === 'function') closeHelpPanel();
    var el = document.getElementById('complexOverviewModal');
    if (el) {
        new bootstrap.Modal(el).show();
    }
};

$(document).ready(function() {

    const STORAGE_KEY = 'erty_complex_overview_dismissed';
    const complexOverviewModalEl = document.getElementById('complexOverviewModal');

    if (complexOverviewModalEl) {
        const modal = new bootstrap.Modal(complexOverviewModalEl);

        complexOverviewModalEl.addEventListener('hide.bs.modal', function() {
            if (document.getElementById('dontShowComplexCheck')?.checked) {
                localStorage.setItem(STORAGE_KEY, '1');
            }
        });
    }

});
