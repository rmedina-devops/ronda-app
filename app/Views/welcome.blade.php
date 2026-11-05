@extends('layout.app')
@section('title')
    {{ $title }}
@endsection
@section('content')
    <h1 class="text-2xl font-bold">{{ $heading }}</h1>
    <p>¡Blade está funcionando correctamente en FlightPHP!</p>
@endsection