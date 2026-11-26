"use strict";

// Global currency configuration - accessible throughout the file
let complexOccupancyTable;
const DEFAULT_CURRENCY = window.appConfig?.currency || 'KES';
const CURRENCY_SYMBOL = window.appConfig?.currencySymbol || 'Ksh';
const tenantTableText = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));

const listingTypeDropdown = $('select[name="listing_type"]');
const complex = $('select[name="complex"]')

// Notice type descriptions
const noticeDescriptions = {
    lease_termination: "Ends the tenancy at the end of the lease term or earlier, giving the tenant formal notice to vacate.",
    rent_increase: "Informs the tenant of a change in rent amount, usually with advance notice required by law.",
    lease_violation: "Alerts the tenant to a breach of lease terms (e.g., noise, unauthorized pets) and gives them a chance to correct it.",
    property_inspection: "Notifies the tenant of a scheduled inspection of the unit, typically with 24–48 hours' notice.",
    maintenance_notice: "Advises the tenant of upcoming repairs or maintenance work that requires access to the property.",
    eviction_notice: "Demands rent payment or requires the tenant to vacate due to serious violations.",
    lease_renewal: "Offers the tenant the option to renew their lease for another term.",
    notice_to_enter: "Required before the landlord or property manager enters the unit for repairs, showings, or inspections.",
    non_renewal_notice: "Informs the tenant that their lease will not be renewed and specifies the move-out date.",
    change_in_terms: "Communicates new rules, policies, or changes to lease conditions (e.g., parking, utilities).",
    unconditional_quit: "Used for extreme violations (illegal activity, severe damage) where no opportunity to cure is given.",
    other: "A flexible option for custom notices not covered by the standard categories."
};

// Tenant Management Utility Class
class TenantManager {
    constructor() {
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        this.initializeEventListeners();
        this.initializeFormValidation();

        // Initialize complex occupancy table if it exists
        if ($('#kt_table_complex_occupancy').length) {
            this.initializeComplexOccupancyTable();
        }
    }

    initializeEventListeners() {
        // Toggle sub-options when main export types are checked/unchecked
        document.getElementById('export_tenants')?.addEventListener('change', (e) => {
            this.toggleSubOptions('tenant-suboptions', e.target.checked);
            this.toggleTenantFilters(e.target.checked);
        });

        // Handle tenant profile options toggle
        document.getElementById('tenant_profiles')?.addEventListener('change', (e) => {
            this.toggleSubOptions('tenant-profile-suboptions', e.target.checked);
        });

        // Handle tenancy details options toggle
        document.getElementById('tenancy_details')?.addEventListener('change', (e) => {
            this.toggleSubOptions('tenancy-details-suboptions', e.target.checked);
        });
        
        // Handle action clicks
        $(document).on('click', '[data-action]', this.handleActionClick.bind(this));
        
        // Handle form submissions - PREVENT DEFAULT FORM SUBMISSION
        $(document).on('submit', '#kt_modal_give_notice_form', this.handleFormSubmission.bind(this, {
            endpoint: '/tenant_management/give-notice',
            successMessage: 'Notice sent successfully!',
            modal: '#kt_modal_give_notice',
            submitButton: '[data-kt-notice-modal-action="submit"]',
            validatorKey: 'noticeValidator'
        }));
    
        $(document).on('submit', '#kt_modal_record_payment_form', this.handleFormSubmission.bind(this, {
            endpoint: '/tenant_management/record-payment',
            successMessage: 'Payment recorded successfully!',
            modal: '#kt_modal_record_payment',
            submitButton: '[data-kt-payment-modal-action="submit"]',
            validatorKey: 'paymentValidator'
        }));

        // Add tenancy modal form submission handler
        $(document).on('submit', '#kt_modal_end_tenancy_form', this.handleFormSubmission.bind(this, {
            endpoint: '/tenant_management/end-tenancy',
            successMessage: 'Tenancy ended successfully!',
            modal: '#kt_modal_end_tenancy',
            submitButton: '[data-kt-tenancy-modal-action="submit"]',
            validatorKey: 'tenancyValidator'
        }));
    
        // ALSO handle button clicks to prevent default form submission
        $(document).on('click', '[data-kt-notice-modal-action="submit"]', (e) => {
            e.preventDefault();
            $('#kt_modal_give_notice_form').trigger('submit');
        });
    
        $(document).on('click', '[data-kt-payment-modal-action="submit"]', (e) => {
            e.preventDefault();
            $('#kt_modal_record_payment_form').trigger('submit');
        });

        $(document).on('click', '[data-kt-tenancy-modal-action="submit"]', (e) => {
            e.preventDefault();
            $('#kt_modal_end_tenancy_form').trigger('submit');
        });
    
        // Auto-calculate effective date based on notice period
        $(document).on('input', '#kt_modal_give_notice_form input[name="notice_period"]', this.calculateEffectiveDate.bind(this));

        // Handle termination reason change
        $(document).on('change', 'select[name="termination_reason"]', this.handleTerminationReasonChange.bind(this));

        // Handle notice compliance radio toggle (show/hide notice selector)
        $(document).on('change', 'input[name="notice_compliance"]', this.handleNoticeComplianceChange.bind(this));

        // Handle financial settlement calculations
        $(document).on('input', 'input[name="outstanding_rent"]', this.calculateNetSettlement.bind(this));

        // Handle security deposit status change
        $(document).on('change', 'select[name="security_deposit_status"]', this.handleSecurityDepositChange.bind(this));

        // Payment status change handler
        $(document).on('change', 'input[name="payment_status"]', this.handlePaymentStatusChange.bind(this));
        
        // Allocation input handlers
        $(document).on('input', '.allocation-input', this.handleAllocationChange.bind(this));
        $(document).on('input', 'input[name="amount_paid"]', this.handleAmountPaidChange.bind(this));

        // Payment date change handler for late fee calculation
        $(document).on('change', 'input[name="payment_date"]', this.handlePaymentDateChange.bind(this));
        
        // Also trigger when invoice selection changes
        $(document).on('change', 'select[name="invoice_id"]', (e) => {
            this.handleInvoiceSelection(e.target.value);
            // Trigger late fee calculation if payment date is already set
            if ($('input[name="payment_date"]').val()) {
                this.handlePaymentDateChange();
            }
        });

    }

    // Initialize form validation
    initializeFormValidation() {
        // Notice form validation
        const noticeFormEl = document.getElementById('kt_modal_give_notice_form');
        if (noticeFormEl) this.noticeValidator = FormValidation.formValidation(
            noticeFormEl,
            {
                fields: {
                    notice_type: {
                        validators: {
                            notEmpty: {
                                message: 'Notice type is required'
                            }
                        }
                    },
                    notice_date: {
                        validators: {
                            notEmpty: {
                                message: 'Notice date is required'
                            },
                            date: {
                                format: 'YYYY-MM-DD',
                                message: 'Please enter a valid date'
                            }
                        }
                    },
                    effective_date: {
                        validators: {
                            notEmpty: {
                                message: 'Effective date is required'
                            },
                            date: {
                                format: 'YYYY-MM-DD',
                                message: 'Please enter a valid date'
                            },
                            callback: {
                                message: 'Effective date must be after notice date',
                                callback: function(input) {
                                    const noticeDate = document.querySelector('input[name="notice_date"]').value;
                                    if (noticeDate && input.value) {
                                        return new Date(input.value) >= new Date(noticeDate);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    notice_subject: {
                        validators: {
                            notEmpty: {
                                message: 'Subject is required'
                            }
                        }
                    },
                    notice_details: {
                        validators: {
                            notEmpty: {
                                message: 'Notice details are required'
                            },
                            stringLength: {
                                min: 10,
                                message: 'Notice details must be at least 10 characters long'
                            }
                        }
                    },
                    'delivery_method[]': {
                        validators: {
                            choice: {
                                min: 1,
                                message: 'Please select at least one delivery method'
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row',
                        eleInvalidClass: '',
                        eleValidClass: ''
                    })
                }
            }
        );

        // Payment form validation
        const paymentFormEl = document.getElementById('kt_modal_record_payment_form');
        if (paymentFormEl) this.paymentValidator = FormValidation.formValidation(
            paymentFormEl,
            {
                fields: {
                    invoice_id: {
                        validators: {
                            notEmpty: {
                                message: 'Payment period is required'
                            }
                        }
                    },
                    payment_date: {
                        validators: {
                            notEmpty: {
                                message: 'Payment date is required'
                            },
                            date: {
                                format: 'YYYY-MM-DD',
                                message: 'Please enter a valid date'
                            }
                        }
                    },
                    payment_method: {
                        validators: {
                            notEmpty: {
                                message: 'Payment method is required'
                            }
                        }
                    },
                    payment_status: {
                        validators: {
                            notEmpty: {
                                message: 'Payment status is required'
                            }
                        }
                    },
                    single_invoice_amount: {
                        validators: {
                            callback: {
                                message: 'Payment amount is required for partial payments',
                                callback: function(input) {
                                    const paymentStatus = document.querySelector('input[name="payment_status"]:checked');
                                    const invoiceType = document.querySelector('select[name="invoice_id"] option:selected')?.getAttribute('data-invoice-type');
                                    
                                    if (paymentStatus && paymentStatus.value === 'partial' && invoiceType === 'single') {
                                        const amount = parseFloat(input.value) || 0;
                                        if (amount <= 0) {
                                            return {
                                                valid: false,
                                                message: 'Payment amount must be greater than 0'
                                            };
                                        }
                                    }
                                    return { valid: true };
                                }
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row',
                        eleInvalidClass: '',
                        eleValidClass: ''
                    })
                }
            }
        );

        // Tenancy termination form validation
        const tenancyFormEl = document.getElementById('kt_modal_end_tenancy_form');
        if (tenancyFormEl) this.tenancyValidator = FormValidation.formValidation(
            tenancyFormEl,
            {
                fields: {
                    termination_date: {
                        validators: {
                            notEmpty: {
                                message: 'Termination date is required'
                            },
                            date: {
                                format: 'YYYY-MM-DD',
                                message: 'Please enter a valid date'
                            }
                        }
                    },
                    termination_reason: {
                        validators: {
                            notEmpty: {
                                message: 'Termination reason is required'
                            }
                        }
                    },
                    other_reason: {
                        validators: {
                            callback: {
                                message: 'Please specify the other reason',
                                callback: function(input) {
                                    const terminationReason = document.querySelector('select[name="termination_reason"]').value;
                                    if (terminationReason === 'other') {
                                        return input.value.trim() !== '';
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row',
                        eleInvalidClass: '',
                        eleValidClass: ''
                    })
                }
            }
        );
    }

    // Unified action handler
    handleActionClick(e) {
        e.preventDefault();
        
        const $element = $(e.currentTarget);
        const action = $element.data('action');
        const tenantId = $element.data('tenant-id');
        
        const actionMap = {
            'notice': () => this.showModal(tenantId, 'notice'),
            'payment': () => this.showModal(tenantId, 'payment'),
            'tenancy': () => this.showModal(tenantId, 'tenancy'),
            'documents': () => this.viewTenantDocuments(tenantId),
            'delete': () => this.deleteTenant(tenantId)
        };

        if (actionMap[action]) {
            actionMap[action]();
        }
    }

    // Unified modal display method
    showModal(tenantId, modalType) {
        const tenantData = this.getTenantDataFromRow(tenantId);
        const modalConfigs = {
            notice: {
                modalId: '#kt_modal_give_notice',
                fields: {
                    '#notice_tenant_id': tenantId,
                    '#notice_tenant_name': tenantData.name,
                    '#notice_tenant_ref': tenantData.ref
                }
            },
            payment: {
                modalId: '#kt_modal_record_payment',
                fields: {
                    '#payment_tenant_id': tenantId,
                    '#payment_tenant_name': tenantData.name,
                    '#payment_tenant_ref': tenantData.ref,
                    '#payment_monthly_rent': tenantData.monthlyRent
                }
            },
            tenancy: {
                modalId: '#kt_modal_end_tenancy',
                fields: {
                    '#tenancy_tenant_id': tenantId,
                    '#tenancy_tenant_name': tenantData.name,
                    '#tenancy_tenant_ref': tenantData.ref
                }
            }
        };

        const config = modalConfigs[modalType];
        if (config) {
            this.populateModalFields(config.fields);
            
            // Reset form validation when modal is shown
            if (modalType === 'notice' && this.noticeValidator) {
                this.noticeValidator.resetForm(true);
            } else if (modalType === 'payment' && this.paymentValidator) {
                this.paymentValidator.resetForm(true);
                $('#invoice_details').remove();
                this.loadTenantInvoices(tenantId);
            } else if (modalType === 'tenancy' && this.tenancyValidator) {
                this.tenancyValidator.resetForm(true);
                this.resetTenancyForm();
                this.loadTenancyNotices(tenantId);
            }
            
            $(config.modalId).modal('show');
        }
    }

    // Extract tenant data from table row
    getTenantDataFromRow(tenantId) {
        const row = $(`[data-tenant-id="${tenantId}"]`).closest('tr');
        return {
            name: row.find('.text-gray-800:first').text().trim(),
            ref: row.find('small:contains("Ref:")').text().replace('• Ref: ', ''),
            monthlyRent: row.find('.fw-bold:contains("Rent:")').text().replace('Rent: ', '')
        };
    }

    // Populate modal fields with data
    populateModalFields(fields) {
        Object.entries(fields).forEach(([selector, value]) => {
            const $element = $(selector);
            if ($element.is('input, textarea, select')) {
                $element.val(value);
            } else {
                $element.text(value);
            }
        });
    }

    // Unified form submission handler
    handleFormSubmission(config, e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $form = $(e.currentTarget);
        const $submitButton = $(config.submitButton);

        // Special validation for payment allocation
        if (config.validatorKey === 'paymentValidator') {
            const paymentStatus = $('input[name="payment_status"]:checked').val();
            
            if (paymentStatus === 'partial') {
                const validationResult = this.validatePaymentAllocation();
                if (!validationResult.isValid) {
                    this.showErrorAlert(validationResult.message);
                    return;
                }
            }
        }
        
        // Get the appropriate validator
        const validator = this[config.validatorKey];
        
        if (validator) {
            // Validate the form
            validator.validate().then((status) => {
                console.log('Validation status:', status);
                
                if (status === 'Valid') {
                    // Form is valid, proceed with submission
                    this.submitForm(config, $form, $submitButton);
                } else {
                    // Form is invalid, show error
                    this.showErrorAlert('Please fill in all required fields correctly.');
                }
            });
        } else {
            // No validator found, submit directly (fallback)
            this.submitForm(config, $form, $submitButton);
        }
    }

    // Validate payment allocation
    validatePaymentAllocation() {
        const selectedOption = $('select[name="invoice_id"] option:selected');
        const invoiceType = selectedOption.data('invoice-type');
        const paymentStatus = $('input[name="payment_status"]:checked').val();
        
        if (!selectedOption.val()) {
            return {
                isValid: false,
                message: 'Please select an invoice before proceeding.'
            };
        }
        
        // Only validate allocation for partial payments
        if (paymentStatus !== 'partial') {
            return { isValid: true };
        }
        
        if (invoiceType === 'combined') {
            const leaseAmount = parseFloat($('input[name="lease_amount"]').val()) || 0;
            const serviceChargeAmount = parseFloat($('input[name="service_charge_amount"]').val()) || 0;
            const totalAllocated = leaseAmount + serviceChargeAmount;
            
            if (totalAllocated === 0) {
                return {
                    isValid: false,
                    message: 'Please enter payment amounts for at least one invoice type (lease or service charge).'
                };
            }
            
            // Validate individual amounts don't exceed balances
            let invoiceData = [];
            try {
                const rawData = selectedOption.data('invoice-data');
                if (typeof rawData === 'string') {
                    invoiceData = JSON.parse(rawData);
                } else if (Array.isArray(rawData)) {
                    invoiceData = rawData;
                } else {
                    invoiceData = this.getFallbackInvoiceData(selectedOption);
                }
            } catch (error) {
                console.error('Error in validation:', error);
                invoiceData = this.getFallbackInvoiceData(selectedOption);
            }
            
            const leaseInvoice = invoiceData.find(inv => inv.invoice_number?.startsWith('Lease-'));
            const serviceChargeInvoice = invoiceData.find(inv => inv.invoice_number?.startsWith('SC-'));
            
            if (leaseAmount > 0 && leaseInvoice) {
                const leaseBalance = parseFloat(leaseInvoice.amount || 0) - parseFloat(leaseInvoice.amount_paid || 0);
                if (leaseAmount > leaseBalance) {
                    return {
                        isValid: false,
                        message: `Lease payment amount (${DEFAULT_CURRENCY} ${leaseAmount.toLocaleString()}) cannot exceed balance (${DEFAULT_CURRENCY} ${leaseBalance.toLocaleString()}).`
                    };
                }
            }
            
            if (serviceChargeAmount > 0 && serviceChargeInvoice) {
                const scBalance = parseFloat(serviceChargeInvoice.amount || 0) - parseFloat(serviceChargeInvoice.amount_paid || 0);
                if (serviceChargeAmount > scBalance) {
                    return {
                        isValid: false,
                        message: `Service charge payment amount (${DEFAULT_CURRENCY} ${serviceChargeAmount.toLocaleString()}) cannot exceed balance (${DEFAULT_CURRENCY} ${scBalance.toLocaleString()}).`
                    };
                }
            }
            
        } else {
            // Single invoice allocation
            const singleAmount = parseFloat($('input[name="single_invoice_amount"]').val()) || 0;
            const balance = parseFloat(selectedOption.data('balance')) || 0;
            
            if (singleAmount === 0) {
                return {
                    isValid: false,
                    message: 'Please enter the payment amount for this invoice.'
                };
            }
            
            if (singleAmount > balance) {
                return {
                    isValid: false,
                    message: `Payment amount (${DEFAULT_CURRENCY} ${singleAmount.toLocaleString()}) cannot exceed invoice balance (${DEFAULT_CURRENCY} ${balance.toLocaleString()}).`
                };
            }
        }
        
        return { isValid: true };
    }

    // Actual form submission
    submitForm(config, $form, $submitButton) {
        const formData = new FormData($form[0]);

        // Add allocation data for partial payments
        const paymentStatus = $('input[name="payment_status"]:checked').val();
        if (paymentStatus === 'partial') {
            const selectedOption = $('select[name="invoice_id"] option:selected');
            const invoiceType = selectedOption.data('invoice-type');
            
            if (invoiceType === 'combined') {
                // Add allocation data for combined invoices
                const leaseAmount = $('input[name="lease_amount"]').val();
                const serviceChargeAmount = $('input[name="service_charge_amount"]').val();
                const leaseInvoiceId = $('input[name="lease_amount"]').data('invoice-id');
                const serviceChargeInvoiceId = $('input[name="service_charge_amount"]').data('invoice-id');
                
                formData.append('payment_allocation', JSON.stringify({
                    type: 'combined',
                    allocations: [
                        ...(parseFloat(leaseAmount) > 0 ? [{
                            invoice_id: leaseInvoiceId,
                            amount: parseFloat(leaseAmount),
                            type: 'lease'
                        }] : []),
                        ...(parseFloat(serviceChargeAmount) > 0 ? [{
                            invoice_id: serviceChargeInvoiceId,
                            amount: parseFloat(serviceChargeAmount),
                            type: 'service_charge'
                        }] : [])
                    ]
                }));
            } else {
                // Add allocation data for single invoice
                const singleAmount = $('input[name="single_invoice_amount"]').val();
                const invoiceId = selectedOption.val();
                
                formData.append('payment_allocation', JSON.stringify({
                    type: 'single',
                    allocations: [{
                        invoice_id: invoiceId,
                        amount: parseFloat(singleAmount),
                        type: 'single'
                    }]
                }));
            }
        }
        
        // Set loading state
        this.setButtonLoadingState($submitButton, true);
        
        $.ajax({
            url: config.endpoint,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => this.handleSubmissionSuccess(response, config),
            error: (xhr) => this.handleSubmissionError(xhr),
            complete: () => this.setButtonLoadingState($submitButton, false)
        });
    }

    // Handle successful form submission
    handleSubmissionSuccess(response, config) {
        if (response.status === 200) {
            this.showSuccessAlert(config.successMessage).then(() => {
                $(config.modal).modal('hide');
                
                // Reset the form
                $(config.modal).find('form')[0].reset();
                
                // Reset validation
                if (config.validatorKey && this[config.validatorKey]) {
                    this[config.validatorKey].resetForm(true);
                }

                // Reload the page after successful submission
                location.reload();
            });
        }
    }

    // Handle form submission errors
    handleSubmissionError(xhr) {
        let errorMessage = 'An error occurred. Please try again.';
        
        if (xhr.responseJSON?.message) {
            errorMessage = xhr.responseJSON.message;
        } else if (xhr.responseJSON?.errors) {
            // Handle Laravel validation errors
            const errors = xhr.responseJSON.errors;
            const errorList = Object.keys(errors).map(key => 
                `• ${key}: ${errors[key].join(', ')}`
            ).join('\n');
            errorMessage = `Please fix the following errors:\n${errorList}`;
        }
        
        this.showErrorAlert(errorMessage);
    }

    // Set button loading state
    setButtonLoadingState($button, isLoading) {
        if (isLoading) {
            $button.attr('data-kt-indicator', 'on').prop('disabled', true);
        } else {
            $button.removeAttr('data-kt-indicator').prop('disabled', false);
        }
    }

    // Show success alert
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

    // Show error alert
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

    // Auto-calculate effective date based on notice period
    calculateEffectiveDate() {
        const noticePeriod = parseInt($('#kt_modal_give_notice_form input[name="notice_period"]').val());
        const noticeDate = new Date($('#kt_modal_give_notice_form input[name="notice_date"]').val());
        
        if (noticePeriod && noticeDate) {
            const effectiveDate = new Date(noticeDate);
            effectiveDate.setDate(effectiveDate.getDate() + noticePeriod);
            
            const formattedDate = effectiveDate.toISOString().split('T')[0];
            $('#kt_modal_give_notice_form input[name="effective_date"]').val(formattedDate);
            
            // Revalidate the effective_date field
            if (this.noticeValidator) {
                this.noticeValidator.revalidateField('effective_date');
            }
        }
    }

    // Delete tenant with confirmation
    deleteTenant(tenantId) {
        $.ajax({
            url: `/tenant_management/${tenantId}`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            success: (response) => this.showDeleteConfirmation(tenantId, response.tenant),
            error: () => this.showDeleteConfirmation(tenantId, null)
        });
    }

    // Show delete confirmation dialog
    showDeleteConfirmation(tenantId, tenant) {
        const tenantName = tenant ? this.getTenantDisplayName(tenant) : 'Tenant';
        
        Swal.fire({
            title: 'Delete Tenant?',
            html: `Are you sure you want to delete <strong>${tenantName}</strong>?<br><small class="text-muted">This action cannot be undone.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                this.performTenantDeletion(tenantId, tenantName);
            }
        });
    }

    // Perform actual tenant deletion
    performTenantDeletion(tenantId, tenantName) {
        $.ajax({
            url: `/tenant_management/${tenantId}`,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: () => {
                Swal.fire({
                    title: 'Deleted!',
                    text: `${tenantName} has been deleted successfully.`,
                    icon: 'success',
                    confirmButtonText: 'Ok'
                });
            },
            error: (xhr) => {
                const errorMessage = xhr.responseJSON?.message || 'An error occurred while deleting the tenant.';
                this.showErrorAlert(errorMessage);
            }
        });
    }

    // Helper methods
    getTenantDisplayName(tenant) {
        if (tenant.tenant_type === 'Business') {
            return tenant.business_name || 'Business Tenant';
        } else {
            return tenant.tenant_name || 'Individual Tenant';
        }
    }

    // Format currency values
    formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Get badge color based on status
    getStatusColor(status) {
        switch(status) {
            case 'Active': return 'success';
            case 'Pending': return 'warning';
            case 'Expired': return 'danger';
            case 'Terminated': return 'secondary';
            default: return 'primary';
        }
    }

    // View tenant documents placeholder
    viewTenantDocuments(tenantId) {
        Swal.fire({
            text: "Documents view coming soon!",
            icon: "info",
            confirmButtonText: "Ok"
        });
    }

    // Handle termination reason change
    handleTerminationReasonChange(e) {
        const selectedReason = $(e.target).val();
        const otherReasonSection = $('#other_reason_section');
        
        if (selectedReason === 'other') {
            otherReasonSection.fadeIn(300);
            $('input[name="other_reason"]').attr('required', true);
        } else {
            otherReasonSection.fadeOut(300);
            $('input[name="other_reason"]').attr('required', false).val('');
        }

        // Update validation
        if (this.tenancyValidator) {
            this.tenancyValidator.revalidateField('other_reason');
        }
    }

    // Handle security deposit status change
    handleSecurityDepositChange(e) {
        const status = $(e.target).val();
        const refundInput = $('input[name="security_deposit_refund"]');
        
        switch(status) {
            case 'full_refund':
                refundInput.prop('disabled', false);
                break;
            case 'partial_refund':
                refundInput.prop('disabled', false);
                break;
            case 'no_refund':
                refundInput.val('0').prop('disabled', true);
                break;
            case 'not_applicable':
                refundInput.val('').prop('disabled', true);
                break;
        }
        
        this.calculateNetSettlement();
    }

    // Calculate net settlement
    calculateNetSettlement() {
        const refund = parseFloat($('input[name="security_deposit_refund"]').val()) || 0;
        const outstanding = parseFloat($('input[name="outstanding_rent"]').val()) || 0;
        const damages = parseFloat($('input[name="damage_charges"]').val()) || 0;
        
        const netSettlement = refund - outstanding - damages;
        
        const settlementElement = $('#net_settlement_amount');
        const formattedAmount = `${DEFAULT_CURRENCY} ${Math.abs(netSettlement).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
        
        if (netSettlement > 0) {
            settlementElement.text(`+${formattedAmount}`).removeClass('text-danger').addClass('text-success');
        } else if (netSettlement < 0) {
            settlementElement.text(`-${formattedAmount}`).removeClass('text-success').addClass('text-danger');
        } else {
            settlementElement.text(formattedAmount).removeClass('text-success text-danger').addClass('text-muted');
        }
    }

    // Load termination/eviction notices for the tenancy into the End Tenancy modal
    loadTenancyNotices(tenantId) {
        const $loading  = $('#notice_selector_loading');
        const $empty    = $('#notice_selector_empty');
        const $dropdown = $('#notice_selector_dropdown');
        const $select   = $('#tenancy_notice_id');

        // Only relevant when proper_notice is chosen
        if ($('input[name="notice_compliance"]:checked').val() !== 'proper_notice') return;

        // Reset states
        $loading.show();
        $empty.hide();
        $dropdown.hide();
        $select.empty().append('<option></option>');

        $.ajax({
            url: `/tenant_management/tenant/${tenantId}/termination-notices`,
            method: 'GET',
            success: (response) => {
                $loading.hide();
                if (response.status === 200 && response.notices && response.notices.length > 0) {
                    response.notices.forEach(n => {
                        $select.append(new Option(n.label, n.id));
                    });
                    $dropdown.show();
                    // Re-init Select2 if present
                    if ($.fn.select2 && $select.data('select2')) {
                        $select.trigger('change');
                    } else if ($.fn.select2) {
                        $select.select2({ placeholder: 'Select the notice...', allowClear: true });
                    }
                } else {
                    $empty.show();
                }
            },
            error: () => {
                $loading.hide();
                $empty.show();
            }
        });
    }

    // Show/hide the notice selector when the compliance radio changes
    handleNoticeComplianceChange(e) {
        const value = $(e.target).val();
        const $section = $('#notice_selection_section');
        if (value === 'proper_notice') {
            $section.show();
            // Load notices using the current tenantId
            const tenantId = $('#tenancy_tenant_id').val();
            if (tenantId) this.loadTenancyNotices(tenantId);
        } else {
            $section.hide();
            $('#tenancy_notice_id').val('').trigger('change');
        }
    }

    // Reset tenancy form
    resetTenancyForm() {
        // Reset form fields
        $('#kt_modal_end_tenancy_form')[0].reset();
        
        // Hide conditional sections
        $('#other_reason_section').hide();
        
        // Reset notice selector state
        $('#notice_selector_loading').hide();
        $('#notice_selector_empty').hide();
        $('#notice_selector_dropdown').hide();
        $('#tenancy_notice_id').empty().append('<option></option>');
        $('#notice_selection_section').show(); // proper_notice is checked by default
        
        // Reset calculated values
        $('#net_settlement_amount').text('${DEFAULT_CURRENCY} 0.00').removeClass('text-success text-danger').addClass('text-muted');
        
        // Reset Select2 dropdowns
        $('select[name="termination_reason"]').val('').trigger('change');
        $('select[name="security_deposit_status"]').val('full_refund').trigger('change');
        
        // Set default date to today
        $('input[name="termination_date"]').val(new Date().toISOString().split('T')[0]);
    }

    // Load tenant invoices
    loadTenantInvoices(tenantId) {
        const invoiceSelect = $('select[name="invoice_id"]');
        
        // Show loading state
        invoiceSelect.html('<option>Loading invoices...</option>');
        invoiceSelect.prop('disabled', true);
        
        $.ajax({
            url: `/tenant_management/${tenantId}/invoices`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            success: (response) => {
                this.populateInvoicesDropdown(response.invoices);
            },
            error: (xhr) => {
                console.error('Error loading invoices:', xhr);
                invoiceSelect.html('<option>Error loading invoices</option>');
                this.showErrorAlert('Failed to load invoices. Please try again.');
            },
            complete: () => {
                invoiceSelect.prop('disabled', false);
            }
        });
    }
    
    // Updated method to populate invoices dropdown with grouping
    populateInvoicesDropdown(invoices) {
        const invoiceSelect = $('select[name="invoice_id"]');
        
        // Clear existing options
        invoiceSelect.html('<option></option>');
        
        if (invoices && invoices.length > 0) {
            // Group invoices by date (extracted from invoice number)
            const groupedInvoices = this.groupInvoicesByDate(invoices);
            
            Object.entries(groupedInvoices).forEach(([dateKey, invoiceGroup]) => {
                if (invoiceGroup.length === 1) {
                    // Single invoice - show normally
                    const invoice = invoiceGroup[0];
                    this.addSingleInvoiceOption(invoiceSelect, invoice);
                } else {
                    // Multiple invoices for same date - group them
                    this.addGroupedInvoiceOption(invoiceSelect, invoiceGroup, dateKey);
                }
            });
            
            // Add event listener for invoice selection
            invoiceSelect.off('change.invoiceSelection').on('change.invoiceSelection', (e) => {
                this.handleInvoiceSelection(e.target.value);
            });
            
        } else {
            invoiceSelect.html('<option>No invoices found for this tenant</option>');
        }
        
        // Reinitialize Select2 if it's being used
        if (invoiceSelect.hasClass('select2-hidden-accessible')) {
            invoiceSelect.select2('destroy').select2({
                placeholder: 'Select invoice to pay',
                allowClear: true
            });
        }
    }
    
    // New method to group invoices by date
    groupInvoicesByDate(invoices) {
        const grouped = {};
        
        invoices.forEach(invoice => {
            // Use combined_invoice_number if available, otherwise fall back to individual invoice ID
            const groupKey = invoice.combined_invoice_number || invoice.id;
            
            if (!grouped[groupKey]) {
                grouped[groupKey] = [];
            }
            grouped[groupKey].push(invoice);
        });
        
        return grouped;
    }
    
    // Method to add single invoice option
    addSingleInvoiceOption(invoiceSelect, invoice) {
        const status = invoice.status || 'pending';
        const amount = parseFloat(invoice.amount || 0);
        const amountPaid = parseFloat(invoice.amount_paid || 0);
        const balance = amount - amountPaid;
        const lateFee = parseFloat(invoice.late_fee || 0);
        
        // Format the option text
        let optionText = `${invoice.invoice_number} - ${invoice.period}`;
        
        if (status === 'paid') {
            optionText += ` (Paid: ${DEFAULT_CURRENCY} ${amount.toLocaleString()})`;
        } else if (status === 'partial') {
            optionText += ` (Balance: ${DEFAULT_CURRENCY} ${balance.toLocaleString()})`;
        } else {
            optionText += ` (Due: ${DEFAULT_CURRENCY} ${amount.toLocaleString()})`;
        }
        
        // Add due date if available
        if (invoice.due_date) {
            const dueDate = new Date(invoice.due_date).toLocaleDateString();
            optionText += ` - Due: ${dueDate}`;
        }
        
        // Use invoice_number as the option value (fallback to id)
        const optionValue = invoice.invoice_number || invoice.id;
        const option = new Option(optionText, optionValue, false, false);
        
        // Add data attributes for additional information
        option.setAttribute('data-amount', amount);
        option.setAttribute('data-amount-paid', amountPaid);
        option.setAttribute('data-balance', balance);
        option.setAttribute('data-status', status);
        option.setAttribute('data-due-date', invoice.due_date || '');
        option.setAttribute('data-period', invoice.period || '');
        option.setAttribute('data-invoice-type', 'single');
        option.setAttribute('data-late-fee', lateFee); // Add late fee data
        // Store single invoice data as JSON string
        option.setAttribute('data-invoice-data', JSON.stringify([invoice]));
        // Keep numeric id handy for code that needs it
        option.setAttribute('data-invoice-id', invoice.id);
        
        // Disable fully paid invoices
        if (status === 'paid') {
            option.disabled = true;
            option.style.color = '#999';
        }
        
        invoiceSelect.append(option);
    }
    
    // Method to add grouped invoice option
    addGroupedInvoiceOption(invoiceSelect, invoiceGroup, groupKey) {
        // Calculate combined totals
        let totalAmount = 0;
        let totalAmountPaid = 0;
        let totalLateFee = 0;
        let hasAllPaid = true;
        let hasAnyPartial = false;
        let latestDueDate = null;
        
        // Separate lease and service charge invoices
        const leaseInvoice = invoiceGroup.find(inv => inv.invoice_number.startsWith('Lease-'));
        const serviceChargeInvoice = invoiceGroup.find(inv => inv.invoice_number.startsWith('SC-'));
        
        invoiceGroup.forEach(invoice => {
            const amount = parseFloat(invoice.amount || 0);
            const amountPaid = parseFloat(invoice.amount_paid || 0);
            const lateFee = parseFloat(invoice.late_fee || 0);
            
            totalAmount += amount;
            totalAmountPaid += amountPaid;
            totalLateFee = lateFee;
            
            if (invoice.status !== 'paid') hasAllPaid = false;
            if (invoice.status === 'partial') hasAnyPartial = true;
            
            // Get the latest due date
            if (invoice.due_date) {
                const dueDate = new Date(invoice.due_date);
                if (!latestDueDate || dueDate > latestDueDate) {
                    latestDueDate = dueDate;
                }
            }
        });
        
        const totalBalance = totalAmount - totalAmountPaid;
        
        // Determine combined status
        let combinedStatus;
        if (hasAllPaid && totalBalance <= 0) {
            combinedStatus = 'paid';
        } else if (hasAnyPartial || totalAmountPaid > 0) {
            combinedStatus = 'partial';
        } else if (latestDueDate && latestDueDate < new Date()) {
            combinedStatus = 'overdue';
        } else {
            combinedStatus = 'pending';
        }
        
        // Format the combined option text using the combined_invoice_number or period info
        let optionText;
        if (invoiceGroup[0].combined_invoice_number) {
            // Use the combined invoice number for display
            optionText = `Combined Invoice - ${invoiceGroup[0].combined_invoice_number}`;
        } else {
            // Fallback to period-based display
            const formattedDate = this.formatPeriodFromInvoice(invoiceGroup[0]);
            optionText = `Combined Invoice - ${formattedDate}`;
        }
        
        // Add lease and service charge info
        const invoiceTypes = [];
        if (leaseInvoice) invoiceTypes.push('Lease');
        if (serviceChargeInvoice) invoiceTypes.push('Service Charge');
        optionText += ` (${invoiceTypes.join(' + ')})`;
        
        if (combinedStatus === 'paid') {
            optionText += ` (Paid: ${DEFAULT_CURRENCY} ${totalAmount.toLocaleString()})`;
        } else if (combinedStatus === 'partial') {
            optionText += ` (Balance: ${DEFAULT_CURRENCY} ${totalBalance.toLocaleString()})`;
        } else {
            optionText += ` (Due: ${DEFAULT_CURRENCY} ${totalAmount.toLocaleString()})`;
        }
        
        // Add due date if available
        if (latestDueDate) {
            const dueDateStr = latestDueDate.toLocaleDateString();
            optionText += ` - Due: ${dueDateStr}`;
        }
        
        // Create combined invoice ID (comma-separated) as fallback
        const combinedId = invoiceGroup.map(inv => inv.id).join(',');
        // Use combined_invoice_number as option value when available, otherwise fallback to combinedId
        const combinedValue = invoiceGroup[0].combined_invoice_number || combinedId;
        
        const option = new Option(optionText, combinedValue, false, false);
        
        // Add data attributes for combined information
        option.setAttribute('data-amount', totalAmount);
        option.setAttribute('data-amount-paid', totalAmountPaid);
        option.setAttribute('data-balance', totalBalance);
        option.setAttribute('data-status', combinedStatus);
        option.setAttribute('data-due-date', latestDueDate ? latestDueDate.toISOString().split('T')[0] : '');
        option.setAttribute('data-period', invoiceGroup[0].period || this.formatPeriodFromInvoice(invoiceGroup[0]));
        option.setAttribute('data-invoice-type', 'combined');
        option.setAttribute('data-combined-invoice-number', invoiceGroup[0].combined_invoice_number || '');
        option.setAttribute('data-total-late-fee', totalLateFee); // Add combined late fee data
        // Store full invoice objects as JSON string
        option.setAttribute('data-invoice-data', JSON.stringify(invoiceGroup));
        // Store individual numeric ids for fallback usage
        option.setAttribute('data-invoice-ids', combinedId);
        option.setAttribute('data-lease-amount', leaseInvoice ? leaseInvoice.amount : 0);
        option.setAttribute('data-sc-amount', serviceChargeInvoice ? serviceChargeInvoice.amount : 0);
        
        // Disable fully paid invoices
        if (combinedStatus === 'paid') {
            option.disabled = true;
            option.style.color = '#999';
        }
        
        invoiceSelect.append(option);
    }

    // Helper method to format period from invoice data
    formatPeriodFromInvoice(invoice) {
        // Check for billing period dates first
        if (invoice.billing_period_start && invoice.billing_period_end) {
            const start = new Date(invoice.billing_period_start);
            const end = new Date(invoice.billing_period_end);
            
            if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
                return start.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }
            
            return start.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) + 
                ' - ' + end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }
        
        // Fallback to due date
        if (invoice.due_date) {
            const dueDate = new Date(invoice.due_date);
            return dueDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }
        
        // Last fallback - try to extract from invoice number
        if (invoice.invoice_number) {
            const dateMatch = invoice.invoice_number.match(/(\d{8})/);
            if (dateMatch) {
                return this.formatDateFromKey(dateMatch[1]);
            }
        }
        
        return 'N/A';
    }

    // Helper method to format date key into readable format
    formatDateFromKey(dateKey) {
        if (dateKey && dateKey.length === 8) {
            // Format YYYYMMDD to readable date
            const year = dateKey.substring(0, 4);
            const month = dateKey.substring(4, 6);
            const day = dateKey.substring(6, 8);
            const date = new Date(year, month - 1, day);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        return dateKey;
    }
    
    // Updated handle invoice selection to work with both single and combined invoices
    handleInvoiceSelection(invoiceId) {
        if (!invoiceId) {
            $('input[name="amount_paid"]').val('');
            $('#invoice_details').remove();
            return;
        }
        
        const selectedOption = $(`select[name="invoice_id"] option[value="${invoiceId}"]`);
        const balance = parseFloat(selectedOption.data('balance')) || 0;
        const amount = parseFloat(selectedOption.data('amount')) || 0;
        const status = selectedOption.data('status');
        const invoiceType = selectedOption.data('invoice-type');

        // Calculate full payment amount for current selection
        const fullAmount = this.calculateFullPaymentAmount(selectedOption);
        
        // Check current payment status and update accordingly
        const paymentStatus = $('input[name="payment_status"]:checked').val();
        
        // Auto-populate the amount based on invoice status
        if (paymentStatus === 'paid') {
            // For full payment, store the calculated amount
            $('#amount_paid').val(fullAmount);
        } else if (paymentStatus === 'partial') {
            // For partial payment, clear amount and let user specify in allocation inputs
            $('#amount_paid').val('0');
            this.setupPaymentAllocation();
        }
        
        // Show invoice details
        if (invoiceType === 'combined') {
            this.displayCombinedInvoiceDetails(selectedOption);
        } else {
            this.displayInvoiceDetails(selectedOption);
        }
    }
    
    // New method to display combined invoice details
    displayCombinedInvoiceDetails(selectedOption) {
        const totalAmount = parseFloat(selectedOption.data('amount')) || 0;
        const amountPaid = parseFloat(selectedOption.data('amount-paid')) || 0;
        const balance = parseFloat(selectedOption.data('balance')) || 0;
        const status = selectedOption.data('status');
        const period = selectedOption.data('period');
        const dueDate = selectedOption.data('due-date');
        const combinedInvoiceNumber = selectedOption.data('combined-invoice-number');
        const leaseAmount = parseFloat(selectedOption.data('lease-amount')) || 0;
        const scAmount = parseFloat(selectedOption.data('sc-amount')) || 0;
        const totalLateFee = parseFloat(selectedOption.data('total-late-fee')) || 0;
        
        // Safely parse invoice data with error handling
        let invoiceData = [];
        try {
            const rawData = selectedOption.data('invoice-data');
            
            if (typeof rawData === 'string') {
                invoiceData = JSON.parse(rawData);
            } else if (Array.isArray(rawData)) {
                invoiceData = rawData;
            } else if (typeof rawData === 'object' && rawData !== null) {
                invoiceData = [rawData];
            } else {
                console.warn('Unexpected invoice data format:', rawData);
                invoiceData = [];
            }
        } catch (error) {
            console.error('Error parsing invoice data:', error);
            invoiceData = [];
        }
        
        let breakdownHtml = '';
        if (invoiceData.length > 0) {
            invoiceData.forEach(invoice => {
                const type = invoice.invoice_number && invoice.invoice_number.startsWith('Lease-') ? 'Lease' : 'Service Charge';
                const invAmount = parseFloat(invoice.amount || 0);
                const invPaid = parseFloat(invoice.amount_paid || 0);
                const invBalance = invAmount - invPaid;
                const invLateFee = parseFloat(invoice.late_fee || 0);
                
                breakdownHtml += `
                    <div class="row mb-2">
                        <div class="col-6">
                            <small><strong>${type}:</strong></small>
                            <br><small class="text-muted">${invoice.invoice_number}</small>
                        </div>
                        <div class="col-6 text-end">
                            <small>${DEFAULT_CURRENCY} ${invAmount.toLocaleString()}</small>
                            ${invPaid > 0 ? `<br><small class="text-muted">Paid: ${DEFAULT_CURRENCY} ${invPaid.toLocaleString()}</small>` : ''}
                            ${invBalance > 0 ? `<br><small class="text-warning">Balance: ${DEFAULT_CURRENCY} ${invBalance.toLocaleString()}</small>` : ''}
                        </div>
                    </div>
                `;
            });
        } else {
            // Fallback breakdown using the amounts stored separately
            if (leaseAmount > 0) {
                breakdownHtml += `
                    <div class="row mb-2">
                        <div class="col-6">
                            <small><strong>Lease:</strong></small>
                        </div>
                        <div class="col-6 text-end">
                            <small>${DEFAULT_CURRENCY} ${leaseAmount.toLocaleString()}</small>
                        </div>
                    </div>
                `;
            }
            if (scAmount > 0) {
                breakdownHtml += `
                    <div class="row mb-2">
                        <div class="col-6">
                            <small><strong>Service Charge:</strong></small>
                        </div>
                        <div class="col-6 text-end">
                            <small>${DEFAULT_CURRENCY} ${scAmount.toLocaleString()}</small>
                        </div>
                    </div>
                `;
            }
        }
        
        // Create combined invoice details display
        let detailsHtml = `
            <div class="alert alert-info mt-3" id="invoice_details">
                <h6 class="mb-3">Combined Invoice Details</h6>
                ${combinedInvoiceNumber ? `<div class="mb-2"><strong>Combined Invoice #:</strong> ${combinedInvoiceNumber}</div>` : ''}
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small><strong>Period:</strong> ${period}</small><br>
                        <small><strong>Total Amount:</strong> ${DEFAULT_CURRENCY} ${totalAmount.toLocaleString()}</small>
                        ${totalLateFee > 0 ? `<br><small><strong>Late Fee:</strong> ${DEFAULT_CURRENCY} ${totalLateFee.toLocaleString()}</small>` : ''}
                    </div>
                    <div class="col-md-6">
                        <small><strong>Balance:</strong> ${DEFAULT_CURRENCY} ${balance.toLocaleString()}</small><br>
                        <small><strong>Status:</strong> <span class="badge badge-${this.getStatusColor(status)}">${status.toUpperCase()}</span></small><br>
                        ${dueDate ? `<small><strong>Due Date:</strong> ${new Date(dueDate).toLocaleDateString()}</small>` : ''}
                    </div>
                </div>
                <div class="border-top pt-3">
                    <h6 class="mb-2">Invoice Breakdown:</h6>
                    ${breakdownHtml}
                </div>
            </div>
        `;
        
        // Remove existing details and add new ones
        $('#invoice_details').remove();
        $('select[name="invoice_id"]').closest('.fv-row').after(detailsHtml);
    }
    
    // Keep the existing displayInvoiceDetails method for single invoices
    displayInvoiceDetails(selectedOption) {
        const amount = parseFloat(selectedOption.data('amount')) || 0;
        const amountPaid = parseFloat(selectedOption.data('amount-paid')) || 0;
        const balance = parseFloat(selectedOption.data('balance')) || 0;
        const status = selectedOption.data('status');
        const period = selectedOption.data('period');
        const dueDate = selectedOption.data('due-date');
        const lateFee = parseFloat(selectedOption.data('late-fee')) || 0;
        
        // Create or update invoice details display
        let detailsHtml = `
            <div class="alert alert-info mt-3" id="invoice_details">
                <h6 class="mb-2">Invoice Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <small><strong>Period:</strong> ${period}</small><br>
                        <small><strong>Total Amount:</strong> ${DEFAULT_CURRENCY} ${amount.toLocaleString()}</small>
                        ${lateFee > 0 ? `<br><small><strong>Late Fee:</strong> ${DEFAULT_CURRENCY} ${lateFee.toLocaleString()}</small>` : ''}
                    </div>
                    <div class="col-md-6">
                        <small><strong>Balance:</strong> ${DEFAULT_CURRENCY} ${balance.toLocaleString()}</small><br>
                        <small><strong>Status:</strong> <span class="badge badge-${this.getStatusColor(status)}">${status.toUpperCase()}</span></small><br>
                        ${dueDate ? `<small><strong>Due Date:</strong> ${new Date(dueDate).toLocaleDateString()}</small>` : ''}
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing details and add new ones
        $('#invoice_details').remove();
        $('select[name="invoice_id"]').closest('.fv-row').after(detailsHtml);
    }

    // Helper method to get status color
    getStatusColor(status) {
        switch(status) {
            case 'paid': return 'success';
            case 'partial': return 'warning';
            case 'pending': return 'info';
            case 'overdue': return 'danger';
            default: return 'secondary';
        }
    }

    // Handle payment status change
    handlePaymentStatusChange(e) {
        const status = e.target.value;
        const allocationSection = $('#payment_allocation_section');
        
        if (status === 'partial') {
            // Show allocation section for partial payments
            allocationSection.fadeIn(300);

            // Reset amount_paid to 0 since user will specify amounts in allocation inputs
            $('#amount_paid').val('0');
            
            this.setupPaymentAllocation();
        } else {
            // Hide allocation section for full/pending payments
            allocationSection.fadeOut(300);
            
            // For full payments, calculate and store the full amount
            if (status === 'paid') {
                const selectedOption = $('select[name="invoice_id"] option:selected');
                const fullAmount = this.calculateFullPaymentAmount(selectedOption);
                
                // Store full amount in hidden input
                $('#amount_paid').val(fullAmount);
                
            } else {
                // For pending payments, clear the amount
                $('#amount_paid').val('0');
            }
            
            this.clearAllocationInputs();
        }
    }

    // calculateFullPaymentAmount method
    calculateFullPaymentAmount(selectedOption) {
        if (!selectedOption || !selectedOption.val()) {
            return 0;
        }
        
        const invoiceType = selectedOption.data('invoice-type');
        const balance = parseFloat(selectedOption.data('balance')) || 0;
        
        return balance;
    }

    // Setup payment allocation based on selected invoice type
    setupPaymentAllocation() {
        const selectedOption = $('select[name="invoice_id"] option:selected');
        const invoiceType = selectedOption.data('invoice-type');
        
        // Check if an invoice is actually selected
        if (!selectedOption.val()) {
            console.warn('No invoice selected for payment allocation');
            return;
        }
        
        if (invoiceType === 'combined') {
            this.setupCombinedInvoiceAllocation(selectedOption);
        } else {
            this.setupSingleInvoiceAllocation(selectedOption);
        }
    }

    // Setup allocation for single invoice
    setupSingleInvoiceAllocation(selectedOption) {
        const balance = parseFloat(selectedOption.data('balance')) || 0;
        
        // Safely get invoice data
        let invoiceData = [];
        try {
            const rawData = selectedOption.data('invoice-data');
            if (typeof rawData === 'string') {
                invoiceData = JSON.parse(rawData);
            } else if (Array.isArray(rawData)) {
                invoiceData = rawData;
            } else if (typeof rawData === 'object' && rawData !== null) {
                invoiceData = [rawData];
            }
        } catch (error) {
            console.error('Error parsing single invoice data:', error);
            invoiceData = [];
        }
        
        const invoiceType = invoiceData.length > 0 && invoiceData[0]?.invoice_number?.startsWith('Lease-') ? 'Lease' : 'Service Charge';
        
        $('#single_invoice_allocation').show();
        $('#combined_invoice_allocation').hide();
        
        $('#single_invoice_balance').text(`${balance.toLocaleString()} (${invoiceType})`);
        
        // Set max amount to the balance
        $('input[name="single_invoice_amount"]').attr('max', balance);
        
        // Suggest the full balance as default for partial payments
        $('input[name="single_invoice_amount"]').attr('placeholder', `Max: ${DEFAULT_CURRENCY} ${balance.toLocaleString()}`);
    }

    // Setup allocation for combined invoices
    setupCombinedInvoiceAllocation(selectedOption) {
        let invoiceData = [];
        
        // Safely parse invoice data with comprehensive error handling
        try {
            const rawData = selectedOption.data('invoice-data');
            console.log('Raw invoice data type:', typeof rawData);
            console.log('Raw invoice data:', rawData);
            
            if (typeof rawData === 'string') {
                invoiceData = JSON.parse(rawData);
            } else if (Array.isArray(rawData)) {
                invoiceData = rawData;
            } else if (typeof rawData === 'object' && rawData !== null) {
                // If it's already an object, wrap it in an array
                invoiceData = [rawData];
            } else {
                console.warn('Unexpected invoice data format:', rawData);
                // Fall back to using data attributes
                invoiceData = this.getFallbackInvoiceData(selectedOption);
            }
        } catch (error) {
            console.error('Error parsing combined invoice data:', error);
            console.log('Problematic data:', selectedOption.data('invoice-data'));
            // Fall back to using individual data attributes
            invoiceData = this.getFallbackInvoiceData(selectedOption);
        }
        
        $('#single_invoice_allocation').hide();
        $('#combined_invoice_allocation').show();
        
        // Find lease and service charge invoices
        const leaseInvoice = invoiceData.find(inv => inv.invoice_number?.startsWith('Lease-'));
        const serviceChargeInvoice = invoiceData.find(inv => inv.invoice_number?.startsWith('SC-'));
        
        if (leaseInvoice) {
            const leaseBalance = parseFloat(leaseInvoice.amount || 0) - parseFloat(leaseInvoice.amount_paid || 0);
            $('#lease_balance').text(leaseBalance.toLocaleString());
            $('input[name="lease_amount"]').attr('max', leaseBalance).data('invoice-id', leaseInvoice.id);
        } else {
            // Fallback: hide lease section if no lease invoice found
            $('input[name="lease_amount"]').closest('.col-md-6').hide();
        }
        
        if (serviceChargeInvoice) {
            const scBalance = parseFloat(serviceChargeInvoice.amount || 0) - parseFloat(serviceChargeInvoice.amount_paid || 0);
            $('#service_charge_balance').text(scBalance.toLocaleString());
            $('input[name="service_charge_amount"]').attr('max', scBalance).data('invoice-id', serviceChargeInvoice.id);
        } else {
            // Fallback: hide service charge section if no SC invoice found
            $('input[name="service_charge_amount"]').closest('.col-md-6').hide();
        }
        
        // Initialize allocation tracking
        this.updateAllocationSummary();
    }

    // Fallback method to get invoice data from individual data attributes
    getFallbackInvoiceData(selectedOption) {
        const leaseAmount = parseFloat(selectedOption.data('lease-amount')) || 0;
        const scAmount = parseFloat(selectedOption.data('sc-amount')) || 0;
        const invoiceIds = selectedOption.val().split(',');
        
        const fallbackData = [];
        
        if (leaseAmount > 0 && invoiceIds.length > 0) {
            fallbackData.push({
                id: invoiceIds[0],
                invoice_number: 'Lease-FALLBACK',
                amount: leaseAmount,
                amount_paid: 0
            });
        }
        
        if (scAmount > 0 && invoiceIds.length > 1) {
            fallbackData.push({
                id: invoiceIds[1],
                invoice_number: 'SC-FALLBACK',
                amount: scAmount,
                amount_paid: 0
            });
        }
        
        console.log('Using fallback invoice data:', fallbackData);
        return fallbackData;
    }

    // Handle allocation input changes
    handleAllocationChange() {
        this.updateAllocationSummary();
    }

    // Handle amount paid changes
    handleAmountPaidChange() {
        this.updateAllocationSummary();
    }

    // Update allocation summary
    updateAllocationSummary() {
        const paymentStatus = $('input[name="payment_status"]:checked').val();
    
        if (paymentStatus === 'partial') {
            // Calculate total from allocation inputs
            const invoiceType = $('select[name="invoice_id"] option:selected').data('invoice-type');
            
            if (invoiceType === 'combined') {
                const leaseAmount = parseFloat($('input[name="lease_amount"]').val()) || 0;
                const serviceChargeAmount = parseFloat($('input[name="service_charge_amount"]').val()) || 0;
                const totalAmount = leaseAmount + serviceChargeAmount;
                
                // Update hidden amount_paid field
                $('#amount_paid').val(totalAmount);
                
                // Update allocation summary
                $('#total_allocated').text(totalAmount.toLocaleString());
                
                if (totalAmount > 0) {
                    $('#allocation_status').text('Ready to process').removeClass('text-warning text-danger').addClass('text-success');
                } else {
                    $('#allocation_status').text('Enter amounts above').removeClass('text-success text-danger').addClass('text-warning');
                }
            } else {
                const singleAmount = parseFloat($('input[name="single_invoice_amount"]').val()) || 0;
                
                // Update hidden amount_paid field
                $('#amount_paid').val(singleAmount);
            }
        }
    }

    // A method to clear and reset allocation inputs
    clearAllocationInputs() {
        $('input[name="single_invoice_amount"]').val('').removeAttr('max');
        $('input[name="lease_amount"]').val('').removeAttr('max').removeData('invoice-id');
        $('input[name="service_charge_amount"]').val('').removeAttr('max').removeData('invoice-id');
        
        // Show both allocation sections (reset visibility)
        $('input[name="lease_amount"]').closest('.col-md-6').show();
        $('input[name="service_charge_amount"]').closest('.col-md-6').show();
        
        // Reset summary
        $('#total_allocated').text('0');
        $('#allocation_status').text('Enter amounts above').removeClass('text-success text-danger').addClass('text-warning');
    }

    // A method to handle payment date changes and late fee calculation
    handlePaymentDateChange() {
        const paymentDateInput = $('input[name="payment_date"]');
        const paymentDate = paymentDateInput.val();
        const selectedOption = $('select[name="invoice_id"] option:selected');
        
        if (!selectedOption.val() || !paymentDate) {
            return;
        }
        
        const dueDate = selectedOption.data('due-date');
        if (!dueDate) {
            return;
        }
        
        const invoiceDueDate = new Date(dueDate);
        const selectedPaymentDate = new Date(paymentDate);
        const lateFeeInput = $('input[name="late_fee"]');
        
        // Check if payment date is after due date
        if (selectedPaymentDate > invoiceDueDate) {
            // Calculate days late
            const daysLate = Math.ceil((selectedPaymentDate - invoiceDueDate) / (1000 * 60 * 60 * 24));
            
            // Get late fee from invoice data
            this.applyLateFeeFromInvoice(selectedOption, daysLate);
        } else {
            // Clear late fee if payment is not late
            lateFeeInput.val('').trigger('change');
            this.hideLateFeeInfo();
        }
    }

    // A method to apply late fee from invoice data
    applyLateFeeFromInvoice(selectedOption, daysLate) {
        const invoiceType = selectedOption.data('invoice-type');
        let totalLateFee = 0;
        
        if (invoiceType === 'combined') {
            // For combined invoices, get late fee from invoice data
            let invoiceData = [];
            try {
                const rawData = selectedOption.data('invoice-data');
                if (typeof rawData === 'string') {
                    invoiceData = JSON.parse(rawData);
                } else if (Array.isArray(rawData)) {
                    invoiceData = rawData;
                }
            } catch (error) {
                console.error('Error parsing invoice data for late fee:', error);
                invoiceData = this.getFallbackInvoiceData(selectedOption);
            }
            
            // Sum up late fees from all invoices in the combination
            invoiceData.forEach(invoice => {
                if (invoice.late_fee) {
                    totalLateFee = parseFloat(invoice.late_fee);
                }
            });
        } else {
            // For single invoices, get late fee directly from the selected option
            try {
                const rawData = selectedOption.data('invoice-data');
                let invoiceData = [];
                
                if (typeof rawData === 'string') {
                    invoiceData = JSON.parse(rawData);
                } else if (Array.isArray(rawData)) {
                    invoiceData = rawData;
                }
                
                if (invoiceData.length > 0 && invoiceData[0].late_fee) {
                    totalLateFee = parseFloat(invoiceData[0].late_fee);
                }
            } catch (error) {
                console.error('Error parsing single invoice data for late fee:', error);
            }
        }
        
        // Apply the late fee
        if (totalLateFee > 0) {
            $('input[name="late_fee"]').val(totalLateFee);
            this.showLateFeeInfo(daysLate, totalLateFee, 'From tenancy late fee configuration');
        } else {
            // Fallback: calculate based on tenant configuration if no late fee in invoice
            const tenantId = $('#payment_tenant_id').val();
            this.calculateLateFeeFromTenant(tenantId, daysLate, selectedOption);
        }
    }

    // A method to calculate late fee from tenant configuration (fallback)
    calculateLateFeeFromTenant(tenantId, daysLate, selectedOption) {
        // Late fees are charged on the tenant account (Tenancy → Account → Add charge), not with the payment.
        this.showLateFeeInfo(daysLate, 0, 'Payment is late. Add any late fee as a charge on the tenant account.');
    }

    // A method to show late fee information
    showLateFeeInfo(daysLate, lateFee, calculationMethod) {
        // Remove existing late fee info
        $('#late_fee_info').remove();
        
        const lateFeeAmount = parseFloat(lateFee || 0);
        const alertClass = lateFeeAmount > 0 ? 'alert-warning' : 'alert-info';
        const iconClass = lateFeeAmount > 0 ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle';
        
        const infoHtml = `
            <div id="late_fee_info" class="alert ${alertClass} mt-2">
                <div class="d-flex align-items-center">
                    <i class="${iconClass} me-2"></i>
                    <div>
                        <strong>Late Payment Detected</strong>
                        <div id="late_fee_details" class="mt-1 fs-7">
                            Payment is ${daysLate} day(s) late. 
                            ${lateFeeAmount > 0 
                                ? `Late fee of ${DEFAULT_CURRENCY} ${lateFeeAmount.toLocaleString()} applied (${calculationMethod}).`
                                : calculationMethod
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('input[name="late_fee"]').closest('.fv-row').after(infoHtml);
        $('#late_fee_info').fadeIn(300);

        // Show the late fee payment option if there's a late fee
        if (lateFeeAmount > 0) {
            $('#late_fee_payment_option').fadeIn(300);
            // Default to not paid (will be transferred to next billing)
            $('#late_fee_paid_now').prop('checked', false);
        } else {
            $('#late_fee_payment_option').fadeOut(300);
        }
    }

    // A method to hide late fee information
    hideLateFeeInfo() {
        $('#late_fee_info').fadeOut(300, function() {
            $(this).remove();
        });
        $('#late_fee_payment_option').fadeOut(300);
        $('#late_fee_paid_now').prop('checked', false);
    }

    initializeComplexOccupancyTable() {
        if ($.fn.DataTable.isDataTable('#kt_table_complex_occupancy')) {
            $('#kt_table_complex_occupancy').DataTable().destroy();
        }
        
        complexOccupancyTable = $('#kt_table_complex_occupancy').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/tenant_management/complex-occupancy/data',
                type: 'GET'
            },
            columns: [
                {
                    data: null,
                    orderable: true,
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex flex-column">
                                <span class="text-gray-800 fw-bold">${tenantTableText(row.title)}</span>
                                <span class="text-gray-400 fw-semibold fs-7">${tenantTableText(row.ref_code)}</span>
                            </div>
                        `;
                    }
                },
                {
                    data: 'type',
                    className: "text-center",
                    orderable: true,
                    render: function(data, type, row) {
                        return `<span class="badge badge-light-primary">${tenantTableText(data)}</span>`;
                    }
                },
                {
                    data: 'total_capacity',
                    className: "text-center",
                    orderable: true,
                    render: function(data, type, row) {
                        return `<span class="text-gray-900 fw-bold">${tenantTableText(data)}</span>`;
                    }
                },
                {
                    data: 'total_occupied',
                    className: "text-center", 
                    orderable: true,
                    render: function(data, type, row) {
                        return `<span class="text-success fw-bold">${tenantTableText(data)}</span>`;
                    }
                },
                {
                    data: 'occupancy_percentage',
                    className: "text-center",
                    orderable: true,
                    render: function(data, type, row) {
                        let occupancyClass = 'text-secondary';
                        if (data >= 80) occupancyClass = 'text-success';
                        else if (data >= 60) occupancyClass = 'text-warning';
                        else if (data >= 40) occupancyClass = 'text-primary';
                        else occupancyClass = 'text-danger';
                        
                        return `<span class="${occupancyClass} fw-bold">${tenantTableText(data)}%</span>`;
                    }
                },
                // {
                //     data: null,
                //     className: "text-center",
                //     orderable: false,
                //     render: function(data, type, row) {
                //         return `<span class="text-gray-700">${row.regular_units.occupied}/${row.regular_units.available}</span>`;
                //     }
                // },
                {
                    data: null,
                    className: "text-center",
                    orderable: false,
                    render: function(data, type, row) {
                        if (row.commercial_units.count > 0) {
                            return `
                                <div class="d-flex flex-column">
                                    <span class="text-gray-700 fw-semibold">${row.commercial_units.count} units</span>
                                    <span class="text-gray-500 fs-7">${row.commercial_units.effective_occupied}/${row.commercial_units.capacity} slots (${row.commercial_units.tenants} tenants)</span>
                                </div>
                            `;
                        } else {
                            return '<span class="text-gray-400">-</span>';
                        }
                    }
                },
                {
                    data: 'monthly_revenue',
                    className: "text-end",
                    orderable: true,
                    render: (data, type, row) => {
                        // Use arrow function to maintain 'this' context
                        return `<span class="text-gray-900 fw-bold">${row.currency} ${this.formatCurrency(data)}</span>`;
                    }
                }
            ],
            order: [[4, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            searchDelay: 500,
            language: {
                emptyTable: "No complexes found",
                info: "Showing _START_ to _END_ of _TOTAL_ complexes",
                infoEmpty: "Showing 0 to 0 of 0 complexes",
                infoFiltered: "(filtered from _MAX_ total complexes)",
                lengthMenu: "Show _MENU_ complexes",
                loadingRecords: "Loading complexes...",
                processing: "Processing...",
                search: "Search complexes:",
                zeroRecords: "No matching complexes found"
            }
        });

        // Handle search
        $('#complex_search').on('keyup', debounce(function(event) {
            complexOccupancyTable.search(this.value).draw();
        }, 500));
    }
}

// Bulk Upload Management Class
class BulkUploadManager {
    constructor() {
        this.currentFileData = null;
        this.csvHeaders = [];
        this.fieldMappings = {};
        this.requiredFields = {};
        this.uploadType = null;
        this.stepper = null;
        
        // Initialize after a small delay to ensure DOM is ready
        setTimeout(() => {
            this.initializeStepper();
            this.initializeEventListeners();
        }, 100);
    }

    initializeStepper() {
        const stepperElement = document.querySelector('#kt_stepper_bulk_upload');
        if (!stepperElement) {
            console.error('Bulk upload stepper element not found');
            return;
        }
    
        try {
            this.stepper = new KTStepper(stepperElement);
            console.log('Stepper initialized successfully:', this.stepper);
    
            // Handle stepper navigation
            this.stepper.on('kt.stepper.next', (stepper) => {
                console.log('Stepper next event fired');
                const currentStepIndex = stepper.getCurrentStepIndex();
                console.log('Current step index:', currentStepIndex);
    
                if (currentStepIndex === 1) {
                    // Moving from step 1 to step 2
                    console.log('Validating step 1...');
                    if (this.validateStep1()) {
                        console.log('Step 1 valid, parsing CSV...');
                        this.parseCSVFile();
                    } else {
                        console.log('Step 1 validation failed');
                        return false; // Prevent navigation
                    }
                } else if (currentStepIndex === 2) {
                    // Moving from step 2 to submit
                    console.log('Validating step 2...');
                    if (this.validateMapping()) {
                        console.log('Step 2 valid, submitting...');
                        this.submitBulkUpload();
                    } else {
                        console.log('Step 2 validation failed');
                        return false; // Prevent navigation
                    }
                }
            });
    
            this.stepper.on('kt.stepper.previous', (stepper) => {
                console.log('Stepper previous clicked');
                stepper.goPrevious();
                this.updateStepperButtons();
            });
    
            // Initial button state
            this.updateStepperButtons();
            
        } catch (error) {
            console.error('Error initializing stepper:', error);
        }
    }

    initializeEventListeners() {
        console.log('Initializing event listeners');
        
        // Upload type change
        $('#upload_type').on('change', (e) => {
            this.uploadType = e.target.value;
            console.log('Upload type changed to:', this.uploadType);
            this.updateUploadTypeInstructions();
            this.updateTemplateDownloadLink();
        });
    
        // File upload change
        $('#data_file').on('change', (e) => {
            console.log('File selected:', e.target.files[0]);
            if (e.target.files.length > 0) {
                this.handleFileSelection(e.target.files[0]);
            }
        });

        // Dropzone interactions
        $('#bulk-upload-dropzone').on('click', (e) => {
            if (!$(e.target).is('input')) $('#data_file').click();
        });

        $('#bulk-upload-dropzone').on('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            $('#bulk-upload-dropzone').css('background', '#EEF6FF');
        });

        $('#bulk-upload-dropzone').on('dragleave dragend', () => {
            $('#bulk-upload-dropzone').css('background', '');
        });

        $('#bulk-upload-dropzone').on('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            $('#bulk-upload-dropzone').css('background', '');
            const file = e.originalEvent.dataTransfer.files[0];
            if (file) this.handleFileSelection(file);
        });
    
        // Manual button handlers - IMPROVED
        $(document).on('click', '[data-kt-stepper-action="next"]', (e) => {
            // Only handle clicks from the bulk upload modal
            if (!$(e.target).closest('#kt_modal_bulk_upload').length) {
                return; // Let other handlers manage this
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Manual next button clicked');
            
            if (!this.stepper) {
                console.error('Stepper not initialized');
                return;
            }
            
            const currentStep = this.stepper.getCurrentStepIndex();
            console.log('Current step:', currentStep);
            
            if (currentStep === 1) {
                console.log('Processing step 1...');
                if (this.validateStep1()) {
                    console.log('Step 1 validated, parsing CSV...');
                    this.parseCSVAndMoveToStep2();
                } else {
                    console.log('Step 1 validation failed');
                }
            } else if (currentStep === 2) {
                console.log('Processing step 2...');
                if (this.validateMapping()) {
                    console.log('Step 2 validated, submitting...');
                    this.submitBulkUpload();
                } else {
                    console.log('Step 2 validation failed');
                }
            }
        });
        
        $(document).on('click', '[data-kt-stepper-action="previous"]', (e) => {
            if (!$(e.target).closest('#kt_modal_bulk_upload').length) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Manual previous button clicked');
            
            if (this.stepper) {
                this.stepper.goPrevious();
                this.updateStepperButtons();
            }
        });
    
        // Submit button handler
        $(document).on('click', '[data-kt-stepper-action="submit"]', (e) => {
            if (!$(e.target).closest('#kt_modal_bulk_upload').length) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Submit button clicked');
            this.submitBulkUpload();
        });
    }

    validateStep1() {
        console.log('Validating step 1...');
        
        if (!this.uploadType) {
            this.showError('Please select an upload type.');
            return false;
        }

        if (!this.currentFile) {
            this.showError('Please select a file to upload.');
            return false;
        }

        console.log('Step 1 validation passed');
        return true;
    }

    updateStepperButtons() {
        if (!this.stepper) return;
    
        try {
            const currentStep = this.stepper.getCurrentStepIndex();
            const form = document.getElementById('kt_modal_bulk_upload_form');
            const nextBtn = form.querySelector('[data-kt-stepper-action="next"]');
            const prevBtn = form.querySelector('[data-kt-stepper-action="previous"]');
            const submitBtn = form.querySelector('[data-kt-stepper-action="submit"]');
    
            // Update stepper navigation classes
            const stepperItems = document.querySelectorAll('#kt_stepper_bulk_upload .stepper-item');
            stepperItems.forEach((item, index) => {
                item.classList.remove('current', 'completed');
                
                if (index + 1 === currentStep) {
                    item.classList.add('current');
                } else if (index + 1 < currentStep) {
                    item.classList.add('completed');
                }
            });
    
            // Update button visibility based on current step
            if (currentStep === 1) {
                if (prevBtn) prevBtn.style.display = 'none';
                if (nextBtn) nextBtn.style.display = 'inline-block';
                if (submitBtn) submitBtn.style.display = 'none';
            } else if (currentStep === 2) {
                if (prevBtn) prevBtn.style.display = 'inline-block';
                if (nextBtn) nextBtn.style.display = 'none';
                if (submitBtn) submitBtn.style.display = 'inline-block';
            }
            
            console.log('Stepper buttons updated for step:', currentStep);
            
        } catch (error) {
            console.error('Error updating stepper buttons:', error);
        }
    }

    updateUploadTypeInstructions() {
        const instructions = this.getUploadTypeInstructions(this.uploadType);
        $('#upload_type_instructions').html(instructions);
    }

    getUploadTypeInstructions(uploadType) {
        const instructionsMap = {
            'tenants': `
                <div class="alert alert-info d-flex align-items-start p-3 mb-4">
                    <i class="fas fa-users fs-2x text-info me-3 mt-1"></i>
                    <div class="d-flex flex-column">
                        <h6 class="mb-1">Tenant Profile Upload</h6>
                        <small class="text-muted">
                            <strong>Required:</strong> tenant_type<br>
                            <strong>Individual:</strong> tenant_name, tenant_email, tenant_phone, tenant_id_number<br>
                            <strong>Business:</strong> business_name, business_email, business_phone, business_registration, business_contact_person
                        </small>
                        <small class="mt-2"><strong>Tenant Types:</strong> <code>Individual</code> or <code>Business</code></small>
                    </div>
                </div>
            `,
            'payments': `
                <div class="alert alert-success d-flex align-items-start p-3 mb-4">
                    <i class="fas fa-credit-card fs-2x text-success me-3 mt-1"></i>
                    <div class="d-flex flex-column">
                        <h6 class="mb-1">Payment Records Upload</h6>
                        <small class="text-muted">
                            <strong>Required:</strong> invoice_id, payment_date, amount_paid, payment_method, payment_status<br>
                            <strong>Tenant identifier:</strong> tenancy_ref (preferred), tenant_profile_ref, or tenant_email<br>
                            <strong>Invoice ID format:</strong> as shown on the invoice, e.g. <code>LEASE-20240105-A3F2B1</code> (see Tenancy Billings page)
                        </small>
                        <small class="mt-2 text-info"><i class="fas fa-link me-1"></i>Attach receipt files separately via <strong>Receipt Reconciliation</strong> after uploading.</small>
                    </div>
                </div>
            `,
            'notices': `
                <div class="alert alert-warning d-flex align-items-start p-3 mb-4">
                    <i class="fas fa-exclamation-triangle fs-2x text-warning me-3 mt-1"></i>
                    <div class="d-flex flex-column">
                        <h6 class="mb-1">Notice Records Upload</h6>
                        <small class="text-muted"><strong>Required:</strong> tenant_ref, notice_type, notice_date, effective_date</small>
                    </div>
                </div>
            `
        };

        return instructionsMap[uploadType] || '';
    }

    updateTemplateDownloadLink() {
        if (!this.uploadType) {
            $('#template_download_section').html('');
            return;
        }

        const endpoints = {
            'tenants': '/tenant_management/bulk-upload/template/tenants',
            'payments': '/tenant_management/bulk-upload/template/payments', 
            'notices': '/tenant_management/bulk-upload/template/notices'
        };

        const label = this.uploadType.charAt(0).toUpperCase() + this.uploadType.slice(1);
        const templateHtml = `
            <a href="${endpoints[this.uploadType]}" class="btn btn-light-primary btn-sm w-100" target="_blank">
                <i class="fas fa-file-download me-2"></i>Download ${label} Template
            </a>
            <p class="text-muted fs-8 mt-2 mb-0 text-center">Fill this in and upload above</p>
        `;

        $('#template_download_section').html(templateHtml);
    }

    handleFileSelection(file) {
        if (!file) return;

        const fileType = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'xlsx', 'xls'].includes(fileType)) {
            this.showError('Please select a CSV or Excel file.');
            return;
        }

        // Store file for later use
        this.currentFile = file;
        console.log('File stored:', this.currentFile);

        // Show file info chip
        const icon = file.name.endsWith('.pdf') ? 'fa-file-pdf text-danger'
                   : file.name.endsWith('.xlsx') || file.name.endsWith('.xls') ? 'fa-file-excel text-success'
                   : 'fa-file-csv text-primary';
        const fileChip = `
            <div class="d-flex align-items-center gap-3 p-3 bg-light-success rounded border border-success">
                <i class="fas ${icon} fs-2x"></i>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-bold fs-7 text-dark text-truncate">${file.name}</div>
                    <div class="text-muted fs-8">${this.formatFileSize(file.size)}</div>
                </div>
                <button type="button" class="btn btn-icon btn-xs btn-light-danger" id="btn-clear-bulk-file" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        $('#bulk-upload-file-info').html(fileChip);

        // Clear button
        $('#btn-clear-bulk-file').on('click', (e) => {
            e.stopPropagation();
            this.currentFile = null;
            $('#data_file').val('');
            $('#bulk-upload-file-info').html('');
            $('#bulk-upload-dropzone').css('background', '');
        });
    }

    validateStep1() {
        if (!this.uploadType) {
            this.showError('Please select an upload type.');
            return;
        }

        if (!this.currentFile) {
            this.showError('Please select a file to upload.');
            return;
        }

        // Parse CSV file
        this.parseCSVFile();
    }

    parseCSVFile() {
        if (!this.currentFile) {
            this.showError('No file selected');
            return;
        }

        console.log('Parsing CSV file:', this.currentFile.name);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const csv = e.target.result;
                const lines = csv.split('\n');
                
                if (lines.length < 2) {
                    this.showError('CSV file must have at least a header row and one data row.');
                    return;
                }

                // Extract headers
                this.csvHeaders = lines[0].split(',').map(header => header.trim().replace(/"/g, ''));
                
                // Extract sample data (first 5 rows)
                const sampleData = lines.slice(1, 6).map(line => 
                    line.split(',').map(cell => cell.trim().replace(/"/g, ''))
                );

                this.currentFileData = {
                    headers: this.csvHeaders,
                    sampleData: sampleData,
                    totalRows: lines.length - 1
                };

                console.log('CSV parsed successfully:', this.currentFileData);

                // Move to step 2
                if (this.stepper) {
                    this.stepper.goNext();
                    this.updateStepperButtons();
                    this.setupColumnMapping();
                }

            } catch (error) {
                console.error('Error parsing CSV:', error);
                this.showError('Error parsing CSV file: ' + error.message);
            }
        };

        reader.readAsText(this.currentFile);
    }

    setupColumnMapping() {
        this.displayFilePreview();
        this.displayColumnMappingInterface();
    }

    displayFilePreview() {
        if (!this.currentFileData) return;

        let previewHtml = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">File Preview</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-info">${this.currentFileData.totalRows} total rows</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    ${this.currentFileData.headers.map(header => `<th>${header}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
        `;

        this.currentFileData.sampleData.forEach((row, index) => {
            if (row.some(cell => cell.trim() !== '')) { // Only show non-empty rows
                previewHtml += `
                    <tr>
                        ${row.map(cell => `<td>${cell || '<em>empty</em>'}</td>`).join('')}
                    </tr>
                `;
            }
        });

        previewHtml += `
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Showing first 5 rows of data</small>
                </div>
            </div>
        `;

        $('#file_preview_section').html(previewHtml);
    }

    displayColumnMappingInterface() {
        const requiredFields = this.getRequiredFields(this.uploadType);
        this.requiredFields = requiredFields;

        let mappingHtml = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Column Mapping</h3>
                </div>
                <div class="card-body">
                    <div class="row">
        `;

        Object.entries(requiredFields).forEach(([fieldKey, fieldInfo]) => {
            const isRequired = fieldInfo.required;
            const fieldLabel = fieldInfo.label;
            const fieldDescription = fieldInfo.description || '';

            mappingHtml += `
                <div class="col-md-6 mb-7">
                    <div class="fv-row">
                        <label class="fs-6 fw-bold form-label mb-2 ${isRequired ? 'required' : ''}">
                            ${fieldLabel}
                        </label>
                        <select name="field_mapping[${fieldKey}]" class="form-select form-select-solid field-mapping-select" data-field-key="${fieldKey}" data-required="${isRequired}">
                            <option value="">-- Select Column --</option>
                            ${this.csvHeaders.map((header, index) => `
                                <option value="${index}" data-header="${header}">${header}</option>
                            `).join('')}
                        </select>
                        ${fieldDescription ? `<div class="form-text">${fieldDescription}</div>` : ''}
                    </div>
                </div>
            `;
        });

        mappingHtml += `
                    </div>
                </div>
            </div>
        `;

        $('#column_mapping_section').html(mappingHtml);

        // Initialize auto-mapping
        this.attemptAutoMapping();

        // Add change listeners for validation
        $('.field-mapping-select').on('change', () => {
            this.validateMapping();
        });
    }

    getRequiredFields(uploadType) {
        const fieldsMap = {
            'tenants': {
                // Tenant Type & Selection
                'tenant_type': { 
                    label: 'Tenant Type', 
                    required: true, 
                    description: 'Individual or Business' 
                },
                
                // Individual Tenant Fields (Required when tenant_type = Individual and use_existing_tenant = false)
                'tenant_name': { 
                    label: 'Tenant Name (for Individual)', 
                    required: false, 
                    description: 'Full name for individual tenants - required for new individual tenants' 
                },
                'tenant_email': { 
                    label: 'Tenant Email (for Individual)', 
                    required: false, 
                    description: 'Email for individual tenants - required for new individual tenants' 
                },
                'tenant_phone': { 
                    label: 'Tenant Phone (for Individual)', 
                    required: false, 
                    description: 'Phone for individual tenants - required for new individual tenants' 
                },
                'tenant_id_number': { 
                    label: 'ID Number (for Individual)', 
                    required: false, 
                    description: 'ID or passport number - required for new individual tenants' 
                },
                'date_of_birth': { 
                    label: 'Date of Birth', 
                    required: false, 
                    description: 'YYYY-MM-DD format' 
                },
                'gender': { 
                    label: 'Gender', 
                    required: false, 
                    description: 'Male, Female, Other' 
                },
                'address': { 
                    label: 'Address', 
                    required: false, 
                    description: 'Personal address for individual tenants' 
                },
                'nationality': { 
                    label: 'Nationality', 
                    required: false, 
                    description: 'Tenant nationality' 
                },
                'occupation': { 
                    label: 'Occupation', 
                    required: false, 
                    description: 'Tenant occupation' 
                },
                'employer': { 
                    label: 'Employer', 
                    required: false, 
                    description: 'Current employer' 
                },
                'monthly_income': { 
                    label: 'Monthly Income', 
                    required: false, 
                    description: 'Monthly income amount' 
                },
                
                // Business Tenant Fields (Required when tenant_type = Business and use_existing_tenant = false)
                'business_name': { 
                    label: 'Business Name', 
                    required: false, 
                    description: 'Business name - required for new business tenants' 
                },
                'business_registration': { 
                    label: 'Business Registration', 
                    required: false, 
                    description: 'Business registration number - required for new business tenants' 
                },
                'business_contact_person': { 
                    label: 'Business Contact Person', 
                    required: false, 
                    description: 'Business contact person - required for new business tenants' 
                },
                'business_email': { 
                    label: 'Business Email', 
                    required: false, 
                    description: 'Business email address - required for new business tenants' 
                },
                'business_phone': { 
                    label: 'Business Phone', 
                    required: false, 
                    description: 'Business phone number - required for new business tenants' 
                },
                'business_address': { 
                    label: 'Business Address', 
                    required: false, 
                    description: 'Business address' 
                },
                'business_type': { 
                    label: 'Business Type', 
                    required: false, 
                    description: 'Type of business' 
                },
                'tax_id_number': { 
                    label: 'Tax ID Number', 
                    required: false, 
                    description: 'Business tax ID' 
                },
                'annual_revenue': { 
                    label: 'Annual Revenue', 
                    required: false, 
                    description: 'Business annual revenue' 
                },
                'years_in_business': { 
                    label: 'Years in Business', 
                    required: false, 
                    description: 'How long business has been operating' 
                },
                
                // Emergency Contact (Optional for both types)
                'emergency_contact_name': { 
                    label: 'Emergency Contact Name', 
                    required: false, 
                    description: 'Emergency contact full name' 
                },
                'emergency_contact_phone': { 
                    label: 'Emergency Contact Phone', 
                    required: false, 
                    description: 'Emergency contact phone number' 
                },
                'emergency_contact_relationship': { 
                    label: 'Emergency Contact Relationship', 
                    required: false, 
                    description: 'Relationship to tenant' 
                },
                'emergency_contact_address': { 
                    label: 'Emergency Contact Address', 
                    required: false, 
                    description: 'Emergency contact address' 
                },
                
                // Profile Status and Notes
                'profile_status': { 
                    label: 'Profile Status', 
                    required: false, 
                    description: 'Active, Inactive, Blacklisted' 
                },
                'profile_notes': { 
                    label: 'Profile Notes', 
                    required: false, 
                    description: 'Additional notes about tenant profile' 
                }
            },
            'payments': {
                'tenancy_ref': { 
                    label: 'Tenancy Reference', 
                    required: false, 
                    description: 'Tenancy reference number (TM12345678) - preferred for payment identification' 
                },
                'tenant_profile_ref': { 
                    label: 'Tenant Profile Reference', 
                    required: false, 
                    description: 'Tenant profile reference (TP12345678) - alternative identifier' 
                },
                'tenant_email': { 
                    label: 'Tenant Email', 
                    required: false, 
                    description: 'Tenant email address - alternative identifier' 
                },
                'invoice_id': { 
                    label: 'Invoice ID', 
                    required: true, 
                    description: 'Invoice number as shown on the invoice (e.g. LEASE-20240105-A3F2B1)' 
                },
                'payment_date': { 
                    label: 'Payment Date', 
                    required: true, 
                    description: 'Date payment was made (YYYY-MM-DD)' 
                },
                'amount_paid': { 
                    label: 'Amount Paid', 
                    required: true, 
                    description: 'Total amount being paid' 
                },
                'payment_method': { 
                    label: 'Payment Method', 
                    required: true, 
                    description: 'cash, bank_transfer, mobile_money, check, card, other' 
                },
                'payment_status': { 
                    label: 'Payment Status', 
                    required: true, 
                    description: 'paid, partial, pending, failed' 
                },
                'transaction_reference': { 
                    label: 'Transaction Reference', 
                    required: false, 
                    description: 'M-Pesa code, check number, bank reference, etc.' 
                },
                'late_fee': { 
                    label: 'Late Fee', 
                    required: false, 
                    description: 'Late fee amount if applicable' 
                },
                'late_fee_paid_now': { 
                    label: 'Late Fee Paid Now', 
                    required: false, 
                    description: 'true/false - whether late fee is paid with this payment' 
                },
                'payment_notes': { 
                    label: 'Payment Notes', 
                    required: false, 
                    description: 'Additional notes about the payment' 
                },
                'single_invoice_amount': { 
                    label: 'Single Invoice Amount', 
                    required: false, 
                    description: 'Amount for single invoice partial payment' 
                },
                'lease_amount': { 
                    label: 'Lease Amount', 
                    required: false, 
                    description: 'Amount allocated to tenancy/lease for combined invoices' 
                },
                'service_charge_amount': { 
                    label: 'Service Charge Amount', 
                    required: false, 
                    description: 'Amount allocated to service charges for combined invoices' 
                }
            },
            'notices': {
                'tenancy_ref': { label: 'Tenant Residency Reference', required: true, description: 'Tenant residency reference number (TP123456)' },
                'tenant_profile_ref': { label: 'Tenant Profile Reference', required: true, description: 'Tenant profile reference number (TP123456)' },
                'tenant_email': { label: 'Tenant Email', required: false, description: 'Tenant email address - optional but recommended' },
                'notice_type': { label: 'Notice Type', required: true, description: 'lease_termination, rent_increase, etc.' },
                'notice_date': { label: 'Notice Date', required: true, description: 'Date notice is given (YYYY-MM-DD)' },
                'effective_date': { label: 'Effective Date', required: true, description: 'Date notice takes effect (YYYY-MM-DD)' },
                'notice_subject': { label: 'Subject', required: true, description: 'Notice subject/title' },
                'notice_details': { label: 'Notice Details', required: true, description: 'Detailed notice content' },
                'notice_period': { label: 'Notice Period (Days)', required: false, description: 'Notice period in days' },
                'delivery_method': { label: 'Delivery Method', required: false, description: 'email, sms, physical (comma-separated)' },
                'priority_level': { label: 'Priority Level', required: false, description: 'Priority of the notice (e.g. High, Medium, Low)' },
                'response_required': { label: 'Response Required', required: false, description: 'Is a response required? true/false' },
                'response_deadline': { label: 'Response Deadline', required: false, description: 'Deadline for response (YYYY-MM-DD)' },
                'follow_up_required': { label: 'Follow Up Required', required: false, description: 'Is follow-up required? true/false' },
                'follow_up_date': { label: 'Follow Up Date', required: false, description: 'Date for follow-up (YYYY-MM-DD)' },
                'escalation_level': { label: 'Escalation Level', required: false, description: 'Level of escalation if not resolved' },
                'legal_reference': { label: 'Legal Reference', required: false, description: 'Legal reference or clause' },
                'amount_involved': { label: 'Amount Involved', required: false, description: 'Amount involved in the notice (if any)' },
                'contact_person': { label: 'Contact Person', required: false, description: 'Contact person for the notice' },
                'contact_phone': { label: 'Contact Phone', required: false, description: 'Contact phone number' },
                'internal_notes': { label: 'Internal Notes', required: false, description: 'Internal notes for staff only' }
            }
        };

        return fieldsMap[uploadType] || {};
    }

    attemptAutoMapping() {
        // Attempt to auto-map based on header names
        Object.keys(this.requiredFields).forEach(fieldKey => {
            const matchingHeaderIndex = this.findMatchingHeader(fieldKey);
            if (matchingHeaderIndex !== -1) {
                $(`select[data-field-key="${fieldKey}"]`).val(matchingHeaderIndex);
            }
        });

        this.validateMapping();
    }

    findMatchingHeader(fieldKey) {
        const variations = this.getFieldVariations(fieldKey);
        
        for (let i = 0; i < this.csvHeaders.length; i++) {
            const header = this.csvHeaders[i].toLowerCase().trim();
            if (variations.some(variation => header.includes(variation.toLowerCase()))) {
                return i;
            }
        }
        
        return -1;
    }

    getFieldVariations(fieldKey) {
        const variationsMap = {
            // Tenant identification and basic info
            'tenant_type': ['tenant_type', 'type', 'tenant type'],
            
            // Individual tenant fields
            'tenant_name': ['tenant_name', 'name', 'tenant name', 'full_name', 'full name'],
            'tenant_email': ['tenant_email', 'email', 'tenant email', 'email_address'],
            'tenant_phone': ['tenant_phone', 'phone', 'tenant phone', 'phone_number'],
            'tenant_id_number': ['tenant_id_number', 'id_number', 'id number', 'passport', 'identification'],
            'date_of_birth': ['date_of_birth', 'dob', 'birth_date', 'birthday'],
            'gender': ['gender', 'sex'],
            'address': ['address', 'personal_address', 'home_address', 'residential_address'],
            'nationality': ['nationality', 'country', 'origin'],
            'occupation': ['occupation', 'job', 'profession', 'work'],
            'employer': ['employer', 'company', 'workplace'],
            'monthly_income': ['monthly_income', 'income', 'salary', 'earnings'],
            
            // Business tenant fields
            'business_name': ['business_name', 'business name', 'company', 'company_name'],
            'business_registration': ['business_registration', 'business registration', 'registration', 'reg_number'],
            'business_contact_person': ['business_contact_person', 'contact_person', 'contact person'],
            'business_email': ['business_email', 'business email', 'company_email', 'company email'],
            'business_phone': ['business_phone', 'business phone', 'company_phone', 'company phone'],
            'business_address': ['business_address', 'business address', 'company_address', 'office_address'],
            'business_type': ['business_type', 'business type', 'industry', 'sector'],
            'tax_id_number': ['tax_id_number', 'tax_id', 'tax number', 'tin'],
            'annual_revenue': ['annual_revenue', 'revenue', 'annual_income', 'yearly_revenue'],
            'years_in_business': ['years_in_business', 'business_years', 'experience', 'establishment_year'],
            
            // Emergency contact fields
            'emergency_contact_name': ['emergency_contact_name', 'emergency contact name', 'emergency_name', 'next_of_kin'],
            'emergency_contact_phone': ['emergency_contact_phone', 'emergency contact phone', 'emergency_phone', 'emergency_number'],
            'emergency_contact_relationship': ['emergency_contact_relationship', 'emergency relationship', 'relationship', 'relation'],
            'emergency_contact_address': ['emergency_contact_address', 'emergency contact address', 'emergency_address'],
            
            // Profile status and notes
            'profile_status': ['profile_status', 'status', 'tenant_status', 'profile status'],
            'profile_notes': ['profile_notes', 'notes', 'comments', 'remarks', 'description'],
            
            // Payment fields (keep existing)
            'tenancy_ref': ['tenancy_ref', 'tenancy reference', 'tenancy_reference', 'tenant_ref', 'reference'],
            'tenant_profile_ref': ['tenant_profile_ref', 'profile_ref', 'tenant profile ref', 'profile reference'],
            'tenant_email': ['tenant_email', 'email', 'tenant email', 'email_address'],
            'invoice_id': ['invoice_id', 'invoice', 'invoice number', 'invoice_number', 'invoice_ref'],
            'payment_date': ['payment_date', 'date', 'payment date', 'paid_date'],
            'amount_paid': ['amount_paid', 'amount', 'paid', 'payment_amount', 'total_paid'],
            'payment_method': ['payment_method', 'method', 'payment type', 'payment_type'],
            'payment_status': ['payment_status', 'status', 'payment_status'],
            'transaction_reference': ['transaction_reference', 'reference', 'transaction_ref', 'receipt', 'mpesa_code'],
            'late_fee': ['late_fee', 'late fee', 'penalty', 'late_fee_amount'],
            'late_fee_paid_now': ['late_fee_paid_now', 'late fee paid', 'pay_late_fee_now'],
            'single_invoice_amount': ['single_invoice_amount', 'single amount', 'invoice_amount'],
            'lease_amount': ['lease_amount', 'tenancy_amount', 'rent_amount', 'lease payment'],
            'service_charge_amount': ['service_charge_amount', 'service_amount', 'sc_amount'],
            'payment_notes': ['payment_notes', 'notes', 'comments', 'remarks'],
            
            // Notice fields (keep existing)
            'notice_type': ['notice_type', 'type', 'notice type'],
            'notice_date': ['notice_date', 'date', 'notice date'],
            'effective_date': ['effective_date', 'effective date', 'effective'],
            'notice_subject': ['notice_subject', 'subject', 'title'],
            'notice_details': ['notice_details', 'details', 'content', 'description'],
            'notice_period': ['notice_period', 'period', 'days'],
            'delivery_method': ['delivery_method', 'delivery', 'method']
        };

        return variationsMap[fieldKey] || [fieldKey];
    }

    validateMapping() {
        console.log('Validating mapping...');
        
        const mappedFields = {};
        const errors = [];
        const warnings = [];
        let isValid = true;
    
        // Collect mappings
        $('.field-mapping-select').each((index, select) => {
            const fieldKey = $(select).data('field-key');
            const isRequired = $(select).data('required') === true;
            const selectedIndex = $(select).val();
            
            if (selectedIndex !== '') {
                mappedFields[fieldKey] = {
                    columnIndex: parseInt(selectedIndex),
                    headerName: this.csvHeaders[selectedIndex]
                };
            } else if (isRequired) {
                errors.push(`${this.requiredFields[fieldKey]?.label || fieldKey} is required`);
                isValid = false;
            }
        });
    
        // Special validation for payment uploads
        if (this.uploadType === 'payments') {
            // At least one tenant identifier must be mapped
            const hasIdentifier = ['tenancy_ref', 'tenant_profile_ref', 'tenant_email'].some(field => mappedFields[field]);
            if (!hasIdentifier) {
                errors.push('At least one tenant identifier (Tenancy Ref, Profile Ref, or Email) must be mapped');
                isValid = false;
            }
            
            // Validate partial payment allocation fields
            const hasPartialFields = mappedFields['single_invoice_amount'] || 
                                   (mappedFields['lease_amount'] && mappedFields['service_charge_amount']);
            
            if (!hasPartialFields) {
                warnings.push('No allocation fields mapped. Ensure your data has allocation amounts for partial payments');
            }
            
            // Validate payment method values
            if (mappedFields['payment_method']) {
                const paymentMethodSamples = this.getSampleDataForField('payment_method', mappedFields);
                const validMethods = ['cash', 'bank_transfer', 'mobile_money', 'check', 'card', 'other'];
                const invalidMethods = paymentMethodSamples.filter(method => 
                    method && !validMethods.includes(method.toLowerCase().replace(/\s+/g, '_'))
                );
                
                if (invalidMethods.length > 0) {
                    warnings.push(`Some payment methods may be invalid: ${invalidMethods.join(', ')}. Valid methods: ${validMethods.join(', ')}`);
                }
            }
            
            // Validate payment status values
            if (mappedFields['payment_status']) {
                const statusSamples = this.getSampleDataForField('payment_status', mappedFields);
                const validStatuses = ['paid', 'partial', 'pending', 'failed'];
                const invalidStatuses = statusSamples.filter(status => 
                    status && !validStatuses.includes(status.toLowerCase())
                );
                
                if (invalidStatuses.length > 0) {
                    warnings.push(`Some payment statuses may be invalid: ${invalidStatuses.join(', ')}. Valid statuses: ${validStatuses.join(', ')}`);
                }
            }
            
            // Validate date formats
            if (mappedFields['payment_date']) {
                const dateSamples = this.getSampleDataForField('payment_date', mappedFields);
                const invalidDates = dateSamples.filter(date => {
                    if (!date) return false;
                    const parsedDate = new Date(date);
                    return isNaN(parsedDate.getTime());
                });
                
                if (invalidDates.length > 0) {
                    warnings.push(`Some payment dates may be invalid: ${invalidDates.join(', ')}. Use YYYY-MM-DD format`);
                }
            }
        }
    
        // Check for duplicate mappings
        const usedColumns = Object.values(mappedFields).map(field => field.columnIndex);
        const duplicates = usedColumns.filter((item, index) => usedColumns.indexOf(item) !== index);
        
        if (duplicates.length > 0) {
            duplicates.forEach(columnIndex => {
                const headerName = this.csvHeaders[columnIndex];
                errors.push(`Column "${headerName}" is mapped to multiple fields`);
            });
            isValid = false;
        }
    
        // Store mappings
        this.fieldMappings = mappedFields;
    
        // Display validation summary
        this.displayValidationSummary(isValid, errors, warnings, mappedFields);
    
        return isValid;
    }

    getSampleDataForField(fieldKey, mappedFields) {
        if (!mappedFields[fieldKey] || !this.currentFileData) return [];
        
        const columnIndex = mappedFields[fieldKey].columnIndex;
        return this.currentFileData.sampleData
            .map(row => row[columnIndex])
            .filter(value => value && value.trim() !== '')
            .slice(0, 5); // Get first 5 non-empty values
    }

    displayValidationSummary(isValid, errors, warnings, mappedFields) {
        let summaryHtml = '';

        if (isValid) {
            summaryHtml += `
                <div class="alert alert-success d-flex align-items-center p-5">
                    <i class="fas fa-check-circle fs-2hx text-success me-4"></i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-success">Ready to Upload!</h4>
                        <span>${Object.keys(mappedFields).length} fields mapped successfully.</span>
                    </div>
                </div>
            `;
        } else {
            summaryHtml += `
                <div class="alert alert-danger d-flex align-items-center p-5">
                    <i class="fas fa-exclamation-circle fs-2hx text-danger me-4"></i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-danger">Mapping Incomplete</h4>
                        <span>Please fix the errors below before proceeding.</span>
                    </div>
                </div>
            `;
        }

        if (errors.length > 0) {
            summaryHtml += `
                <div class="alert alert-danger">
                    <h5>Errors:</h5>
                    <ul class="mb-0">
                        ${errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        if (warnings.length > 0) {
            summaryHtml += `
                <div class="alert alert-warning">
                    <h5>Warnings:</h5>
                    <ul class="mb-0">
                        ${warnings.map(warning => `<li>${warning}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        // Show mapping summary
        if (Object.keys(mappedFields).length > 0) {
            summaryHtml += `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Field Mapping Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Mapped Column</th>
                                        <th>Sample Data</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;

            Object.entries(mappedFields).forEach(([fieldKey, mapping]) => {
                const fieldLabel = this.requiredFields[fieldKey].label;
                const sampleValue = this.currentFileData.sampleData[0] ? 
                    (this.currentFileData.sampleData[0][mapping.columnIndex] || 'N/A') : 'N/A';

                summaryHtml += `
                    <tr>
                        <td class="fw-bold">${fieldLabel}</td>
                        <td>${mapping.headerName}</td>
                        <td class="text-muted">${sampleValue}</td>
                    </tr>
                `;
            });

            summaryHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        $('#mapping_validation_summary').html(summaryHtml);

        // Enable/disable submit button
        const submitBtn = document.querySelector('[data-kt-stepper-action="submit"]');
        if (submitBtn) {
            submitBtn.disabled = !isValid;
        }
    }

    submitBulkUpload() {
        if (!this.validateMapping()) {
            this.showError('Please fix the mapping errors before submitting.');
            return;
        }

        if (this.uploadType === 'payments') {
            const validationResult = this.validatePaymentData();
            if (!validationResult.isValid) {
                this.showError(validationResult.message);
                return;
            }
        }

        const formData = new FormData();
        formData.append('upload_type', this.uploadType);
        formData.append('data_file', this.currentFile);
        formData.append('field_mappings', JSON.stringify(this.fieldMappings));
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

        const submitBtn = document.querySelector('#tenant_bulk_upload_button');
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;

        $.ajax({
            url: '/tenant_management/bulk-upload/process',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                this.handleUploadSuccess(response);
            },
            error: (xhr) => {
                this.handleUploadError(xhr);
            },
            complete: () => {
                submitBtn.removeAttribute('data-kt-indicator');
                submitBtn.disabled = false;
            }
        });
    }

    validatePaymentData() {
        const errors = [];
        
        // Check if required fields have data in sample rows
        const requiredPaymentFields = ['invoice_id', 'payment_date', 'amount_paid', 'payment_method', 'payment_status'];
        
        requiredPaymentFields.forEach(fieldKey => {
            if (this.fieldMappings[fieldKey]) {
                const samples = this.getSampleDataForField(fieldKey, this.fieldMappings);
                if (samples.length === 0) {
                    errors.push(`${this.requiredFields[fieldKey]?.label || fieldKey} column appears to be empty`);
                }
            }
        });
        
        // Validate amount fields are numeric
        const amountFields = ['amount_paid', 'late_fee', 'single_invoice_amount', 'lease_amount', 'service_charge_amount', 'bank_charges'];
        amountFields.forEach(fieldKey => {
            if (this.fieldMappings[fieldKey]) {
                const samples = this.getSampleDataForField(fieldKey, this.fieldMappings);
                const invalidAmounts = samples.filter(amount => {
                    if (!amount) return false;
                    const cleanAmount = amount.toString().replace(/[^\d.-]/g, '');
                    return isNaN(parseFloat(cleanAmount));
                });
                
                if (invalidAmounts.length > 0) {
                    errors.push(`${this.requiredFields[fieldKey]?.label || fieldKey} contains non-numeric values: ${invalidAmounts.join(', ')}`);
                }
            }
        });
        
        return {
            isValid: errors.length === 0,
            message: errors.length > 0 ? `Validation failed:\n${errors.join('\n')}` : ''
        };
    }

    handleUploadSuccess(response) {
        if (response.status === 200) {
            Swal.fire({
                title: 'Upload Successful!',
                text: response.message,
                icon: 'success',
                confirmButtonText: 'Ok'
            }).then(() => {
                $('#kt_modal_bulk_upload').modal('hide');
                // Redirect to mapping page instead of reloading
                window.location.href = '/tenant_management/bulk-upload/mapping';
            });
        }
    }

    handleUploadError(xhr) {
        let errorMessage = 'Upload failed. Please try again.';
        
        if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMessage = xhr.responseJSON.message;
        }

        this.showError(errorMessage);
    }

    showError(message) {
        console.error('Error:', message);
        Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error',
            confirmButtonText: 'Ok'
        });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    parseCSVAndMoveToStep2() {
        if (!this.currentFile) {
            this.showError('No file selected');
            return;
        }
    
        console.log('Parsing CSV file:', this.currentFile.name);
        
        // Show loading state on the continue button
        const nextBtn = document.querySelector('[data-kt-stepper-action="next"]');
        if (nextBtn) {
            nextBtn.setAttribute('data-kt-indicator', 'on');
            nextBtn.disabled = true;
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const csv = e.target.result;
                const lines = csv.split('\n');
                
                if (lines.length < 2) {
                    this.showError('CSV file must have at least a header row and one data row.');
                    return;
                }
    
                // Extract headers
                this.csvHeaders = lines[0].split(',').map(header => header.trim().replace(/"/g, ''));
                
                // Extract sample data (first 5 rows)
                const sampleData = lines.slice(1, 6).map(line => 
                    line.split(',').map(cell => cell.trim().replace(/"/g, ''))
                );
    
                this.currentFileData = {
                    headers: this.csvHeaders,
                    sampleData: sampleData,
                    totalRows: lines.length - 1
                };
    
                console.log('CSV parsed successfully:', this.currentFileData);
    
                // Move to step 2
                if (this.stepper) {
                    this.stepper.goNext();
                    
                    // Add a small delay to ensure the step transition completes
                    setTimeout(() => {
                        this.updateStepperButtons();
                        this.setupColumnMapping();
                    }, 100);
                }
    
            } catch (error) {
                console.error('Error parsing CSV:', error);
                this.showError('Error parsing CSV file: ' + error.message);
            } finally {
                // Remove loading state
                if (nextBtn) {
                    nextBtn.removeAttribute('data-kt-indicator');
                    nextBtn.disabled = false;
                }
            }
        };
    
        reader.onerror = () => {
            this.showError('Error reading file');
            if (nextBtn) {
                nextBtn.removeAttribute('data-kt-indicator');
                nextBtn.disabled = false;
            }
        };
    
        reader.readAsText(this.currentFile);
    }
}

// Export Modal Management
class ExportModalManager {
    constructor() {
        this.modal = document.getElementById('kt_modal_export_data');
        this.form = document.getElementById('kt_modal_export_form');
        this.previewText = document.getElementById('export_preview_text');
        
        if (this.modal && this.form) {
            this.initializeEventListeners();
        }
    }

    initializeEventListeners() {
        // Toggle sub-options when main export types are checked/unchecked
        document.getElementById('export_tenants')?.addEventListener('change', (e) => {
            this.toggleSubOptions('tenant-suboptions', e.target.checked);
            this.updatePreview();
        });

        document.getElementById('export_payments')?.addEventListener('change', (e) => {
            this.toggleSubOptions('payment-suboptions', e.target.checked);
            this.togglePaymentFilters(e.target.checked);
            this.updatePreview();
        });

        document.getElementById('export_notices')?.addEventListener('change', (e) => {
            this.toggleSubOptions('notice-suboptions', e.target.checked);
            this.toggleNoticeFilters(e.target.checked);
            this.updatePreview();
        });

        // Event listener for "All Tenants" checkbox
        const selectAllTenantOptions = document.getElementById('select_all_tenant_options');
        if (selectAllTenantOptions) {
            // Check the initial state of the checkbox and toggle filters accordingly
            this.toggleTenantFilters(selectAllTenantOptions.checked);
        
            // Add event listener for changes to the checkbox
            selectAllTenantOptions.addEventListener('change', (e) => {
                this.toggleTenantFilters(e.target.checked);
            });
        }

        // Date range handling
        $('#date_range').on('select2:select', (e) => {
            const selectedValue = e.params.data.id;
            console.log('Date range changed to:', selectedValue);
            this.toggleCustomDateRange(selectedValue === 'custom');
            this.updatePreview();
        });

        // Preview updates
        this.form.addEventListener('change', () => {
            this.updatePreview();
        });

        // Form submission
        this.form.addEventListener('submit', (e) => {
            this.handleExportSubmission(e);
        });
    }

    toggleSubOptions(className, show) {
        const elements = document.querySelectorAll(`.${className}`);
        elements.forEach(el => {
            el.style.display = show ? 'block' : 'none';
        });
    }

    toggleTenantFilters(show) {
        const tenantFilters = document.querySelector('.tenant_specific_options');
        const specificTenantSelection = document.querySelector('.specific_tenant_selection');
        const selectAllTenantOptions = document.getElementById('select_all_tenant_options');

        if (tenantFilters && selectAllTenantOptions && specificTenantSelection) {
            tenantFilters.style.display = show && selectAllTenantOptions.checked ? 'block' : 'none';
            specificTenantSelection.style.display = !show && !specificTenantSelection.checked ? 'block' : 'none';
        }
    }

    togglePaymentFilters(show) {
        const paymentFilters = document.querySelector('.payment-filters');
        if (paymentFilters) {
            paymentFilters.style.display = show ? 'block' : 'none';
        }
    }

    toggleNoticeFilters(show) {
        const noticeFilters = document.querySelector('.notice-filters');
        if (noticeFilters) {
            noticeFilters.style.display = show ? 'block' : 'none';
        }
    }

    toggleCustomDateRange(show) {
        const customDateRange = document.querySelector('.custom-date-range');
        if (customDateRange) {
            customDateRange.style.display = show ? 'flex' : 'none';
        }
    }

    updatePreview() {
        if (!this.previewText) return;

        const formData = new FormData(this.form);
        const exportTypes = formData.getAll('export_types[]');
        
        let previewParts = [];

        if (exportTypes.includes('tenants')) {
            const tenantOptions = formData.getAll('tenant_options[]');
            const profileOptions = formData.getAll('tenant_profile_options[]');
            const tenancyOptions = formData.getAll('tenancy_detail_options[]');
            
            let tenantPreview = [];
            
            if (tenantOptions.includes('tenant_profiles')) {
                tenantPreview.push(`Tenant Profiles (${profileOptions.length} options)`);
            }
            if (tenantOptions.includes('tenancy_details')) {
                tenantPreview.push(`Tenancy Details (${tenancyOptions.length} options)`);
            }
            
            if (tenantPreview.length > 0) {
                previewParts.push(tenantPreview.join(' + '));
            }
        }

        if (exportTypes.includes('payments')) {
            const paymentOptions = formData.getAll('payment_options[]');
            let paymentParts = ['Payment records'];
            
            if (paymentOptions.includes('transaction_refs')) paymentParts.push('with transaction references');
            if (paymentOptions.includes('verification_details')) paymentParts.push('and verification details');
            
            previewParts.push(paymentParts.join(' '));
        }

        if (exportTypes.includes('notices')) {
            const noticeOptions = formData.getAll('notice_options[]');
            let noticeParts = ['Notice records'];
            
            if (noticeOptions.includes('delivery_status')) noticeParts.push('with delivery status');
            if (noticeOptions.includes('follow_up_details')) noticeParts.push('and follow-up details');
            
            previewParts.push(noticeParts.join(' '));
        }

        // Add filter information
        const filters = [];
        const dateRange = formData.get('date_range');
        const tenantStatus = formData.get('tenant_status');
        const tenantType = formData.get('tenant_type');

        if (dateRange && dateRange !== '') {
            filters.push(`filtered by ${dateRange.replace('_', ' ')}`);
        }
        if (tenantStatus && tenantStatus !== '') {
            filters.push(`${tenantStatus.toLowerCase()} tenants only`);
        }
        if (tenantType && tenantType !== '') {
            filters.push(`${tenantType.toLowerCase()} tenants only`);
        }

        let previewText = previewParts.join(', ');
        if (filters.length > 0) {
            previewText += ` (${filters.join(', ')})`;
        }

        const format = formData.get('export_format') || 'excel';
        previewText += ` will be exported as ${format.toUpperCase()} file`;

        this.previewText.textContent = previewText;
    }

    async handleExportSubmission(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('export_submit_btn');
        const formData = new FormData(this.form);

        // Validate at least one export type is selected
        const exportTypes = formData.getAll('export_types[]');
        if (exportTypes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Export Type Selected',
                text: 'Please select at least one type of data to export.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Set loading state
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;

        try {
            // Prepare export data
            const exportData = {};
            
            // Add all form data to exportData
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    const arrayKey = key.slice(0, -2);
                    if (!exportData[arrayKey]) {
                        exportData[arrayKey] = [];
                    }
                    exportData[arrayKey].push(value);
                } else {
                    exportData[key] = value;
                }
            }

            // Make the export request
            const response = await fetch('/tenant_management/export/custom', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify(exportData)
            });

            if (response.ok) {
                // Handle file download
                const blob = await response.blob();
                const contentDisposition = response.headers.get('Content-Disposition');
                const filename = contentDisposition 
                    ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
                    : `tenant_export_${new Date().getTime()}.${exportData.export_format || 'xlsx'}`;

                // Create download link
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                // Close modal and show success message
                const modal = bootstrap.Modal.getInstance(this.modal);
                modal.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Export Successful',
                    text: 'Your export has been downloaded successfully.',
                    confirmButtonText: 'OK'
                });

            } else {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Export failed');
            }

        } catch (error) {
            console.error('Export failed:', error);
            
            Swal.fire({
                icon: 'error',
                title: 'Export Failed',
                text: error.message || 'An error occurred while exporting data. Please try again.',
                confirmButtonText: 'OK'
            });

        } finally {
            // Reset button state
            submitBtn.removeAttribute('data-kt-indicator');
            submitBtn.disabled = false;
        }
    }
}

// Tenant onboarding owns its conditional validation and navigation.
var KTCreateApp = {
    init() {
        const root = document.getElementById('kt_modal_add_tenant_stepper');
        const form = document.getElementById('kt_modal_add_tenant_form');
        if (root && form) window.TenantOnboarding.attach(root, form, new KTStepper(root));
    }
};

// Tenant Profiles List
var KTTenantProfilesList = function () {
    // Define shared variables
    var container = document.getElementById('tenant-profiles-container');
    var loadingElement = document.getElementById('tenant-profiles-loading');
    var noResultsElement = document.getElementById('tenant-profiles-no-results');
    var infoElement = document.getElementById('tenant-profiles-info');
    var paginationElement = document.getElementById('tenant-profiles-pagination');
    
    var currentPage = 1;
    var perPage = 24;
    var totalResults = 0;
    var currentView = 'grid';
    var searchTerm = '';
    var filterTenantType = '';
    var filterProfileStatus = '';
    var searchTimeout;

    // Private functions
    var initTenantProfilesList = function () {
        setupEventListeners();
        loadTenantProfiles();
    }

    var setupEventListeners = function() {
        // Search input with debounce
        const searchInput = document.querySelector('[data-kt-tenant-profiles-filter="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchTerm = e.target.value;
                    currentPage = 1;
                    loadTenantProfiles();
                }, 500);
            });
        }

        // Filter dropdowns
        const tenantTypeFilter = $('[data-kt-tenant-profiles-filter="tenant_type"]');
        const statusFilter = $('[data-kt-tenant-profiles-filter="profile_status"]');
        
        tenantTypeFilter.on('change', function() {
            filterTenantType = $(this).val();
            currentPage = 1;
            loadTenantProfiles();
        });

        statusFilter.on('change', function() {
            filterProfileStatus = $(this).val();
            currentPage = 1;
            loadTenantProfiles();
        });

        // Search button
        const searchButton = document.querySelector('[data-kt-tenant-profiles-filter="search"]');
        if (searchButton) {
            searchButton.addEventListener('click', function() {
                loadTenantProfiles();
            });
        }

        // View toggle
        const viewSelect = document.getElementById('tenant-profiles-view');
        if (viewSelect) {
            viewSelect.addEventListener('change', function(e) {
                currentView = e.target.value;
                renderTenantProfiles();
            });
        }

        // Per page selector
        const perPageSelect = document.getElementById('tenant-profiles-per-page');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function(e) {
                perPage = parseInt(e.target.value);
                currentPage = 1;
                loadTenantProfiles();
            });
        }
    }

    var loadTenantProfiles = function() {
        showLoading();

        $.ajax({
            url: "/tenant_management/tenant-profiles/data",
            type: "GET",
            data: {
                search_term: searchTerm,
                tenant_type: filterTenantType,
                profile_status: filterProfileStatus,
                page: currentPage,
                per_page: perPage
            },
            success: function(response) {
                hideLoading();
                
                if (response.data && response.data.length > 0) {
                    totalResults = response.recordsTotal;
                    renderTenantProfiles(response.data);
                    updatePagination(response);
                    updateInfo();
                } else {
                    showNoResults();
                }
            },
            error: function() {
                hideLoading();
                showError();
            }
        });
    }

    var renderTenantProfiles = function(profiles) {
        if (!profiles && window.lastLoadedProfiles) {
            profiles = window.lastLoadedProfiles;
        } else if (profiles) {
            window.lastLoadedProfiles = profiles;
        }

        if (!profiles) return;

        let html = '';
        
        if (currentView === 'grid') {
            html = '<div class="row g-6">';
            profiles.forEach(profile => {
                html += renderGridCard(profile);
            });
            html += '</div>';
        } else {
            html = '<div class="d-flex flex-column gap-4">';
            profiles.forEach(profile => {
                html += renderListCard(profile);
            });
            html += '</div>';
        }

        container.innerHTML = html;
        container.style.display = 'block';
        
        // Initialize any interactive elements
        KTMenu.createInstances();
    }

    var renderGridCard = function(profile) {
        const email = profile.primary_email || 'No email';
        const phone = profile.primary_phone || 'No phone';
        const idNumber = profile.tenant_id_number || profile.business_registration || 'No ID';
        
        const isBusinessTenant = profile.tenant_type === 'Business';
        const iconClass = isBusinessTenant ? 'fa-building' : 'fa-user';
        const bgColor = isBusinessTenant ? 'bg-success' : 'bg-primary';
        
        const statusClass = profile.is_blacklisted ? 'danger' : 
                          profile.profile_status === 'Active' ? 'success' : 'secondary';

        const tenancyInfo = profile.latest_tenancy ? `
            <div class="border-top pt-3 mt-3 mb-10">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-gray-600 fs-8">Latest Tenancy</span>
                    <span class="badge badge-light-${profile.latest_tenancy.status === 'Active' ? 'success' : 'secondary'} fs-8">
                        ${profile.latest_tenancy.status}
                    </span>
                </div>
                <div class="text-gray-800 fw-bold fs-7 mb-1">${profile.latest_tenancy.tenancy_ref}</div>
                <div class="text-gray-600 fs-8">${profile.latest_tenancy.complex_name}</div>
                <div class="text-gray-600 fs-8">Unit: ${profile.latest_tenancy.unit_number || 'N/A'}</div>
            </div>
        ` : '<div class="border-top pt-3 mt-3 text-center text-muted fs-8">No tenancies</div>';

        return `
            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="card card-stretch shadow-sm hover-elevate-up rounded">
                    <div class="card-body p-6">
                        <!--begin::Header-->
                        <div class="d-flex align-items-center mb-4">
                            <div class="symbol symbol-50px me-3">
                                <div class="symbol-label ${bgColor}">
                                    <i class="fas ${iconClass} text-white fs-3"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="text-gray-800 fw-bold fs-6">${profile.tenant_ref}</span>
                                        <div class="text-muted fs-8">ID: ${profile.id}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Header-->

                        <!--begin::Badges-->
                        <div class="d-flex gap-2 mb-4">
                            <span class="badge badge-${isBusinessTenant ? 'success' : 'primary'} fs-8">
                                ${profile.tenant_type || 'Individual'}
                            </span>
                            <span class="badge badge-${statusClass} fs-8">
                                ${profile.profile_status}
                            </span>
                            <span class="badge badge-light-info fs-8">
                                ${profile.tenancies_count} Tenancies
                            </span>
                        </div>
                        <!--end::Badges-->

                        <!--begin::Name-->
                        <h5 class="text-gray-900 fw-bold mb-3">${profile.display_name}</h5>
                        <!--end::Name-->

                        <!--begin::Contact Info-->
                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-envelope text-muted me-2 fs-7"></i>
                                <span class="text-gray-600 fs-7">${email}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone text-muted me-2 fs-7"></i>
                                <span class="text-gray-600 fs-7">${phone}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-id-card text-muted me-2 fs-7"></i>
                                <span class="text-gray-600 fs-7">${idNumber}</span>
                            </div>
                        </div>
                        <!--end::Contact Info-->

                        <!--begin::Tenancy Info-->
                        ${tenancyInfo}
                        <!--end::Tenancy Info-->
                    </div>
                </div>
            </div>
        `;
    }

    var renderListCard = function(profile) {
        const email = profile.primary_email || 'No email';
        const phone = profile.primary_phone || 'No phone';
        const idNumber = profile.tenant_id_number || profile.business_registration || 'No ID';
        
        const isBusinessTenant = profile.tenant_type === 'Business';
        const iconClass = isBusinessTenant ? 'fa-building' : 'fa-user';
        const bgColor = isBusinessTenant ? 'bg-success' : 'bg-primary';
        
        const statusClass = profile.is_blacklisted ? 'danger' : 
                          profile.profile_status === 'Active' ? 'success' : 'secondary';

        const tenancyInfo = profile.latest_tenancy ? `
            <div class="col-md-3">
                <div class="text-gray-600 fs-8 mb-1">Latest Tenancy</div>
                <div class="text-gray-800 fw-bold fs-7">${profile.latest_tenancy.tenancy_ref}</div>
                <div class="text-gray-600 fs-8">${profile.latest_tenancy.complex_name}</div>
                <span class="badge badge-light-${profile.latest_tenancy.status === 'Active' ? 'success' : 'secondary'} fs-8 mt-1">
                    ${profile.latest_tenancy.status}
                </span>
            </div>
        ` : `
            <div class="col-md-3">
                <div class="text-center text-muted fs-8">No tenancies</div>
            </div>
        `;

        return `
            <div class="card card-stretch shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <!--begin::Profile Info-->
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px me-4">
                                    <div class="symbol-label ${bgColor}">
                                        <i class="fas ${iconClass} text-white fs-5"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="text-gray-900 fw-bold fs-6">${profile.tenant_ref}</span>
                                            <span class="badge badge-light-secondary fs-8 ms-2">ID: ${profile.id}</span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="badge badge-${isBusinessTenant ? 'success' : 'primary'} fs-8">
                                                ${profile.tenant_type || 'Individual'}
                                            </span>
                                            <span class="badge badge-${statusClass} fs-8">
                                                ${profile.profile_status}
                                            </span>
                                        </div>
                                    </div>
                                    <h6 class="text-gray-800 fw-bold mb-2">${profile.display_name}</h6>
                                </div>
                            </div>
                        </div>
                        <!--end::Profile Info-->

                        <!--begin::Contact Info-->
                        <div class="col-md-4">
                            <div class="text-gray-600 fs-7">
                                <div class="mb-1">
                                    <i class="fas fa-envelope text-muted me-2"></i>${email}
                                </div>
                                <div class="mb-1">
                                    <i class="fas fa-phone text-muted me-2"></i>${phone}
                                </div>
                                <div>
                                    <i class="fas fa-id-card text-muted me-2"></i>${idNumber}
                                </div>
                            </div>
                        </div>
                        <!--end::Contact Info-->

                        <!--begin::Tenancy Info-->
                        ${tenancyInfo}
                        <!--end::Tenancy Info-->
                    </div>
                </div>
            </div>
        `;
    }

    var updatePagination = function(response) {
        const totalPages = Math.ceil(response.recordsTotal / perPage);
        let paginationHtml = '';

        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>`;
        }

        // Page numbers
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            paginationHtml += `<li class="page-item ${activeClass}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>`;
        }

        document.getElementById('pagination-controls').innerHTML = paginationHtml;

        // Add click handlers
        document.querySelectorAll('#pagination-controls a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = parseInt(this.dataset.page);
                loadTenantProfiles();
            });
        });
    }

    var updateInfo = function() {
        const start = ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, totalResults);
        
        infoElement.textContent = `Showing ${start} to ${end} of ${totalResults} tenant profiles`;
        document.getElementById('pagination-info').textContent = `Page ${currentPage} of ${Math.ceil(totalResults / perPage)}`;
    }

    var showLoading = function() {
        container.style.display = 'none';
        noResultsElement.style.display = 'none';
        loadingElement.style.display = 'block';
        infoElement.textContent = 'Loading...';
    }

    var hideLoading = function() {
        loadingElement.style.display = 'none';
    }

    var showNoResults = function() {
        container.style.display = 'none';
        noResultsElement.style.display = 'block';
        infoElement.textContent = 'No results found';
        paginationElement.style.display = 'none';
    }

    var showError = function() {
        hideLoading();
        container.innerHTML = `
            <div class="text-center py-10">
                <i class="fas fa-exclamation-triangle fs-4x text-warning mb-4"></i>
                <h5 class="text-gray-600 mb-2">Error Loading Profiles</h5>
                <p class="text-muted mb-4">There was an error loading tenant profiles. Please try again.</p>
                <button class="btn btn-primary" onclick="location.reload()">Retry</button>
            </div>
        `;
        container.style.display = 'block';
    }

    // Public methods
    return {
        init: function () {
            if (!container) {
                return;
            }
            initTenantProfilesList();
        }
    }
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTTenantProfilesList.init();
    if (window.location.pathname === '/tenant_management/onboarding/tenant') {
        KTCreateApp.init();


    }

    // Initialize TenantManager on all tenant management pages
    if (window.location.pathname.startsWith('/tenant_management')) {
        window.tenantManager = new TenantManager();
    }
});

// Global functions for actions
window.viewTenantProfile = function(profileId) {
    // TODO: Implement view tenant profile functionality
    console.log('View tenant profile:', profileId);
    toastr.info('View tenant profile functionality to be implemented');
};

window.createTenancyForProfile = function(profileId) {
    // TODO: Open the "Add Tenant" modal with this profile pre-selected
    console.log('Create tenancy for profile:', profileId);
    toastr.info('Create tenancy functionality to be implemented');
};

window.viewBlacklistReason = function(profileId) {
    // TODO: Show blacklist reason in a modal
    console.log('View blacklist reason for profile:', profileId);
    toastr.info('View blacklist reason functionality to be implemented');
};

// Open the Tenant Feature Guide modal from the help panel
window.openTenantFeatureGuide = function() {
    if (typeof closeHelpPanel === 'function') closeHelpPanel();
    var el = document.getElementById('tenantOverviewModal');
    if (el) {
        var modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
    }
};

// Keep ALL existing document ready functionality
$(document).ready(function() {

    const STORAGE_KEY = 'erty_tenant_overview_dismissed';
    const tenantOverviewModalEl = document.getElementById('tenantOverviewModal');

    if (tenantOverviewModalEl) {

        // Hide the floating help button while this modal is open
        tenantOverviewModalEl.addEventListener('show.bs.modal', function () {
            var btn = document.getElementById('kt_help_trigger');
            if (btn) { btn.style.opacity = '0'; btn.style.pointerEvents = 'none'; }
        });

        if (!localStorage.getItem(STORAGE_KEY)) {
            var btn = document.getElementById('kt_help_trigger');
            if (btn) { btn.style.opacity = '0'; btn.style.pointerEvents = 'none'; }
            var overviewModal = new bootstrap.Modal(tenantOverviewModalEl, {
                backdrop: 'static',
                keyboard: false
            });
            overviewModal.show();
        }
        tenantOverviewModalEl.addEventListener('hidden.bs.modal', function () {
            var btn = document.getElementById('kt_help_trigger');
            if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        });

        tenantOverviewModalEl.addEventListener('hide.bs.modal', function () {
            if ($('#dontShowAgainCheck').is(':checked')) {
                localStorage.setItem(STORAGE_KEY, '1');
            }
        });
    }

    // Handle notice type selection to show descriptions (Select2 compatible)
    $(document).on('change select2:select', 'select[name="notice_type"]', function(e) {
        const noticeType = $(e.target).val();
        const descriptionContainer = $('#notice_type_description');
        
        if (noticeType && noticeDescriptions[noticeType]) {
            const description = noticeDescriptions[noticeType];
            descriptionContainer.find('span.fw-bold').text(description);
            descriptionContainer.removeClass('d-none');
        } else {
            descriptionContainer.addClass('d-none');
        }
    });

    $(document).on('select2:clear', 'select[name="notice_type"]', function() {
        $('#notice_type_description').addClass('d-none');
    });

    /*
    *
    * * TENANTS TABLE
    */

    let tenantsTable;
    let leasesTable;
    
    
    // Initialize the table when page loads
    initializeTenantsTable();
    
    // Initialize leases table if on tenant management page
    if ($('#kt_table_leases').length) {
        initializeLeasesTable();
    }

    if ($('#complex').length && $('#listing_type').length) {
        initializeComplexSelect();
        handleListingTypeChange();
    }
    
    function initializeTenantsTable() {
        if ($.fn.DataTable.isDataTable('#kt_table_tenants')) {
            $('#kt_table_tenants').DataTable().destroy();
        }
        
        tenantsTable = $('#kt_table_tenants').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/tenant_management/data',
                type: 'POST',
                data: function(d) {
                    d._token = $('meta[name="csrf-token"]').attr('content');
                    d.search_term = $('[data-kt-user-table-filter="search"]').val();
                }
            },
            columns: [
                {
                    data: null,
                    orderable: true,
                    render: function(data, type, row) {
                        return renderTenantProfileColumn(row);
                    }
                }
            ],
            columnDefs: [
                { className: "align-top", targets: 0 }
            ],
            autoWidth: false,
            scrollX: false,
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                processing: '<div class="spinner-border text-primary" role="status"></div>',
                emptyTable: "No tenants found",
                info: "Showing _START_ to _END_ of _TOTAL_ tenant profiles",
                infoEmpty: "Showing 0 to 0 of 0 tenant profiles",
                infoFiltered: "(filtered from _MAX_ total profiles)"
            },
            drawCallback: function() {
                KTMenu.createInstances();
            }
        });
    }
    
    function initializeLeasesTable() {
        if ($.fn.DataTable.isDataTable('#kt_table_leases')) {
            $('#kt_table_leases').DataTable().destroy();
        }
        
        leasesTable = $('#kt_table_leases').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/tenant_management/leases/data',
                type: 'GET'
            },
            columns: [
                {
                    data: 'tenant',
                    name: 'tenant',
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex flex-column">
                                <span class="text-gray-800 fw-bold fs-6">${tenantTableText(data.name)}</span>
                                <span class="text-muted fs-7">${tenantTableText(data.ref)}</span>
                                <span class="text-muted fs-7">${tenantTableText(data.email)}</span>
                            </div>
                        `;
                    }
                },
                {
                    data: 'property',
                    render: $.fn.dataTable.render.text(),
                    name: 'property'
                },
                {
                    data: 'lease_period',
                    render: $.fn.dataTable.render.text(),
                    name: 'lease_period',
                    className: 'text-center'
                },
                {
                    data: 'rent_amount',
                    render: $.fn.dataTable.render.text(),
                    name: 'rent_amount',
                    className: 'text-center'
                },
                {
                    data: 'status',
                    name: 'status',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<span class="badge badge-light-${tenantTableText(data.class)}">${tenantTableText(data.label)}</span>`;
                    }
                },
                {
                    data: 'expiry',
                    render: $.fn.dataTable.render.text(),
                    name: 'expiry',
                    className: 'text-center'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (!row.has_lease) {
                            return `<a class="tenant-row-link" href="/tenant_management/tenancy/${encodeURIComponent(row.tenant.ref)}/view">View tenancy →</a>`;
                        }
                        const leaseId = encodeURIComponent(row.encrypted_lease_id);
                        return `<div class="d-flex gap-3"><a href="/tenant_management/lease/${leaseId}">View</a><a href="/tenant_management/lease/edit/${leaseId}">Edit</a></div>`;
                    }
                }
            ],
            order: [[2, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                emptyTable: "No leases found. Create a tenant with lease dates to see them here.",
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>'
            },
            drawCallback: function() {
                KTMenu.createInstances();
            }
        });
        
        // Search functionality for leases
        $('#lease_search').on('keyup', debounce(function() {
            leasesTable.search(this.value).draw();
        }, 500));
    }

    // Search functionality with debounce
    const searchInput = $('[data-kt-user-table-filter="search"]');

    if (searchInput.length) {
        searchInput.on('keyup', debounce(function(event) {
            const searchValue = $(this).val();
            
            if (typeof tenantsTable !== 'undefined') {
                // This will trigger the ajax call with the search term
                tenantsTable.ajax.reload(null, false); // false keeps pagination
            } else {
                console.error('tenantsTable is not initialized.');
            }
        }, 500)); // 500ms debounce to avoid too many requests
    } 
    
    function renderTenantProfileColumn(profile) {
        const isBusinessType = profile.tenant_type === 'Business';
        const statusClass = profile.is_blacklisted ? 'danger' :  profile.profile_status === 'Active' ? 'success' : 'secondary';
        
        // Tenant Profile Header
        let html = `
            <div class="card  mb-3">
                <div class="card-body p-5">
                    <!-- Tenant Profile Info -->
                    <div class="d-flex align-items-start mb-4">
                        <div class="symbol symbol-circle symbol-50px overflow-hidden me-4">
                            <div class="symbol-label fs-2 ${isBusinessType ? 'bg-light-success text-success' : 'bg-light-primary text-primary'}">
                                <i class="fas fa-${isBusinessType ? 'building' : 'user'}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="text-primary mb-1"><a href="/tenant_management/tenant/${profile.tenant_ref}/view"> ${profile.display_name}</a></h4>
                                    <div class="text-muted fs-6 mb-2">${profile.primary_email}</div>
                                    <div class="d-flex gap-2">
                                        <span class="badge badge-${isBusinessType ? 'success' : 'primary'}">${profile.tenant_type}</span>
                                        <span class="badge badge-${statusClass}">${profile.profile_status}</span>
                                        <span class="badge badge-light-info">${profile.tenancies_count} Tenancies</span>
                                        ${profile.active_tenancies > 0 ? `<span class="badge badge-light-success">${profile.active_tenancies} Active</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2">
                                <strong>Ref:</strong> ${profile.tenant_ref}
                                ${profile.primary_phone ? `• <strong>Phone:</strong> ${profile.primary_phone}` : ''}
                            </div>
                        </div>
                    </div>
        `;
        
        // Tenancies Section
        if (profile.tenancies && profile.tenancies.length > 0) {
            html += `
                    <div class="separator separator-dashed my-4"></div>
                    <h6 class="text-gray-700 mb-3">Tenancies (${profile.tenancies.length})</h6>
                    <div class="accordion accordion-flush" id="tenancies_accordion_${profile.id}">
            `;
            
            profile.tenancies.forEach((tenancy, index) => {
                const leaseStatusClass = getLeaseStatusClass(tenancy.lease_status);
                const isExpanded = index === 0; // Expand first tenancy by default
                
                html += `
                        <div class="accordion-item border rounded mb-2">
                            <h2 class="accordion-header" id="tenancy_heading_${tenancy.id}">
                                <button class="accordion-button ${isExpanded ? '' : 'collapsed'} p-4" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#tenancy_collapse_${tenancy.id}">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-${tenancy.is_commercial ? 'building' : 'home'} me-3 text-primary"></i>
                                            <div>
                                                <div class="fw-bold text-gray-800">${tenancy.tenancy_ref}</div>
                                                <div class="text-muted fs-7">
                                                    ${tenancy.complex ? tenancy.complex.title : 'N/A'} 
                                                    ${tenancy.unit_number || tenancy.specific_unit_number ? `• Unit: ${tenancy.unit_number || tenancy.specific_unit_number}` : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <span class="badge ${getGeneralStatusClass(tenancy.status)}">Tenant ${tenancy.status}</span>
                                            ${tenancy.lease_status ? `<span class="badge ${leaseStatusClass}">Lease ${tenancy.lease_status}</span>` : ''}
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="tenancy_collapse_${tenancy.id}" 
                                class="accordion-collapse collapse ${isExpanded ? 'show' : ''}" 
                                data-bs-parent="#tenancies_accordion_${profile.id}">
                                <div class="accordion-body p-4 bg-light">
                                    <div class="row g-4">
                                        <!-- Property Details -->
                                        <div class="col-md-4">
                                            <h6 class="text-gray-700 mb-3">Property Details</h6>
                                            ${renderTenancyPropertyDetails(tenancy)}
                                        </div>
                                        
                                        <!-- Financial Details -->
                                        <div class="col-md-4">
                                            <h6 class="text-gray-700 mb-3">Financial Details</h6>
                                            ${renderTenancyFinancials(tenancy)}
                                        </div>
                                        
                                        <!-- Agreement Details -->
                                        <div class="col-md-4">
                                            <h6 class="text-gray-700 mb-3">Agreement Details</h6>
                                            ${renderTenancyAgreement(tenancy)}
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2 mt-4 pt-4 border-top">
                                        ${generateTenancyActions(tenancy, profile)}
                                    </div>
                                </div>
                            </div>
                        </div>
                `;
            });
            
            html += `
                    </div>
            `;
        } else {
            html += `
                    <div class="separator separator-dashed my-4"></div>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fs-2x mb-3"></i>
                            <p>No tenancies found for this profile</p>
                            <button class="btn btn-sm btn-primary" onclick="createTenancyForProfile(${profile.id})">
                                <i class="fas fa-plus"></i> Create Tenancy
                            </button>
                        </div>
                `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    }

    // Helper function to render property details
    function renderTenancyPropertyDetails(tenancy) {
        return `
            <div class="text-gray-700 fs-7">
                <div class="mb-2"><strong>Type:</strong> ${tenancy.listing_type}</div>
                ${tenancy.is_commercial ? `
                    <div class="mb-2"><strong>Sq. Area:</strong> ${tenancy.occupied_sq_area || tenancy.sq_area} sq ft</div>
                    ${tenancy.rate_per_sq_ft ? `<div class="mb-2"><strong>Rate/Sq Ft:</strong> ${tenancy.currency} ${formatCurrency(tenancy.rate_per_sq_ft)}</div>` : ''}
                ` : `
                    ${tenancy.bedrooms ? `<div class="mb-2"><strong>Bedrooms:</strong> ${tenancy.bedrooms}</div>` : ''}
                    ${tenancy.bathrooms ? `<div class="mb-2"><strong>Bathrooms:</strong> ${tenancy.bathrooms}</div>` : ''}
                    ${tenancy.furnishing ? `<div class="mb-2"><strong>Furnishing:</strong> ${tenancy.furnishing}</div>` : ''}
                `}
            </div>
        `;
    }

    // Helper function to render financials
    function renderTenancyFinancials(tenancy) {
        return `
            <div class="text-gray-700 fs-7">
                <div class="mb-2">
                    <strong>Rent:</strong> ${tenancy.currency} ${formatCurrency(tenancy.monthly_payment)}/${tenancy.payment_frequency}
                </div>
                ${tenancy.security_deposit ? `
                    <div class="mb-2"><strong>Deposit:</strong> ${tenancy.currency} ${formatCurrency(tenancy.security_deposit)}</div>
                ` : ''}
                ${tenancy.late_fee_amount ? `
                    <div class="mb-2"><strong>Late Fee:</strong> ${tenancy.currency} ${formatCurrency(tenancy.late_fee_amount)}</div>
                ` : ''}
            </div>
        `;
    }

    // Helper function to render agreement
    function renderTenancyAgreement(tenancy) {
        return `
            <div class="text-gray-700 fs-7">
                <div class="mb-2"><strong>Type:</strong> ${tenancy.agreement_type}</div>
                ${tenancy.lease_start_date ? `
                    <div class="mb-2"><strong>Start:</strong> ${new Date(tenancy.lease_start_date).toLocaleDateString()}</div>
                ` : ''}
                ${tenancy.lease_end_date ? `
                    <div class="mb-2"><strong>End:</strong> ${new Date(tenancy.lease_end_date).toLocaleDateString()}</div>
                ` : ''}
                ${tenancy.days_until_expiry !== null && tenancy.days_until_expiry >= 0 ? `
                    <div class="mb-2"><strong>Days Until Expiry:</strong> ${tenancy.days_until_expiry}</div>
                ` : ''}
            </div>
        `;
    }

    // Helper function to generate tenancy actions
    function generateTenancyActions(tenancy, profile) {
        const actions = `
            <a href="/tenant_management/tenancy/${tenancy.tenancy_ref}/view" class="btn btn-sm btn-light-primary">
                <i class="fas fa-eye"></i> View Tenancy
            </a>
            ${tenancy.status === 'Active' ? `
                <button class="btn btn-sm btn-light-success" data-tenant-id="${tenancy.id}" data-action="payment">
                    <i class="fas fa-credit-card"></i> Record Payment
                </button>
                <button class="btn btn-sm btn-light-warning" data-tenant-id="${tenancy.id}" data-action="notice">
                    <i class="fas fa-exclamation-triangle"></i> Give Notice
                </button>
                <button class="btn btn-sm btn-light-danger" data-tenant-id="${tenancy.id}" data-action="tenancy">
                    <i class="fas fa-stop"></i> End Tenancy
                </button>
            ` : ''}
        `;
        
        return actions;
    }
    
    
    // Utility functions
    function formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
    
    function getLeaseStatusClass(status) {
        switch(status) {
            case 'Active': return 'badge-light-success';
            case 'Expiring Soon': return 'badge-light-warning';
            case 'Expired': return 'badge-light-danger';
            case 'Upcoming': return 'badge-light-info';
            default: return 'badge-light-secondary';
        }
    }
    
    function getGeneralStatusClass(status) {
        switch(status) {
            case 'Active': return 'badge-light-success';
            case 'Pending': return 'badge-light-warning';
            case 'Terminated': return 'badge-light-danger';
            default: return 'badge-light-secondary';
        }
    }
    

    /*
    *
    * * TENANT FORM INTERACTIONS
    */

    // Initialize elements
    const complexSelect = $('#complex');
    const amenityDropdown = $('#complex_amenitiy');
    const commercialUnitSelect = $('#commercial_unit_id');
    const nonCommercialDiv = $('.non-commercial');
    const commercialDiv = $('.commercial');
    const commercialDetailsDiv = $('#commercial_unit_details');
    const commercialSummaryDiv = $('#commercial_summary');
    const parkingSlotsSection = document.getElementById('parking_slots_section'); // Parking slots section
    const parkingSlotsDropdown = $('#parking_slots'); // Parking slots dropdown
    
    // Property type change handler
    listingTypeDropdown.on("change", function() {
        const selectedType = $(this).val();
        console.log('Tenant property type changed to:', selectedType);
        
        if (!selectedType) {
            // If listing type is cleared, hide everything
            nonCommercialDiv.fadeOut(500);
            commercialDiv.fadeOut(500);
            commercialDetailsDiv.hide();
            commercialSummaryDiv.hide();
            $('#residential_unit_selection').hide();
            clearResidentialUnitSelection();
        } else if (selectedType === "Commercial Unit") {
            // Hide non-commercial fields and show commercial fields
            nonCommercialDiv.fadeOut(500);
            commercialDiv.fadeIn(500);
            $('#residential_unit_selection').hide();
            clearResidentialUnitSelection();
            
        } else {
            // Show non-commercial fields and hide commercial fields
            nonCommercialDiv.fadeIn(500);
            commercialDiv.fadeOut(500);
            commercialDetailsDiv.hide();
            commercialSummaryDiv.hide();
        }
        
        complexSelect.val('').trigger('change');
        commercialUnitSelect.val('').trigger('change');

        // Update agreement step fields based on listing type
        applyAgreementFieldVisibility(selectedType);
    });

    // Apply show/hide rules on the Agreement step based on listing type
    window.applyAgreementFieldVisibility = function applyAgreementFieldVisibility(selectedType) {
        const isCommercial  = selectedType === 'Commercial Unit';
        const isResidential = !!selectedType && !isCommercial;

        // Commercial-only fields
        const $commercialOnlyFields = $('#agreement_build_out_section');

        if (isCommercial) {
            $commercialOnlyFields.show();
        } else if (isResidential) {
            $commercialOnlyFields.hide();
            $('input[name="build_out_period"]').val('0');
        } else {
            // Nothing selected — show all
            $commercialOnlyFields.show();
        }

        // Agreement type option visibility
        if (isCommercial) {
            // Commercial: Lease only
            $('#agreement_type_rental_option').hide();
            // If current selection is not lease, clear it and auto-select lease
            if ($('input[name="agreement_type"]:checked').val() !== 'lease') {
                $('input[name="agreement_type"]').prop('checked', false);
                $('input[name="agreement_type"][value="lease"]').prop('checked', true);
            }
        } else if (isResidential) {
            // Residential: Rental or Lease
            $('#agreement_type_rental_option').show();
        } else {
            // No type selected — show all options
            $('#agreement_type_rental_option').show();
        }
    }
    
    // Select2 also emits change; listen once to avoid duplicate availability requests.
    complexSelect.on('change', function(e) {
        const complexId = $(this).val();
        const selectedType = listingTypeDropdown.val();
        
        // Clear commercial unit selection
        commercialUnitSelect.val('').trigger('change');
        commercialDetailsDiv.hide();
        commercialSummaryDiv.hide();
        
        parkingSlotsDropdown.empty().trigger('change');
        clearResidentialUnitSelection();
        $('[data-unit-facts]').empty().prop('hidden', true);
        $('[data-unit-availability]').attr('data-state', 'loading').text('Checking available units…');

        if (complexId && selectedType === "Commercial Unit") {
            // Load commercial units for this complex
            $.ajax({
                url: '/tenant_management/complex/' + encodeURIComponent(complexId) + '/commercial-units',
                type: 'GET',
                success: function(response) {
                    if (complexSelect.val() !== complexId) return;
                    if (response.status == 200 && response.complex.commercial_units) {
                        loadCommercialUnits(response.complex.commercial_units);
                    }
                },
                error: function(e) {
                    console.error('Error loading commercial units:', e);
                }
            });
        }
        
        // Load residential units for apartment complex selection
        if (complexId && selectedType && selectedType !== "Commercial Unit") {
            loadResidentialUnitsForTenant(complexId);
        } else {
            // Hide residential unit selection if not an apartment complex
            $('#residential_unit_selection').hide();
            // clearResidentialUnitSelection();
        }

        const selectedComplexId = complexId;
        
        if (selectedComplexId) {
            // Clear the amenities dropdown
            amenityDropdown.html('<option></option>');
            
            // Show loading state
            amenityDropdown.prop('disabled', true);

            fetch(`/get-parking-slots/${selectedComplexId}/available`)
                .then(response => response.json())
                .then(data => {
                    if (complexSelect.val() !== selectedComplexId) return;
                    if (data.success && Array.isArray(data.parking_slots) && data.parking_slots.length > 0) {
                        // Clear the parking slots dropdown
                        parkingSlotsDropdown.html('<option></option>');
    
                        // Populate the parking slots dropdown
                        data.parking_slots.forEach(slot => {
                            const option = new Option(slot.parking_id, slot.id, false, false);
                            parkingSlotsDropdown.append(option);
                        });
    
                        // Trigger change to refresh Select2
                        parkingSlotsDropdown.trigger('change');
                    } else {
                        // If no parking slots are available, show a placeholder
                        parkingSlotsDropdown.empty().trigger('change');
                    }
                })
                .catch(error => {
                    console.error('Error fetching parking slots:', error);
                });
            
           
        } else {
            // Clear the amenities dropdown if no complex is selected
            amenityDropdown.html('<option></option>').trigger('change');
            amenityDropdown.prop('disabled', true);
            
            // Clear and hide residential unit selection for apartments
            $('#residential_unit_selection').hide();
            $('#residential_unit_select').empty().trigger('change');
            clearResidentialUnitSelection();
        }
    });
    
    // Load residential units for tenant onboarding (apartments)
    function loadResidentialUnitsForTenant(complexId) {
        const select = $('#residential_unit_select');
        const notice = $('[data-unit-availability]');
        select.prop('disabled', true).empty().append(new Option('Checking units…', ''));
        notice.attr('data-state', 'loading').text('Checking available units…');
        $('#residential_unit_selection').show();
        $.ajax({
            url: '/complex/' + encodeURIComponent(complexId) + '/residential-units-for-tenant', type: 'GET',
            success(response) {
                if (complexSelect.val() !== complexId) return;
                select.empty().append(new Option('Select an available unit', ''));
                if (!response.success || !Array.isArray(response.units)) {
                    notice.attr('data-state', 'error').text('Availability could not be checked. Select the property again to retry.');
                    return;
                }
                response.units.forEach(unit => {
                    const option = new Option(unit.label, unit.id);
                    $(option).data('unit', unit); select.append(option);
                });
                const required = response.has_inventory || response.units.length > 0;
                notice.attr('data-state', required ? 'required' : 'standalone').text(required
                    ? (response.units.length ? `${response.units.length} available ${response.units.length === 1 ? 'unit' : 'units'}. Select one to view its recorded details.` : 'No units are available at this property. Choose another property or review its inventory.')
                    : 'This property has no separate residential units. The tenancy will occupy the whole property.');
                select.prop('disabled', response.units.length === 0);
                $('#residential_unit_selection').toggle(required);
            },
            error() {
                if (complexSelect.val() !== complexId) return;
                notice.attr('data-state', 'error').text('Availability could not be checked. Select the property again to retry.');
                select.empty();
            }
        });
    }

    // Clear residential unit selection
    function clearResidentialUnitSelection() {
        $('#residential_unit_select').val('').trigger('change');
        $('#unit_number_input').val('').prop('readonly', false);
        $('#bedrooms_input').val('').prop('readonly', false);
        $('#bathrooms_input').val('').prop('readonly', false);
    }
    
    // Handle residential unit selection
    $('#residential_unit_select').on('change', function() {
        const selectedValue = $(this).val();
        const selectedOption = $(this).find('option:selected');
        const unitData = selectedOption.data('unit');
        
        if (selectedValue && unitData) {
            preselectAgent(unitData.agent);
            // Auto-populate the structural fields
            $('#unit_number_input').val(unitData.unit_number || '').prop('readonly', true);
            $('#bedrooms_input').val(unitData.bedrooms ?? '').prop('readonly', true);
            $('#bathrooms_input').val(unitData.bathrooms ?? '').prop('readonly', true);
            $('[name="sq_area"]').val(unitData.sq_ft ?? '');
            const facts = document.querySelector('[data-unit-facts]');
            if (facts) {
                facts.replaceChildren();
                [['Unit', unitData.unit_number], ['Bedrooms', unitData.bedrooms], ['Bathrooms', unitData.bathrooms], ['Area (sq ft)', unitData.sq_ft]].forEach(([label, value]) => {
                    const row = document.createElement('div'), term = document.createElement('dt'), description = document.createElement('dd');
                    term.textContent = label; description.textContent = value ?? 'Not recorded'; row.append(term, description); facts.append(row);
                });
                facts.hidden = false;
            }
            
            // Optional: Show a subtle indicator that these are auto-filled
            $('#unit_number_input, #bedrooms_input, #bathrooms_input')
                .addClass('bg-light-primary')
                .attr('title', 'Auto-populated from selected unit');
        } else {
            // Discard the previous unit's specifications when selection is cleared.
            $('[name="sq_area"]').val('');
            const facts = document.querySelector('[data-unit-facts]');
            if (facts) { facts.replaceChildren(); facts.hidden = true; }
            $('#unit_number_input').val('').prop('readonly', false);
            $('#bedrooms_input').val('').prop('readonly', false);
            $('#bathrooms_input').val('').prop('readonly', false);
            $('#unit_number_input, #bedrooms_input, #bathrooms_input')
                .removeClass('bg-light-primary')
                .removeAttr('title');
        }
    });
    
    // Commercial unit change handler
    // The unit's agent (or the property's lead agent) is suggested for the tenancy; it can be changed.
    function preselectAgent(agent) {
        const $agent = $('#assigned_agent');
        if (!agent || !$agent.length || $agent.data('userPicked')) return;
        if (!$agent.find(`option[value="${agent.id}"]`).length) $agent.append(new Option(agent.name, agent.id, false, false));
        $agent.val(String(agent.id)).trigger('change');
    }
    $('#assigned_agent').on('select2:select select2:clear', function () { $(this).data('userPicked', true); });

    commercialUnitSelect.on('change', function() {
        const unitId = $(this).val();
        
        if (unitId) {
            const selectedOption = $(this).find('option:selected');
            preselectAgent(selectedOption.data('agent') || null);
            const totalSqFt = selectedOption.data('sq-ft') || 0;
            const recommendedRate = selectedOption.data('rate') || 0;
            
            // Show unit details section
            commercialDetailsDiv.fadeIn(500);
            
            // Update available area and recommended rate
            $('#total_sq_area').text(totalSqFt);
            $('#recommended_rate').text(recommendedRate);
            
            // Set recommended rate as default
            $('input[name="rate_per_sq_ft"]').val(recommendedRate);
            
            // Trigger calculation
            calculateCommercialTotal();
        } else {
            commercialDetailsDiv.fadeOut(500);
            commercialSummaryDiv.fadeOut(500);
        }
    });
    
    // Calculation handlers
    $('input[name="occupied_sq_area"], input[name="rate_per_sq_ft"], input[name="service_charge_per_sq_ft"]').on('input', function() {
        calculateCommercialTotal();
    });

    // Pet details toggle handler
    $('input[name="has_pets"]').on('change', function() {
        const hasPets = $(this).val() === '1';
        const petDetailsSection = $('#pet_details_section');
        
        if (hasPets) {
            petDetailsSection.fadeIn(300);
            $('textarea[name="pet_details"]').attr('required', true);
        } else {
            petDetailsSection.fadeOut(300);
            $('textarea[name="pet_details"]').attr('required', false).val('');
        }
    });
    
    function loadCommercialUnits(units) {
        commercialUnitSelect.empty();
        commercialUnitSelect.append('<option value="">Select Unit</option>');
        
        units.forEach(unit => {
            // Check if unit has available space
            if (unit.remaining_subdivisions > 0 && unit.has_space_available) {
                // Use remaining space data instead of total unit data
                const availableSqFt = unit.remaining_sq_ft || unit.unit_sq_ft;
                const ratePerSqFt = unit.rate_per_sq_ft || 0;
                const occupancyInfo = unit.occupied_count > 0 
                    ? ` (${unit.occupied_count}/${unit.max_subdivisions} occupied)` 
                    : '';
                
                const option = `<option value="${unit.id}" 
                    data-agent='${unit.agent ? JSON.stringify(unit.agent).replace(/'/g, '&#39;') : ''}'
                    data-sq-ft="${availableSqFt}" 
                    data-rate="${ratePerSqFt}"
                    data-remaining-subdivisions="${unit.remaining_subdivisions}"
                    data-max-subdivisions="${unit.max_subdivisions}"
                    data-occupied-count="${unit.occupied_count || 0}"
                    data-min-sq-ft="${unit.min_sq_ft}"
                    data-total-sq-ft="${unit.unit_sq_ft}"
                    data-occupancy-percentage="${unit.occupancy_percentage || 0}">
                    ${unit.unit_number} - ${availableSqFt} sq ft available - Floor ${unit.effective_floor}${occupancyInfo}
                </option>`;
                commercialUnitSelect.append(option);
            }
        });
        
        // Show message if no units available
        if (commercialUnitSelect.find('option').length === 1) {
            commercialUnitSelect.append('<option disabled>No available units in this complex</option>');
        }
        
        // Refresh Select2
        commercialUnitSelect.select2('destroy').select2({
            allowClear: true,
            placeholder: 'Select Available Unit'
        });
    }
    
    // Updated commercial unit change handler
    commercialUnitSelect.on('change', function() {
        const unitId = $(this).val();
        
        if (unitId) {
            const selectedOption = $(this).find('option:selected');
            const availableSqFt = parseFloat(selectedOption.data('sq-ft')) || 0;
            const recommendedRate = selectedOption.data('rate') || 0;
            const minSqFt = parseFloat(selectedOption.data('min-sq-ft')) || 0;
            const totalSqFt = selectedOption.data('total-sq-ft') || 0;
            const occupiedCount = selectedOption.data('occupied-count') || 0;
            const maxSubdivisions = selectedOption.data('max-subdivisions') || 0;
            const occupancyPercentage = selectedOption.data('occupancy-percentage') || 0;
            const remainingSubdivisions = selectedOption.data('remaining-subdivisions') || 0;
            
            // Show unit availability info
            $('#unit_availability_info').fadeIn(500);
            
            // Update availability information
            $('#total_unit_size').text(totalSqFt.toLocaleString());
            $('#available_sq_area').text(availableSqFt.toLocaleString());
            $('#min_sq_area').text(minSqFt.toLocaleString());
            // $('#occupancy_status').text(`${occupiedCount}/${maxSubdivisions} subdivisions (${occupancyPercentage}% occupied)`);
            $('#occupancy_status').text(`(${occupancyPercentage}% occupied)`);
            
            // Show unit details section
            commercialDetailsDiv.fadeIn(500);
            
            // Update unit information display
            $('#total_sq_area').text(availableSqFt.toLocaleString());
            $('#min_required_space').text(minSqFt.toLocaleString());
            $('#recommended_rate').text(recommendedRate.toLocaleString());
            
            // Set recommended rate as default
            $('input[name="rate_per_sq_ft"]').val(recommendedRate);
            
            // Set input constraints
            const areaInput = $('input[name="occupied_sq_area"]');
            areaInput.attr('max', availableSqFt);
            areaInput.attr('min', minSqFt);
            areaInput.attr('placeholder', `Min: ${minSqFt} - Max: ${availableSqFt} sq ft`);
            
            // Clear previous values and warnings
            areaInput.val('');
            $('#area_warnings').hide();
            $('#area_validation_message').hide();
            
            // Trigger calculation
            calculateCommercialTotal();
        } else {
            $('#unit_availability_info').fadeOut(500);
            commercialDetailsDiv.fadeOut(500);
            commercialSummaryDiv.fadeOut(500);
            $('#area_warnings').hide();
        }
    });
    
    // Enhanced calculation function with validation
    function calculateCommercialTotal() {
        const sqArea = parseFloat($('input[name="occupied_sq_area"]').val()) || 0;
        const rate = parseFloat($('input[name="rate_per_sq_ft"]').val()) || 0;
        const service_charge = parseFloat($('input[name="service_charge_per_sq_ft"]').val()) || 0;
        const total = sqArea * (rate + service_charge);
        
        
        // Get unit data from selected option
        const selectedOption = commercialUnitSelect.find('option:selected');
        const availableSqFt = parseFloat(selectedOption.data('sq-ft')) || 0;
        const minSqFt = parseFloat(selectedOption.data('min-sq-ft')) || 0;
        const remainingSubdivisions = parseInt(selectedOption.data('remaining-subdivisions')) || 0;
        
        // Validation and warnings
        let warnings = [];
        let isValid = true;
        
        if (sqArea > 0) {
            if (sqArea > availableSqFt) {
                warnings.push(`Requested area (${sqArea.toLocaleString()} sq ft) exceeds available space (${availableSqFt.toLocaleString()} sq ft)`);
                isValid = false;
            }
            
            if (sqArea < minSqFt) {
                warnings.push(`Area must be at least ${minSqFt.toLocaleString()} sq ft (minimum requirement)`);
                isValid = false;
            }
            
            if (remainingSubdivisions <= 0) {
                warnings.push('This unit is fully occupied');
                isValid = false;
            }
        }
        
        // Update calculation display
        $('#calc_sq_area').text(sqArea.toLocaleString());
        $('#calc_rate').text(rate.toLocaleString());
        $('#calc_service_charge').text(service_charge.toLocaleString());
        $('#calc_rent').text((sqArea * rate).toLocaleString());
        $('#calc_sc_total').text((sqArea * service_charge).toLocaleString());
        $('#calc_total').text(total.toLocaleString());
        
        // Calculate and show percentage of space being used
        if (availableSqFt > 0 && sqArea > 0) {
            const percentage = Math.round((sqArea / availableSqFt) * 100);
            $('#calc_percentage').text(percentage);
        } else {
            $('#calc_percentage').text(0);
        }
        
        // Show warnings if any
        const warningDiv = $('#area_warnings');
        const validationMessage = $('#area_validation_message');
        
        if (warnings.length > 0) {
            const warningHtml = warnings.map(warning => 
                `<div class="alert alert-warning py-2 mb-2 d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${warning}
                </div>`
            ).join('');
            warningDiv.html(warningHtml).fadeIn(300);
            validationMessage.text(warnings[0]).show();
            
            // Add error styling to input
            $('input[name="occupied_sq_area"]').addClass('is-invalid');
        } else {
            warningDiv.fadeOut(300);
            validationMessage.hide();
            $('input[name="occupied_sq_area"]').removeClass('is-invalid');
        }
        
        // Auto-populate monthly rent in the billing step. Rent only: the service charge is billed as its own
        // invoice from the rate per sq ft (TenantChargesService::serviceChargeFor), so it must not be added here.
        const rentOnly = sqArea * rate;
        if (isValid && rentOnly > 0) {
            $('input[name="monthly_payment"]').val(rentOnly.toFixed(2));
        }
        
        // Show/hide summary based on validation
        if (sqArea > 0 && rate > 0 && isValid) {
            commercialSummaryDiv.fadeIn(500);
        } else {
            commercialSummaryDiv.fadeOut(500);
        }
    }
    
    // Add real-time validation to area input
    $('input[name="occupied_sq_area"]').on('input', function() {
        calculateCommercialTotal();
    });
    
    $('input[name="occupied_sq_area"]').on('blur', function() {
        const sqArea = parseFloat($(this).val()) || 0;
        const selectedOption = commercialUnitSelect.find('option:selected');
        const minSqFt = parseFloat(selectedOption.data('min-sq-ft')) || 0;
        const availableSqFt = parseFloat(selectedOption.data('sq-ft')) || 0;
        
        // Auto-correct values if possible
        if (sqArea > 0) {
            if (sqArea < minSqFt) {
                $(this).val(minSqFt);
                calculateCommercialTotal();
            } else if (sqArea > availableSqFt) {
                $(this).val(availableSqFt);
                calculateCommercialTotal();
            }
        }
    });
    
    // function calculateCommercialTotal() {
    //     const sqArea = parseFloat($('input[name="occupied_sq_area"]').val()) || 0;
    //     const rate = parseFloat($('input[name="rate_per_sq_ft"]').val()) || 0;
    //     const total = sqArea * rate;
        
    //     // Update calculation display
    //     $('#calc_sq_area').text(sqArea);
    //     $('#calc_rate').text(rate);
    //     $('#calc_total').text(total.toLocaleString());
        
    //     // Auto-populate monthly payment in step 3
    //     $('input[name="monthly_payment"]').val(total);
        
    //     // Show/hide summary
    //     if (sqArea > 0 && rate > 0) {
    //         commercialSummaryDiv.fadeIn(500);
    //     } else {
    //         commercialSummaryDiv.fadeOut(500);
    //     }
    // }

    // Tenant option change handler
    $('input[name="tenant_option"]').on('change', function() {
        const selectedOption = $(this).val();
        const selectedTenantType = $('input[name="tenant_type"]:checked').val();
        
        console.log('Tenant option changed to:', selectedOption);
        
        if (selectedOption === 'existing') {
            // Hide new tenant fields and show existing tenant selection
            $('.new-tenant-fields').fadeOut(300);
            $('.existing-tenant-selection').fadeIn(500);
            
            // Load existing tenants filtered by the selected type
            loadExistingTenants();
        } else {
            // Show new tenant fields based on tenant type and hide existing selection
            $('.existing-tenant-selection').fadeOut(300);
            $('.selected-tenant-preview').hide();
            $('#existing_tenant_id').val('');
            
            // Show the correct fields immediately since we know the type
            if (selectedTenantType === 'Business') {
                $('.individual-fields').hide();
                $('.business-fields').fadeIn(300);
            } else if (selectedTenantType === 'Individual') {
                $('.business-fields').hide();
                $('.individual-fields').fadeIn(300);
            }
        }
    });

    // Update existing tenant type change handler
    $('input[name="tenant_type"]').on('change', function() {
        const selectedType = $(this).val();
        const tenantOption = $('input[name="tenant_option"]:checked').val();
        
        console.log('Tenant type changed to:', selectedType);
        $('.tenant-information-section').stop(true, true).fadeIn(300);
        
        if (tenantOption === 'new') {
            if (selectedType === 'Business') {
                $('.individual-fields').fadeOut(300, function() {
                    $('.business-fields').fadeIn(300);
                    $('.individual-fields input').not('[name="emergency_contact_name"], [name="emergency_contact_phone"]').val('');
                });
            } else if (selectedType === 'Individual') {
                $('.business-fields').fadeOut(300, function() {
                    $('.individual-fields').fadeIn(300);
                    $('.business-fields input').val('');
                });
            }
        }
        // If existing tenant is selected, reload the list with the new filter
        else if (tenantOption === 'existing') {
            clearSelectedTenant();
            loadExistingTenants();
        }
    });

    $(document).on('click', '.existing-tenant-item', function() {
        const $item = $(this);
        const tenantId = $item.data('tenant-id');
        const name = $item.data('tenant-name');
        const type = $item.data('tenant-type');
        const email = $item.data('tenant-email');
        const phone = $item.data('tenant-phone');
        const status = $item.data('tenant-status');
        const lastProperty = $item.data('last-property');

        console.log('Selecting tenant:', { tenantId, name, type, email, phone, status, lastProperty });

        selectExistingTenant(tenantId, name, type, email, phone, status, lastProperty);
    });

    // Search and filter handlers for existing tenants
    $('#existing_tenant_search').on('keyup', debounce(function() {
        loadExistingTenants();
    }, 300));

    $('#existing_tenant_filter').on('change', function() {
        loadExistingTenants();
    });

    /**
     * 
     * *  BULK UPLOAD MANAGEMENT
    */
    
    // Initialize bulk upload manager when modal is shown
    $('#kt_modal_bulk_upload').on('shown.bs.modal', function() {
        if (!window.bulkUploadManager) {
            window.bulkUploadManager = new BulkUploadManager();
        }
    });

    // Reset file area when modal is hidden
    $('#kt_modal_bulk_upload').on('hidden.bs.modal', function() {
        $('#bulk-upload-file-info').html('');
        $('#bulk-upload-dropzone').css('background', '');
        $('#data_file').val('');
    });

    $('#legal_files').select2({
        placeholder: 'Select legal files to send',
        allowClear: true,
        width: '100%'
    });


    /*
    *
    * * TENANT PAYMENT INTERVAL HANDLERS
    */

    // Handle Payment Interval changes
    function handleIntervalChange(selectElement, quarterlySectionId, quarterlyTypeId) {
        const selectedInterval = $(selectElement).val();
        const quarterlySection = $(quarterlySectionId);
        
        if (selectedInterval === 'Quarterly') {
            quarterlySection.removeClass('d-none').hide().fadeIn(300);
            $(quarterlyTypeId).attr('required', true);
        } else {
            quarterlySection.fadeOut(300, function() {
                $(this).addClass('d-none');
            });
            $(quarterlyTypeId).attr('required', false).val('').trigger('change');
        }
    }

    // Handle Payment Interval changes
    $('#payment_interval').on('change select2:select', function() {
        handleIntervalChange(this, '#tenancy_quarterly_payment_type_section', '#tenancy_quarterly_payment_type');
    });

    // Initialize Select2 for lead selection with filtering by lead type
    $('#individual_lead_id').select2({
        ajax: {
            url: '/customer_relationship/leads/data', // <-- updated endpoint
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    lead_type: 'Individual', // <-- filter for individual leads
                    search: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data) {
                return {
                    results: data.data.map(function(item) {
                        // Access nested profile object for individual leads
                        const profile = item.profile || {};
                        return {
                            id: item.id,
                            text: (profile.first_name ? profile.first_name : '') + ' ' + (profile.last_name ? profile.last_name : '') + (profile.email ? ' (' + profile.email + ')' : '')
                        };
                    })
                };
            },
            cache: true
        },
        placeholder: 'Select Individual Lead',
        minimumInputLength: 1
    });
    
    $('#business_lead_id').select2({
        ajax: {
            url: '/customer_relationship/leads/data', // <-- updated endpoint
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    lead_type: 'Business', // <-- filter for business leads
                    search: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data) {
                return {
                    results: data.data.map(function(item) {
                        // Access nested profile object for business leads
                        const profile = item.profile || {};
                        return {
                            id: item.id,
                            text: (profile.company_name ? profile.company_name : '') + (profile.business_email ? ' (' + profile.business_email + ')' : '')
                        };
                    })
                };
            },
            cache: true
        },
        placeholder: 'Select Business Lead',
        minimumInputLength: 1
    });

    // Handle individual lead selection
    $('#individual_lead_id').on('select2:select', function(e) {
        const leadId = e.params.data.id;
        fetchAndPopulateLeadData(leadId, 'Individual');
    });
    
    // Handle business lead selection
    $('#business_lead_id').on('select2:select', function(e) {
        const leadId = e.params.data.id;
        fetchAndPopulateLeadData(leadId, 'Business');
    });
    
    // Handle lead clearing
    $('#individual_lead_id, #business_lead_id').on('select2:clear', function() {
        clearLeadData();
    });

});

// Initialize export modal when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new ExportModalManager();
    const parkingSlotsDropdown = $('#parking_slots'); // Parking slots dropdown

    const autoClickButton = document.getElementById('auto-click-button');
    if (autoClickButton) {
        autoClickButton.click();
    }

    let currentRequest = null;

    $('#specific_tenant').select2({
        placeholder: 'Search and select tenant',
        allowClear: true,
        ajax: {
            url: '/tenant_management/search/tenants',
            type: 'GET',
            dataType: 'json',
            delay: 300, // Debounce the search request
            transport: function (params, success, failure) {
                // Cancel the last request if still running
                if (currentRequest) {
                    currentRequest.abort();
                }
                currentRequest = $.ajax(params).done(success).fail(failure);
                return currentRequest;
            },
            data: function (params) {
                return {
                    search: params.term, // Search term entered by the user
                    page: params.page || 1 // Pagination support
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
    
                return {
                    results: data.data.map(tenant => ({
                        id: tenant.tenant_ref,
                        text: tenant.text // Use the `text` field directly from the response
                    })),
                    pagination: {
                        more: data.pagination.more // Use the `more` flag from the response
                    }
                };
            },
            cache: true // Cache results for better performance
        },
        minimumInputLength: 1 // Start searching after 1 character
    });
    
    // Enforce single selection
    $('#specific_tenant').on('select2:select', function (e) {
        const selectedValues = $(this).val();
        if (selectedValues.length > 1) {
            // Keep only the most recently selected value
            $(this).val([e.params.data.id]).trigger('change');
        }
    });

    // Initialize Select2 for the parking slots dropdown
    if(parkingSlotsDropdown.length){
        parkingSlotsDropdown.select2({
            placeholder: 'Select Parking Slot',
            allowClear: true
        });
    }

});

// Event listener for late fee input changes
$(document).on('input change', 'input[name="late_fee"]', function() {
    const lateFeeAmount = parseFloat($(this).val()) || 0;
    
    if (lateFeeAmount > 0) {
        $('#late_fee_payment_option').fadeIn(300);
    } else {
        $('#late_fee_payment_option').fadeOut(300);
        $('#late_fee_paid_now').prop('checked', false);
    }
});

// Event listener for the late fee payment checkbox
$(document).on('change', '#late_fee_paid_now', function() {
    const isChecked = $(this).is(':checked');
    const lateFeeAmount = parseFloat($('input[name="late_fee"]').val()) || 0;
    const paymentStatus = $('input[name="payment_status"]:checked').val();
    
    if (isChecked && lateFeeAmount > 0) {
        // Late fee will be included in this payment
        // Update the total amount display if needed
        // updatePaymentTotalWithLateFee(lateFeeAmount, true);
        
        // Show confirmation message
        const confirmationHtml = `
            <div class="alert alert-success mt-2" id="late_fee_inclusion_notice">
                <i class="fas fa-check me-2"></i>
                Late fee of ${DEFAULT_CURRENCY} ${lateFeeAmount.toLocaleString()} is included in this payment.
            </div>
        `;
        $('#late_fee_payment_option').after(confirmationHtml);
    } else {
        // Late fee will be transferred to next billing
        // updatePaymentTotalWithLateFee(lateFeeAmount, false);
        
        // Show transfer notice
        const transferHtml = `
            <div class="alert alert-info mt-2" id="late_fee_transfer_notice">
                <i class="fas fa-arrow-right me-2"></i>
                Late fee of ${DEFAULT_CURRENCY} ${lateFeeAmount.toLocaleString()} will be added to next billing cycle.
            </div>
        `;
        $('#late_fee_payment_option').after(transferHtml);
        
        // Remove inclusion notice if it exists
        $('#late_fee_inclusion_notice').remove();
    }
    
    // Remove existing notices after a delay
    setTimeout(() => {
        $('#late_fee_inclusion_notice, #late_fee_transfer_notice').fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
});

// Function to update payment total display
function updatePaymentTotalWithLateFee(lateFeeAmount, includeInPayment) {
    const paymentStatus = $('input[name="payment_status"]:checked').val();
    const currentAmount = parseFloat($('#amount_paid').val()) || 0;
    
    if (paymentStatus === 'paid' && includeInPayment) {
        // For full payments with late fee included, update the hidden amount
        const selectedOption = $('select[name="invoice_id"] option:selected');
        const invoiceBalance = parseFloat(selectedOption.data('balance')) || 0;
        const totalWithLateFee = invoiceBalance + lateFeeAmount;
        $('#amount_paid').val(totalWithLateFee);
        
        console.log('Updated amount_paid for full payment with late fee:', totalWithLateFee);
    } else if (paymentStatus === 'partial') {
        // For partial payments, the allocation inputs handle the amount
        // Late fee inclusion doesn't change the allocation amounts
        console.log('Partial payment - late fee handling is separate from allocation');
    }
}

// Debounce utility to limit function calls
function debounce(func, wait) {
    // Keep existing implementation
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Load existing tenants function
function loadExistingTenants() {
    const search = $('#existing_tenant_search').val();
    const selectedTenantType = $('input[name="tenant_type"]:checked').val(); // Get from Step 1
    var token = $('meta[name="csrf-token"]').attr('content');

    $('#tenants-loading').show();
    $('#existing-tenants-container').hide();
    $('#no-tenants-found').hide();

    $.ajax({
        url: '/tenant_management/existing-tenants',
        method: 'POST',
        data: {
            _token: token,
            search: search,
            tenant_type: selectedTenantType, // Filter by the selected type from Step 1
            exclude_active: false
        },
        success: function(response) {
            $('#tenants-loading').hide();
            
            if (response.tenants && response.tenants.length > 0) {
                renderExistingTenants(response.tenants);
                $('#existing-tenants-container').show();
            } else {
                $('#no-tenants-found').show();
            }
        },
        error: function() {
            $('#tenants-loading').hide();
            $('#no-tenants-found').show();
            $('#no-tenants-found div').text('Error loading tenants. Please try again.');
        }
    });
}

// Render existing tenants list
function renderExistingTenants(tenants) {
    const container = $('#existing-tenants-container');
    let html = '';

    tenants.forEach(tenant => {
        const displayName = tenant.tenant_type === 'Business' ? tenant.business_name : tenant.tenant_name;
        const displayEmail = tenant.tenant_type === 'Business' ? tenant.business_email : tenant.tenant_email;
        const displayPhone = tenant.tenant_type === 'Business' ? tenant.business_phone : tenant.tenant_phone;
        
        

        // Escape single quotes in data to prevent JavaScript errors
        const escapedName = displayName.replace(/'/g, "\\'");
        const escapedEmail = displayEmail.replace(/'/g, "\\'");
        const escapedPhone = displayPhone.replace(/'/g, "\\'");
        const escapedType = tenant.tenant_type.replace(/'/g, "\\'");

        html += `
            <div class="existing-tenant-item p-4 mb-3 border border-gray-300 rounded cursor-pointer hover-elevate-up" 
                 data-tenant-id="${tenant.id}" 
                 data-tenant-name="${escapedName}"
                 data-tenant-type="${escapedType}"
                 data-tenant-email="${escapedEmail}"
                 data-tenant-phone="${escapedPhone}"
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label bg-light-${tenant.tenant_type === 'Business' ? 'success' : 'primary'}">
                            <i class="fas fa-${tenant.tenant_type === 'Business' ? 'building' : 'user'} text-${tenant.tenant_type === 'Business' ? 'success' : 'primary'} fs-2x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-dark fw-bolder fs-6 mb-1">${displayName}</div>
                                <div class="text-muted fs-7">${displayEmail}</div>
                                <div class="text-muted fs-7">${displayPhone}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted fs-8 mt-1">${tenant.tenant_type}</div>
                                ${tenant.tenant_profile_ref ? `<div class="text-muted fs-8">Ref: ${tenant.tenant_profile_ref}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.html(html);
}

// Select existing tenant function
function selectExistingTenant(tenantId, name, type, email, phone, status, lastProperty) {
    // Set the hidden field
    $('#existing_tenant_id').val(tenantId);

    // Update preview
    $('#preview-name').text(name);
    $('#preview-type').text(type);
    $('#preview-email').text(email);
    $('#preview-phone').text(phone);
    $('#preview-status').html(getStatusBadgeHtml(status));
    $('#preview-last-property').text(lastProperty);

    // Show preview
    $('.selected-tenant-preview').fadeIn(300);

    // Highlight selected item
    $('.existing-tenant-item').removeClass('bg-light-primary border-primary');
    $(`.existing-tenant-item[data-tenant-id="${tenantId}"]`).addClass('bg-light-primary border-primary');

    // Scroll to preview
    $('.selected-tenant-preview')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Clear selected tenant
function clearSelectedTenant() {
    $('#existing_tenant_id').val('');
    $('.selected-tenant-preview').fadeOut(300);
    $('.existing-tenant-item').removeClass('bg-light-primary border-primary');
}

// Get status badge HTML
function getStatusBadgeHtml(status) {
    const statusClasses = {
        'Active': 'badge-light-success',
        'Pending': 'badge-light-warning',
        'Inactive': 'badge-light-secondary',
        'Expired': 'badge-light-danger',
        'Terminated': 'badge-light-dark'
    };
    
    const badgeClass = statusClasses[status] || 'badge-light-primary';
    return `<span class="badge ${badgeClass} fs-8">${status}</span>`;
}

// Define the functions in global scope, outside of document.ready
function initializeComplexSelect(selectedType) {
    complex.select2({
        ajax: {
            url: '/complexes/search',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    listing_type: selectedType, // Pass the selected listing type
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        placeholder: 'Search and select complex',
        minimumInputLength: 0,
        templateResult: formatComplexResult,
        templateSelection: formatComplexSelection,
        escapeMarkup: function (markup) {
            return markup;
        }
    });
}

function handleListingTypeChange() {
    listingTypeDropdown.on("change", function() {
        const selectedType = $(this).val();
        const complexSelect = $('#complex');
        const helpText = $('#complex-filter-text');
        const helpIcon = $('#complex-filter-help i');
        
        if (selectedType) {
            // Enable the complex dropdown
            complexSelect.prop('disabled', false);
            complexSelect.attr('data-placeholder', 'Search and select complex');
            
            // Update help text
            helpText.text(`Showing complexes for ${selectedType}`);
            helpIcon.removeClass('text-muted').addClass('text-primary');
            
            // Reinitialize Select2 with AJAX for the selected listing type
            if (complexSelect.hasClass('select2-hidden-accessible')) {
                complexSelect.select2('destroy');
            }
            
            // Initialize Select2 with AJAX and listing type filter
            initializeComplexSelect(selectedType);
            
            // Clear any previous selection
            complexSelect.val(null).trigger('change');
            
            // Hide and clear residential unit selection
            $('#residential_unit_selection').hide();
            // clearResidentialUnitSelection();
            
        } else {
            // Disable the complex dropdown
            complexSelect.prop('disabled', true);
            complexSelect.attr('data-placeholder', 'Select Property Type First');
            
            // Update help text
            helpText.text('Please select a property type first to enable complex selection');
            helpIcon.removeClass('text-primary').addClass('text-muted');
            
            // Clear and reset Select2
            if (complexSelect.hasClass('select2-hidden-accessible')) {
                complexSelect.select2('destroy');
            }
            
            complexSelect.select2({
                placeholder: 'Select Property Type First',
                minimumInputLength: 999, // Effectively disable search
                data: []
            });
            
            // Clear selection
            complexSelect.val(null).trigger('change');
            
            // Hide and clear residential unit selection
            $('#residential_unit_selection').hide();
            // clearResidentialUnitSelection();
        }
        
        // Show/hide filter help
        $('#complex-filter-help').show();
    });
}

function togglePropertyFields(listingType) {
    if (listingType === 'Commercial Unit') {
        $('.commercial').show();
        $('.non-commercial').hide();
    } else {
        $('.commercial').hide();
        $('.non-commercial').show();
    }
}

// Keep your existing format functions
function formatComplexResult(complex) {
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

function formatComplexSelection(complex) {
    return complex.title || complex.text;
}

// Function to fetch and populate lead data
function fetchAndPopulateLeadData(leadId, leadType) {
    $.ajax({
        url: '/customer_relationship/leads/data',
        method: 'GET',
        data: {
            lead_type: leadType,
            search: '',
            page: 1
        },
        success: function(response) {
            const leadItem = response.data.find(l => l.id == leadId);
            if (leadItem) {
                populateFormWithLeadData(leadItem, leadType);
            } else {
                toastr.error('Lead not found');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to fetch lead data');
            console.error('Error fetching lead data:', xhr);
        }
    });
}

// Function to populate form fields with lead data
function populateFormWithLeadData(leadItem, leadType) {
    // Store lead data for later use
    window.selectedLeadData = leadItem;
    
    // Access the nested profile object
    const profile = leadItem.profile || {};
    
    if (leadType === 'Individual') {
        // Populate individual fields
        $('input[name="tenant_name"]').val((profile.first_name || '') + ' ' + (profile.last_name || '')).trigger('change');
        $('input[name="tenant_email"]').val(profile.email || '').trigger('change');
        $('input[name="tenant_phone"]').val(profile.phone || '').trigger('change');
    } else if (leadType === 'Business') {
        // Populate business fields
        $('input[name="business_name"]').val(profile.company_name || '').trigger('change');
        $('input[name="business_registration"]').val(profile.registration_number || '').trigger('change');
        $('input[name="business_contact_person"]').val(profile.contact_person || '').trigger('change');
        $('input[name="business_email"]').val(profile.business_email || '').trigger('change');
        $('input[name="business_phone"]').val(profile.business_phone || '').trigger('change');
    }
    
    // Show success notification
    toastr.success('Lead data populated successfully');
    
    // Store property preferences for Step 3
    storePropertyPreferences(leadItem);
}

// Function to store property preferences
function storePropertyPreferences(lead) {
    const preferences = {
        property_type: lead.property_type,
        complex_id: lead.complex_id,
        complex_name: lead.complex ? lead.complex.title : null,
        purpose: lead.purpose,
        budget_min: lead.budget_min,
        budget_max: lead.budget_max,
        bedrooms: lead.bedrooms,
        bathrooms: lead.bathrooms,
        min_sq_area: lead.min_sq_area,
        max_sq_area: lead.max_sq_area,
        location_preference: lead.location_preference,
        timeline: lead.timeline,
        special_requirements: lead.special_requirements,
        business_use: lead.business_use,
        industry: lead.industry,
        company_size: lead.company_size
    };
    
    // Store in sessionStorage or a global variable
    sessionStorage.setItem('leadPropertyPreferences', JSON.stringify(preferences));
    
    // Auto-populate property type if available
    if (lead.property_type) {
        $('#listing_type').val(lead.property_type).trigger('change');
        
        // After complex options load, select the preferred complex
        if (lead.complex_id) {
            setTimeout(function() {
                $('#complex').val(lead.complex_id).trigger('change');
            }, 1000);
        }
    }
}

// Function to clear lead data
function clearLeadData() {
    window.selectedLeadData = null;
    sessionStorage.removeItem('leadPropertyPreferences');
    
    // Clear form fields
    $('input[name="tenant_name"], input[name="tenant_email"], input[name="tenant_phone"]').val('');
    $('input[name="business_name"], input[name="business_registration"], input[name="business_contact_person"], input[name="business_email"], input[name="business_phone"]').val('');
    
    // Hide property preferences display
    $('#lead_property_preferences_display').hide();
}

// Function to display property preferences in Step 3
function displayPropertyPreferences() {
    const preferences = sessionStorage.getItem('leadPropertyPreferences');
    if (!preferences) {
        $('#lead_property_preferences_display').hide();
        return;
    }
    
    const prefs = JSON.parse(preferences);
    let html = `
        <div class="alert alert-info border border-info border-dashed d-flex flex-column flex-sm-row p-5 mb-10">
            <i class="ki-duotone ki-information fs-2hx text-info me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column pe-0 pe-sm-10">
                <h5 class="mb-3">Lead's Property Preferences</h5>
                <div class="row">
    `;
    
    if (prefs.property_type) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Property Type:</span> ${prefs.property_type}
            </div>
        `;
    }
    
    if (prefs.complex_name) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Preferred Complex:</span> ${prefs.complex_name}
            </div>
        `;
    }
    
    if (prefs.purpose) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Purpose:</span> ${prefs.purpose.charAt(0).toUpperCase() + prefs.purpose.slice(1)}
            </div>
        `;
    }
    
    if (prefs.budget_min || prefs.budget_max) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Budget:</span> ${CURRENCY_SYMBOL} ${prefs.budget_min || 0} - ${CURRENCY_SYMBOL} ${prefs.budget_max || 'No limit'}
            </div>
        `;
    }
    
    if (prefs.bedrooms) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Bedrooms:</span> ${prefs.bedrooms}
            </div>
        `;
    }
    
    if (prefs.bathrooms) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Bathrooms:</span> ${prefs.bathrooms}
            </div>
        `;
    }
    
    if (prefs.min_sq_area || prefs.max_sq_area) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Area (sq ft):</span> ${prefs.min_sq_area || 0} - ${prefs.max_sq_area || 'No limit'}
            </div>
        `;
    }
    
    if (prefs.timeline) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Timeline:</span> ${prefs.timeline.replace(/_/g, '-')}
            </div>
        `;
    }
    
    if (prefs.industry) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Industry:</span> ${prefs.industry.charAt(0).toUpperCase() + prefs.industry.slice(1)}
            </div>
        `;
    }
    
    if (prefs.company_size) {
        html += `
            <div class="col-md-6 mb-3">
                <span class="fw-bold">Company Size:</span> ${prefs.company_size}
            </div>
        `;
    }
    
    if (prefs.business_use) {
        html += `
            <div class="col-md-12 mb-3">
                <span class="fw-bold">Business Use:</span> ${prefs.business_use}
            </div>
        `;
    }
    
    if (prefs.special_requirements) {
        html += `
            <div class="col-md-12 mb-3">
                <span class="fw-bold">Special Requirements:</span> ${prefs.special_requirements}
            </div>
        `;
    }
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    $('#lead_property_preferences_display').html(html).show();
}
