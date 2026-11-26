/**
 * The bell panel: unread count on the bell, the list in the side drawer (All / Unread), opening a
 * notification (marks it read and goes to what it is about), "Mark all as read", and a toast for
 * anything new while the page is open (checked every 30 seconds).
 */
(function () {
    'use strict';
    var POLL_MS = 30000;
    var panel, list, bell, countBadge, filter = 'all', known = null;
    var csrf = function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; };

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function setCount(n) {
        if (!bell) return;
        n = Number(n) || 0;
        bell.classList.toggle('has-unread', n > 0);
        bell.setAttribute('aria-label', n > 0 ? 'Notifications, ' + n + ' unread' : 'Notifications');
        if (countBadge) {
            countBadge.textContent = n > 99 ? '99+' : String(n);
            countBadge.hidden = n === 0;
        }
        var head = document.querySelector('[data-notif-unread-label]');
        if (head) head.textContent = n > 0 ? n + ' unread' : 'All caught up';
        var readAll = document.querySelector('[data-notif-read-all]');
        if (readAll) readAll.disabled = n === 0;
    }

    function itemHtml(n) {
        return '<li class="notif-item' + (n.read ? '' : ' is-unread') + '" data-notif-id="' + esc(n.id) + '">' +
            '<a class="notif-link" href="' + esc(n.open_url) + '">' +
            '<span class="notif-icon is-' + esc(n.tone || 'primary') + '" aria-hidden="true"><i class="bi bi-' + esc(n.icon || 'bell') + '"></i></span>' +
            '<span class="notif-text"><strong>' + esc(n.title) + '</strong>' +
            (n.body ? '<span class="notif-body">' + esc(n.body) + '</span>' : '') +
            '<span class="notif-meta">' + esc(n.time || '') + (n.actor ? ' · ' + esc(n.actor) : '') + '</span></span>' +
            (n.read ? '' : '<span class="notif-dot" aria-label="Unread"></span>') +
            '</a>' +
            (n.read ? '' : '<button type="button" class="notif-mark" data-notif-mark="' + esc(n.id) + '" title="Mark as read" aria-label="Mark as read"><i class="bi bi-check2" aria-hidden="true"></i></button>') +
            '</li>';
    }

    function render(items) {
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<li class="notif-empty"><i class="bi bi-bell-slash" aria-hidden="true"></i><strong>' +
                (filter === 'unread' ? 'Nothing unread' : 'No notifications yet') + '</strong><span>' +
                (filter === 'unread' ? 'You have read everything.' : 'Leads, units, repairs, payments and commission that concern you will appear here.') + '</span></li>';
            return;
        }
        list.innerHTML = items.map(itemHtml).join('');
    }

    function load() {
        if (!list) return;
        list.setAttribute('aria-busy', 'true');
        fetch('/notifications?filter=' + filter, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                list.removeAttribute('aria-busy');
                if (!data) return;
                setCount(data.unread);
                render(data.items || []);
            })
            .catch(function () { list.removeAttribute('aria-busy'); });
    }

    function post(url) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .then(function (r) { return r.ok ? r.json() : null; });
    }

    function toast(n) {
        if (!window.toastr) return;
        var tone = { success: 'success', danger: 'error', warning: 'warning' }[n.tone] || 'info';
        toastr.options = Object.assign({}, toastr.options, {
            closeButton: true, newestOnTop: true, progressBar: true, positionClass: 'toastr-bottom-right',
            timeOut: 8000, extendedTimeOut: 3000, escapeHtml: true,
            onclick: function () { window.location.href = n.open_url; }
        });
        toastr[tone](n.body || '', n.title);
    }

    function poll() {
        fetch('/notifications/unread', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (items) {
                if (!Array.isArray(items)) return;
                setCount(items.length);
                var ids = items.map(function (n) { return n.id; });
                if (known !== null) {
                    // Only what arrived since the page loaded: never a pile of old toasts.
                    items.filter(function (n) { return known.indexOf(n.id) === -1; }).slice(0, 3).reverse().forEach(toast);
                    if (panel && panel.classList.contains('drawer-on')) load();
                }
                known = (known || []).concat(ids);
            })
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        panel = document.getElementById('kt_drawer_chat');
        list = document.querySelector('[data-notif-list]');
        bell = document.getElementById('kt_drawer_chat_toggle');
        countBadge = document.querySelector('[data-notif-count]');
        if (!bell) return;

        bell.addEventListener('click', load);
        document.querySelectorAll('[data-notif-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                filter = tab.getAttribute('data-notif-filter');
                document.querySelectorAll('[data-notif-filter]').forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                load();
            });
        });
        var readAll = document.querySelector('[data-notif-read-all]');
        if (readAll) readAll.addEventListener('click', function () {
            post('/notifications/read-all').then(function () { setCount(0); load(); });
        });
        if (list) list.addEventListener('click', function (event) {
            var mark = event.target.closest('[data-notif-mark]');
            if (!mark) return;
            event.preventDefault();
            post('/notifications/' + encodeURIComponent(mark.getAttribute('data-notif-mark')) + '/mark-as-read').then(function (data) {
                if (data) setCount(data.unread);
                load();
            });
        });

        poll();
        setInterval(poll, POLL_MS);
    });
})();
