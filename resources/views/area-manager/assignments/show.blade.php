@extends('layouts.authenticated')

@section('title', 'Expediente '.$procedureRequest->tracking_code)

@section('content')
    @php($current = $procedureRequest->latestDerivation)
    @php($statusCode = $procedureRequest->status->code)
    <div class="mb-4"><a class="small" href="{{ route('area-manager.assignments.index') }}">← Volver a expedientes</a><h1 class="h2 mt-2"><code class="tracking-code">{{ $procedureRequest->tracking_code }}</code></h1><span class="badge text-bg-primary">{{ $procedureRequest->status->name }}</span></div>

    <div class="row g-4"><div class="col-xl-7">
        <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Expediente</h2><dl class="row user-detail-list mb-0"><dt class="col-sm-4">Solicitante</dt><dd class="col-sm-8">{{ $procedureRequest->user->first_name }} {{ $procedureRequest->user->last_name }}</dd><dt class="col-sm-4">Trámite</dt><dd class="col-sm-8">{{ $procedureRequest->procedureType->name }}</dd><dt class="col-sm-4">Asunto</dt><dd class="col-sm-8">{{ $procedureRequest->subject }}</dd><dt class="col-sm-4">Descripción</dt><dd class="col-sm-8">{{ $procedureRequest->description }}</dd></dl></div></section>

        <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Documentos del expediente</h2><div class="list-group list-group-flush border rounded">@forelse($procedureRequest->documents as $document)<div class="list-group-item d-flex justify-content-between align-items-center gap-2"><div><strong>{{ $document->requirement?->name ?? 'Documento adicional' }}</strong><small class="d-block text-secondary">{{ strtoupper($document->extension) }} · {{ number_format($document->size_bytes / 1024, 1) }} KB</small></div><a class="btn btn-sm btn-outline-primary" href="{{ route('area-manager.assignments.documents.download', [$procedureRequest, $document]) }}">Descargar</a></div>@empty<div class="list-group-item text-secondary">No hay documentos adjuntos.</div>@endforelse</div></div></section>

        <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Acciones de atención</h2><ol class="timeline list-unstyled mb-0">@forelse($procedureRequest->attentionActions as $action)<li class="timeline-item"><p class="mb-1">{{ $action->description }}</p><small>{{ $action->created_at->format('d/m/Y H:i') }} · {{ $action->author->first_name }} {{ $action->author->last_name }}</small></li>@empty<li class="text-secondary">Todavía no se registraron acciones de atención.</li>@endforelse</ol></div></section>

        <section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5">Historial de derivaciones</h2><ol class="timeline list-unstyled mb-0">@foreach($procedureRequest->derivations as $derivation)<li class="timeline-item"><strong>{{ $derivation->destinationArea->name }}</strong><p class="mb-1 text-secondary">{{ $derivation->reason ?: 'Sin motivo adicional.' }}</p><small>{{ $derivation->derived_at->format('d/m/Y H:i') }} · {{ $derivation->received_at ? 'Recibido '.$derivation->received_at->format('d/m/Y H:i') : 'Recepción pendiente' }}</small></li>@endforeach</ol></div></section>
    </div>

    <div class="col-xl-5">
        <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Recepción y atención</h2><p>Área destinataria: <strong>{{ $current->destinationArea->name }}</strong></p>
            @if(!$current->received_at)
                <form method="POST" action="{{ route('area-manager.assignments.receive', [$procedureRequest, $current]) }}">@csrf @method('PATCH')<p class="text-secondary">Confirma que el área recibió este expediente antes de atenderlo.</p><button class="btn btn-primary" type="submit">Registrar recepción</button></form>
            @elseif($statusCode === 'DERIVADO')
                <div class="alert alert-success"><strong>Recepción registrada.</strong><br>{{ $current->received_at->format('d/m/Y H:i') }}</div><form method="POST" action="{{ route('area-manager.assignments.attention.start', $procedureRequest) }}">@csrf @method('PATCH')<button class="btn btn-primary" type="submit">Iniciar atención</button></form>
            @else
                <div class="alert alert-light border mb-0">Recepción registrada el {{ $current->received_at->format('d/m/Y H:i') }}.</div>
            @endif
            @error('action')<div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>@enderror
        </div></section>

        @if($statusCode === 'EN_ATENCION')
            <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Registrar acción</h2><form method="POST" action="{{ route('area-manager.assignments.attention-actions.store', $procedureRequest) }}">@csrf<div class="mb-3"><label class="form-label" for="description">Descripción de la acción</label><textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" maxlength="3000" required>{{ old('description') }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><button class="btn btn-outline-primary" type="submit">Guardar acción</button></form></div></section>

            <section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5">Respuesta final</h2><form method="POST" action="{{ route('area-manager.assignments.response.store', $procedureRequest) }}" enctype="multipart/form-data">@csrf<div class="mb-3"><label class="form-label" for="summary">Respuesta escrita</label><textarea class="form-control @error('summary') is-invalid @enderror" id="summary" name="summary" rows="5" maxlength="5000" required>{{ old('summary') }}</textarea>@error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="mb-3"><label class="form-label" for="document">Documento de respuesta</label><input class="form-control @error('document') is-invalid @enderror" id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required><div class="form-text">PDF, JPG o PNG. Máximo 5 MB.</div>@error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><button class="btn btn-primary" type="submit">Registrar respuesta y marcar atendido</button></form></div></section>
        @elseif($procedureRequest->response)
            <section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5">Respuesta registrada</h2><p>{{ $procedureRequest->response->summary }}</p><p class="small text-secondary">Autor: {{ $procedureRequest->response->author->first_name }} {{ $procedureRequest->response->author->last_name }} · {{ $procedureRequest->response->responded_at->format('d/m/Y H:i') }}</p><a class="btn btn-primary" href="{{ route('area-manager.assignments.response.download', $procedureRequest) }}">Descargar respuesta</a></div></section>
        @endif
    </div></div>
@endsection
