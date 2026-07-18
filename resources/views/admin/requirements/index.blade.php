@extends('layouts.authenticated')

@section('title', 'Requisitos del trámite')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('admin.procedure-types.index') }}">Tipos de trámite</a></li>
                <li class="breadcrumb-item active" aria-current="page">Requisitos</li>
            </ol></nav>
            <h1 class="h2 mb-2">Requisitos de {{ $procedureType->name }}</h1>
            <p class="text-secondary mb-0">Los requisitos inactivos no se exigirán en solicitudes nuevas.</p>
        </div>
        <a class="btn btn-primary align-self-md-start" href="{{ route('admin.procedure-types.requirements.create', $procedureType) }}">Crear requisito</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Requisitos del tipo de trámite {{ $procedureType->name }}</caption>
                <thead><tr><th>Requisito</th><th>Condición</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td><strong>{{ $requirement->name }}</strong><small class="d-block text-secondary">{{ $requirement->description }}</small><small>{{ $requirement->variant?->name ?? 'General' }} · orden {{ $requirement->sort_order }}</small></td>
                            <td>
                                <span class="badge {{ $requirement->required ? 'text-bg-primary' : 'text-bg-light border text-dark' }}">
                                    {{ $requirement->required ? 'Obligatorio' : 'Opcional' }}
                                </span>
                                <small class="d-block">{{ $requirement->type->value }}{{ $requirement->sensitive ? ' · sensible' : '' }}</small>
                            </td>
                            <td><x-catalog-status :active="$requirement->active" /></td>
                            <td class="text-end"><div class="d-inline-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.procedure-types.requirements.edit', [$procedureType, $requirement]) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.procedure-types.requirements.toggle', [$procedureType, $requirement]) }}" data-confirm-submit="¿Confirmas el cambio de estado?">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $requirement->active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $requirement->active ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="4">Este trámite todavía no tiene requisitos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requirements->hasPages())
            <div class="card-footer bg-white">{{ $requirements->links() }}</div>
        @endif
    </div>
@endsection
