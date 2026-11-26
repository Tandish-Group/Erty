@extends('emails.layouts.plain')

@section('content')
    <p style="margin:0 0 16px;font-size:17px;font-weight:bold;">Welcome, {{ $tenantName }}!</p>
    <p style="margin:0 0 14px;">Welcome to <strong>{{ $complexName }}</strong>, managed by <strong>{{ $businessName }}</strong>.</p>
    <p style="margin:0 0 14px;">You will receive your invoices and statements by email. If any documents were shared with you, such as your lease, they are attached to this email.</p>
    <p style="margin:0;">For anything about your tenancy, contact {{ $businessName }}.</p>
@endsection
