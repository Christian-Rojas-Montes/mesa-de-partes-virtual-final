@extends(auth()->check() ? 'layouts.authenticated' : 'layouts.public')

@section('title', 'Error del sistema')

@section('content')
    <section class="error-page" aria-labelledby="error-title">
        <span class="error-code" aria-hidden="true">500</span>
        <span class="section-eyebrow">Mesa de Partes Virtual</span>
        <h1 id="error-title" class="h2 mt-2">No pudimos completar la operación</h1>
        <p class="text-secondary">Intenta nuevamente en unos minutos. Si el problema continúa, comunícate con el personal responsable.</p>
        <a class="btn btn-primary" href="{{ auth()->check() ? route('dashboard') : route('home') }}">{{ auth()->check() ? 'Volver a mi panel' : 'Volver al inicio' }}</a>
    </section>
@endsection
