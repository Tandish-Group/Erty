@extends('errors.partials.app')

@section('title', 'No access')
@section('code', 'Error 403')
@section('heading', 'No access')
@section('message')
    {{ (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : 'Your role does not give you access to this page. Ask your account owner if you need it.' }}
@endsection
