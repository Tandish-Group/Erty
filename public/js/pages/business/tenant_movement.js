/**
 * Tenant Movement Manager
 * Handles: movement overview dashboard, initiate-transfer create page, show page
 *
 * Loaded ONLY on:  tenant_management/movement
 *                  tenant_management/movement/*
 */
var MovementManager = (function ($) {
    'use strict';

    // ─── State ──────────────────────────────────────────────────────────────
    var state = {
        tenancyId:          null,
        complexId:          null,
        unitType:           null,
        currency:           'USD',
        currentStep:        1,
        totalSteps:         4,
        arrearsData:        null,
        hasParking:         false,
        parkingSlotId:      null,
        newUnitId:          null,
        newUnitRent:        null,
        selectedUnitData:   {},
        // list pagination
        currentPage:        1,
        pageSize:           25,
        searchTerm:         '',
        statusFilter:       '',
    };

    var BASE = '/tenant_management/movement';

    // ─── Init ────────────────────────────────────────────────────────────────

    function init() {
        var path = window.location.pathname;

        if (path === '/tenant_management/movement' || path === '/tenant_management/movement/') {
            initIndexPage();
        } else if (path.indexOf('/tenant_management/movement/create') === 0) {
            initCreatePage();
        } else if (path.match(/\/tenant_management\/movement\/[A-Z0-9-]+$/)) {
            initShowPage();
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INDEX PAGE
    // ═══════════════════════════════════════════════════════════════════════

    function initIndexPage() {
        loadStats();
        loadMovementsList();
        bindIndexEvents();
    }

    function bindIndexEvents() {
        // Stat card click → filter by status
        $(document).on('click', '[data-status]', function () {
            var status = $(this).data('status');
            // Toggle: click same card again to clear filter
            if (state.statusFilter === status) {
                state.statusFilter = '';
                $('#movements_status_filter').val('');
            } else {
                state.statusFilter = status;
                $('#movements_status_filter').val(status);
            }
            $('[data-status]').attr('aria-pressed', 'false');
            if (state.statusFilter) {
                $('[data-status="' + state.statusFilter + '"]').attr('aria-pressed', 'true');
            }
            state.currentPage = 1;
            loadMovementsList();
        });

        $(document).on('keydown', '[data-status][role="button"]', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                $(this).trigger('click');
            }
        });

        // Search input with debounce
        var searchTimer;
        $(document).on('keyup', '#movements_search', function () {
            clearTimeout(searchTimer);
            var val = $(this).val();
            searchTimer = setTimeout(function () {
                state.searchTerm = val;
                state.currentPage = 1;
                loadMovementsList();
            }, 350);
        });

        // Status filter dropdown
        $(document).on('change', '#movements_status_filter', function () {
            state.statusFilter = $(this).val();
            $('[data-status]').attr('aria-pressed', 'false');
            if (state.statusFilter) {
                $('[data-status="' + state.statusFilter + '"]').attr('aria-pressed', 'true');
            }
            state.currentPage = 1;
            loadMovementsList();
        });

        // Approve button in table
        $(document).on('click', '[data-action="approve-movement"]', function () {
            var ref = $(this).data('ref');
            approveMovement(ref);
        });

        // Cancel button in table → open modal
        $(document).on('click', '[data-action="cancel-movement"]', function () {
            var ref = $(this).data('ref');
            $('#cancel_movement_ref').val(ref);
            $('#cancel_reason').val('');
            $('#cancelMovementModal').modal('show');
        });

        // Cancel modal confirm
        $(document).on('click', '#confirm_cancel_btn', function () {
            var ref    = $('#cancel_movement_ref').val();
            var reason = $('#cancel_reason').val();
            cancelMovement(ref, reason);
        });
    }

    function loadStats() {
        $.get(BASE + '/list', { length: 0, start: 0 }, function (res) {
            if (res.status === 200 && res.stats) {
                $('#stat_pending').text(res.stats.pending);
                $('#stat_approved').text(res.stats.approved);
                $('#stat_completed').text(res.stats.completed);
                $('#stat_cancelled').text(res.stats.cancelled);
            }
        });
    }

    function loadMovementsList() {
        var params = {
            search: state.searchTerm,
            status: state.statusFilter,
            start:  (state.currentPage - 1) * state.pageSize,
            length: state.pageSize,
        };

        $('#movements_table_body').html(
            '<tr><td colspan="9" class="text-center text-muted py-10">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>'
        );

        $.get(BASE + '/list', params, function (res) {
            if (res.status !== 200) {
                showTableError('Failed to load transfers.');
                return;
            }

            // Update stats too
            if (res.stats) {
                $('#stat_pending').text(res.stats.pending);
                $('#stat_approved').text(res.stats.approved);
                $('#stat_completed').text(res.stats.completed);
                $('#stat_cancelled').text(res.stats.cancelled);
            }

            renderMovementsTable(res.data, res.total);
        }).fail(function () {
            showTableError('Failed to load transfers. Please refresh.');
        });
    }

    function renderMovementsTable(rows, total) {
        if (!rows || rows.length === 0) {
            $('#movements_table_body').html(
                '<tr><td colspan="9" class="text-center text-muted py-10">No transfers found.</td></tr>'
            );
            return;
        }

        var html = '';
        rows.forEach(function (m) {
            html += '<tr>' +
                '<td><span class="fw-bolder text-dark">' + escHtml(m.ref) + '</span></td>' +
                '<td>' + escHtml(m.tenant_name) + '</td>' +
                '<td><span class="badge badge-light-danger">' + escHtml(m.from_unit) + '</span></td>' +
                '<td class="text-center text-muted">→</td>' +
                '<td><span class="badge badge-light-success">' + escHtml(m.to_unit) + '</span></td>' +
                '<td>' + escHtml(m.transfer_date) + '</td>' +
                '<td>' + escHtml(m.move_type) + '</td>' +
                '<td>' + m.status_badge + '</td>' +
                '<td class="text-end">' + renderRowActions(m) + '</td>' +
                '</tr>';
        });

        $('#movements_table_body').html(html);
        renderPagination(total);
    }

    function renderRowActions(m) {
        var btns = '';
        if (m.can_approve) {
            btns += '<button class="btn btn-sm btn-light-success me-2" data-action="approve-movement" data-ref="' + escHtml(m.ref) + '">' +
                    '<i class="fas fa-check"></i> Approve</button>';
        }
        if (m.can_cancel) {
            btns += '<button class="btn btn-sm btn-light-danger" data-action="cancel-movement" data-ref="' + escHtml(m.ref) + '">' +
                    '<i class="fas fa-times"></i> Cancel</button>';
        }
        btns += '<a href="' + BASE + '/' + escHtml(m.ref) + '" class="btn btn-sm btn-light ms-2"><i class="fas fa-eye"></i></a>';
        return btns;
    }

    function renderPagination(total) {
        var totalPages = Math.ceil(total / state.pageSize);
        if (totalPages <= 1) {
            $('#movements_pagination_wrapper').hide();
            return;
        }

        var start = (state.currentPage - 1) * state.pageSize + 1;
        var end   = Math.min(state.currentPage * state.pageSize, total);
        $('#movements_showing_text').text('Showing ' + start + '–' + end + ' of ' + total);

        var pages = '';
        for (var i = 1; i <= totalPages; i++) {
            pages += '<button class="btn btn-sm ' + (i === state.currentPage ? 'btn-primary' : 'btn-light') + ' ms-1" data-page="' + i + '">' + i + '</button>';
        }
        $('#movements_pagination').html(pages);
        $('#movements_pagination_wrapper').show();

        $(document).off('click', '[data-page]').on('click', '[data-page]', function () {
            state.currentPage = parseInt($(this).data('page'));
            loadMovementsList();
        });
    }

    function showTableError(msg) {
        $('#movements_table_body').html(
            '<tr><td colspan="9" class="text-center text-danger py-10"><i class="fas fa-exclamation-circle me-2"></i>' + escHtml(msg) + '</td></tr>'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  APPROVE / CANCEL (shared between index and show)
    // ═══════════════════════════════════════════════════════════════════════

    function approveMovement(ref) {
        if (!confirm('Approve and execute this transfer? This will move the tenant immediately.')) return;

        var btn = $('[data-action="approve-movement"][data-ref="' + ref + '"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post(BASE + '/' + ref + '/approve', { _token: getToken() }, function (res) {
            if (res.status === 200) {
                toastSuccess(res.message || 'Transfer approved and executed.');
                // Reload page or list
                if ($('#kt_table_movements').length) {
                    loadStats();
                    loadMovementsList();
                } else {
                    setTimeout(function () { window.location.href = BASE; }, 1200);
                }
            } else {
                toastError(res.message || 'Failed to approve transfer.');
                btn.prop('disabled', false).html('<i class="fas fa-check"></i> Approve');
            }
        }).fail(function (xhr) {
            toastError('Failed to approve. Please try again.');
            btn.prop('disabled', false).html('<i class="fas fa-check"></i> Approve');
        });
    }

    function cancelMovement(ref, reason) {
        var btn = $('#confirm_cancel_btn');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post(BASE + '/' + ref + '/cancel', { _token: getToken(), reason: reason }, function (res) {
            $('#cancelMovementModal').modal('hide');
            btn.prop('disabled', false).text('Cancel Transfer');

            if (res.status === 200) {
                toastSuccess(res.message || 'Transfer cancelled.');
                if ($('#kt_table_movements').length) {
                    loadStats();
                    loadMovementsList();
                } else {
                    setTimeout(function () { window.location.href = BASE; }, 1200);
                }
            } else {
                toastError(res.message || 'Failed to cancel transfer.');
            }
        }).fail(function () {
            $('#cancelMovementModal').modal('hide');
            btn.prop('disabled', false).text('Cancel Transfer');
            toastError('Failed to cancel. Please try again.');
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  CREATE PAGE
    // ═══════════════════════════════════════════════════════════════════════

    function initCreatePage() {
        var pd = window.currentPageData || {};

        if (pd.tenancy_id) {
            // Pre-filled from tenancy
            state.tenancyId  = pd.tenancy_id;
            state.complexId  = pd.complex_id;
            state.unitType   = pd.unit_type;
            state.currency   = pd.currency || 'USD';
            state.arrearsData = pd.arrears || null;
            state.hasParking  = pd.has_parking || false;
            state.parkingSlotId = pd.parking_slot ? pd.parking_slot.id : null;

            if (pd.arrears) applyArrearsUI(pd.arrears);
            if (pd.lease_data) prefillLeaseTerms(pd.lease_data, pd.monthly_payment, pd.currency);

            loadVacantUnits();
            setDefaultTransferDate();
        } else {
            // No pre-fill — show tenant select2
            initTenantSelect2();
        }

        setCurrencyLabels(state.currency);
        bindCreateEvents();
        goToStep(1);
    }

    function bindCreateEvents() {
        // Tenant select2 change
        $(document).on('change', '#movement_tenant_select', function () {
            var tenantProfileId = $(this).val();
            if (tenantProfileId) fetchActiveTenancy(tenantProfileId);
        });

        // Move type change → update default transfer date
        $(document).on('change', '#movement_move_type', function () {
            setDefaultTransferDate();
        });

        // Unit selection → capture unit data & show conditional extras
        $(document).on('change', '#movement_new_unit', function () {
            var selected = $(this).find(':selected');
            state.newUnitId   = $(this).val();
            state.newUnitRent = parseFloat(selected.data('rent')) || null;

            // Store full unit data from data-* attributes
            state.selectedUnitData = {
                rent:             parseFloat(selected.data('rent'))             || null,
                bedrooms:         selected.data('bedrooms'),
                bathrooms:        selected.data('bathrooms'),
                sq_area:          selected.data('sqarea'),
                unit_sq_ft:       parseFloat(selected.data('sqft'))            || null,
                min_sq_ft:        parseFloat(selected.data('minsqft'))         || null,
                max_subdivisions: parseInt(selected.data('maxsubdivisions'))   || null,
            };

            if (!state.newUnitId) {
                $('#residential_unit_extra').addClass('d-none');
                $('#commercial_unit_extra').addClass('d-none');
                return;
            }

            if (state.unitType === 'residential') {
                showResidentialExtras(state.selectedUnitData);
            } else if (state.unitType === 'commercial') {
                showCommercialExtras(state.selectedUnitData);
            }
        });

        // Deposit change → live adjustment banner
        $(document).on('input', '#movement_new_deposit', function () {
            updateDepositBanner();
        });

        // Parking action radios → show/hide new slot selector
        $(document).on('change', 'input[name="parking_action"]', function () {
            var action = $(this).val();
            if (action === 'replace') {
                $('#new_parking_slot_wrapper').removeClass('d-none');
                loadAvailableParkingSlots();
            } else {
                $('#new_parking_slot_wrapper').addClass('d-none');
            }
        });

        // Nav buttons
        $('#movement_next_btn').on('click', function () {
            if (!validateStep(state.currentStep)) return;
            goToStep(state.currentStep + 1);
        });

        $('#movement_prev_btn').on('click', function () {
            goToStep(state.currentStep - 1);
        });

        $('#movement_submit_btn').on('click', function () {
            submitMovement();
        });
    }

    function initTenantSelect2() {
        if (!$('#movement_tenant_select').length) return;

        $('#movement_tenant_select').select2({
            placeholder:  'Search tenants…',
            minimumInputLength: 2,
            allowClear: true,
            ajax: {
                url:      '/tenant_management/search/tenants',
                dataType: 'json',
                delay:    300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) {
                    var items = Array.isArray(data) ? data : (data.data || []);
                    return {
                        results: items,
                        pagination: data.pagination || { more: false },
                    };
                },
            },
        });
    }

    function fetchActiveTenancy(tenantProfileId) {
        $.get(BASE + '/tenant/' + tenantProfileId + '/active-tenancy', function (res) {
            if (res.status === 200 && res.data) {
                var d = res.data;
                state.tenancyId     = d.tenancy_id;
                state.complexId     = d.complex_id;
                state.unitType      = d.unit_type;
                state.currency      = d.currency || 'USD';
                state.arrearsData   = d.arrears;
                state.hasParking    = d.has_parking;
                state.parkingSlotId = d.parking_slot ? d.parking_slot.id : null;

                // Show tenancy info box
                $('#tenancy_info_name').text($('#movement_tenant_select option:selected').text());
                $('#tenancy_info_unit').text(d.unit_number || '');
                $('#tenancy_info_rent').text(d.currency + ' ' + parseFloat(d.monthly_payment).toFixed(2));
                $('#current_tenancy_info').removeClass('d-none');
                $('#tenant_select_wrapper').hide();

                if (d.arrears) applyArrearsUI(d.arrears);
                if (d.lease_data) prefillLeaseTerms(d.lease_data, d.monthly_payment, d.currency);

                setCurrencyLabels(d.currency);
                loadVacantUnits();
                setDefaultTransferDate();
            } else {
                toastError(res.message || 'No active tenancy found for this tenant.');
            }
        }).fail(function () {
            toastError('Failed to fetch tenancy data. Please try again.');
        });
    }

    function loadVacantUnits() {
        if (!state.complexId || !state.unitType) return;

        var params = {
            complex_id:          state.complexId,
            unit_type:           state.unitType,
            exclude_tenancy_id:  state.tenancyId || 0,
        };

        $('#movement_new_unit').html('<option value="">Loading units…</option>');
        // Reset conditional extras while units reload
        $('#residential_unit_extra').addClass('d-none');
        $('#commercial_unit_extra').addClass('d-none');
        state.selectedUnitData = {};

        $.get(BASE + '/vacant-units', params, function (res) {
            if (res.status === 200) {
                var opts = '<option value="">— select target unit —</option>';
                (res.data || []).forEach(function (u) {
                    if (u.unit_type === 'commercial') {
                        opts += '<option value="' + u.id + '"'
                              + ' data-rent="'             + (u.rent             || '') + '"'
                              + ' data-sqft="'             + (u.unit_sq_ft       || '') + '"'
                              + ' data-minsqft="'          + (u.min_sq_ft        || '') + '"'
                              + ' data-maxsubdivisions="'  + (u.max_subdivisions || '') + '">'
                              + escHtml(u.label)
                              + '</option>';
                    } else {
                        opts += '<option value="' + u.id + '"'
                              + ' data-rent="'      + (u.rent      || '') + '"'
                              + ' data-bedrooms="'  + (u.bedrooms  || '') + '"'
                              + ' data-bathrooms="' + (u.bathrooms || '') + '"'
                              + ' data-sqarea="'    + (u.sq_area   || '') + '">'
                              + escHtml(u.label)
                              + (u.rent ? ' — ' + state.currency + ' ' + parseFloat(u.rent).toFixed(2) : '')
                              + '</option>';
                    }
                });
                $('#movement_new_unit').html(opts);
            } else {
                $('#movement_new_unit').html('<option value="">No vacant units available</option>');
            }
        }).fail(function () {
            $('#movement_new_unit').html('<option value="">Failed to load units</option>');
        });
    }

    function loadAvailableParkingSlots() {
        if (!state.complexId) return;

        $.get(BASE + '/parking-slots', { complex_id: state.complexId, exclude_slot_id: state.parkingSlotId || 0 }, function (res) {
            if (res.status === 200) {
                var opts = '<option value="">— select parking slot —</option>';
                (res.data || []).forEach(function (s) {
                    opts += '<option value="' + s.id + '">' + escHtml(s.label) + ' (' + escHtml(s.type || '') + ')</option>';
                });
                $('#movement_new_parking_slot').html(opts);
            }
        });
    }

    // ─── Unit-type conditional extras ─────────────────────────────────────

    function showResidentialExtras(unitData) {
        $('#commercial_unit_extra').addClass('d-none');
        $('#residential_unit_extra').removeClass('d-none');

        $('#res_info_bedrooms').text(unitData.bedrooms != null && unitData.bedrooms !== '' ? unitData.bedrooms : '—');
        $('#res_info_bathrooms').text(unitData.bathrooms != null && unitData.bathrooms !== '' ? unitData.bathrooms : '—');
        $('#res_info_sqarea').text(unitData.sq_area ? unitData.sq_area + ' sq ft' : '—');
        $('#res_info_rent').text(unitData.rent ? state.currency + ' ' + parseFloat(unitData.rent).toFixed(2) : '—');

        // Pre-fill rent in Step 2 (editable for residential)
        if (unitData.rent) {
            $('#movement_new_rent').val(parseFloat(unitData.rent).toFixed(2));
        }
        $('#movement_new_rent').prop('readonly', false);
        $('#rent_auto_note').addClass('d-none');

        // Pet details toggle
        $('input[name="movement_has_pets"]').off('change.petToggle').on('change.petToggle', function () {
            if ($(this).val() === '1') {
                $('#movement_pet_details_wrapper').removeClass('d-none');
            } else {
                $('#movement_pet_details_wrapper').addClass('d-none');
            }
        });
    }

    function showCommercialExtras(unitData) {
        $('#residential_unit_extra').addClass('d-none');
        $('#commercial_unit_extra').removeClass('d-none');

        if (unitData.unit_sq_ft) {
            $('#comm_total_sqft').text(unitData.unit_sq_ft + ' sq ft');
            $('#comm_min_sqft').text(unitData.min_sq_ft ? unitData.min_sq_ft + ' sq ft' : '—');
            $('#comm_max_subdivisions').text(unitData.max_subdivisions || '—');
            $('#comm_base_rent').text(unitData.rent ? state.currency + ' ' + parseFloat(unitData.rent).toFixed(2) : '—');
            $('#comm_min_label').text(unitData.min_sq_ft || '—');
            $('#commercial_availability_info').removeClass('d-none');
        }

        // Rent is auto-calculated for commercial
        $('#movement_new_rent').prop('readonly', true).val('');
        $('#rent_auto_note').removeClass('d-none');

        bindCommercialCalc();
    }

    function bindCommercialCalc() {
        function recalc() {
            var sqft  = parseFloat($('#movement_occupied_sqft').val())          || 0;
            var rate  = parseFloat($('#movement_rate_per_sqft').val())          || 0;
            var sc    = parseFloat($('#movement_service_charge_per_sqft').val()) || 0;
            var total = sqft * (rate + sc);

            $('#calc_sqft').text(sqft);
            $('#calc_rate').text(rate.toFixed(2));
            $('#calc_sc').text(sc.toFixed(2));
            $('#calc_total_rent').text(total.toFixed(2));
            $('#rent_currency_calc_label').text(state.currency);

            if (total > 0) {
                $('#commercial_rent_calc_banner').removeClass('d-none');
                $('#movement_new_rent').val(total.toFixed(2));
            } else {
                $('#commercial_rent_calc_banner').addClass('d-none');
                $('#movement_new_rent').val('');
            }
        }

        $('#movement_occupied_sqft, #movement_rate_per_sqft, #movement_service_charge_per_sqft')
            .off('input.commCalc').on('input.commCalc', recalc);
    }

    function setDefaultTransferDate() {
        var moveType = $('#movement_move_type').val() || 'end_of_month';
        var today    = new Date();

        if (moveType === 'end_of_month') {
            // 1st of next month
            var firstNext = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            $('#movement_transfer_date').val(formatDate(firstNext));
        } else {
            // Today
            $('#movement_transfer_date').val(formatDate(today));
        }
    }

    function prefillLeaseTerms(leaseData, monthlyPayment, currency) {
        if (leaseData.lease_start_date) $('#lease_start_date').val(leaseData.lease_start_date);
        if (leaseData.lease_end_date)   $('#lease_end_date').val(leaseData.lease_end_date);
        if (leaseData.lease_duration_months) $('#lease_duration_months').val(leaseData.lease_duration_months);
        if (leaseData.payment_frequency)     $('#lease_payment_frequency').val(leaseData.payment_frequency);
        if (leaseData.payment_day_of_month)  $('#lease_payment_day').val(leaseData.payment_day_of_month);
        if (leaseData.late_fee_amount)       $('#lease_late_fee').val(leaseData.late_fee_amount);
        if (leaseData.lease_type)            $('#lease_type').val(leaseData.lease_type);
        if (leaseData.special_terms)         $('#lease_special_terms').val(leaseData.special_terms);

        var rent = leaseData.rent_amount || monthlyPayment || 0;
        if (rent) $('#movement_new_rent').val(parseFloat(rent).toFixed(2));

        var deposit = leaseData.security_deposit || 0;
        if (deposit) {
            $('#movement_old_deposit').val(parseFloat(deposit).toFixed(2));
            $('#movement_new_deposit').val(parseFloat(deposit).toFixed(2));
        }
    }

    function applyArrearsUI(arrears) {
        state.arrearsData = arrears;
        var banner = '';

        if (arrears.type === 'block') {
            banner = '<div class="alert alert-danger d-flex align-items-center p-5 mb-6">' +
                     '<i class="fas fa-ban fs-2tx text-danger me-4"></i><div>' +
                     '<h4 class="mb-1 fw-bolder">Transfer Blocked</h4>' +
                     '<div class="fs-7">' + escHtml(arrears.message) + '</div></div></div>';
            // Disable continue button
            $('#movement_next_btn').prop('disabled', true);
        } else if (arrears.type === 'warning') {
            banner = '<div class="alert alert-warning d-flex align-items-center p-5 mb-6">' +
                     '<i class="fas fa-exclamation-triangle fs-2tx text-warning me-4"></i><div>' +
                     '<h4 class="mb-1 fw-bolder">Outstanding Arrears</h4>' +
                     '<div class="fs-7">' + escHtml(arrears.message) + '</div></div></div>';
            $('#arrears_ack_wrapper').removeClass('d-none');
            $('#movement_next_btn').prop('disabled', false);
        } else {
            $('#movement_next_btn').prop('disabled', false);
        }

        $('#arrears_banner').html(banner);
    }

    function setCurrencyLabels(currency) {
        state.currency = currency || 'USD';
        $('#rent_currency_label').text(state.currency);
        $('#deposit_currency_label').text(state.currency);
    }

    function updateDepositBanner() {
        var oldDeposit = parseFloat($('#movement_old_deposit').val()) || 0;
        var newDeposit = parseFloat($('#movement_new_deposit').val()) || 0;
        var diff = newDeposit - oldDeposit;
        var banner = $('#deposit_adjustment_banner');

        if (diff === 0) {
            banner.addClass('d-none');
        } else if (diff > 0) {
            banner.removeClass('d-none alert-success alert-warning').addClass('alert-warning')
                  .html('<i class="fas fa-arrow-up me-2"></i><strong>Tenant top-up required:</strong> ' +
                         state.currency + ' ' + Math.abs(diff).toFixed(2));
        } else {
            banner.removeClass('d-none alert-warning').addClass('alert-success')
                  .html('<i class="fas fa-arrow-down me-2"></i><strong>Refund owed to tenant:</strong> ' +
                         state.currency + ' ' + Math.abs(diff).toFixed(2));
        }
    }

    // ─── Stepper ──────────────────────────────────────────────────────────

    function goToStep(n) {
        if (n < 1 || n > state.totalSteps) return;

        // Hide all steps
        for (var i = 1; i <= state.totalSteps; i++) {
            $('#movement_step_' + i).addClass('d-none');
            $('#step_nav_' + i).removeClass('current completed');
        }

        // Mark previous steps completed
        for (var j = 1; j < n; j++) {
            $('#step_nav_' + j).addClass('completed');
        }

        // Show current step
        $('#movement_step_' + n).removeClass('d-none');
        $('#step_nav_' + n).addClass('current');
        state.currentStep = n;

        // Buttons
        if (n === 1) {
            $('#movement_prev_btn').css('display', 'none !important').attr('style', 'display:none!important');
        } else {
            $('#movement_prev_btn').removeAttr('style').show();
        }

        if (n === state.totalSteps) {
            $('#movement_next_btn').addClass('d-none');
            $('#movement_submit_btn').removeClass('d-none');
            loadPreview();
        } else {
            $('#movement_next_btn').removeClass('d-none');
            $('#movement_submit_btn').addClass('d-none');
        }

        // Step 3 specific: populate parking section
        if (n === 3) {
            populateParkingStep();
        }
    }

    function validateStep(step) {
        var valid = true;

        if (step === 1) {
            // Check tenant if select wrapper visible
            if ($('#tenant_select_wrapper').is(':visible') && !state.tenancyId) {
                $('#tenant_select_error').removeClass('d-none');
                valid = false;
            } else {
                $('#tenant_select_error').addClass('d-none');
            }

            // Check unit
            if (!$('#movement_new_unit').val()) {
                $('#unit_select_error').removeClass('d-none');
                valid = false;
            } else {
                state.newUnitId = $('#movement_new_unit').val();
                $('#unit_select_error').addClass('d-none');
            }

            // Residential extra validation
            if (state.unitType === 'residential' && state.newUnitId) {
                if (!$('#movement_furnishing').val()) {
                    toastError('Please select a furnishing option.');
                    valid = false;
                }
            }

            // Commercial extra validation
            if (state.unitType === 'commercial' && state.newUnitId) {
                var sqftVal = parseFloat($('#movement_occupied_sqft').val()) || 0;
                var rateVal = parseFloat($('#movement_rate_per_sqft').val()) || 0;
                if (sqftVal <= 0) {
                    $('#commercial_fields_error').removeClass('d-none').text('Please enter the occupied area (sq ft).');
                    valid = false;
                } else if (rateVal <= 0) {
                    $('#commercial_fields_error').removeClass('d-none').text('Please enter the rate per sq ft.');
                    valid = false;
                } else {
                    $('#commercial_fields_error').addClass('d-none');
                }
            }

            // Check date
            if (!$('#movement_transfer_date').val()) {
                $('#date_error').removeClass('d-none');
                valid = false;
            } else {
                $('#date_error').addClass('d-none');
            }

            // Check arrears ack
            if (state.arrearsData && state.arrearsData.type === 'block') {
                toastError('Transfer is blocked due to arrears. Please resolve arrears first.');
                return false;
            }
            if (state.arrearsData && state.arrearsData.type === 'warning' && !$('#arrears_acknowledged').is(':checked')) {
                $('#arrears_ack_error').removeClass('d-none');
                valid = false;
            } else {
                $('#arrears_ack_error').addClass('d-none');
            }
        }

        if (step === 2) {
            if (!$('#movement_new_rent').val() || parseFloat($('#movement_new_rent').val()) < 0) {
                $('#rent_error').removeClass('d-none');
                valid = false;
            } else {
                $('#rent_error').addClass('d-none');
            }
        }

        if (step === 3) {
            // If parking replace action selected, a new slot must be chosen
            if ($('input[name="parking_action"]:checked').val() === 'replace') {
                if (!$('#movement_new_parking_slot').val()) {
                    $('#parking_slot_error').removeClass('d-none');
                    valid = false;
                } else {
                    $('#parking_slot_error').addClass('d-none');
                }
            }
        }

        return valid;
    }

    function populateParkingStep() {
        var pd = window.currentPageData || {};
        var hasParking = state.hasParking || pd.has_parking;

        if (hasParking) {
            var label = (pd.parking_slot && pd.parking_slot.label) ? pd.parking_slot.label : 'Current slot';
            $('#current_slot_label').text(label);
            $('#parking_section').removeClass('d-none');
        } else {
            $('#parking_section').addClass('d-none');
        }

        // Set deposit old value
        var pd2 = window.currentPageData || {};
        var oldDeposit = (pd2.lease_data && pd2.lease_data.security_deposit) || 0;
        if (!$('#movement_old_deposit').val() && oldDeposit) {
            $('#movement_old_deposit').val(parseFloat(oldDeposit).toFixed(2));
            if (!$('#movement_new_deposit').val()) {
                $('#movement_new_deposit').val(parseFloat(oldDeposit).toFixed(2));
            }
        }

        updateDepositBanner();
    }

    // ─── Preview ──────────────────────────────────────────────────────────

    function loadPreview() {
        $('#preview_loading').removeClass('d-none');
        $('#preview_content').addClass('d-none');
        $('#preview_error').addClass('d-none');

        var data = buildSubmitPayload();
        data._token = getToken();

        $.post(BASE + '/preview', data, function (res) {
            $('#preview_loading').addClass('d-none');
            if (res.status === 200 && res.data) {
                renderPreview(res.data);
                $('#preview_content').removeClass('d-none');
            } else {
                $('#preview_error').removeClass('d-none');
            }
        }).fail(function () {
            $('#preview_loading').addClass('d-none');
            $('#preview_error').removeClass('d-none');
        });
    }

    function renderPreview(d) {
        $('#preview_tenant_name').text(d.tenant_name || '—');
        $('#preview_move_type').text(d.move_type === 'end_of_month' ? 'End of Month' : 'Mid Month');
        $('#preview_from_unit').text(d.old_unit || '—');
        $('#preview_to_unit').text(d.new_unit || '—');
        $('#preview_transfer_date').text(d.transfer_date || '—');
        $('#preview_rent_change').html(
            '<span class="text-danger me-2">' + (d.currency || '') + ' ' + parseFloat(d.old_rent || 0).toFixed(2) + '</span>' +
            '<i class="fas fa-arrow-right mx-1"></i>' +
            '<span class="text-success">' + (d.currency || '') + ' ' + parseFloat(d.new_rent || 0).toFixed(2) + '</span>'
        );

        // Financial rows
        var fin = '';
        if (d.deposit && d.deposit.type !== 'none') {
            fin += '<div class="d-flex justify-content-between py-2 border-bottom fs-7">' +
                   '<span>Deposit Adjustment</span>' +
                   '<span class="fw-bolder ' + (d.deposit.type === 'top_up' ? 'text-danger' : 'text-success') + '">' +
                   (d.deposit.type === 'top_up' ? '+' : '-') + (d.currency || '') + ' ' + parseFloat(d.deposit.adjustment || 0).toFixed(2) +
                   '</span></div>';
        }
        if (d.proration && d.proration.net !== 0) {
            fin += '<div class="d-flex justify-content-between py-2 border-bottom fs-7">' +
                   '<span>Proration (' + d.move_type.replace('_', ' ') + ')</span>' +
                   '<span class="fw-bolder ' + (d.proration.net > 0 ? 'text-danger' : 'text-success') + '">' +
                   (d.proration.net > 0 ? '+' : '') + (d.currency || '') + ' ' + parseFloat(d.proration.net || 0).toFixed(2) +
                   ' (' + d.proration.net_type + ')</span></div>';
        }
        $('#preview_financial_rows').html(fin || '<p class="text-muted fs-7 mb-0">No additional financial adjustments.</p>');

        // Parking
        if (d.parking && d.parking.current_slot) {
            var pk = d.parking;
            var pText = 'Action: <strong>' + escHtml(pk.action) + '</strong>';
            if (pk.action === 'keep') pText += ' — slot remains assigned to new tenancy.';
            if (pk.action === 'release') pText += ' — slot will be released to vacant.';
            if (pk.action === 'replace') pText += ' — old slot released, new slot assigned.';
            $('#preview_parking_text').html(pText);
            $('#preview_parking_card').removeClass('d-none');
        }
    }

    // ─── Submit ───────────────────────────────────────────────────────────

    function submitMovement() {
        if (!validateStep(state.currentStep)) return;

        var btn = $('#movement_submit_btn');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting…');

        var data = buildSubmitPayload();
        data._token = getToken();

        $.post(BASE + '/initiate', data, function (res) {
            if (res.status === 200) {
                toastSuccess(res.message || 'Transfer request submitted.');
                setTimeout(function () {
                    window.location.href = BASE;
                }, 1200);
            } else {
                toastError(res.message || 'Failed to submit transfer.');
                btn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Submit Transfer Request');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Submission failed. Please try again.';
            toastError(msg);
            btn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Submit Transfer Request');
        });
    }

    function buildSubmitPayload() {
        var unitDetails = {};
        if (state.unitType === 'residential') {
            unitDetails = {
                furnishing:  $('#movement_furnishing').val(),
                occupants:   $('#movement_occupants').val(),
                has_pets:    $('input[name="movement_has_pets"]:checked').val(),
                pet_details: $('#movement_pet_details').val(),
            };
        } else if (state.unitType === 'commercial') {
            unitDetails = {
                specific_unit_number:    $('#movement_specific_unit_number').val(),
                occupied_sqft:           $('#movement_occupied_sqft').val(),
                rate_per_sqft:           $('#movement_rate_per_sqft').val(),
                service_charge_per_sqft: $('#movement_service_charge_per_sqft').val(),
            };
        }

        return {
            tenancy_id:          state.tenancyId,
            new_unit_id:         $('#movement_new_unit').val(),
            new_unit_type:       state.unitType,
            new_unit_number:     $('#movement_new_unit option:selected').text().split(' — ')[0].trim(),
            move_type:           $('#movement_move_type').val(),
            transfer_date:       $('#movement_transfer_date').val(),
            new_rent:            $('#movement_new_rent').val(),
            new_deposit:         $('#movement_new_deposit').val(),
            parking_action:      $('input[name="parking_action"]:checked').val() || 'none',
            new_parking_slot_id: $('#movement_new_parking_slot').val() || null,
            arrears_acknowledged:$('#arrears_acknowledged').is(':checked') ? 1 : 0,
            notes:               $('#movement_notes').val(),
            unit_details:        unitDetails,
            lease_data: {
                lease_start_date:      $('#lease_start_date').val(),
                lease_end_date:        $('#lease_end_date').val(),
                lease_duration_months: $('#lease_duration_months').val(),
                rent_amount:           $('#movement_new_rent').val(),
                security_deposit:      $('#movement_new_deposit').val(),
                payment_frequency:     $('#lease_payment_frequency').val(),
                payment_day_of_month:  $('#lease_payment_day').val(),
                late_fee_amount:       $('#lease_late_fee').val(),
                grace_period_days:     0,
                lease_type:            $('#lease_type').val(),
                special_terms:         $('#lease_special_terms').val(),
            },
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  SHOW PAGE
    // ═══════════════════════════════════════════════════════════════════════

    function initShowPage() {
        // Approve
        $(document).on('click', '#btn_approve_movement', function () {
            var ref = $(this).data('ref');
            approveMovement(ref);
        });

        // Cancel → open modal
        $(document).on('click', '#btn_cancel_movement', function () {
            $('#cancelMovementModal').modal('show');
        });

        // Modal confirm cancel
        $(document).on('click', '#confirm_cancel_btn', function () {
            var ref    = $('#cancel_movement_ref').val();
            var reason = $('#cancel_reason').val();
            cancelMovement(ref, reason);
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  UTILITIES
    // ═══════════════════════════════════════════════════════════════════════

    function getToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function formatDate(d) {
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toastSuccess(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: msg, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
        } else {
            alert(msg);
        }
    }

    function toastError(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: msg, timer: 4000, showConfirmButton: false, toast: true, position: 'top-end' });
        } else {
            alert('Error: ' + msg);
        }
    }

    // ─── Boot ────────────────────────────────────────────────────────────

    $(document).ready(function () {
        init();
    });

    return {
        reload: loadMovementsList,
    };

})(jQuery);
