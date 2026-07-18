@extends('layouts.authenticated')

@section('title', 'Derivaciones')

@section('content')
    <div class="mb-4">
        <span class="section-eyebrow">Mesa de Partes</span>
        <h1 class="h2 mt-2 mb-2">Derivaciones</h1>
        <p class="text-secondary mb-0">Expedientes validados y derivaciones que pueden corregirse sin perder su trazabilidad.</p>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="derivation-filters-title">
        <div class="card-body p-4">
            <h2 class="h5" id="derivation-filters-title">Filtros</h2>
            <form method="GET" action="{{ route('front-desk.derivations.index') }}" role="search">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="codigo">Código de seguimiento</label><input class="form-control" id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}"></div>
                    <div class="col-md-4"><label class="form-label" for="tramite">Tipo de trámite</label><select class="form-select" id="tramite" name="tramite"><option value="">Todos</option>@foreach($procedureTypes as $type)<option value="{{ $type->id }}" @selected(($filters['tramite'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="area">Área actual</label><select class="form-select" id="area" name="area"><option value="">Todas</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(($filters['area'] ?? null) == $area->id)>{{ $area->name }}</option>@endforeach</select></div>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-outline-secondary" href="{{ route('front-desk.derivations.index') }}">Limpiar</a></div>
                </div>
            </form>
        </div>
    </section>

    <div class="card border-0 shadow-sm"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Expedientes disponibles para derivación</caption>
            <thead><tr><th>Código</th><th>Trámite</th><th>Estado</th><th>Área actual</th><th class="text-end">Acción</th></tr></thead>
            <tbody>@forelse($procedureRequests as $item)<tr>
                <td><code class="tracking-code">{{ $item->tracking_code }}</code></td>
                <td>{{ $item->procedureType->name }}<small class="d-block text-secondary">{{ $item->subject }}</small></td>
                <td><span class="badge text-bg-primary">{{ $item->status->name }}</span></td>
                <td>{{ $item->latestDerivation?->destinationArea?->name ?? 'Sin derivación previa' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('front-desk.derivations.create', $item) }}">{{ $item->latestDerivation ? 'Corregir derivación' : 'Derivar' }}</a></td>
            </tr>@empty<tr><td colspan="5" class="text-center text-secondary py-5">No hay expedientes con los filtros indicados.</td></tr>@endforelse</tbody>
        </table>
    </div>@if($procedureRequests->hasPages())<div class="card-footer bg-white">{{ $procedureRequests->links() }}</div>@endif</div>
@endsection
