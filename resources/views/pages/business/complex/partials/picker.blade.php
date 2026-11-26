{{-- Searchable property picker (server search, never the full list). Params: $name, $id, $selected (Complex|null), $placeholder. --}}
<select name="{{ $name }}" id="{{ $id }}" class="form-select" data-complex-picker data-url="{{ route('complexes.search') }}" data-placeholder="{{ $placeholder ?? 'Search properties' }}">
    <option value=""></option>
    @if(!empty($selected))<option value="{{ $selected->id }}" selected>{{ $selected->title }}</option>@endif
</select>
@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn.select2) return;
        document.querySelectorAll('select[data-complex-picker]').forEach(function (select) {
            const modal = select.closest('.modal');
            jQuery(select).select2({
                width: '100%', allowClear: true, placeholder: select.dataset.placeholder,
                dropdownParent: modal ? jQuery(modal) : jQuery(document.body),
                ajax: { url: select.dataset.url, dataType: 'json', delay: 250,
                    data: p => ({ q: p.term || '', page: p.page || 1 }),
                    processResults: d => ({ results: (d.results || []).map(c => ({ id: c.id, text: c.title || c.text })), pagination: { more: !!(d.pagination && d.pagination.more) } }) },
            });
        });
    });
</script>
@endonce
