@extends('errors.partials.app')

@section('title', 'Too many requests')
@section('code', 'Error 429')
@section('heading', 'Too many requests')
@section('message')
    Please wait a minute and try again.
@endsection
