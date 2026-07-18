@extends('layouts.authenticated')

@section('title', 'Bandeja de revisión')

@section('content')
    <div class="mb-4">
        <span class="section-eyebrow">Mesa de Partes</span>
        <h1 class="h2 mt-2 mb-2">Bandeja de revisión</h1>
        <p class="text-secondary mb-0">Solicitudes registradas o actualmente en revisión.</p>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="review-filters-title">
        <div class="card-body p-4">
            <h2 class="h5" id="review-filters-title">Filtros</h2>
            <form method="GET" action="{{ route('front-desk.reviews.index') }}" role="search">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3"><label class="form-label" for="codigo">Código</label><input class="form-control" id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}"></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label" for="fecha">Fecha</label><input class="form-control" id="fecha" name="fecha" type="date" value="{{ $filters['fecha'] ?? '' }}"></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label" for="tramite">Tipo de trámite</label><select class="form-select" id="tramite" name="tramite"><option value="">Todos</option>@foreach($procedureTypes as $type)<option value="{{ $type->id }}" @selected(($filters['tramite'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(($filters['estado'] ?? null) == $status->id)>{{ $status->name }}</option>@endforeach</select></div>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-outline-secondary" href="{{ route('front-desk.reviews.index') }}">Limpiar</a></div>
                </div>
            </form>
        </div>
    </section>

    <div class="card border-0 shadow-sm"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Solicitudes disponibles para revisión</caption>
            <thead><tr><th>Código</th><th>Solicitante</th><th>Trámite</th><th>Estado</th><th>Fecha</th><th class="text-end">Acción</th></tr></thead>
            <tbody>@forelse($procedureRequests as $item)<tr>
                <td><code class="tracking-code">{{ $item->tracking_code }}</code></td>
                <td>{{ $item->user->first_name }} {{ $item->user->last_name }}<small class="d-block text-secondary">{{ $item->user->document_type }} {{ $item->user->document_number }}</small></td>
                <td>{{ $item->procedureType->name }}<small class="d-block text-secondary">{{ $item->subject }} · {{ $item->documents_count }} documento(s)</small></td>
                <td><span class="badge text-bg-primary">{{ $item->status->name }}</span></td>
                <td>{{ $item->submitted_at->format('d/m/Y H:i') }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('front-desk.reviews.show', $item) }}">Revisar</a></td>
            </tr>@empty<tr><td colspan="6" class="text-center text-secondary py-5">No hay solicitudes con los filtros indicados.</td></tr>@endforelse</tbody>
        </table>
    </div>@if($procedureRequests->hasPages())<div class="card-footer bg-white">{{ $procedureRequests->links() }}</div>@endif</div>
@endsection
