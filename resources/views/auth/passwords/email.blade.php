@extends('layouts.app')

@section('title', 'Reset your password | ' . config('app.name'))

@section('content')

    <!--begin::Content-->
    <div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
        <!--begin::Logo-->
        <a href="/login" class="mb-12">
            <img alt="Logo" src="{{asset(config('app.logo'))}}" class="h-60px" />
        </a>
        <!--end::Logo-->
        <!--begin::Wrapper-->
        <div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
            <!--begin::Form-->
            <form class="form w-100" novalidate="novalidate" id="kt_password_reset_form">
                <!--begin::Heading-->
                <div class="text-center mb-10">
                    <!--begin::Title-->
                    <h1 class="text-dark mb-3">Forgot Password ?</h1>
                    <!--end::Title-->
                    <!--begin::Link-->
                    <div class="text-gray-400 fw-bold fs-4">Enter your email to reset your password.</div>
                    <!--end::Link-->
                </div>
                <!--begin::Heading-->
                <!--begin::Input group-->
                <div class="fv-row mb-10">
                    <input class="form-control form-control-solid @error('email') border-red-500 is-invalid @enderror" type="email" name="email" autocomplete="off" placeholder="Enter your linked email address" />
                </div>
                <!--end::Input group-->
                <!--begin::Actions-->
                <div class="d-flex flex-row justify-content-between w-100">
                    <button type="button" id="kt_password_reset_submit" class="btn btn-lg btn-primary fw-bolder me-4">
                        <span class="indicator-label">Send Reset Link</span>
                        <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                    <button class="btn btn-lg btn-info fw-bolder me-4"><a href="/login" class="text-white">Back to Sign-In</a></button>
                </div>
                <!--end::Actions-->
            </form>
            <!--end::Form-->
        </div>
        <!--end::Wrapper-->
    </div>
    <!--end::Content-->
    <!--begin::Footer-->
    <div class="d-flex flex-center flex-column-auto p-10">
        <!--begin::Links-->
        <div class="d-flex align-items-center fw-bold fs-6">
        </div>
        <!--end::Links-->
    </div>
    <!--end::Footer-->


@endsection


