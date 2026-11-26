@extends('errors.partials.app')

@section('title', 'Sign in needed')
@section('code', 'Error 401')
@section('heading', 'Sign in needed')
@section('message')
    Sign in to see this page.
@endsection
@section('actions')
    <a class="btn-primary" href="{{ url('/login') }}">Sign in</a>
@endsection
