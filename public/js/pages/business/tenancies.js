"use strict";

// Tenant Profile - Tenancies Tab Filtering & Search
var TenancyProfileFilter = function() {
    var container;
    var searchInput;
    var filterComplex;
    var filterStatus;
    var filterListingType;
    var resetButton;
    var tenanciesInfo;
    var tenanciesCount;
    var allTenancyItems;

    // Initialize
    var init = function() {
        container = document.getElementById('tenancies_container');
        searchInput = document.getElementById('tenancy_search');
        filterComplex = document.getElementById('filter_complex');
        filterStatus = document.getElementById('filter_status');
        filterListingType = document.getElementById('filter_listing_type');
        resetButton = document.getElementById('reset_tenancy_filters');
        tenanciesInfo = document.getElementById('tenancies_info');
        tenanciesCount = document.getElementById('tenancies_count');

        if (!container) return;

        allTenancyItems = container.querySelectorAll('.tenancy-item');

        // Initialize Select2 for filters
        if (filterComplex) {
            $(filterComplex).select2({
                placeholder: 'All Complexes',
                allowClear: true
            });
        }

        if (filterStatus) {
            $(filterStatus).select2({
                placeholder: 'All Status',
                allowClear: true
            });
        }

        if (filterListingType) {
            $(filterListingType).select2({
                placeholder: 'All Types',
                allowClear: true
            });
        }

        // Event listeners
        if (searchInput) {
            searchInput.addEventListener('keyup', debounce(filterTenancies, 300));
        }

        if (filterComplex) {
            $(filterComplex).on('change', filterTenancies);
        }

        if (filterStatus) {
            $(filterStatus).on('change', filterTenancies);
        }

        if (filterListingType) {
            $(filterListingType).on('change', filterTenancies);
        }

        if (resetButton) {
            resetButton.addEventListener('click', resetFilters);
        }
    };

    // Filter tenancies
    var filterTenancies = function() {
        var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        var complexId = filterComplex ? $(filterComplex).val() : '';
        var status = filterStatus ? $(filterStatus).val() : '';
        var listingType = filterListingType ? $(filterListingType).val() : '';

        var visibleCount = 0;

        allTenancyItems.forEach(function(item) {
            var itemComplexId = item.getAttribute('data-complex-id');
            var itemStatus = item.getAttribute('data-status');
            var itemListingType = item.getAttribute('data-listing-type');
            var itemSearchText = item.getAttribute('data-search-text');

            var matchesSearch = !searchTerm || itemSearchText.includes(searchTerm);
            var matchesComplex = !complexId || itemComplexId === complexId;
            var matchesStatus = !status || itemStatus === status;
            var matchesListingType = !listingType || itemListingType === listingType;

            if (matchesSearch && matchesComplex && matchesStatus && matchesListingType) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Update count
        if (tenanciesCount) {
            tenanciesCount.textContent = visibleCount;
        }

        // Show/hide no results message
        var noResultsDiv = document.getElementById('no_tenancies_found');
        if (visibleCount === 0) {
            if (!noResultsDiv) {
                container.insertAdjacentHTML('beforeend', `
                    <div id="no_tenancies_found" class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-15">
                                <i class="fas fa-search fs-3x text-muted mb-5"></i>
                                <h3 class="text-gray-800 mb-3">No Tenancies Found</h3>
                                <p class="text-gray-600">No tenancies match your current filters.</p>
                            </div>
                        </div>
                    </div>
                `);
            }
        } else {
            if (noResultsDiv) {
                noResultsDiv.remove();
            }
        }
    };

    // Reset filters
    var resetFilters = function() {
        if (searchInput) searchInput.value = '';
        if (filterComplex) $(filterComplex).val('').trigger('change');
        if (filterStatus) $(filterStatus).val('').trigger('change');
        if (filterListingType) $(filterListingType).val('').trigger('change');
        
        filterTenancies();
    };

    // Debounce helper
    var debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    };

    // Public methods
    return {
        init: function() {
            init();
        }
    };
}();

// Blacklist functions
function addToBlacklist(tenantRef) {
    Swal.fire({
        title: 'Blacklist Tenant',
        html: `
            <p class="text-muted mb-4">Please provide a reason for blacklisting this tenant. This action cannot be undone while the tenant has an active tenancy.</p>
            <textarea id="blacklist_reason" class="form-control" rows="4" placeholder="Enter reason for blacklisting (required)..." maxlength="500"></textarea>
            <div id="blacklist_reason_error" class="text-danger mt-2 text-start d-none fs-7"></div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Blacklist Tenant',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const reason = document.getElementById('blacklist_reason').value.trim();
            if (!reason || reason.length < 5) {
                document.getElementById('blacklist_reason_error').textContent = 'A reason of at least 5 characters is required.';
                document.getElementById('blacklist_reason_error').classList.remove('d-none');
                return false;
            }
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/tenant_management/tenant/' + tenantRef + '/blacklist',
                method: 'POST',
                data: {
                    reason: result.value,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1500);
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'An error occurred. Please try again.';
                    toastr.error(message);
                }
            });
        }
    });
}

function removeFromBlacklist(tenantRef) {
    Swal.fire({
        title: 'Remove from Blacklist?',
        text: "Are you sure you want to remove this tenant from the blacklist and restore their status to Active?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/tenant_management/tenant/' + tenantRef + '/unblacklist',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1500);
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'An error occurred. Please try again.';
                    toastr.error(message);
                }
            });
        }
    });
}

// On DOM ready
KTUtil.onDOMContentLoaded(function() {
    TenancyProfileFilter.init();
});
