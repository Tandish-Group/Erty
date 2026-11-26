@if(empty($records))
    <p class="text-muted">Nothing recorded in this section yet.</p>
@else
    <dl class="property-records mb-4">
        @foreach($records as $field => $value)
            @continue(in_array($field, ['id', 'complex_id', 'user_id', 'assigned_agent_id', 'complex_unit_id', 'amenity_id', 'created_at', 'updated_at'], true))
            @if(is_array($value))
                <dt class="mt-4">{{ is_numeric($field) ? 'Record '.($field + 1) : ucfirst(str_replace('_', ' ', $field)) }}</dt>
                <dd class="border-start ps-4">@include('pages.business.complex.review-records', ['records' => $value])</dd>
            @else
                <dt>{{ ['ref_code' => 'Property reference', 'principal' => 'Management relationship', 'county_specific' => 'Area / neighbourhood', 'sq_ft' => 'Area (sq ft)', 'unit_count' => 'Planned units', 'unit_subdivision' => 'Subdivision capacity'][$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</dt><dd class="text-muted">{{ $field === 'principal' ? ($value ? 'Owner / principal' : 'Managing on behalf of owner') : ($value === null || $value === '' ? 'Not recorded' : $value) }}</dd>
            @endif
        @endforeach
    </dl>
@endif
