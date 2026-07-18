@extends('layouts.authenticated')

@section('title', 'Estados del trámite')

@section('content')
    <x-admin.catalog-header title="Estados del trámite" description="Consulta la secuencia de estados configurada. Este catálogo es de solo lectura." />

    <div class="alert alert-info" role="note"><strong>Modo consulta:</strong> los estados no pueden crearse, editarse ni desactivarse desde este módulo.</div>
    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2" method="GET" action="{{ route('admin.statuses.index') }}" role="search">
                <div class="col-md-8 col-lg-6">
                    <label class="form-label" for="buscar-estado">Buscar por código o nombre</label>
                    <input class="form-control" id="buscar-estado" name="buscar" value="{{ $search }}" maxlength="100">
                </div>
                <div class="col-md-auto align-self-end d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    @if ($search)
                        <a class="btn btn-outline-secondary" href="{{ route('admin.statuses.index') }}">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Estados del ciclo de atención</caption>
                <thead><tr><th>Orden</th><th>Código</th><th>Estado</th><th>Disponibilidad</th></tr></thead>
                <tbody>
                    @forelse ($statuses as $status)
                        <tr>
                            <td>{{ $status->sort_order }}</td>
                            <td><code>{{ $status->code }}</code></td>
                            <td><strong>{{ $status->name }}</strong><small class="d-block text-secondary">{{ $status->description }}</small></td>
                            <td><x-catalog-status :active="$status->active" /></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-4" colspan="4">No se encontraron estados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($statuses->hasPages())
            <div class="card-footer bg-white">{{ $statuses->links() }}</div>
        @endif
    </div>
@endsection
