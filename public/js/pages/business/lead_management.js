"use strict";

// Lead Management Utility Class
class LeadManager {
    constructor() {
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        this.initializeEventListeners();
        this.initializeFormValidation();
    }

    initializeEventListeners() {
        // Handle lead type selection - NO LONGER DIRECTLY SHOWS/HIDES FIELDS
        const leadTypeRadios = document.querySelectorAll('input[name="lead_type"]');
        const sourceSelect = document.querySelector('select[name="source"]');
        const referralSection = document.getElementById('referral_details_section');
    
        // Handle lead type selection - just store the value, don't show/hide fields
        leadTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Lead type is now just stored, fields are shown based on profile mode selection
                console.log('Lead type selected:', this.value);
            });
        });
    
        // Handle referral details section
        if (sourceSelect) {
            // The source is a select2 control: it fires jQuery "change", which native listeners never receive,
            // so a native listener left the required referral field hidden and the form could not be submitted.
            $(sourceSelect).on('change', function() {
                if (this.value === 'referral' || this.value === 'agent_referral') {
                    referralSection.style.display = 'block';
                } else {
                    referralSection.style.display = 'none';
                }
            });
        }
    
        // Handle form submissions
        $(document).on('submit', '#kt_modal_add_lead_form', this.handleLeadFormSubmission.bind(this));
    }

    initializeFormValidation() {
        // This will be populated by the stepper initialization
        this.leadValidators = [];
    }

    handleLeadFormSubmission(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const submitButton = document.querySelector('[data-kt-stepper-action="submit"]');
        
        // Set loading state
        this.setButtonLoadingState($(submitButton), true);
        
        $.ajax({
            url: '/customer_relationship/lead_management/store',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => this.handleLeadSubmissionSuccess(response),
            error: (xhr) => this.handleLeadSubmissionError(xhr),
            complete: () => this.setButtonLoadingState($(submitButton), false)
        });
    }

    handleLeadSubmissionSuccess(response) {
        if (response.status === 200) {
            this.showSuccessAlert('Lead created successfully!').then(() => {
                $('#kt_modal_add_lead').modal('hide');
                // Reload leads table if it exists
                if (window.leadsTable) {
                    window.leadsTable.ajax.reload();
                } else if (typeof loadLeads === 'function') {
                    loadLeads();
                } else {
                    location.reload();
                }
            });
        }
    }

    handleLeadSubmissionError(xhr) {
        let errorMessage = 'An error occurred while creating the lead. Please try again.';
        
        if (xhr.responseJSON?.message) {
            errorMessage = xhr.responseJSON.message;
        } else if (xhr.responseJSON?.errors) {
            const errors = Object.values(xhr.responseJSON.errors).flat();
            errorMessage = errors.join('\n');
        }
        
        this.showErrorAlert(errorMessage);
    }

    // Utility methods
    setButtonLoadingState($button, isLoading) {
        if (isLoading) {
            $button.attr('data-kt-indicator', 'on').prop('disabled', true);
        } else {
            $button.removeAttr('data-kt-indicator').prop('disabled', false);
        }
    }

    showSuccessAlert(message) {
        return Swal.fire({
            text: message,
            icon: "success",
            buttonsStyling: false,
            confirmButtonText: "Ok, got it!",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    }

    showErrorAlert(message) {
        return Swal.fire({
            text: message,
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok, got it!",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    }
}

// Lead Creation Stepper (similar to tenant creation)
var KTCreateLead = function() {
    var e, t, o, r, a, i, n = [];

    return {
        init: function() {
            t = document.querySelector("#kt_modal_add_lead_stepper"),
            o = document.querySelector("#kt_modal_add_lead_form"),
            r = t.querySelector('[data-kt-stepper-action="submit"]'),
            a = t.querySelector('[data-kt-stepper-action="next"]'),
            (i = new KTStepper(t)).on("kt.stepper.changed", (function(e) {
                const stepNames = ["Lead type", "Contact information", "Requirements", "Additional information"];
                const stepNumber = Math.min(Math.max(i.getCurrentStepIndex(), 1), stepNames.length);
                const progressContainer = t.closest(".lead-onboard-shell");
                const progress = progressContainer && progressContainer.querySelector("[data-lead-progress]");
                const progressLabel = progressContainer && progressContainer.querySelector("[data-lead-progress-label]");
                if (progress) progress.value = stepNumber;
                if (progressLabel) progressLabel.textContent = `Step ${stepNumber} of ${stepNames.length} · ${stepNames[stepNumber - 1]}`;
                if (4 === i.getCurrentStepIndex()) {
                    r.classList.remove("d-none");
                    r.classList.add("d-inline-block");
                    a.classList.add("d-none");
                } else if (5 === i.getCurrentStepIndex()) {
                    r.classList.add("d-none");
                    a.classList.add("d-none");
                } else {
                    r.classList.add("d-none");
                    a.classList.remove("d-none");
                }
            })),
            i.on("kt.stepper.next", (function(e) {
                var t = n[e.getCurrentStepIndex() - 1];
                t ? t.validate().then((function(t) {
                    if ("Valid" == t) {
                        e.goNext();
                        KTUtil.scrollTop();
                    } else {
                        Swal.fire({
                            text: "Sorry, looks like there are some errors detected, please try again.",
                            icon: "error",
                            buttonsStyling: !1,
                            confirmButtonText: "Ok, got it!",
                            customClass: {
                                confirmButton: "btn btn-light-primary"
                            }
                        });
                    }
                })) : (e.goNext(), KTUtil.scrollTop());
            })),
            i.on("kt.stepper.previous", (function(e) {
                e.goPrevious();
                KTUtil.scrollTop();
            })),
            
            r.addEventListener("click", (function(e) {
                e.preventDefault();
                var t = n[3]; // Last step validator (index 3 for step 4)
                
                if (t) {
                    t.validate().then((function(validationResult) {
                        if ("Valid" == validationResult) {
                            // Submit using AJAX directly
                            submitLeadForm();
                        } else {
                            Swal.fire({
                                text: "Sorry, looks like there are some errors detected, please try again.",
                                icon: "error",
                                buttonsStyling: !1,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn btn-light-primary"
                                }
                            });
                        }
                    }));
                } else {
                    // Submit using AJAX directly
                    submitLeadForm();
                }
                
                // AJAX submission function
                function submitLeadForm() {
                    const formData = new FormData(o);
                    const csrfToken = $('meta[name="csrf-token"]').attr('content');
                    
                    // Set loading state on submit button
                    r.setAttribute('data-kt-indicator', 'on');
                    r.disabled = true;
                    
                    $.ajax({
                        url: '/customer_relationship/lead_management',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                Swal.fire({
                                    text: response.message || 'Lead created successfully!',
                                    icon: "success",
                                    buttonsStyling: false,
                                    confirmButtonText: "Ok, got it!",
                                    customClass: {
                                        confirmButton: "btn btn-primary"
                                    }
                                }).then(() => {
                                    window.location = '/customer_relationship/lead_management';
                                });
                            }
                        },
                        error: function(xhr) {
                            let errorMessage = 'An error occurred while creating the lead. Please try again.';
                            
                            if (xhr.responseJSON?.message) {
                                errorMessage = xhr.responseJSON.message;
                            } else if (xhr.responseJSON?.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join('\n');
                            }
                            
                            Swal.fire({
                                text: errorMessage,
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            });
                        },
                        complete: function() {
                            // Remove loading state
                            r.removeAttribute('data-kt-indicator');
                            r.disabled = false;
                        }
                    });
                }
            })),

            // Step 1 - Lead Type Selection
            n.push(FormValidation.formValidation(o, {
                fields: {
                    lead_type: {
                        validators: {
                            notEmpty: {
                                message: "Please select a lead type"
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            })),

            // Step 2 - Profile Mode & Contact Information
            n.push(FormValidation.formValidation(o, {
                fields: {
                    profile_mode: {
                        validators: {
                            notEmpty: {
                                message: "Please select profile mode"
                            }
                        }
                    },
                    existing_profile_id: {
                        validators: {
                            callback: {
                                message: "Please select an existing profile",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    if (profileMode && profileMode.value === 'existing') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    first_name: {
                        validators: {
                            callback: {
                                message: "First name is required for individual leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Individual') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    last_name: {
                        validators: {
                            callback: {
                                message: "Last name is required for individual leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Individual') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    email: {
                        validators: {
                            callback: {
                                message: "Valid email is required for individual leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Individual') {
                                        return input.value !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    phone: {
                        validators: {
                            callback: {
                                message: "Phone is required for individual leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Individual') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    company_name: {
                        validators: {
                            callback: {
                                message: "Company name is required for business leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Business') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    contact_person: {
                        validators: {
                            callback: {
                                message: "Contact person is required for business leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Business') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    business_email: {
                        validators: {
                            callback: {
                                message: "Valid business email is required for business leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Business') {
                                        return input.value !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    business_phone: {
                        validators: {
                            callback: {
                                message: "Business phone is required for business leads",
                                callback: function(input) {
                                    const profileMode = document.querySelector('input[name="profile_mode"]:checked');
                                    const leadType = document.querySelector('input[name="lead_type"]:checked');
                                    if (profileMode && profileMode.value === 'new' && leadType && leadType.value === 'Business') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            })),

            // Step 3 - Property Requirements
            n.push(FormValidation.formValidation(o, {
                fields: {
                    property_type: {
                        validators: {
                            notEmpty: {
                                message: "Property type is required"
                            }
                        }
                    },
                    complex: {
                        validators: {
                            notEmpty: {
                                message: "Complex is required"
                            }
                        }
                    },
                    purpose: {
                        validators: {
                            notEmpty: {
                                message: "Purpose is required"
                            }
                        }
                    },
                    budget_min: {
                        validators: {
                            callback: {
                                message: "Minimum budget must be less than maximum budget",
                                callback: function(input) {
                                    const budgetMax = parseFloat(document.querySelector('input[name="budget_max"]').value) || 0;
                                    const budgetMin = parseFloat(input.value) || 0;
                                    
                                    if (budgetMin > 0 && budgetMax > 0) {
                                        return budgetMin < budgetMax;
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    budget_max: {
                        validators: {
                            callback: {
                                message: "Maximum budget must be greater than minimum budget",
                                callback: function(input) {
                                    const budgetMin = parseFloat(document.querySelector('input[name="budget_min"]').value) || 0;
                                    const budgetMax = parseFloat(input.value) || 0;
                                    
                                    if (budgetMin > 0 && budgetMax > 0) {
                                        return budgetMax > budgetMin;
                                    }
                                    return true;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            })),

            // Step 4 - Additional Information
            n.push(FormValidation.formValidation(o, {
                fields: {
                    source: {
                        validators: {
                            notEmpty: {
                                message: "Lead source is required"
                            }
                        }
                    },
                    contact_method: {
                        validators: {
                            notEmpty: {
                                message: "Contact method is required"
                            }
                        }
                    },
                    priority: {
                        validators: {
                            notEmpty: {
                                message: "Priority level is required"
                            }
                        }
                    },
                    referral_details: {
                        validators: {
                            callback: {
                                message: "Please provide referral details",
                                callback: function(input) {
                                    const source = document.querySelector('select[name="source"]').value;
                                    if (source === 'referral' || source === 'agent_referral') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            }))
        }
    }
}();

// Handle profile mode selection (new vs existing)
$(document).on('change', 'input[name="profile_mode"]', function() {
    const profileMode = this.value;
    const profileModeSelection = $('.profile-mode-selection');
    const existingProfileSearch = $('.existing-profile-search');
    const individualFields = $('.individual-fields');
    const businessFields = $('.business-fields');
    
    if (profileMode === 'existing') {
        // Hide mode selection, show search
        profileModeSelection.fadeOut(200, function() {
            existingProfileSearch.fadeIn(200);
        });
        individualFields.hide();
        businessFields.hide();
    } else if (profileMode === 'new') {
        // Hide mode selection, show fields based on lead type
        const leadType = $('input[name="lead_type"]:checked').val();
        profileModeSelection.fadeOut(200, function() {
            if (leadType === 'Individual') {
                individualFields.fadeIn(200);
            } else if (leadType === 'Business') {
                businessFields.fadeIn(200);
            }
        });
        existingProfileSearch.hide();
    }
});

// Handle back to profile mode selection
$(document).on('click', '#back_to_profile_mode, .back-to-profile-mode-btn', function(e) {
    e.preventDefault();
    
    const profileModeSelection = $('.profile-mode-selection');
    const existingProfileSearch = $('.existing-profile-search');
    const individualFields = $('.individual-fields');
    const businessFields = $('.business-fields');
    
    // Clear profile mode selection
    $('input[name="profile_mode"]').prop('checked', false);
    
    // Clear existing profile search if selected
    if ($('#profile_search').length) {
        $('#profile_search').val(null).trigger('change');
        $('#existing_profile_id').val('');
        $('#selected_profile_preview').hide();
    }
    
    // Hide all sections and show profile mode selection
    existingProfileSearch.fadeOut(200);
    individualFields.fadeOut(200);
    businessFields.fadeOut(200, function() {
        profileModeSelection.fadeIn(200);
    });
});

// Initialize profile search select2 with AJAX
if ($('#profile_search').length && $.fn.select2) {
    $('#profile_search').select2({
        placeholder: 'Type to search profiles...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '/customer_relationship/lead_profiles/search',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.results.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text,
                            lead_type: item.lead_type,
                            contact: item.contact,
                            phone: item.phone,
                            profile: item.profile
                        };
                    })
                };
            },
            cache: true
        },
        templateResult: formatProfileResult,
        templateSelection: formatProfileSelection,
        escapeMarkup: function (m) { return m; }
    });
    
    // Handle profile selection
    $('#profile_search').on('select2:select', function (e) {
        const data = e.params.data;
        $('#existing_profile_id').val(data.id);
        
        // Show profile preview
        displayProfilePreview(data);
    });
    
    // Handle profile clear
    $('#profile_search').on('select2:clear', function () {
        $('#existing_profile_id').val('');
        $('#selected_profile_preview').fadeOut();
    });
}

// Format profile search result
function formatProfileResult(profile) {
    if (profile.loading) return profile.text;
    
    const badge = profile.lead_type === 'Individual' 
        ? '<span class="badge badge-light-primary">Individual</span>'
        : '<span class="badge badge-light-success">Business</span>';
    
    return $(`
        <div class="d-flex flex-column">
            <div class="d-flex align-items-center mb-1">
                <span class="fw-bold">${profile.text}</span>
                <span class="ms-2">${badge}</span>
            </div>
            <div class="text-muted small">${profile.contact || ''}</div>
            ${profile.phone ? `<div class="text-muted small">${profile.phone}</div>` : ''}
        </div>
    `);
}

// Format profile selection
function formatProfileSelection(profile) {
    return profile.text;
}

// Display selected profile preview
function displayProfilePreview(data) {
    const profile = data.profile;
    let previewHTML = '';
    
    if (data.lead_type === 'Individual') {
        previewHTML = `
            <div class="row">
                <div class="col-6">
                    <div class="mb-2"><strong>Name:</strong> ${profile.first_name} ${profile.last_name}</div>
                    <div class="mb-2"><strong>Email:</strong> ${profile.email || 'N/A'}</div>
                    <div class="mb-2"><strong>Phone:</strong> ${profile.phone || 'N/A'}</div>
                </div>
                <div class="col-6">
                    ${profile.occupation ? `<div class="mb-2"><strong>Occupation:</strong> ${profile.occupation}</div>` : ''}
                    ${profile.date_of_birth ? `<div class="mb-2"><strong>DOB:</strong> ${profile.date_of_birth}</div>` : ''}
                </div>
            </div>
            ${profile.current_address ? `<div class="mt-2"><strong>Address:</strong><br>${profile.current_address}</div>` : ''}
        `;
    } else {
        previewHTML = `
            <div class="row">
                <div class="col-6">
                    <div class="mb-2"><strong>Company:</strong> ${profile.company_name}</div>
                    <div class="mb-2"><strong>Contact Person:</strong> ${profile.contact_person || 'N/A'}</div>
                    <div class="mb-2"><strong>Email:</strong> ${profile.business_email || 'N/A'}</div>
                </div>
                <div class="col-6">
                    <div class="mb-2"><strong>Phone:</strong> ${profile.business_phone || 'N/A'}</div>
                    ${profile.industry ? `<div class="mb-2"><strong>Industry:</strong> ${profile.industry}</div>` : ''}
                    ${profile.company_size ? `<div class="mb-2"><strong>Size:</strong> ${profile.company_size}</div>` : ''}
                </div>
            </div>
            ${profile.business_address ? `<div class="mt-2"><strong>Address:</strong><br>${profile.business_address}</div>` : ''}
        `;
    }
    
    $('#profile_preview_content').html(previewHTML);
    $('#selected_profile_preview').fadeIn();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the lead manager
    window.leadManager = new LeadManager();
    
    // Initialize the lead creation stepper (only on the onboarding page)
    if (window.location.pathname.includes('/customer_relationship/lead_management/onboarding/lead')) {
        KTCreateLead.init();
    }
    
    const autoClickButton = document.getElementById('auto-click-button');
    if (autoClickButton) {
        autoClickButton.click();
    }
    
    // Handle lead type change to update step title
    $(document).on('change', 'input[name="lead_type"]', function() {
        const selectedType = this.value;
        const stepTitle = document.querySelector('[data-kt-stepper-element="nav"]:nth-child(2) .stepper-title');
        if (stepTitle) {
            stepTitle.textContent = selectedType === 'Business' ? 'Business Info' : 'Personal Info';
        }
        
        const stepDesc = document.querySelector('[data-kt-stepper-element="nav"]:nth-child(2) .stepper-desc');
        if (stepDesc) {
            stepDesc.textContent = selectedType === 'Business' ? 'Business details' : 'Personal details';
        }
    });
    
    // Handle budget validation
    $(document).on('input', 'input[name="budget_min"], input[name="budget_max"]', function() {
        const budgetMin = parseFloat($('input[name="budget_min"]').val()) || 0;
        const budgetMax = parseFloat($('input[name="budget_max"]').val()) || 0;
        
        if (budgetMin > 0 && budgetMax > 0 && budgetMin >= budgetMax) {
            $(this).addClass('is-invalid');
        } else {
            $('input[name="budget_min"], input[name="budget_max"]').removeClass('is-invalid');
        }
    });

    // Initialize Select2 dropdowns with proper configuration
    if ($.fn.select2) {
        // Property type dropdown
        $('select[name="property_type"]').select2({
            placeholder: 'Select property type',
            allowClear: true
        });

        // Purpose dropdown
        $('select[name="purpose"]').select2({
            placeholder: 'Select purpose',
            allowClear: true
        });

        // Bedrooms dropdown
        $('select[name="bedrooms"]').select2({
            placeholder: 'Select bedrooms',
            allowClear: true
        });

        // Bathrooms dropdown
        $('select[name="bathrooms"]').select2({
            placeholder: 'Select bathrooms',
            allowClear: true
        });

        // Timeline dropdown
        $('select[name="timeline"]').select2({
            placeholder: 'Select timeline',
            allowClear: true
        });

        // Source dropdown
        $('select[name="source"]').select2({
            placeholder: 'Select source',
            allowClear: true
        });

        // Priority dropdown
        $('select[name="priority"]').select2({
            placeholder: 'Select priority',
            allowClear: true
        });

        // Contact method dropdown
        $('select[name="contact_method"]').select2({
            placeholder: 'Select method',
            allowClear: true
        });

        // Contact time dropdown
        $('select[name="contact_time"]').select2({
            placeholder: 'Select time',
            allowClear: true
        });

        // Assigned agent dropdown
        $('select[name="assigned_agent"]').select2({
            placeholder: 'Select or auto-assign',
            allowClear: true
        });

        // Industry dropdown for business leads
        $('select[name="industry"]').select2({
            placeholder: 'Select industry',
            allowClear: true
        });

        // Company size dropdown for business leads
        $('select[name="company_size"]').select2({
            placeholder: 'Select size',
            allowClear: true
        });
    }

    // Initialize property type dropdown with change handler for complex filtering
    $('select[name="property_type"]').on('change', function() {
        const selectedPropertyType = $(this).val();
        const complexSelect = $('#lead_complex');
        
        // Toggle property-specific fields
        toggleLeadPropertyFields(selectedPropertyType);
        
        if (selectedPropertyType) {
            // Enable the complex dropdown and update its configuration
            complexSelect.prop('disabled', false);
            
            // Update help text
            // $('#lead-complex-filter-text').text(`Showing complexes for ${selectedPropertyType}`);
            
            // Reinitialize Select2 with AJAX for filtered complexes
            complexSelect.select2('destroy');
            complexSelect.select2({
                ajax: {
                    url: '/complexes/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            listing_type: selectedPropertyType, // Filter by property type
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        
                        return {
                            results: data.results.map(complex => ({
                                id: complex.id,
                                text: complex.title || complex.text,
                                title: complex.title,
                                ref_code: complex.ref_code,
                                type: complex.type,
                                location: complex.location,
                                assigned_agent_id: complex.assigned_agent ? complex.assigned_agent.id : null,
                                assigned_agent_name: complex.assigned_agent ? complex.assigned_agent.name : null
                            })),
                            pagination: {
                                more: (params.page * 10) < data.total
                            }
                        };
                    },
                    cache: true
                },
                placeholder: 'Search and select complex',
                minimumInputLength: 0,
                templateResult: formatLeadComplexResult,
                templateSelection: formatLeadComplexSelection,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
            
            // Clear any existing selection
            complexSelect.val(null).trigger('change');
        } else {
            // Disable the complex dropdown if no property type is selected
            complexSelect.prop('disabled', true);
            complexSelect.val(null).trigger('change');
            $('#lead-complex-filter-text').text('Please select a property type first to enable complex selection');
            
            // Hide all property-specific fields
            $('.residential-property-fields').addClass('d-none');
            $('.commercial-property-fields').addClass('d-none');
        }
    });

    // Handle complex selection to show assigned agent info and load units
    $('#lead_complex').on('select2:select', function (e) {
        const data = e.params.data;
        const complexId = data.id;
        
        // Check if complex data includes assigned agent info
        if (data.assigned_agent_id && data.assigned_agent_name) {
            // Show notification about the assigned agent
            showComplexAgentInfo(data.assigned_agent_name, data.title || data.text);
            
            // Store the agent ID in a hidden field for backend to use if no manual selection
            $('#complex_assigned_agent_id').val(data.assigned_agent_id);
            $('#complex_assigned_agent_name').val(data.assigned_agent_name);
        } else {
            // No assigned agent for this complex
            hideComplexAgentInfo();
            $('#complex_assigned_agent_id').val('');
            $('#complex_assigned_agent_name').val('');
        }
        
        // Load complex details including commercial units
        if (complexId) {
            loadLeadComplexUnits(complexId);
        }
    });
    
    // Clear agent info when complex is cleared
    $('#lead_complex').on('select2:clear', function () {
        hideComplexAgentInfo();
        $('#complex_assigned_agent_id').val('');
        $('#complex_assigned_agent_name').val('');
        hideLeadComplexUnits(); // Also hide units display
    });
    
    // Show complex agent information
    function showComplexAgentInfo(agentName, complexName) {
        const infoHTML = `
            <div class="alert alert-info d-flex align-items-center p-5 mb-5" id="complex_agent_info">
                <i class="las la-info-circle fs-2hx text-info me-4"></i>
                <div class="d-flex flex-column">
                    <h5 class="mb-1">Complex Has Assigned Agent</h5>
                    <span><strong>${agentName}</strong> is assigned to <strong>${complexName}</strong>.</span>
                    <span class="text-muted fs-7 mt-1">This agent will be used unless you manually select a different one below.</span>
                </div>
            </div>
        `;
        
        // Remove any existing info
        $('#complex_agent_info').remove();
        
        // Insert before the agent select field
        $('#lead_assigned_agent').closest('.fv-row').before(infoHTML);
    }
    
    // Hide complex agent information
    function hideComplexAgentInfo() {
        $('#complex_agent_info').fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    // Load and display complex commercial units
    function loadLeadComplexUnits(complexId) {
        $.ajax({
            url: '/complex/' + encodeURIComponent(complexId) + '/commercial-spaces',
            type: 'GET',
            success: function(response) {
                if (response.status == 200 && response.complex.commercial_units && response.complex.commercial_units.length > 0) {
                    displayLeadComplexUnits(response.complex.commercial_units, response.complex.title);
                } else {
                    hideLeadComplexUnits();
                }
            },
            error: function(e) {
                console.error('Error loading complex units:', e);
                hideLeadComplexUnits();
            }
        });
    }
    
    // Display commercial units in a banner
    function displayLeadComplexUnits(units, complexTitle) {
        const container = $('#lead_complex_units_container');
        const unitsList = $('#lead_complex_units_list');
        
        // Clear existing units
        unitsList.empty();
        
        // Add each unit as a card
        units.forEach(function(unit) {
            const statusClass = getUnitStatusClass(unit.status);
            const statusBadge = getUnitStatusBadge(unit.status);
            
            const unitCard = `
                <div class="col-md-4 col-sm-6">
                    <div class="card border border-gray-300 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0 fw-bold">${unit.unit_number}</h5>
                                <span class="badge badge-${statusClass}">${statusBadge}</span>
                            </div>
                            <div class="text-muted fs-7 mb-3">
                                <i class="fas fa-ruler-combined me-1"></i>${unit.unit_sq_ft || 'N/A'} sq ft
                            </div>
                            <div class="text-muted fs-7">
                                <i class="fas fa-layer-group me-1"></i>Floor: ${unit.effective_floor || unit.custom_floors || unit.floors || 'N/A'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            unitsList.append(unitCard);
        });
        
        // Show the container with animation
        container.slideDown(300);
    }
    
    // Hide complex units display
    function hideLeadComplexUnits() {
        $('#lead_complex_units_container').slideUp(300, function() {
            $('#lead_complex_units_list').empty();
        });
    }
    
    // Get status badge class
    function getUnitStatusClass(status) {
        switch(status) {
            case 'Available':
                return 'success';
            case 'Occupied':
                return 'warning';
            case 'Under Maintenance':
                return 'info';
            case 'Reserved':
                return 'secondary';
            default:
                return 'light';
        }
    }
    
    // Get status badge text
    function getUnitStatusBadge(status) {
        return status || 'Unknown';
    }

    // Function to toggle property-specific fields based on property type
    function toggleLeadPropertyFields(propertyType) {
        console.log("Toggling fields for property type:", propertyType);
        const residentialFields = $('.residential-property-fields');
        const commercialFields = $('.commercial-property-fields');
        
        // Define residential property types (adjust based on your system)
        const residentialTypes = [
            'Apartment',
            'House',
            'Villa',
            'Condo',
            'Townhouse',
            'Studio',
            'Duplex',
            'Penthouse'
        ];
        
        // Define commercial property types (adjust based on your system)
        const commercialTypes = [
            'Office',
            'Retail',
            'Commercial',
            'Commercial Unit',
            'Warehouse',
            'Industrial',
            'Mixed Use',
            'Shop',
            'Store'
        ];
        
        if (!propertyType) {
            // Hide all if no selection
            residentialFields.addClass('d-none');
            commercialFields.addClass('d-none');
            
            // Clear values
            residentialFields.find('select, input').val('').trigger('change');
            commercialFields.find('select, input').val('').trigger('change');
        } else if (residentialTypes.includes(propertyType)) {
            // Show residential fields, hide commercial
            residentialFields.removeClass('d-none');
            commercialFields.addClass('d-none');
            
            // Clear commercial fields
            commercialFields.find('select, input').val('').trigger('change');
        } else if (commercialTypes.includes(propertyType)) {
            // Show commercial fields, hide residential
            commercialFields.removeClass('d-none');
            residentialFields.addClass('d-none');
            
            // Clear residential fields
            residentialFields.find('select, input').val('').trigger('change');
        } else {
            // Default behavior for unknown types - show residential
            residentialFields.removeClass('d-none');
            commercialFields.addClass('d-none');
            
            // Clear commercial fields
            commercialFields.find('select, input').val('').trigger('change');
        }
    }

    // Format functions for complex dropdown
    function formatLeadComplexResult(complex) {
        if (complex.loading) {
            return complex.text;
        }

        const $container = $(
            `<div class="select2-result-complex">
                <div class="select2-result-complex__title">${complex.title || complex.text}</div>
                <div class="select2-result-complex__details">
                    <small class="text-muted">
                        ${complex.ref_code ? `Code: ${complex.ref_code}` : ''} 
                        ${complex.type ? `• Type: ${complex.type}` : ''}
                        ${complex.location ? `• ${complex.location}` : ''}
                    </small>
                </div>
            </div>`
        );

        return $container;
    }

    function formatLeadComplexSelection(complex) {
        return complex.title || complex.text;
    }

    // Handle modal reset when closed to clear all fields
    $('#kt_modal_add_lead').on('hidden.bs.modal', function() {
        // Reset property type and complex
        $('#lead_property_type').val('').trigger('change');
        $('#lead_complex').val('').trigger('change').prop('disabled', true);
        
        // Hide all property-specific fields
        $('.residential-property-fields').addClass('d-none');
        $('.commercial-property-fields').addClass('d-none');
        
        // Clear all property-specific field values
        $('.residential-property-fields select, .residential-property-fields input').val('').trigger('change');
        $('.commercial-property-fields select, .commercial-property-fields input').val('').trigger('change');
        
        // Reset help text
        $('#lead-complex-filter-text').text('Please select a property type first to enable complex selection');
    });
});

(function() {
    const $agentSelect = $('#lead_assigned_agent'); // use id to be explicit
    const ajaxUrl = $agentSelect.data('ajax-url') || '/agents/search';

    function formatAgentResult(agent) {
        if (agent.loading) return agent.text;
        const email = agent.email ? `<div class="text-muted small">${agent.email}</div>` : '';
        const phone = agent.phone ? `<div class="text-muted small">${agent.phone}</div>` : '';
        return $(`
            <div class="d-flex flex-column">
                <div>${agent.text}</div>
                ${email}
                ${phone}
            </div>
        `);
    }

    function formatAgentSelection(agent) {
        if (!agent || agent.id === '') return '';
        if (agent.id === 'auto') return 'Auto-assign';
        return agent.text || agent.name || agent.email || agent.phone || agent.id;
    }

    // ensure select2 exists
    if ($agentSelect.length && $.fn.select2) {
        $agentSelect.select2({
            placeholder: 'Search and select agent',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term, page: params.page || 1 };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: (data.results || []).map(agent => ({
                            id: agent.id,
                            text: agent.text || agent.name || agent.full_name,
                            email: agent.email,
                            phone: agent.phone
                        })),
                        pagination: { more: (params.page * 10) < (data.total || 0) }
                    };
                },
                cache: true
            },
            templateResult: formatAgentResult,
            templateSelection: formatAgentSelection,
            escapeMarkup: function (m) { return m; }
        });
    }
})();


$(document).ready(function() {
    const autoClickButton = document.getElementById('auto-click-button');
    if (autoClickButton) {
        autoClickButton.click();
    }

    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Handle lead source type change to show/hide relevant filters
    $('select[name="lead_source_type"]').on('change', function() {
        const sourceType = $(this).val();
        const $priorityFilter = $('#priority-filter-group');
        const $statusFilter = $('#status-filter-group');
        const $propertyTypeFilter = $('#property-type-filter-group');
        const $agentFilter = $('#agent-filter-group');
        const $hintText = $('#lead-source-hint');
        
        if (sourceType === 'schedule') {
            // Schedule leads - only show lead type (hide others with smooth animation)
            $priorityFilter.slideUp(200);
            $statusFilter.slideUp(200);
            $propertyTypeFilter.slideUp(200);
            $agentFilter.slideUp(200);
            
            // Update hint text
            $hintText.html('Schedule leads show users who requested property viewings');
            
            // Clear the hidden filter values
            $('select[name="priority_level"]').val('').trigger('change');
            $('select[name="lead_status"]').val('').trigger('change');
            $('select[name="property_type"]').val('').trigger('change');
            $('select[name="assigned_agent_id"]').val('').trigger('change');
        } else if (sourceType === 'manual') {
            // Manual leads - show all filters
            $priorityFilter.slideDown(200);
            $statusFilter.slideDown(200);
            $propertyTypeFilter.slideDown(200);
            $agentFilter.slideDown(200);
            
            // Update hint text
            $hintText.html('Manual leads show entries created through lead onboarding');
        } else {
            // All sources - show all filters
            $priorityFilter.slideDown(200);
            $statusFilter.slideDown(200);
            $propertyTypeFilter.slideDown(200);
            $agentFilter.slideDown(200);
            
            // Update hint text
            $hintText.html('All filters available for complete lead search');
        }
    });

    // Search button click handler
    $('#kt_button_search_leads').on('click', function (e) {
        e.preventDefault();
        var $button = $(this);
        // Collect form data
        var $form = $('#kt_lead_management_search_form');
        var hasSearchCriteria = $form.serializeArray().some(function (field) {
            return field.value.trim() !== '';
        });

        if (!hasSearchCriteria) {
            $('#leads-results').html(`
                <div class="crm-lead-search-prompt d-flex flex-column align-items-center justify-content-center text-center py-10">
                    <span class="crm-lead-search-prompt-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <h3>Search to view leads</h3>
                    <p>Enter a name, phone number, or email, or choose a filter above.</p>
                </div>
            `);
            return;
        }

        $button.attr('data-kt-indicator', 'on');
        var params = $form.serialize();
    
        // Send AJAX GET request
        $.get('/customer_relationship/leads/data', params, function (response) {
            var html = '';

            // Add view toggle buttons at the top
            var viewToggle = `
                <div class="d-flex justify-content-end align-items-center mb-4">
                    <div class="text-muted fs-7">
                        <strong>${response.data.length}</strong> lead${response.data.length !== 1 ? 's' : ''} found
                    </div>
                </div>
            `;
            
            if (response.data && response.data.length > 0) {
                // Table View HTML
                var tableHTML = `
                    <div id="leads-table-view">
                        <table id="leads-table" class="table align-middle table-row-dashed fs-6 gy-3">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-300px">Lead</th>
                                    <th class="min-w-300px">Contact</th>
                                    <th class="min-w-160px">Priority &amp; status</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                `;
                
                // Lead details are typed by users: escape before building HTML.
                var esc = function (value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]; }); };
                response.data.forEach(function (lead) {
                    // Get profile data
                    var profile = lead.profile || {};
                    
                    // Determine lead name from profile
                    var leadName = esc(profile.lead_type === 'Business' 
                        ? (profile.company_name || 'N/A')
                        : ((profile.first_name || '') + ' ' + (profile.last_name || '')).trim() || 'N/A');
                    
                    // Priority badge
                    var priorityBadge = '';
                    if (lead.priority === 'hot') {
                        priorityBadge = '<span class="badge lead-priority-badge lead-priority-hot">HOT</span>';
                    } else if (lead.priority === 'warm') {
                        priorityBadge = '<span class="badge lead-priority-badge lead-priority-warm">WARM</span>';
                    } else {
                        priorityBadge = '<span class="badge lead-priority-badge lead-priority-cold">COLD</span>';
                    }
                    
                    // Status badge
                    var statusBadge = '';
                    if (lead.converted_tenant_id) {
                        statusBadge = '<span class="badge lead-status-badge">CONVERTED</span>';
                    } else if (lead.status === 'new') {
                        statusBadge = '<span class="badge lead-status-badge">NEW</span>';
                    } else if (lead.status === 'contacted') {
                        statusBadge = '<span class="badge lead-status-badge">CONTACTED</span>';
                    } else if (lead.status === 'qualified') {
                        statusBadge = '<span class="badge lead-status-badge">QUALIFIED</span>';
                    } else if (lead.status === 'converted') {
                        statusBadge = '<span class="badge lead-status-badge">CONVERTED</span>';
                    } else if (lead.status === 'proposal') {
                        statusBadge = '<span class="badge lead-status-badge">PROPOSAL</span>';
                    } else if (lead.status === 'disqualified') {
                        statusBadge = '<span class="badge lead-status-badge">DISQUALIFIED</span>';
                    } else {
                        statusBadge = `<span class="badge lead-status-badge">${esc((lead.status || 'N/A').replace(/_/g, ' ').toUpperCase())}</span>`;
                    }
                    
                    // Keep the list focused on essentials; the profile page holds the full record.
                    var profileUrl = `/customer_relationship/lead_management/lead/${lead.encrypted_lead_id}`;
                    var contactName = esc(profile.lead_type === 'Business'
                        ? (profile.contact_person || '')
                        : `${profile.first_name || ''} ${profile.last_name || ''}`.trim());
                    var email = esc(profile.email || profile.business_email || '');
                    var phone = esc(profile.phone || profile.business_phone || '');
                    var addedOn = lead.created_at ? new Date(lead.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
                    var addedBy = `<div class="text-muted fs-8 mt-1" data-lead-added-by>Added by ${lead.added_by ? esc(lead.added_by) : 'not recorded'}${addedOn ? ' · ' + esc(addedOn) : ''}</div>`;
                    var agentLine = lead.agent_name
                        ? `<div class="text-muted fs-8" data-lead-agent>Agent: ${lead.agent_url ? `<a href="${esc(lead.agent_url)}">${esc(lead.agent_name)}</a>` : esc(lead.agent_name)}</div>`
                        : '<div class="text-muted fs-8" data-lead-agent>Agent: not assigned</div>';

                    tableHTML += `
                        <tr class="lead-row ${lead.lead_source_type === 'schedule' ? 'lead-source-schedule' : 'lead-source-manual'}">
                            <td class="py-4 lead-name-cell">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="lead-score-bubble ${lead.lead_score ? 'has-score' : 'no-score'}">
                                        ${lead.lead_score
                                            ? `<span>${lead.lead_score}</span>`
                                            : `<i class="bi ${lead.lead_source_type === 'schedule' ? 'bi-calendar-check' : 'bi-person-plus'}" aria-hidden="true"></i>`
                                        }
                                    </div>
                                    <div class="d-flex align-items-center gap-3 min-w-0 w-100">
                                        <div class="min-w-0">
                                            <div class="lead-row-name"><a href="${profileUrl}" class="text-reset text-hover-primary">${leadName}</a></div>
                                            <div class="d-flex gap-1 align-items-center flex-wrap lead-row-tags">
                                                <span class="badge lead-source-pill">${lead.lead_source_type === 'schedule' ? `Schedule (${lead.schedule_count || 1})` : 'Manual'}</span>
                                                ${profile.lead_type === 'Business' ? `<span class="badge lead-type-pill">Business</span>` : ''}
                                            </div>
                                            ${addedBy}
                                            ${agentLine}
                                        </div>
                                        <a href="${profileUrl}" class="btn btn-sm lead-profile-button ms-auto" aria-label="View ${leadName} profile">
                                            <i class="bi bi-person-vcard" aria-hidden="true"></i><span>View profile</span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 lead-row-contact-cell">
                                <div class="d-flex flex-column gap-1 lead-row-contact">
                                    ${contactName ? `<span class="lead-contact-primary">${contactName}</span>` : ''}
                                    ${email ? `<a href="mailto:${email}" class="lead-contact-line"><i class="bi bi-envelope" aria-hidden="true"></i>${email}</a>` : ''}
                                    ${phone ? `<a href="tel:${phone}" class="lead-contact-line"><i class="bi bi-telephone" aria-hidden="true"></i>${phone}</a>` : ''}
                                    ${!contactName && !email && !phone ? '<span class="lead-contact-empty">No contact details</span>' : ''}
                                </div>
                            </td>
                            <td class="py-4 lead-row-status-cell">
                                <div class="d-flex flex-wrap align-items-center gap-2 lead-row-status">
                                    ${priorityBadge}
                                    ${statusBadge}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                tableHTML += '</tbody></table></div>';
                
                html = viewToggle + tableHTML;
                
            } else {
                html = `
                    <div class="text-center py-20">
                        <div class="mb-10">
                            <i class="bi bi-inbox fs-5x text-gray-400"></i>
                        </div>
                        <h3 class="fw-bold text-gray-800 mb-3">No Leads Found</h3>
                        <p class="text-gray-600 fs-5 mb-0">Try adjusting your search filters to find what you're looking for.</p>
                    </div>
                `;
            }
            
            $('#leads-results').html(html);
            $button.removeAttr('data-kt-indicator');
        
            // Initialize DataTables if there is data
            if (response.data && response.data.length > 0) {
                // Initialize DataTable
                if (!$.fn.DataTable.isDataTable('#leads-table')) {
                    $('#leads-table').DataTable({
                        pageLength: 10,
                        lengthMenu: [5, 10, 25, 50, 100],
                        ordering: true,
                        searching: true,
                        lengthChange: true,
                        autoWidth: false,
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search leads..."
                        }
                    });
                }

            }

            $button.removeAttr('data-kt-indicator');

            
        }).fail(function () {
            $button.attr('data-kt-indicator', 'off');
            $('#leads-results').html(`
                <div class="alert alert-danger d-flex align-items-center p-5">
                    <i class="bi bi-exclamation-triangle-fill fs-2x me-4"></i>
                    <div>
                        <h4 class="mb-1 text-danger">Error Loading Leads</h4>
                        <span class="text-gray-700">There was a problem loading the leads. Please try again.</span>
                    </div>
                </div>
            `);
        }).always(function () {
            $button.removeAttr('data-kt-indicator');
        });

    });

    // Allow submitting the search with Enter from a text field.
    $('#kt_lead_management_search_form').on('submit', function (e) {
        e.preventDefault();
        $('#kt_button_search_leads').trigger('click');
    });
});
