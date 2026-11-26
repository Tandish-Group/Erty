{{-- Session flash messages (redirect()->with('success'|'error'|'warning'|'info', ...)) shown as a toast. --}}
@php
    $flashMessages = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => filled(session($type)))
        ->map(fn ($type) => ['icon' => $type, 'text' => is_string(session($type)) ? session($type) : implode(' ', (array) session($type))])
        ->values();
@endphp
<script>
    // A message carried across a full reload by the live page scripts (see property-portfolio.js).
    (function () {
        var text = null;
        try { text = sessionStorage.getItem('erty_flash'); sessionStorage.removeItem('erty_flash'); } catch (e) {}
        if (!text) return;
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', text: text, showConfirmButton: false, timer: 4500, timerProgressBar: true });
        });
    })();
</script>
@if($flashMessages->isNotEmpty())
<script>
    (function () {
        var messages = @json($flashMessages);
        function show() {
            if (!window.Swal) { messages.forEach(function (m) { console.info(m.text); }); return; }
            messages.reduce(function (chain, m) {
                return chain.then(function () {
                    return Swal.fire({ toast: true, position: 'top-end', icon: m.icon, text: m.text, showConfirmButton: false, timer: m.icon === 'error' ? 7000 : 4500, timerProgressBar: true });
                });
            }, Promise.resolve());
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show); else show();
    })();
</script>
@endif
