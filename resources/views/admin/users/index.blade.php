@extends('layouts.authenticated')

@section('title', 'Usuarios')

@section('content')
    <x-admin.catalog-header
        title="Usuarios"
        description="Administra cuentas, roles, áreas y estado de acceso."
        :create-route="route('admin.users.create')"
        create-label="Crear usuario interno"
    />

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" role="search">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <label class="form-label" for="buscar-usuario">Documento, nombre o correo</label>
                        <input class="form-control" id="buscar-usuario" name="buscar" value="{{ $filters['buscar'] ?? '' }}" maxlength="100">
                    </div>
                    <div class="col-sm-6 col-lg">
                        <label class="form-label" for="filtro-rol">Rol</label>
                        <select class="form-select" id="filtro-rol" name="rol">
                            <option value="">Todos</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(($filters['rol'] ?? null) == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg">
                        <label class="form-label" for="filtro-area">Área</label>
                        <select class="form-select" id="filtro-area" name="area">
                            <option value="">Todas</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" @selected(($filters['area'] ?? null) == $area->id)>{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg">
                        <label class="form-label" for="filtro-estado">Estado</label>
                        <select class="form-select" id="filtro-estado" name="estado">
                            <option value="">Todos</option>
                            <option value="1" @selected(($filters['estado'] ?? null) === '1')>Activos</option>
                            <option value="0" @selected(($filters['estado'] ?? null) === '0')>Inactivos</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                        @if (array_filter($filters, fn ($value) => $value !== null && $value !== ''))
                            <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Limpiar filtros</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Listado de usuarios del sistema</caption>
                <thead><tr><th>Usuario</th><th>Documento</th><th>Rol y área</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><strong>{{ $user->first_name }} {{ $user->last_name }}</strong><small class="d-block text-secondary">{{ $user->email }}</small></td>
                            <td>{{ $user->document_type }} {{ $user->document_number }}</td>
                            <td><span>{{ $user->role->name }}</span><small class="d-block text-secondary">{{ $user->area?->name ?? 'Sin área asignada' }}</small></td>
                            <td><x-catalog-status :active="$user->active" /></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $user) }}">Ver detalle</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="5">No se encontraron usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
