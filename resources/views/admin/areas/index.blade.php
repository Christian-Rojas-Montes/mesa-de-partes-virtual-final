@extends('layouts.authenticated')

@section('title', 'Áreas')

@section('content')
    <x-admin.catalog-header
        title="Áreas"
        description="Gestiona las áreas responsables de la atención de trámites."
        :create-route="route('admin.areas.create')"
        create-label="Crear área"
    />

    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2" method="GET" action="{{ route('admin.areas.index') }}" role="search">
                <div class="col-md-8 col-lg-6">
                    <label class="form-label" for="buscar-area">Buscar por código o nombre</label>
                    <input class="form-control" id="buscar-area" name="buscar" value="{{ $search }}" maxlength="100">
                </div>
                <div class="col-md-auto align-self-end d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    @if ($search)
                        <a class="btn btn-outline-secondary" href="{{ route('admin.areas.index') }}">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Listado de áreas administrativas</caption>
                <thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td><code>{{ $area->code }}</code></td>
                            <td><strong>{{ $area->name }}</strong><small class="d-block text-secondary">{{ $area->description }}</small></td>
                            <td><x-catalog-status :active="$area->active" /></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.areas.edit', $area) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.areas.toggle', $area) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm {{ $area->active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">
                                            {{ $area->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="4">No se encontraron áreas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($areas->hasPages())
            <div class="card-footer bg-white">{{ $areas->links() }}</div>
        @endif
    </div>
@endsection
