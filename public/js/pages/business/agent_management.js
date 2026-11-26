"use strict";

// Agent Management Utility Class
class AgentManager {
    constructor() {
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Handle form submissions on button click (not form submit)
        $(document).on('click', '#kt_modal_add_agent_submit_button', this.handleAgentFormSubmission.bind(this));
        $(document).on('click', '#kt_modal_invite_agent_submit_button', this.handleInviteFormSubmission.bind(this));
    }

    handleAgentFormSubmission(e) {
        e.preventDefault();
        
        const form = document.getElementById('kt_modal_add_agent_form');
        const formData = new FormData(form);
        const submitButton = e.currentTarget;
        
        // Set loading state
        this.setButtonLoadingState(submitButton, true);
        
        $.ajax({
            url: '/agents',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => this.handleAgentSubmissionSuccess(response),
            error: (xhr) => this.handleAgentSubmissionError(xhr),
            complete: () => this.setButtonLoadingState(submitButton, false)
        });
    }

    handleInviteFormSubmission(e) {
        e.preventDefault();
        
        const form = document.getElementById('kt_modal_invite_agent_form');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        const submitButton = e.currentTarget;
        
        // Set loading state
        this.setButtonLoadingState(submitButton, true);
        
        $.ajax({
            url: '/agents/invite',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => this.handleInviteSubmissionSuccess(response),
            error: (xhr) => this.handleAgentSubmissionError(xhr),
            complete: () => this.setButtonLoadingState(submitButton, false)
        });
    }

    handleAgentSubmissionSuccess(response) {
        if (response.status === 200) {
            this.showSuccessAlert('Agent added successfully!').then(() => {
                window.location = '/agents';
            });
        }
    }

    handleInviteSubmissionSuccess(response) {
        if (response.status === 200) {
            this.showSuccessAlert(response.message || 'Invitation sent.').then(() => {
                $('#kt_modal_invite_agent').modal('hide');
                document.getElementById('kt_modal_invite_agent_form')?.reset();
                window.location.reload();
            });
        }
    }

    handleAgentSubmissionError(xhr) {
        let errorMessage = 'An error occurred. Please try again.';
        
        if (xhr.responseJSON?.message) {
            errorMessage = xhr.responseJSON.message;
        } else if (xhr.responseJSON?.errors) {
            const errors = Object.values(xhr.responseJSON.errors).flat();
            errorMessage = errors.join('\n');
        }
        
        this.showErrorAlert(errorMessage);
    }

    // Utility methods
    setButtonLoadingState(button, isLoading) {
        if (isLoading) {
            button.setAttribute('data-kt-indicator', 'on');
            button.disabled = true;
        } else {
            button.removeAttribute('data-kt-indicator');
            button.disabled = false;
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

// Agent Creation Stepper
var KTCreateAgent = function() {
    var e, t, o, r, a, i, n = [];

    function scrollToProgress() {
        var progress = document.querySelector('.agent-onboard-progress');
        if (!progress) return;

        var top = progress.getBoundingClientRect().top + window.pageYOffset - 96;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    // Handle agent type selection
    function handleAgentTypeChange() {
        const agentType = $('input[name="agent_type"]:checked').val();
        const hasAgentType = ['agency', 'employee', 'contractor'].includes(agentType);

        $('#agent_type_details').toggle(hasAgentType);
        $('#agent-type-hint').toggle(!hasAgentType);

        if (!hasAgentType) {
            $('#agency_fields, #individual_fields').hide();
            $('#employment_fields_employee, #employment_fields_contractor, #common_individual_fields').hide();
            $('#compensation_employee_agency, #compensation_contractor, #benefits_employee').hide();
            return;
        }
        
        // Toggle visibility based on agent type
        if (agentType === 'agency') {
            $('#agency_fields').show();
            $('#individual_fields').hide();
            $('#employment_fields_employee').hide();
            $('#employment_fields_contractor').hide();
            $('#common_individual_fields').hide();
            $('#compensation_employee_agency').show();
            $('#compensation_contractor').hide();
            $('#benefits_employee').hide();
        } else if (agentType === 'employee') {
            $('#agency_fields').hide();
            $('#individual_fields').show();
            $('#employment_fields_employee').show();
            $('#employment_fields_contractor').hide();
            $('#common_individual_fields').show();
            $('#compensation_employee_agency').show();
            $('#compensation_contractor').hide();
            $('#benefits_employee').show();
        } else if (agentType === 'contractor') {
            $('#agency_fields').hide();
            $('#individual_fields').show();
            $('#employment_fields_employee').hide();
            $('#employment_fields_contractor').show();
            $('#common_individual_fields').show();
            $('#compensation_employee_agency').hide();
            $('#compensation_contractor').show();
            $('#benefits_employee').hide();
        }
    }

    return {
        init: function() {
            t = document.querySelector("#kt_modal_add_agent_stepper"),
            o = document.querySelector("#kt_modal_add_agent_form"),
            r = t.querySelector('[data-kt-stepper-action="submit"]'),
            a = t.querySelector('[data-kt-stepper-action="next"]'),
            
            // Initialize agent type change handler
            $('input[name="agent_type"]').on('change', handleAgentTypeChange);
            
            // Trigger initial state
            handleAgentTypeChange();
            
            (i = new KTStepper(t)).on("kt.stepper.changed", (function(e) {
                var stepNames = ["Agent details", "Employment", "Compensation", "Additional info"];
                var currentStep = Math.min(Math.max(i.getCurrentStepIndex(), 1), stepNames.length);
                var progress = document.querySelector('[data-agent-onboard-progress]');
                var progressLabel = document.querySelector('[data-agent-onboard-progress-label]');
                if (progress) progress.value = currentStep;
                if (progressLabel) progressLabel.textContent = `Step ${currentStep} of ${stepNames.length} · ${stepNames[currentStep - 1]}`;

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
                        scrollToProgress();
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
                })) : (e.goNext(), scrollToProgress());
            })),
            i.on("kt.stepper.previous", (function(e) {
                e.goPrevious();
                scrollToProgress();
            })),

            // Step 1 - Personal Information
            n.push(FormValidation.formValidation(o, {
                fields: {
                    agent_type: {
                        validators: {
                            notEmpty: {
                                message: "Agent type is required"
                            }
                        }
                    },
                    // Agency fields
                    company_name: {
                        validators: {
                            callback: {
                                message: "Company name is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'agency') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    contact_person_name: {
                        validators: {
                            callback: {
                                message: "Contact person name is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'agency') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    contact_person_email: {
                        validators: {
                            callback: {
                                message: "Contact person email is required and must be valid",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'agency') {
                                        if (input.value === '') return false;
                                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                        return emailRegex.test(input.value);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    contact_person_phone: {
                        validators: {
                            callback: {
                                message: "Contact person phone is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'agency') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    // Individual fields (Employee/Contractor)
                    first_name: {
                        validators: {
                            callback: {
                                message: "First name is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee' || agentType === 'contractor') {
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
                                message: "Last name is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee' || agentType === 'contractor') {
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
                                message: "Email is required and must be valid",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee' || agentType === 'contractor') {
                                        if (input.value === '') return false;
                                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                        return emailRegex.test(input.value);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    phone: {
                        validators: {
                            callback: {
                                message: "Phone number is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee' || agentType === 'contractor') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    gender: {
                        validators: {
                            callback: {
                                message: "Gender is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee' || agentType === 'contractor') {
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

            // Step 2 - Employment Details
            n.push(FormValidation.formValidation(o, {
                fields: {
                    department_id: {
                        validators: {
                            notEmpty: {
                                message: "Department is required"
                            }
                        }
                    },
                    role_id: {
                        validators: {
                            notEmpty: {
                                message: "Role is required"
                            }
                        }
                    },
                    // Employee-specific fields
                    employment_type: {
                        validators: {
                            callback: {
                                message: "Employment type is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    start_date: {
                        validators: {
                            callback: {
                                message: "Start date is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    work_location: {
                        validators: {
                            callback: {
                                message: "Work location is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'employee') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    // Contractor-specific fields
                    contract_type: {
                        validators: {
                            callback: {
                                message: "Contract type is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'contractor') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    contract_start_date: {
                        validators: {
                            callback: {
                                message: "Contract start date is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'contractor') {
                                        return input.value !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    work_location_contractor: {
                        validators: {
                            callback: {
                                message: "Work location is required",
                                callback: function(input) {
                                    const agentType = $('input[name="agent_type"]:checked').val();
                                    if (agentType === 'contractor') {
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

            // Step 3 - Compensation Details
            n.push(FormValidation.formValidation(o, {
                fields: {
                    commission_rate: {
                        validators: {
                            notEmpty: {
                                message: "Commission rate is required"
                            },
                            between: {
                                min: 0,
                                max: 100,
                                message: "Commission rate must be between 0 and 100"
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

            // Step 4 - Additional Information (optional fields, no validation)
            n.push(FormValidation.formValidation(o, {
                fields: {},
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


// DataTable initialization
var KTAgentsList = function() {
    var container = document.getElementById('agents-results');
    var filterStatus = '';
    var filterDepartment = '';

    var fetchAgents = function() {
        $.ajax({
            url: '/agents/data',
            method: 'GET',
            data: {
                search: document.querySelector('[data-kt-agents-table-filter="search"]').value,
                status: filterStatus,
                department: filterDepartment
            },
            success: function(response) {
                var agents = response.data || [];
                
                // Check if no agents exist
                if (agents.length === 0) {
                    container.innerHTML = `
                        <div class="col-12">
                            <div class="card rounded crm-agent-empty-card">
                                <div class="card-body text-center py-15">
                                    <span class="crm-agent-empty-icon mb-5">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M22 12C22 17.5 17.5 22 12 22S2 17.5 2 12S6.5 2 12 2S22 6.5 22 12ZM12 7C10.3 7 9 8.3 9 10C9 11.7 10.3 13 12 13C13.7 13 15 11.7 15 10C15 8.3 13.7 7 12 7Z" fill="black"></path>
                                            <path d="M12 22C14.6 22 17 21 18.7 19.4C17.9 16.9 15.2 15 12 15C8.8 15 6.09999 16.9 5.3 19.4C6.99999 21 9.4 22 12 22Z" fill="black"></path>
                                        </svg>
                                    </span>
                                    <h3 class="text-gray-800 fw-bold mb-3">No Agents Found</h3>
                                    <div class="text-gray-600 fw-semibold fs-6 mb-7">
                                        Get started by setting up your agent management system
                                    </div>
                                    <div class="mb-5 d-flex flex-column align-items-center ">
                                        <div class="d-flex align-items-center mb-3 justify-content-center">
                                            <span class="badge badge-circle badge-primary me-3">1</span>
                                            <span class="text-gray-700 fw-semibold">Configure departments, roles, and locations</span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <span class="badge badge-circle badge-primary me-3">2</span>
                                            <span class="text-gray-700 fw-semibold">Add your first agent</span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-center gap-3">
                                        <a href="/agents/settings" class="btn btn-light crm-agent-secondary-action">
                                            <i class="bi bi-gear-fill me-2"></i>Preconfiguration
                                        </a>
                                        <a href="/agents/onboarding" class="btn btn-primary">
                                            <i class="bi bi-plus-circle-fill me-2"></i>Add First Agent
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    return;
                }
                
                var html = '<div class="row g-6">';
                // Agent names and settings are typed by users, so escape them before building HTML.
                const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
                agents.forEach(function(agent) {
                    // Get initials for avatar
                    let initials = (agent.first_name && agent.last_name)
                        ? agent.first_name.charAt(0).toUpperCase() + agent.last_name.charAt(0).toUpperCase()
                        : 'A';

                    // Department, Role, Location
                    initials = esc(initials);
                    let department = esc(agent.department?.name || 'N/A');
                    let role = esc(agent.role?.name || 'N/A');
                    let workLocation = esc(agent.work_location?.name || 'N/A');
                    // Agents with a sign-in are team members who also work as agents.
                    const loginBadge = agent.has_login
                        ? '<span class="badge badge-light-info ms-1" title="This agent can sign in">Has sign-in</span>'
                        : '<span class="badge badge-light ms-1" title="External agent without a sign-in">No sign-in</span>';

                    // Earnings, Tasks, Sales (use actual values if available, else fallback)
                    let earnings = agent.base_salary ? `Ksh ${parseFloat(agent.base_salary).toLocaleString()}` : 'Ksh 0';
                    let tasks = agent.tasks || '0'; // If you have a tasks count, otherwise '0'
                    let sales = agent.sales_target ? `Ksh ${parseFloat(agent.sales_target).toLocaleString()}` : 'Ksh 0';

                    // Status badge
                    let statusBadge = '';
                    switch (agent.status) {
                        case 'active':
                            statusBadge = '<span class="badge badge-light-success">Active</span>'; break;
                        case 'on_leave':
                            statusBadge = '<span class="badge badge-light-warning">On Leave</span>'; break;
                        case 'suspended':
                            statusBadge = '<span class="badge badge-light-danger">Suspended</span>'; break;
                        default:
                            statusBadge = `<span class="badge badge-light-secondary">${esc(agent.status)}</span>`;
                    }

                    html += `
                        <div class="col-12 col-lg-6 col-xxl-4">
                            <article class="card crm-agent-card h-100">
                                <div class="card-body crm-agent-card-body">
                                    <div class="crm-agent-identity">
                                        <span class="crm-agent-avatar">${initials}</span>
                                        <div class="crm-agent-identity-copy">
                                            <a href="/agents/profile/${encodeURIComponent(agent.encrypted_id)}" class="crm-agent-name">${esc(agent.full_name)}</a>
                                            <span class="crm-agent-role">${role} · ${department}</span>
                                            ${workLocation !== 'N/A' ? `<span class="crm-agent-location"><i class="bi bi-geo-alt" aria-hidden="true"></i>${workLocation}</span>` : ''}
                                        </div>
                                        <div class="crm-agent-status">${statusBadge}${loginBadge}</div>
                                    </div>
                                    <div class="crm-agent-metrics">
                                        <div class="crm-agent-metric"><strong>${earnings}</strong><span>Earnings</span></div>
                                        <div class="crm-agent-metric"><strong>${tasks}</strong><span>Tasks</span></div>
                                        <div class="crm-agent-metric"><strong>${sales}</strong><span>Sales target</span></div>
                                    </div>
                                    <div class="crm-agent-card-footer">
                                        <span><i class="bi bi-calendar3" aria-hidden="true"></i>Joined ${agent.start_date ? new Date(agent.start_date).toLocaleDateString() : 'N/A'}</span>
                                        <a href="/agents/profile/${encodeURIComponent(agent.encrypted_id)}" class="crm-agent-profile-link">View profile<i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            },
            error: function() {
                container.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center p-5">
                        <i class="bi bi-exclamation-triangle-fill fs-2x me-4"></i>
                        <div>
                            <h4 class="mb-1 text-danger">Error Loading Agents</h4>
                            <span class="text-gray-700">There was a problem loading the agents. Please try again.</span>
                        </div>
                    </div>
                `;
            }
        });
    };

    var handleSearch = function() {
        const filterSearch = document.querySelector('[data-kt-agents-table-filter="search"]');
        if (filterSearch) {
            filterSearch.addEventListener('keyup', function(e) {
                fetchAgents();
            });
        }
    };

    var handleFilter = function() {
        filterStatus = document.querySelector('[data-kt-agents-table-filter="status"]');
        filterDepartment = document.querySelector('[data-kt-agents-table-filter="department"]');
        var filterButton = document.querySelector('[data-kt-agents-table-filter="filter"]');
        var resetButton = document.querySelector('[data-kt-agents-table-filter="reset"]');

        if (!filterStatus || !filterDepartment || !filterButton || !resetButton) {
            return;
        }

        filterButton.addEventListener('click', function() {
            var statusValue = filterStatus.value;
            var departmentValue = filterDepartment.value;

            filterStatus = statusValue;
            filterDepartment = departmentValue;

            fetchAgents();
        });

        resetButton.addEventListener('click', function() {
            filterStatus.value = '';
            filterDepartment.value = '';
            $(filterStatus).trigger('change');
            $(filterDepartment).trigger('change');

            filterStatus = '';
            filterDepartment = '';

            fetchAgents();
        });
    };

    return {
        init: function() {
            if (!container) {
                return;
            }
            fetchAgents();
            handleSearch();
            handleFilter();
        },
        refresh: function() {
            fetchAgents();
        }
    }
}();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the agent manager
    window.agentManager = new AgentManager();
    
    // Initialize the agent creation stepper
    if (window.location.pathname === '/agents/onboarding') {
        KTCreateAgent.init();
    }

    const autoClickButton = document.getElementById('auto-click-button');
    if (autoClickButton) {
        autoClickButton.click();
    }
    
    // Initialize the agents list DataTable
    KTAgentsList.init();
    
    // Make agents table globally accessible
    window.agentsTable = KTAgentsList;
    
    // Handle modal reset when closed
    $('#kt_modal_add_agent').on('hidden.bs.modal', function() {
        document.getElementById('kt_modal_add_agent_form').reset();
        
        const stepper = KTStepper.getInstance(document.querySelector('#kt_modal_add_agent_stepper'));
        if (stepper) {
            stepper.goFirst();
        }
        
        $('select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).val('').trigger('change');
            }
        });
        
        $('input[type="checkbox"]').prop('checked', false);
        $('#view_leads, #manage_leads, #view_properties, #create_deals').prop('checked', true);
    });
    
    // Initialize Select2 dropdowns
    if ($.fn.select2) {
        $('#kt_modal_add_agent select[data-control="select2"]').each(function() {
            $(this).select2({
                dropdownParent: $('#kt_modal_add_agent'),
                placeholder: $(this).data('placeholder') || 'Select option',
                allowClear: true
            });
        });
        
        $('#kt_modal_invite_agent select[data-control="select2"]').each(function() {
            $(this).select2({
                dropdownParent: $('#kt_modal_invite_agent'),
                placeholder: $(this).data('placeholder') || 'Select option',
                allowClear: true
            });
        });
    }
});
