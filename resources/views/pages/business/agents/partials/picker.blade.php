{{-- Searchable agent picker. Params: $name, $id, $selected (Agent|null), $query (extra search params, e.g. ['signin' => 1]). --}}
<select name="{{ $name }}" id="{{ $id }}" class="form-select" data-agent-picker data-url="{{ route('agents.search', $query ?? []) }}" data-placeholder="{{ $placeholder ?? 'Search agents by name, email or phone' }}">
    <option value=""></option>
    @if($selected)<option value="{{ $selected->id }}" selected>{{ $selected->full_name }}</option>@endif
</select>
@once
<script>
    // Select2 search over the business's active agents (agents.search, open to every team member).
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn.select2) return;
        document.querySelectorAll('select[data-agent-picker]').forEach(function (select) {
            const modal = select.closest('.modal');
            jQuery(select).select2({
                width: '100%', allowClear: true, placeholder: select.dataset.placeholder,
                dropdownParent: modal ? jQuery(modal) : jQuery(document.body),
                ajax: { url: select.dataset.url, dataType: 'json', delay: 250,
                    data: p => ({ q: p.term || '', page: p.page || 1 }),  // route() already carries the filters
                    processResults: d => ({ results: d.results || [], pagination: { more: !!(d.pagination && d.pagination.more) } }) },
            }).on('change', function () { select.dispatchEvent(new Event('input', { bubbles: true })); });
        });
    });
</script>
@endonce
