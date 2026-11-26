<!--begin::Javascript-->
<!--begin::Global Javascript Bundle(used by all pages)-->
<script src="{{ app('asset_versioned')('include/dashboard/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ app('asset_versioned')('include/dashboard/js/scripts.bundle.js') }}"></script>

<!--end::Global Javascript Bundle-->

{{-- There is no page-view analytics: its request on every click raced the one-time-code check and
     could overwrite the new signed-in session, so it was removed. --}}

<script src="{{ app('asset_versioned')('js/loader.js') }}"></script>

<script>
    // Force reload when page is restored from bfcache — ensures unauthenticated users
    // can't see authenticated pages via mobile back button
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

@if(request()->is('login*'))
 


    <!--begin::Page Custom Javascript(used by this page)-->
    <script src="{{ app('asset_versioned')('include/dashboard/js/custom/authentication/sign-in/general.js') }}"></script>

    
    <!--end::Page Custom Javascript-->
    @if ($errors->has('email'))
        <script type="text/javascript">
            Swal.fire({
                text: '{{ $errors->first('email') }}',
                icon: "error",
                buttonsStyling: !1,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            })
        </script>
    @endif

@endif

@if(request()->is('password/reset'))

<script src="{{ app('asset_versioned')('include/dashboard/js/custom/authentication/password-reset/password-reset.js') }}"></script>

@endif

@if(request()->is('password/reset/*'))

<script src="{{ app('asset_versioned')('include/dashboard/js/custom/authentication/password-reset/new-password.js') }}"></script>

@endif

@if(request()->is('auth*'))

{{-- The sign-in code step; the pending sign-in is kept in the session, nothing about the user is in the page script. --}}
<script src="{{ app('asset_versioned')('js/pages/auth/auth.js') }}"></script>

@endif


