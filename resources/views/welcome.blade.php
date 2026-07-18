@extends('layouts.public')

@section('title', 'Inicio')

@section('content')
    <div class="container py-3"><a class="btn btn-primary" href="{{ route('catalog.index') }}">Consultar catálogo de trámites</a></div>
    <section class="public-hero py-5 py-lg-6" aria-labelledby="page-title">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="section-eyebrow">IESTP “Pedro P. Díaz”</span>
                <h1 id="page-title" class="display-5 fw-bold mt-3">
                    Sistema Web de Mesa de Partes Virtual
                </h1>
                <p class="lead text-secondary mt-3">
                    Canal institucional para consultar, presentar y dar seguimiento seguro a trámites documentarios.
                </p>

                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    @auth
                        <a class="btn btn-primary btn-lg" href="{{ route('dashboard') }}">Ingresar a mi panel</a>
                    @else
                        <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Crear cuenta de solicitante</a>
                        <a class="btn btn-outline-primary btn-lg" href="{{ route('login') }}">Iniciar sesión</a>
                    @endauth
                </div>
            </div>

            <div class="col-lg-5">
                <div class="institution-card p-4 p-md-5">
                    <img
                        class="institution-card-mark"
                        src="{{ asset('images/logo-pedro-p-diaz.jpg') }}"
                        alt="Logotipo del IESTP Pedro P. Díaz"
                    >
                    <h2 class="h4 mt-4">Instituto de Educación Superior Tecnológico Público “Pedro P. Díaz”</h2>
                    <p class="text-secondary mb-0">
                        Atención documentaria con seguridad, trazabilidad y claridad para la comunidad institucional.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5" aria-labelledby="scope-title">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
            <div>
                <span class="section-eyebrow">Alcance previsto</span>
                <h2 id="scope-title" class="h3 mt-2 mb-0">Una plataforma para cada participante</h2>
            </div>
            <span class="text-secondary">Funciones disponibles progresivamente</span>
        </div>

        <div class="row g-4">
            @foreach ([
                ['Solicitantes', 'Presentación y consulta de trámites desde una cuenta personal.'],
                ['Mesa de Partes', 'Revisión, observación y derivación de expedientes.'],
                ['Áreas responsables', 'Atención de expedientes y emisión de respuestas.'],
            ] as [$audience, $description])
                <div class="col-md-4">
                    <article class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <span class="feature-index" aria-hidden="true">0{{ $loop->iteration }}</span>
                            <h3 class="h5 mt-3">{{ $audience }}</h3>
                            <p class="text-secondary mb-0">{{ $description }}</p>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>
@endsection
