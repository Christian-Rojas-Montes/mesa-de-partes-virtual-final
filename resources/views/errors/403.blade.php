@extends(auth()->check() ? 'layouts.authenticated' : 'layouts.public')

@section('title', 'Acceso denegado')

@section('content')
    <section class="error-page" aria-labelledby="error-title">
        <span class="error-code" aria-hidden="true">403</span>
        <span class="section-eyebrow">Mesa de Partes Virtual</span>
        <h1 id="error-title" class="h2">Acceso denegado</h1>
        <p class="text-secondary">Tu cuenta no tiene permiso para acceder a esta sección.</p>
        <a class="btn btn-primary" href="{{ auth()->check() ? route('dashboard') : route('home') }}">
            {{ auth()->check() ? 'Volver a mi panel' : 'Volver al inicio' }}
        </a>
    </section>
@endsection
