@extends('layouts.public')

@section('title', 'Página no encontrada')

@section('content')
    <section class="error-page" aria-labelledby="error-title">
        <span class="error-code" aria-hidden="true">404</span>
        <span class="section-eyebrow">Mesa de Partes Virtual</span>
        <h1 id="error-title" class="h2">Página no encontrada</h1>
        <p class="text-secondary">La dirección solicitada no existe o ya no está disponible.</p>
        <a class="btn btn-primary" href="{{ route('home') }}">Volver al inicio</a>
    </section>
@endsection
