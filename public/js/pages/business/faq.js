// Help & FAQ: search across every answer, open a question from a link (#id), and highlight the topic in view.
document.addEventListener('DOMContentLoaded', function () {
    var search = document.getElementById('faq-search');
    if (!search) return;

    var items = Array.prototype.slice.call(document.querySelectorAll('[data-faq-item]'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-faq-section]'));
    var topics = Array.prototype.slice.call(document.querySelectorAll('[data-faq-topic]'));
    var countEl = document.querySelector('[data-faq-count]');
    var emptyEl = document.querySelector('[data-faq-empty]');

    // Keep each question's original HTML so highlighting can be undone.
    items.forEach(function (item) {
        var q = item.querySelector('.faq-question');
        item._question = q.innerHTML;
        item._text = (q.textContent + ' ' + item.querySelector('.faq-answer').textContent).toLowerCase();
    });

    function escapeRegExp(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    function highlight(item, words) {
        var q = item.querySelector('.faq-question');
        q.innerHTML = item._question;
        if (!words.length) return;
        var pattern = new RegExp('(' + words.map(escapeRegExp).join('|') + ')', 'gi');
        q.innerHTML = q.textContent.replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        }).replace(pattern, '<mark>$1</mark>');
    }

    function applySearch() {
        var words = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        var shown = 0;

        items.forEach(function (item) {
            var hit = words.every(function (w) { return item._text.indexOf(w) !== -1; });
            item.hidden = !hit;
            item.classList.toggle('is-hit', hit && words.length > 0);
            if (hit) shown++;
            highlight(item, hit ? words : []);
            // Search opens the matches; clearing it closes only what the search opened.
            if (words.length) {
                item.open = hit;
                item._bySearch = hit;
            } else if (item._bySearch) {
                item.open = false;
                item._bySearch = false;
            }
        });

        sections.forEach(function (section) {
            var visible = section.querySelectorAll('[data-faq-item]:not([hidden])').length;
            section.hidden = visible === 0;
            var topic = document.querySelector('[data-faq-topic="' + section.id + '"]');
            if (topic) {
                topic.classList.toggle('is-empty', visible === 0);
                topic.querySelector('[data-faq-topic-count]').textContent = visible;
            }
        });

        if (countEl) countEl.textContent = words.length ? shown + (shown === 1 ? ' answer' : ' answers') : '';
        if (emptyEl) emptyEl.hidden = shown > 0;
    }

    var timer;
    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(applySearch, 120);
    });
    search.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { search.value = ''; applySearch(); }
    });

    // Open the question named in the address (#question-id) and bring it into view.
    function openFromHash() {
        var id = decodeURIComponent(location.hash.slice(1));
        if (!id) return;
        var target = document.getElementById(id);
        if (!target || !target.matches('[data-faq-item]')) return;
        if (target.hidden) { search.value = ''; applySearch(); }
        target.open = true;
        target._bySearch = false;
        target.classList.remove('is-target');
        void target.offsetWidth; // restart the highlight animation
        target.classList.add('is-target');
        target.scrollIntoView({ block: 'start' });
    }
    window.addEventListener('hashchange', openFromHash);
    openFromHash();

    // "Link to this answer": copy the address of the question.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-faq-copy]');
        if (!link) return;
        e.preventDefault();
        var url = location.origin + location.pathname + link.getAttribute('href');
        history.replaceState(null, '', link.getAttribute('href'));
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                var original = link.innerHTML;
                link.textContent = 'Link copied';
                setTimeout(function () { link.innerHTML = original; }, 1500);
            }).catch(function () {});
        }
    });

    // Highlight the topic whose section is on screen.
    function setActive(key) {
        topics.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-faq-topic') === key); });
    }
    if (sections.length) setActive(sections[0].id);
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) setActive(entry.target.id);
            });
        }, { rootMargin: '-90px 0px -65% 0px' });
        sections.forEach(function (s) { observer.observe(s); });
    }
    topics.forEach(function (t) {
        t.addEventListener('click', function () { setActive(t.getAttribute('data-faq-topic')); });
    });
});
