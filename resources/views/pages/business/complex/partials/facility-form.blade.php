@php($catalogue = config('property_facilities'))
<form data-facility-form method="POST" action="{{ route('complex.amenities.store', $complex->slug) }}" data-create-url="{{ route('complex.amenities.store', $complex->slug) }}">
    @csrf
    <input type="hidden" name="_method" value="POST">
    <div class="facility-form-message" data-facility-message role="status" hidden></div>
    <div class="facility-form-section">
        <span class="facility-eyebrow">01 · Facility</span>
        <label for="facility-type" class="form-label required">What does the property provide?</label>
        <select class="form-select" id="facility-type" name="amenity_type" required>
            <option value="">Choose a facility or service</option>
            @foreach(collect($catalogue)->groupBy('group', preserveKeys: true) as $group => $types)
                <optgroup label="{{ $group }}">@foreach($types as $key => $type)<option value="{{ $key }}">{{ $type['label'] }}</option>@endforeach</optgroup>
            @endforeach
        </select>
        <p class="form-text">Add one record per service or distinct facility. Only record what this property provides.</p>
    </div>
    <div data-facility-details hidden>
        <div class="facility-form-section">
            <span class="facility-eyebrow">02 · Details</span>
            @foreach($catalogue as $key => $type)
                <fieldset data-facility-fields="{{ $key }}" hidden disabled>
                    <legend class="visually-hidden">{{ $type['label'] }} details</legend>
                    <p class="facility-field-help">{{ $type['help'] }}</p>
                    <div class="row g-4">
                        @foreach($type['fields'] as $name => $field)
                            <div class="{{ count($type['fields']) > 1 && $field['type'] !== 'textarea' ? 'col-sm-6' : 'col-12' }}">
                                <label class="form-label" for="facility-{{ $name }}">{{ $field['label'] }}</label>
                                @if($field['type'] === 'select')
                                    <select class="form-select" name="{{ $name }}" id="facility-{{ $name }}"><option value="">Not specified</option>@foreach($field['options'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                                @elseif($field['type'] === 'textarea')
                                    <textarea class="form-control" name="{{ $name }}" id="facility-{{ $name }}" rows="3" maxlength="5000"></textarea>
                                @else
                                    <input class="form-control" type="{{ $field['type'] }}" name="{{ $name }}" id="facility-{{ $name }}" placeholder="{{ $field['placeholder'] ?? '' }}" @if($field['type'] === 'number') min="{{ $field['min'] }}" max="1000000" step="1" @else maxlength="255" @endif>
                                @endif
                                @if(isset($field['help']))<p class="form-text">{{ $field['help'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        <div class="facility-form-section facility-cost-section">
            <span class="facility-eyebrow">03 · Annual cost <span>Optional</span></span>
            <p class="facility-field-help">Record the annual cost of this facility. Leave it blank if unknown; enter 0 if there is no cost. Tenant service charges are managed separately.</p>
            <div class="row g-4">
                <div class="col-sm-5"><label for="facility-currency" class="form-label">Currency</label><select class="form-select" id="facility-currency" name="amenity_currency"><option value="">Choose currency</option>@foreach(['KES', 'USD', 'GBP', 'EUR', 'JPY', 'DHS'] as $currency)<option value="{{ $currency }}">{{ $currency }}</option>@endforeach</select></div>
                <div class="col-sm-7"><label for="facility-cost" class="form-label">Amount per year</label><input class="form-control" type="number" id="facility-cost" name="annual_cost" min="0" max="9999999999999.99" step="0.01" placeholder="Not recorded"></div>
            </div>
        </div>
    </div>
    <div class="facility-form-footer">
        @if($inModal ?? false)<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>@else<a class="btn btn-light" href="{{ route('complex.show', $complex->slug) }}#amenities">Back to facilities</a>@endif
        <button type="submit" class="btn btn-primary" data-facility-submit>Save facility</button>
    </div>
</form>
