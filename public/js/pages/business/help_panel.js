/**
 * Help Panel & Guided Tour
 * Available on all business pages via scripts.blade.php.
 * Provides the slide-out glossary panel and spotlight tour.
 */

/* =====================================================================
   GUIDED SPOTLIGHT TOUR
   ===================================================================== */

const _allTourSteps = [
    {
        selector: '[data-tour-nav="new-complex"]',
        title: 'Property portfolio',
        description: 'Your buildings and estates, their unit types and every unit inside them. Set up a property here first: tenants, billing and maintenance all build on it.',
    },
    {
        // Shown only when the Listings module is on (the button is not rendered otherwise).
        selector: '[data-tour-nav="new-listing"]',
        title: 'Listings — Advertise Vacant Units',
        description: 'Each property has one listing per unit type in each property, for example "2-bedroom" and "4-bedroom". Add rent, deposit, a description and photos, then publish. The number of vacant units is counted for you.',
    },
    {
        selector: '[data-tour-nav="dashboard"]',
        title: 'Home — Your Overview',
        description: 'Your properties, occupancy, rent due and collected, and the follow-ups that need attention today, with shortcuts to the most common tasks.',
    },
    {
        selector: '[data-tour-nav="crm"]',
        title: 'CRM — Leads & Follow-ups',
        description: 'Track every prospective tenant from first contact to move-in. Capture and qualify leads, assign agents, and manage follow-up tasks.',
    },
    {
        selector: '[data-tour-nav="agents"]',
        title: 'Agents — Tasks & Commissions',
        description: 'Your agents, the tasks assigned to them, and the commission they earn on rent collected.',
    },
    {
        selector: '[data-tour-nav="tenants"]',
        title: 'Tenants — Tenancies & Rent',
        description: 'Tenancies, move-ins and move-outs, rent bills and payments, statements, and documents for each tenant.',
    },
    {
        selector: '[data-tour-nav="maintenance"]',
        title: 'Maintenance — Repairs & Requests',
        description: 'Log repair requests for units and common areas, assign who handles them, and record what they cost.',
    },
    {
        selector: '[data-tour-nav="legal"]',
        title: 'Legal — Contracts & Documents',
        description: 'Keep lease agreements, contracts and compliance documents in one secure, searchable place.',
    },
];

// Optional modules (Listings, Sales) and permissions may hide some targets: only tour what is on the page.
let _tourSteps         = _allTourSteps;
let _currentTourStep   = 0;
let _tourOverlay       = null;
let _tourCard          = null;
let _tourSpotlight     = null;  // body-level spotlight, escapes all stacking contexts
let _tourOpenedAside   = false;
let _savedHeaderZIndex = null;  // no longer used but kept in case of future need

function startGuidedTour() {
    closeHelpPanel();
    _tourSteps = _allTourSteps.filter(function (step) { return document.querySelector(step.selector); });
    if (!_tourSteps.length) return;
    _currentTourStep = 0;
    _buildTourShell();
    _renderTourStep(_currentTourStep);
}

function _openAsideForTour(callback) {
    var aside = document.getElementById('kt_aside');
    if (!aside) { callback(); return; }

    // Check if the drawer is already open
    var alreadyOpen = aside.classList.contains('drawer-on');
    if (alreadyOpen) {
        _tourOpenedAside = false;
        callback();
        return;
    }

    _tourOpenedAside = true;

    // KTDrawer uses KTEventHandler (its own event system), NOT native DOM events.
    // addEventListener('kt.drawer.shown') will never fire — must use KTEventHandler.one().
    // NOTE: kt.drawer.shown fires at the START of the CSS transition (not the end).
    // The drawer has a 300ms ease-in-out transition, so we wait 320ms after the event
    // before calling back to ensure getBoundingClientRect() returns final coordinates.
    if (window.KTDrawer && window.KTEventHandler) {
        var instance = KTDrawer.getInstance(aside);
        if (instance) {
            var _fired = false;
            var _safetyTimer = setTimeout(function () {
                if (!_fired) { _fired = true; callback(); }
            }, 900);

            KTEventHandler.one(aside, 'kt.drawer.shown', function () {
                if (!_fired) {
                    _fired = true;
                    clearTimeout(_safetyTimer);
                    // Wait for the 300ms CSS slide-in to finish, then a rAF paint frame
                    setTimeout(function () {
                        requestAnimationFrame(callback);
                    }, 320);
                }
            });
            instance.show();
            return;
        }
    }

    // Fallback: click the toggle and wait for the full transition
    var toggle = document.getElementById('kt_aside_toggle');
    if (toggle) toggle.click();
    setTimeout(callback, 420);
}

function _buildTourShell() {
    _destroyTour();

    // Full-screen dim overlay — no background, just catches nothing; the spotlight
    // div provides the actual dimming via its box-shadow.
    _tourOverlay = document.createElement('div');
    _tourOverlay.id = 'kt_tour_overlay';
    _tourOverlay.style.cssText = 'position:fixed;inset:0;z-index:9990;pointer-events:none;';
    document.body.appendChild(_tourOverlay);

    // Spotlight: a body-level fixed div that sits above ALL stacking contexts.
    // Its transparent background lets the target element show through;
    // the box-shadow dims everything outside it.
    _tourSpotlight = document.createElement('div');
    _tourSpotlight.id = 'kt_tour_spotlight';
    _tourSpotlight.style.cssText = 'position:fixed;z-index:9995;pointer-events:none;' +
        'background:transparent;border-radius:8px;transition:top .2s,left .2s,width .2s,height .2s;';
    document.body.appendChild(_tourSpotlight);

    _tourCard = document.createElement('div');
    _tourCard.id = 'kt_tour_card';
    _tourCard.style.cssText = 'position:fixed;z-index:9999;background:#fff;border-radius:12px;' +
        'box-shadow:0 8px 40px rgba(0,0,0,.22);padding:22px 24px;width:300px;max-width:90vw;';
    document.body.appendChild(_tourCard);
}

function _renderTourStep(index) {
    const step = _tourSteps[index];

    // On mobile, lazily open the aside only when we hit a step that targets an element inside it
    var aside = document.getElementById('kt_aside');
    if (aside && window.innerWidth < 992) {
        var target = document.querySelector(step.selector);
        var needsAside = target ? aside.contains(target) : false;
        var asideOpen  = aside.classList.contains('drawer-on');

        if (needsAside && !asideOpen) {
            // Open aside first, then re-call this step
            _openAsideForTour(function () { _renderTourStep(index); });
            return;
        }
        if (!needsAside && asideOpen && _tourOpenedAside) {
            // We opened it — close it again for topbar-only steps
            _tourOpenedAside = false;
            if (window.KTDrawer) {
                var inst = KTDrawer.getInstance(aside);
                if (inst) inst.hide();
            } else {
                var tog = document.getElementById('kt_aside_toggle');
                if (tog) tog.click();
            }
        }
    }

    const el = document.querySelector(step.selector);

    // Clear previous element-level styles (legacy cleanup, spotlight div now handles visuals)
    document.querySelectorAll('.__tour_highlight').forEach(function(e) {
        e.classList.remove('__tour_highlight');
        e.style.removeProperty('position');
        e.style.removeProperty('z-index');
        e.style.removeProperty('box-shadow');
        e.style.removeProperty('border-radius');
    });

    // Position the body-level spotlight div over the target element.
    // Because it lives directly in <body> (not inside any stacking context),
    // its box-shadow reliably dims the entire page regardless of the target's context.
    var pad = 6;
    if (el && _tourSpotlight) {
        var r = el.getBoundingClientRect();
        var cs = window.getComputedStyle(el);
        // Snap immediately (no transition) so the spotlight doesn't animate from
        // the previous step's position — especially important after the aside opens
        _tourSpotlight.style.transition = 'none';
        _tourSpotlight.style.top    = (r.top    - pad) + 'px';
        _tourSpotlight.style.left   = (r.left   - pad) + 'px';
        _tourSpotlight.style.width  = (r.width  + pad * 2) + 'px';
        _tourSpotlight.style.height = (r.height + pad * 2) + 'px';
        _tourSpotlight.style.borderRadius = '10px';
        _tourSpotlight.style.boxShadow = '0 0 0 9999px rgba(0,0,0,0.55), 0 0 0 2px #009ef7';
        // Re-enable smooth transition for manual next/prev navigation
        requestAnimationFrame(function () {
            if (_tourSpotlight) _tourSpotlight.style.transition = 'top .2s, left .2s, width .2s, height .2s';
        });
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (_tourSpotlight) {
        _tourSpotlight.style.boxShadow = '0 0 0 9999px rgba(0,0,0,0.55)';
        _tourSpotlight.style.top = '-9999px';
    }

    const isFirst = index === 0;
    const isLast  = index === _tourSteps.length - 1;

    _tourCard.innerHTML =
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">' +
            '<span style="font-size:11px;color:#b5b5c3;font-weight:600;letter-spacing:.06em;">STEP ' + (index + 1) + ' / ' + _tourSteps.length + '</span>' +
            '<button onclick="endTour()" style="background:none;border:none;cursor:pointer;color:#b5b5c3;font-size:18px;line-height:1;padding:0;" title="Skip tour">&times;</button>' +
        '</div>' +
        '<h6 style="font-weight:700;margin-bottom:8px;color:#181c32;">' + step.title + '</h6>' +
        '<p style="font-size:13px;color:#5e6278;line-height:1.6;margin-bottom:16px;">' + step.description + '</p>' +
        '<div style="display:flex;gap:8px;">' +
            (!isFirst ? '<button onclick="_prevTourStep()" class="btn btn-sm btn-light" style="flex:1;">&larr; Back</button>' : '') +
            '<button onclick="' + (isLast ? 'endTour()' : '_nextTourStep()') + '" class="btn btn-sm btn-primary" style="flex:2;">' +
                (isLast ? '&#10003; Done' : 'Next &rarr;') +
            '</button>' +
        '</div>';

    // On mobile: pin card to bottom of screen (aside drawer covers most of the viewport)
    var isMobile = window.innerWidth < 992;
    if (isMobile) {
        _tourCard.style.bottom    = '16px';
        _tourCard.style.top       = '';
        _tourCard.style.left      = '50%';
        _tourCard.style.transform = 'translateX(-50%)';
        _tourCard.style.width     = (window.innerWidth - 32) + 'px';
        _tourCard.style.maxWidth  = '400px';
        return;
    }

    // Desktop: position card to the right of the element (side nav), fall back to below
    if (el) {
        var rect   = el.getBoundingClientRect();
        var cardW  = 300;
        var cardH  = 180;
        var spaceRight = window.innerWidth - rect.right;
        var top, left;

        if (spaceRight >= cardW + 20) {
            // Place to the right
            left = rect.right + 16;
            top  = rect.top + (rect.height / 2) - (cardH / 2);
        } else {
            // Fall back: place below
            left = rect.left + (rect.width / 2) - (cardW / 2);
            top  = rect.bottom + 14;
            if (top + cardH > window.innerHeight - 20) top = rect.top - cardH - 14;
        }

        if (top < 10) top = 10;
        if (top + cardH > window.innerHeight - 10) top = window.innerHeight - cardH - 10;
        if (left < 10) left = 10;
        if (left + cardW > window.innerWidth - 10) left = window.innerWidth - cardW - 10;

        _tourCard.style.top       = top + 'px';
        _tourCard.style.bottom    = '';
        _tourCard.style.left      = left + 'px';
        _tourCard.style.transform = '';
    } else {
        _tourCard.style.top       = '50%';
        _tourCard.style.bottom    = '';
        _tourCard.style.left      = '50%';
        _tourCard.style.transform = 'translate(-50%, -50%)';
    }
}

function _nextTourStep() {
    if (_currentTourStep < _tourSteps.length - 1) {
        _currentTourStep++;
        _renderTourStep(_currentTourStep);
    }
}

function _prevTourStep() {
    if (_currentTourStep > 0) {
        _currentTourStep--;
        _renderTourStep(_currentTourStep);
    }
}

function endTour() {
    _destroyTour();

    // If we opened the aside for the tour on mobile, close it again
    if (_tourOpenedAside) {
        _tourOpenedAside = false;
        var aside = document.getElementById('kt_aside');
        if (aside && aside.classList.contains('drawer-on')) {
            if (window.KTDrawer) {
                var instance = KTDrawer.getInstance(aside);
                if (instance) { instance.hide(); }
            } else {
                var toggle = document.getElementById('kt_aside_toggle');
                if (toggle) toggle.click();
            }
        }
    }

    fetch('/business/complete-walkthrough', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || ''
        }
    }).catch(function() {});
}

function _destroyTour() {
    document.querySelectorAll('.__tour_highlight').forEach(function(e) {
        e.classList.remove('__tour_highlight');
        e.style.removeProperty('position');
        e.style.removeProperty('z-index');
        e.style.removeProperty('box-shadow');
        e.style.removeProperty('border-radius');
    });
    if (_tourOverlay)   { _tourOverlay.remove();   _tourOverlay   = null; }
    if (_tourSpotlight) { _tourSpotlight.remove(); _tourSpotlight = null; }
    if (_tourCard)      { _tourCard.remove();      _tourCard      = null; }
}

/* =====================================================================
   HELP PANEL (slide-out glossary)
   ===================================================================== */

function toggleHelpPanel() {
    var panel = document.getElementById('kt_help_panel');
    if (!panel) return;
    var isOpen = panel.style.transform === 'translateX(0px)' || panel.style.transform === 'translateX(0)';
    isOpen ? closeHelpPanel() : openHelpPanel();
}

function openHelpPanel() {
    var panel    = document.getElementById('kt_help_panel');
    var backdrop = document.getElementById('kt_help_backdrop');
    var trigger  = document.getElementById('kt_help_trigger');
    if (panel)    panel.style.transform    = 'translateX(0)';
    if (backdrop) backdrop.style.display   = 'block';
    if (trigger)  { trigger.style.opacity = '0'; trigger.style.pointerEvents = 'none'; }
}

function closeHelpPanel() {
    var panel    = document.getElementById('kt_help_panel');
    var backdrop = document.getElementById('kt_help_backdrop');
    var trigger  = document.getElementById('kt_help_trigger');
    if (panel)    panel.style.transform    = 'translateX(100%)';
    if (backdrop) backdrop.style.display   = 'none';
    if (trigger)  { trigger.style.opacity = '1'; trigger.style.pointerEvents = 'auto'; }
}

function disableHelpAutoOpen() {
    localStorage.setItem('kt_help_panel_no_auto', '1');
    var note = document.getElementById('kt_help_auto_note');
    if (note) {
        note.innerHTML = '<span class="text-success">&#10003; Got it — won\'t open automatically again.</span>';
    }
}

/* Auto-open on dashboard only */
document.addEventListener('DOMContentLoaded', function () {
    var path        = window.location.pathname.replace(/\/+$/, '');
    var isDashboard = path === '/dashboard' || path === '';
    var optedOut    = localStorage.getItem('kt_help_panel_no_auto') === '1';
    if (isDashboard && !optedOut) {
        setTimeout(openHelpPanel, 800);
    }
});
