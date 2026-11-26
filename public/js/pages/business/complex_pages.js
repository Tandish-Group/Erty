// Property pages (/complex/*): open the tab named in the address, or the tab holding the element named in
// it (for example a unit linked from another page), and scroll that element into view.
document.addEventListener('DOMContentLoaded', function () {
    var hashTarget = window.location.hash
        ? document.getElementById(decodeURIComponent(window.location.hash.slice(1)))
        : null;
    var targetPane = hashTarget ? hashTarget.closest('.tab-pane') : null;
    var targetTabHash = targetPane ? '#' + targetPane.id : window.location.hash;
    var initialTab = Array.from(document.querySelectorAll('.nav-tabs .nav-link')).find(function (tab) {
        return tab.getAttribute('href') === targetTabHash;
    });
    if (!initialTab) return;

    if (hashTarget && targetPane) {
        initialTab.addEventListener('shown.bs.tab', function () {
            hashTarget.scrollIntoView({ block: 'center' });
        }, { once: true });
    }
    bootstrap.Tab.getOrCreateInstance(initialTab).show();
});
