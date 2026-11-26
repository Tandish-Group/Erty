"use strict";

class AgentSettingsManager {
    constructor() {
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        this.baseUrl = '/agents/settings/handle';
        this.initializeModals();
        this.initializeTables();
        this.initializeSelect2();
    }

    initializeSelect2() {
        // Initialize Select2 for all modals
        if ($.fn.select2) {
            $('select[data-control="select2"]').each(function() {
                const $this = $(this);
                const modalParent = $this.closest('.modal');
                
                $this.select2({
                    dropdownParent: modalParent.length ? modalParent : $(document.body),
                    placeholder: $this.data('placeholder') || 'Select option',
                    allowClear: true,
                    minimumResultsForSearch: 5
                });
            });
        }
    }

    initializeModals() {
        // Setup all modals with their types
        this.setupModal('department');
        this.setupModal('role');
        this.setupModal('employment_type');
        this.setupModal('location');
        this.setupModal('specialization');
    }

    setupModal(type) {
        const formId = `#kt_modal_add_${type}_form`;
        const modalId = `#kt_modal_add_${type}`;

        // Form submission handler
        $(formId).on('submit', (e) => {
            e.preventDefault();
            const submitButton = $(formId).find('button[type="submit"]');
            const formData = new FormData(e.target);
            
            // Add type to form data
            formData.append('type', type);

            // Disable button and show loading
            this.setButtonLoadingState(submitButton, true);

            $.ajax({
                url: this.baseUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken
                },
                success: (response) => {
                    this.showSuccessAlert(`${this.capitalize(type)} added successfully!`).then(() => {
                        $(modalId).modal('hide');
                        $(formId)[0].reset();
                        $(formId).find('select').val('').trigger('change');
                        this.refreshTable(type);
                    });
                },
                error: (xhr) => {
                    this.handleError(xhr);
                },
                complete: () => {
                    this.setButtonLoadingState(submitButton, false);
                }
            });
        });

        // Reset form when modal is closed
        $(modalId).on('hidden.bs.modal', function() {
            $(formId)[0].reset();
            $(formId).find('select').val('').trigger('change');
            $(formId).find('.is-invalid').removeClass('is-invalid');
            $(formId).find('.invalid-feedback').remove();
        });

        // Reset form when cancel button is clicked
        $(formId).find('button[type="reset"]').on('click', function() {
            $(modalId).modal('hide');
        });
    }

    initializeTables() {
        this.initializeDepartmentsTable();
        this.initializeRolesTable();
        this.initializeEmploymentTypesTable();
        this.initializeLocationsTable();
        this.initializeSpecializationsTable();
    }

    initializeDepartmentsTable() {
        const table = $('#kt_departments_table');
        if (!table.length) return;

        $.ajax({
            url: `${this.baseUrl}?type=department`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => {
                this.renderDepartmentsTable(table, response.data || []);
            },
            error: (xhr) => {
                console.error('Error loading departments:', xhr);
                const sampleData = [
                    { id: 1, name: 'Sales', agents_count: 25, head: 'John Smith' },
                    { id: 2, name: 'Leasing', agents_count: 18, head: 'Sarah Wilson' },
                    { id: 3, name: 'Property Management', agents_count: 12, head: 'Mike Johnson' }
                ];
                this.renderDepartmentsTable(table, sampleData);
            }
        });
    }

    renderDepartmentsTable(table, data) {
        const tbody = table.find('tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="3" class="text-center py-10">
                        <div class="text-gray-600 fs-5">No departments found. Click "Add Department" to create one.</div>
                    </td>
                </tr>
            `);
            return;
        }

        data.forEach(item => {
            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-primary">
                                    <span class="svg-icon svg-icon-2x svg-icon-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M21 22H14C13.4 22 13 21.6 13 21V3C13 2.4 13.4 2 14 2H21C21.6 2 22 2.4 22 3V21C22 21.6 21.6 22 21 22Z" fill="currentColor"/>
                                            <path d="M10 22H3C2.4 22 2 21.6 2 21V3C2 2.4 2.4 2 3 2H10C10.6 2 11 2.4 11 3V21C11 21.6 10.6 22 10 22Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-gray-800 fw-bold fs-6">${item.name}</span>
                                ${item.head ? `<span class="text-muted fs-7">Head: ${item.head}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-light-primary fs-7 fw-bold">${item.agents_count || 0} agents</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-5 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="10" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="17" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="3" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-action="edit" data-id="${item.id}" data-type="department">Edit</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3 text-danger" data-action="delete" data-id="${item.id}" data-type="department">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        KTMenu.createInstances();
        this.attachActionHandlers(table, 'department');
    }

    initializeRolesTable() {
        const table = $('#kt_roles_table');
        if (!table.length) return;

        $.ajax({
            url: `${this.baseUrl}?type=role`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => {
                this.renderRolesTable(table, response.data || []);
            },
            error: (xhr) => {
                console.error('Error loading roles:', xhr);
                const sampleData = [
                    { id: 1, name: 'Sales Agent', level: 'Junior', department: 'Sales' },
                    { id: 2, name: 'Senior Agent', level: 'Senior', department: 'Sales' },
                    { id: 3, name: 'Team Lead', level: 'Lead', department: 'Sales' }
                ];
                this.renderRolesTable(table, sampleData);
            }
        });
    }

    renderRolesTable(table, data) {
        const tbody = table.find('tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="3" class="text-center py-10">
                        <div class="text-gray-600 fs-5">No roles found. Click "Add Role" to create one.</div>
                    </td>
                </tr>
            `);
            return;
        }

        const levelColors = {
            'entry': 'info',
            'junior': 'success',
            'mid': 'primary',
            'senior': 'warning',
            'lead': 'danger',
            'manager': 'dark',
            'director': 'dark',
            'executive': 'dark'
        };

        data.forEach(item => {
            const levelColor = levelColors[item.level?.toLowerCase()] || 'secondary';
            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-success">
                                    <span class="svg-icon svg-icon-2x svg-icon-success">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="M6.28548 15.0861C7.34369 13.1814 9.35142 12 11.5304 12H12.4696C14.6486 12 16.6563 13.1814 17.7145 15.0861L19.3493 18.0287C20.0899 19.3618 19.1259 21 17.601 21H6.39903C4.87406 21 3.91012 19.3618 4.65071 18.0287L6.28548 15.0861Z" fill="currentColor"/>
                                            <rect opacity="0.3" x="8" y="3" width="8" height="8" rx="4" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-gray-800 fw-bold fs-6">${item.name}</span>
                                ${item.department ? `<span class="text-muted fs-7">${item.department}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-light-${levelColor} fs-7 fw-bold">${item.level}</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-5 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="10" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="17" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="3" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-action="edit" data-id="${item.id}" data-type="role">Edit</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3 text-danger" data-action="delete" data-id="${item.id}" data-type="role">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        KTMenu.createInstances();
        this.attachActionHandlers(table, 'role');
    }

    initializeEmploymentTypesTable() {
        const table = $('#kt_employment_types_table');
        if (!table.length) return;

        $.ajax({
            url: `${this.baseUrl}?type=employment_type`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => {
                this.renderEmploymentTypesTable(table, response.data || []);
            },
            error: (xhr) => {
                console.error('Error loading employment types:', xhr);
                const sampleData = [
                    { id: 1, name: 'Full Time', description: 'Standard full-time employment' },
                    { id: 2, name: 'Part Time', description: 'Part-time employment' },
                    { id: 3, name: 'Contract', description: 'Fixed-term contract' }
                ];
                this.renderEmploymentTypesTable(table, sampleData);
            }
        });
    }

    renderEmploymentTypesTable(table, data) {
        const tbody = table.find('tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="3" class="text-center py-10">
                        <div class="text-gray-600 fs-5">No employment types found. Click "Add Type" to create one.</div>
                    </td>
                </tr>
            `);
            return;
        }

        data.forEach(item => {
            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-warning">
                                    <span class="svg-icon svg-icon-2x svg-icon-warning">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M18.0624 15.3453L13.1624 20.7453C12.5624 21.4453 11.5624 21.4453 10.9624 20.7453L6.06242 15.3453C4.56242 13.6453 3.76242 11.4453 4.06242 8.94534C4.56242 5.34534 7.46242 2.44534 11.0624 2.04534C15.8624 1.54534 19.9624 5.24534 19.9624 9.94534C20.0624 12.0453 19.2624 13.9453 18.0624 15.3453Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <span class="text-gray-800 fw-bold fs-6">${item.name}</span>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted fs-7">${item.description || '-'}</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-5 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="10" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="17" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="3" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-action="edit" data-id="${item.id}" data-type="employment_type">Edit</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3 text-danger" data-action="delete" data-id="${item.id}" data-type="employment_type">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        KTMenu.createInstances();
        this.attachActionHandlers(table, 'employment_type');
    }

    initializeLocationsTable() {
        const table = $('#kt_locations_table');
        if (!table.length) return;

        $.ajax({
            url: `${this.baseUrl}?type=location`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => {
                this.renderLocationsTable(table, response.data || []);
            },
            error: (xhr) => {
                console.error('Error loading locations:', xhr);
                const sampleData = [
                    { id: 1, name: 'Main Office', address: '123 Main St, City Center', type: 'office' },
                    { id: 2, name: 'Branch Office 1', address: '456 Oak Ave, Downtown', type: 'branch' },
                    { id: 3, name: 'Remote', address: 'Virtual Office', type: 'remote' }
                ];
                this.renderLocationsTable(table, sampleData);
            }
        });
    }

    renderLocationsTable(table, data) {
        const tbody = table.find('tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="3" class="text-center py-10">
                        <div class="text-gray-600 fs-5">No locations found. Click "Add Location" to create one.</div>
                    </td>
                </tr>
            `);
            return;
        }

        data.forEach(item => {
            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-info">
                                    <span class="svg-icon svg-icon-2x svg-icon-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M18.0624 15.3453L13.1624 20.7453C12.5624 21.4453 11.5624 21.4453 10.9624 20.7453L6.06242 15.3453C4.56242 13.6453 3.76242 11.4453 4.06242 8.94534C4.56242 5.34534 7.46242 2.44534 11.0624 2.04534C15.8624 1.54534 19.9624 5.24534 19.9624 9.94534C20.0624 12.0453 19.2624 13.9453 18.0624 15.3453Z" fill="currentColor"/>
                                            <path d="M12.0624 13.0453C13.7193 13.0453 15.0624 11.7022 15.0624 10.0453C15.0624 8.38849 13.7193 7.04535 12.0624 7.04535C10.4056 7.04535 9.06241 8.38849 9.06241 10.0453C9.06241 11.7022 10.4056 13.0453 12.0624 13.0453Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-gray-800 fw-bold fs-6">${item.name}</span>
                                ${item.type ? `<span class="badge badge-light-info fs-8 mt-1">${item.type}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted fs-7">${item.address || '-'}</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-5 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="10" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="17" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="3" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-action="edit" data-id="${item.id}" data-type="location">Edit</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3 text-danger" data-action="delete" data-id="${item.id}" data-type="location">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        KTMenu.createInstances();
        this.attachActionHandlers(table, 'location');
    }

    initializeSpecializationsTable() {
        const table = $('#kt_specializations_table');
        if (!table.length) return;

        $.ajax({
            url: `${this.baseUrl}?type=specialization`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            success: (response) => {
                this.renderSpecializationsTable(table, response.data || []);
            },
            error: (xhr) => {
                console.error('Error loading specializations:', xhr);
                const sampleData = [
                    { id: 1, name: 'Residential Sales', description: 'Residential property sales', category: 'Sales' },
                    { id: 2, name: 'Commercial Sales', description: 'Commercial property sales', category: 'Sales' },
                    { id: 3, name: 'Luxury Properties', description: 'High-end luxury properties', category: 'Property Type' }
                ];
                this.renderSpecializationsTable(table, sampleData);
            }
        });
    }

    renderSpecializationsTable(table, data) {
        const tbody = table.find('tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="4" class="text-center py-10">
                        <div class="text-gray-600 fs-5">No specializations found. Click "Add Specialization" to create one.</div>
                    </td>
                </tr>
            `);
            return;
        }

        const categoryColors = {
            'sales': 'success',
            'leasing': 'primary',
            'property_type': 'warning',
            'service': 'info',
            'technical': 'danger'
        };

        data.forEach(item => {
            const categoryColor = categoryColors[item.category?.toLowerCase().replace(' ', '_')] || 'secondary';
            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-danger">
                                    <span class="svg-icon svg-icon-2x svg-icon-danger">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.6 14 16V13C14 12.4 13.6 12 13 12Z" fill="currentColor"/>
                                            <path d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <span class="text-gray-800 fw-bold fs-6">${item.name}</span>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted fs-7">${item.description || '-'}</span>
                    </td>
                    <td>
                        <span class="badge badge-light-${categoryColor} fs-7 fw-bold">${item.category}</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-5 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect x="10" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="17" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                    <rect x="3" y="10" width="4" height="4" rx="2" fill="currentColor"/>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-action="edit" data-id="${item.id}" data-type="specialization">Edit</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3 text-danger" data-action="delete" data-id="${item.id}" data-type="specialization">Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        KTMenu.createInstances();
        this.attachActionHandlers(table, 'specialization');
    }

    attachActionHandlers(table, type) {
        table.find('[data-action="edit"]').off('click').on('click', (e) => {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.editItem(type, id);
        });

        table.find('[data-action="delete"]').off('click').on('click', (e) => {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            this.deleteItem(type, id);
        });
    }

    editItem(type, id) {
        // TODO: Implement edit functionality
        console.log(`Edit ${type} with ID: ${id}`);
        
        Swal.fire({
            title: 'Edit Feature',
            text: 'Edit functionality will be implemented soon.',
            icon: 'info',
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    }

    deleteItem(type, id) {
        const typeText = this.capitalize(type);
        
        Swal.fire({
            title: `Delete ${typeText}?`,
            text: `Are you sure you want to delete this ${typeText.toLowerCase()}? This action cannot be undone.`,
            icon: "warning",
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: "Yes, delete!",
            cancelButtonText: "No, cancel",
            customClass: {
                confirmButton: "btn fw-bold btn-danger",
                cancelButton: "btn fw-bold btn-active-light-primary"
            }
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: `${this.baseUrl}/${id}?type=${type}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    success: (response) => {
                        this.showSuccessAlert(`${typeText} deleted successfully!`).then(() => {
                            this.refreshTable(type);
                        });
                    },
                    error: (xhr) => {
                        this.handleError(xhr);
                    }
                });
            }
        });
    }

    refreshTable(type) {
        switch(type) {
            case 'department':
                this.initializeDepartmentsTable();
                break;
            case 'role':
                this.initializeRolesTable();
                break;
            case 'employment_type':
                this.initializeEmploymentTypesTable();
                break;
            case 'location':
                this.initializeLocationsTable();
                break;
            case 'specialization':
                this.initializeSpecializationsTable();
                break;
        }
    }

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

    handleError(xhr) {
        let errorMessage = 'An error occurred. Please try again.';
        
        if (xhr.responseJSON?.message) {
            errorMessage = xhr.responseJSON.message;
        } else if (xhr.responseJSON?.errors) {
            const errors = Object.values(xhr.responseJSON.errors).flat();
            errorMessage = errors.join('<br>');
        }
        
        Swal.fire({
            html: errorMessage,
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok, got it!",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    }

    capitalize(str) {
        return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.agentSettingsManager = new AgentSettingsManager();
});