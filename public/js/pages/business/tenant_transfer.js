// Unit transfers: confirm buttons, and on the transfer form show the date and parking fields when needed,
// work out commercial rent, and keep the "What will happen" summary up to date from the server.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    var form = document.querySelector('[data-transfer-form]');
    if (!form) return;
    var currency = form.dataset.currency;
    var money = function (n) {
        return currency + ' ' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var value = function (name) {
        var field = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('[name="' + name + '"]:not([type="radio"])');
        return field ? field.value : '';
    };
    var set = function (key, text) {
        var el = form.querySelector('[data-s="' + key + '"]');
        if (el) el.textContent = text;
    };

    function toggles() {
        var mid = value('move_type') === 'mid_month';
        form.querySelectorAll('[data-mid-only]').forEach(function (el) { el.hidden = !mid; });
        var replace = value('parking_action') === 'replace';
        form.querySelectorAll('[data-replace-only]').forEach(function (el) { el.hidden = !replace; });
        var unit = form.querySelector('[name="new_unit_id"]:checked');
        var note = form.querySelector('[data-free-note]');
        if (note) note.textContent = unit && unit.dataset.freeSqft ? Number(unit.dataset.freeSqft).toLocaleString() + ' sq ft free in this unit.' : '';
    }

    var timer, request = 0;
    function refresh() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var body = new FormData(form);
            var mine = ++request;
            fetch(form.dataset.previewUrl, {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (s) {
                if (!s || mine !== request) return;
                set('date', s.transfer_date_label + (s.immediate ? ' (today: approving moves the tenant at once)' : ''));
                set('rent', money(s.new_rent) + (s.new_rent !== s.old_rent ? ' (was ' + money(s.old_rent) + ')' : ''));
                var row = form.querySelector('[data-s-row="proration"]');
                if (s.proration) {
                    row.hidden = false;
                    var net = s.proration.net;
                    set('proration', net > 0 ? money(net) + ' invoiced for the last ' + s.proration.old_days_unused + ' days of the period'
                        : net < 0 ? money(-net) + ' credited for the last ' + s.proration.old_days_unused + ' days of the period' : 'None');
                } else {
                    row.hidden = true;
                }
                var d = s.deposit;
                set('deposit', d.type === 'top_up' ? money(d.difference) + ' top-up billed (new deposit ' + money(d.new) + ')'
                    : d.type === 'refund' ? money(-d.difference) + ' returned as credit (new deposit ' + money(d.new) + ')'
                    : 'Unchanged at ' + money(d.new));
            }).catch(function () { /* the summary is a guide; the request is checked on submit */ });
        }, 250);
    }

    form.addEventListener('change', function () { toggles(); refresh(); });
    form.addEventListener('input', function (event) { if (event.target.matches('input[type="number"], input[type="date"]')) refresh(); });
    toggles();
    refresh();
});
