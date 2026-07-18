@extends('layouts.authenticated')

@section('title', $title)

@section('content')
    <div class="dashboard-heading mb-4">
        <nav aria-label="Ruta de navegación">
            <ol class="breadcrumb small mb-2"><li class="breadcrumb-item active" aria-current="page">Inicio del panel</li></ol>
        </nav>
        <x-ui.page-title id="dashboard-title" :title="$title" :eyebrow="auth()->user()->role->name" :description="$description" />
    </div>

    <section class="account-status-card card border-0 shadow-sm mb-4" aria-labelledby="account-status-title">
        <div class="card-body p-4"><div class="row align-items-center g-3">
            <div class="col-md">
                <h2 id="account-status-title" class="h5 mb-1">Estado de la cuenta</h2>
                <p class="text-secondary mb-0">Tu sesión está activa y el acceso corresponde al rol asignado.</p>
            </div>
            <div class="col-md-auto"><span class="status-label"><span aria-hidden="true">✓</span> Cuenta activa</span></div>
        </div></div>
    </section>

    <section aria-labelledby="options-title">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <h2 id="options-title" class="h4 mb-0">Opciones del rol</h2>
            <span class="text-secondary small">Las opciones no disponibles se habilitarán en fases posteriores.</span>
        </div>
        <div class="row g-4">
            @foreach ($options as $option)
                <div class="col-md-6 col-xl-4">
                    <x-future-option
                        :title="$option['title']"
                        :description="$option['description']"
                        :href="isset($option['route']) ? route($option['route']) : null"
                    />
                </div>
            @endforeach
        </div>
    </section>
@endsection
