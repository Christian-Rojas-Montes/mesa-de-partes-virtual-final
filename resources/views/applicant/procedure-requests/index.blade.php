@extends('layouts.authenticated')

@section('title', 'Mis trámites')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb mb-2"><li class="breadcrumb-item active" aria-current="page">Mis trámites</li></ol></nav>
            <h1 class="h2 mb-2">Mis trámites</h1>
            <p class="text-secondary mb-0">Consulta el estado y seguimiento de tus solicitudes enviadas.</p>
        </div>
        <a class="btn btn-primary align-self-md-start" href="{{ route('applicant.procedure-requests.create') }}">Registrar solicitud</a>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="filters-title">
        <div class="card-body p-4">
            <h2 class="h5" id="filters-title">Buscar y filtrar</h2>
            <form method="GET" action="{{ route('applicant.procedure-requests.index') }}" role="search">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="codigo">Código de seguimiento</label>
                        <input class="form-control" id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}" maxlength="30" placeholder="MPV-2026-000001">
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected(($filters['estado'] ?? null) == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="tramite">Tipo de trámite</label>
                        <select class="form-select" id="tramite" name="tramite">
                            <option value="">Todos los trámites</option>
                            @foreach ($procedureTypes as $procedureType)
                                <option value="{{ $procedureType->id }}" @selected(($filters['tramite'] ?? null) == $procedureType->id)>{{ $procedureType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label" for="desde">Desde</label>
                        <input class="form-control" id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}">
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label" for="hasta">Hasta</label>
                        <input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                        @if (array_filter($filters, fn ($value) => $value !== null && $value !== ''))
                            <a class="btn btn-outline-secondary" href="{{ route('applicant.procedure-requests.index') }}">Limpiar filtros</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </section>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Solicitudes registradas por el usuario</caption>
                <thead><tr><th>Código</th><th>Trámite y asunto</th><th>Estado actual</th><th>Documentos</th><th>Fecha</th><th class="text-end">Acción</th></tr></thead>
                <tbody>
                    @forelse ($procedureRequests as $procedureRequest)
                        <tr>
                            <td><code class="tracking-code">{{ $procedureRequest->tracking_code }}</code></td>
                            <td><strong>{{ $procedureRequest->procedureType->name }}</strong><small class="d-block text-secondary">{{ $procedureRequest->subject }}</small></td>
                            <td><span class="badge text-bg-primary">{{ $procedureRequest->status->name }}</span></td>
                            <td>{{ $procedureRequest->documents_count }}</td>
                            <td>{{ $procedureRequest->submitted_at->format('d/m/Y H:i') }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('applicant.procedure-requests.show', $procedureRequest) }}">Ver seguimiento</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="6">No se encontraron solicitudes con los filtros indicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($procedureRequests->hasPages())<div class="card-footer bg-white">{{ $procedureRequests->links() }}</div>@endif
    </div>
@endsection
