{{-- Business details form (Business settings → Edit). Personal details live on My profile. --}}
<form class="form" method="POST" action="{{ action('App\Http\Controllers\Business\Account\AccountsController@update', 'business') }}" data-business-details-form>
    @csrf
    @method('PUT')
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ implode(' ', $errors->all()) }}</div>
        @endif
        <div class="row mb-6">
            <span class="col-lg-4 col-form-label fw-bold fs-6">Business name</span>
            <div class="col-lg-8"><div class="form-control form-control-lg form-control-solid" aria-readonly="true">{{ $user->business_name }}</div><div class="form-text">Contact {{ config('app.name') }} support to change the registered business name.</div></div>
        </div>
        <div class="row mb-6">
            <label class="col-lg-4 col-form-label required fw-bold fs-6" for="business-email">Business email</label>
            <div class="col-lg-8 fv-row"><input type="email" id="business-email" name="business_email" class="form-control form-control-lg form-control-solid" value="{{ old('business_email', $user->business_email) }}" required maxlength="255"></div>
        </div>
        <div class="row mb-6">
            <label class="col-lg-4 col-form-label required fw-bold fs-6" for="business_description">Business description</label>
            <div class="col-lg-8 fv-row"><textarea class="form-control form-control-solid" id="business_description" style="height: 120px;" name="business_description" maxlength="240" required>{{ old('business_description', $user->business_description) }}</textarea></div>
        </div>
        @if($user->account_type == 'agency')
        <div class="row mb-6">
            <label class="col-lg-4 col-form-label required fw-bold fs-6" for="business-site">Business website</label>
            <div class="col-lg-8 fv-row"><input type="url" id="business-site" name="website" class="form-control form-control-lg form-control-solid" placeholder="https://" value="{{ old('website', $user->business_site) }}" required maxlength="255"></div>
        </div>
        @endif
    </div>
    <div class="card-footer d-flex justify-content-end py-6 px-9">
        <button type="submit" class="btn btn-lg btn-primary">Save business details</button>
    </div>
</form>
