@extends('errors.partials.app')

@section('title', 'Back soon')
@section('code', 'Error 503')
@section('heading', 'Back soon')
@section('message')
    {{ (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : config('app.name') . ' is being updated. Please try again in a few minutes.' }}
@endsection
