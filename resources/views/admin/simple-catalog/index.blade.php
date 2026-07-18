@extends('layouts.authenticated')
@section('title', $title)
@section('content')
<x-admin.catalog-header :title="$title" :description="$description" :create-route="route($routeBase.'.create')" create-label="Crear" />
<div class="card border-0 shadow-sm">
 <div class="card-body border-bottom"><form class="row g-2" method="GET" action="{{ route($routeBase.'.index') }}" role="search"><div class="col-md-8"><label class="form-label" for="buscar">Buscar</label><input class="form-control" id="buscar" name="buscar" value="{{ $search }}" maxlength="100"></div><div class="col-md-auto align-self-end"><button class="btn btn-primary">Buscar</button></div></form></div>
 <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Orden</th><th>Código y nombre</th><th>{{ $countLabel }}</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
 @forelse($items as $item)<tr><td>{{ $item->sort_order ?? '—' }}</td><td><code>{{ $item->code instanceof \BackedEnum ? $item->code->value : $item->code }}</code><strong class="d-block">{{ $item->name }}</strong><small class="text-secondary">{{ $item->description }}</small></td><td>{{ $item->procedure_types_count }}</td><td><x-catalog-status :active="$item->active" /></td><td class="text-end"><div class="d-inline-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route($routeBase.'.edit', $item) }}">Editar</a><form method="POST" action="{{ route($routeBase.'.toggle', $item) }}" data-confirm-submit="¿Confirmas el cambio de estado?"><input type="hidden" name="_token" value="{{ csrf_token() }}">@method('PATCH')<button class="btn btn-sm {{ $item->active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $item->active ? 'Desactivar' : 'Activar' }}</button></form></div></td></tr>
 @empty<tr><td colspan="5" class="text-center py-4">No se encontraron registros.</td></tr>@endforelse
 </tbody></table></div>@if($items->hasPages())<div class="card-footer bg-white">{{ $items->links() }}</div>@endif
</div>
@endsection
