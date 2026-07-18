@extends('layouts.authenticated')

@section('title', 'Expedientes asignados')

@section('content')
    <div class="mb-4"><span class="section-eyebrow">Responsable de área</span><h1 class="h2 mt-2 mb-2">Expedientes asignados</h1><p class="text-secondary mb-0">Derivaciones vigentes destinadas a {{ auth()->user()->area->name }}.</p></div>
    <section class="card border-0 shadow-sm mb-4" aria-labelledby="assignment-filters-title"><div class="card-body p-4">
        <h2 class="h5" id="assignment-filters-title">Filtros</h2>
        <form method="GET" action="{{ route('area-manager.assignments.index') }}" role="search"><div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="codigo">Código</label><input class="form-control" id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}"></div>
            <div class="col-md-4"><label class="form-label" for="tramite">Tipo de trámite</label><select class="form-select" id="tramite" name="tramite"><option value="">Todos</option>@foreach($procedureTypes as $type)<option value="{{ $type->id }}" @selected(($filters['tramite'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="recepcion">Recepción</label><select class="form-select" id="recepcion" name="recepcion"><option value="">Todas</option><option value="pendiente" @selected(($filters['recepcion'] ?? null) === 'pendiente')>Pendiente</option><option value="recibido" @selected(($filters['recepcion'] ?? null) === 'recibido')>Recibido</option></select></div>
            <div class="col-md-3"><label class="form-label" for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}"></div>
            <div class="col-md-3"><label class="form-label" for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}"></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-outline-secondary" href="{{ route('area-manager.assignments.index') }}">Limpiar</a></div>
        </div></form>
    </div></section>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Expedientes derivados al área</caption><thead><tr><th>Código</th><th>Trámite</th><th>Derivado</th><th>Recepción</th><th class="text-end">Acción</th></tr></thead>
        <tbody>@forelse($procedureRequests as $item)<tr><td><code class="tracking-code">{{ $item->tracking_code }}</code></td><td>{{ $item->procedureType->name }}<small class="d-block text-secondary">{{ $item->subject }}</small></td><td>{{ $item->latestDerivation->derived_at->format('d/m/Y H:i') }}</td><td><span class="badge {{ $item->latestDerivation->received_at ? 'text-bg-success' : 'text-bg-warning' }}">{{ $item->latestDerivation->received_at ? 'Recibido' : 'Pendiente' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('area-manager.assignments.show', $item) }}">Ver expediente</a></td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-5">No hay expedientes con los filtros indicados.</td></tr>@endforelse</tbody>
    </table></div>@if($procedureRequests->hasPages())<div class="card-footer bg-white">{{ $procedureRequests->links() }}</div>@endif</div>
@endsection
