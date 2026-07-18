@extends('layouts.authenticated')

@section('title', 'Revisión '.$procedureRequest->tracking_code)

@section('content')
    @php($statusCode = $procedureRequest->status->code)
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div><a class="small" href="{{ route('front-desk.reviews.index') }}">← Volver a la bandeja</a><h1 class="h2 mt-2"><code class="tracking-code">{{ $procedureRequest->tracking_code }}</code></h1><span class="badge text-bg-primary">{{ $procedureRequest->status->name }}</span></div><div class="d-flex gap-2 align-self-start"><a class="btn btn-outline-primary" href="{{ route('history.staff',$procedureRequest) }}">Historial completo</a><a class="btn btn-outline-primary" href="{{ route('communications.show',$procedureRequest) }}">Citas y notificaciones</a></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                <h2 class="h5">Solicitud</h2>
                <dl class="row user-detail-list mb-0">
                    <dt class="col-sm-4">Solicitante</dt><dd class="col-sm-8">{{ $procedureRequest->user->first_name }} {{ $procedureRequest->user->last_name }}</dd>
                    <dt class="col-sm-4">Documento</dt><dd class="col-sm-8">{{ $procedureRequest->user->document_type }} {{ $procedureRequest->user->document_number }}</dd>
                    <dt class="col-sm-4">Trámite</dt><dd class="col-sm-8">{{ $procedureRequest->procedureType->name }}</dd>
                    <dt class="col-sm-4">Asunto</dt><dd class="col-sm-8">{{ $procedureRequest->subject }}</dd>
                    <dt class="col-sm-4">Descripción</dt><dd class="col-sm-8">{{ $procedureRequest->description }}</dd>
                </dl>
            </div></section>

            <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                <h2 class="h5">Documentos</h2>
                <div class="list-group list-group-flush border rounded">@foreach($procedureRequest->documents as $document)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2"><div><strong>{{ $document->requirement?->name ?? ($document->request_correction_id ? 'Documento de subsanación' : 'Documento adicional') }}</strong><small class="d-block text-secondary">{{ strtoupper($document->extension) }} · {{ number_format($document->size_bytes / 1024, 1) }} KB</small></div><a class="btn btn-sm btn-outline-primary" href="{{ route('front-desk.reviews.documents.download', [$procedureRequest, $document]) }}">Descargar</a></div>
                @endforeach</div>
            </div></section>

            <section class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5">Historial de acciones</h2>
                <ol class="timeline list-unstyled mb-0">@foreach($procedureRequest->histories as $history)<li class="timeline-item"><strong>{{ $history->status->name }}</strong><p class="mb-1 text-secondary">{{ $history->description }}</p><small>{{ $history->created_at->format('d/m/Y H:i') }}</small></li>@endforeach</ol>
            </div></section>
        </div>

        <div class="col-xl-5">
            <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                <h2 class="h5">Acciones de revisión</h2>
                @if($statusCode === 'REGISTRADO')
                    <form method="POST" action="{{ route('front-desk.reviews.start', $procedureRequest) }}">@csrf @method('PATCH')<button class="btn btn-primary" type="submit">Iniciar revisión</button></form>
                @elseif($statusCode === 'EN_REVISION')
                    @if($procedureRequest->validated_at)
                        <p class="alert alert-success"><strong>Solicitud validada.</strong> Ya puede derivarse al área responsable.</p>
                        <a class="btn btn-primary" href="{{ route('front-desk.derivations.create', $procedureRequest) }}">Derivar expediente</a>
                    @else
                        <form method="POST" action="{{ route('front-desk.reviews.validate', $procedureRequest) }}">@csrf @method('PATCH')<button class="btn btn-success" type="submit">Validar solicitud</button></form>
                    @endif
                @else
                    <p class="text-secondary mb-0">No hay acciones disponibles para el estado actual.</p>
                @endif
            </div></section>

            @if($statusCode === 'EN_REVISION')
                @if($procedureRequest->procedureType->category?->code === 'CONVALIDACIONES')
                    <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5">Expediente académico o externo</h2><form method="POST" action="{{ route('front-desk.reviews.academic-file.assign', $procedureRequest) }}">@csrf<div class="mb-3"><label class="form-label" for="academic_file_number">Número asignado</label><input class="form-control" id="academic_file_number" name="academic_file_number" value="{{ old('academic_file_number', $procedureRequest->academic_file_number) }}" maxlength="100" required></div><button class="btn btn-outline-primary">Registrar número</button></form></div></section>
                @endif
                <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                    <h2 class="h5">Observar solicitud</h2>
                    <form method="POST" action="{{ route('front-desk.reviews.observe', $procedureRequest) }}">@csrf
                        <div class="mb-3"><label class="form-label" for="description">Motivo obligatorio</label><textarea class="form-control" id="description" name="description" rows="3" required></textarea></div>
                        <div class="mb-3"><label class="form-label" for="correction_instructions">Instrucciones de subsanación</label><textarea class="form-control" id="correction_instructions" name="correction_instructions" rows="3"></textarea></div>
                        <div class="mb-3"><label class="form-label" for="correction_deadline">Plazo opcional</label><input class="form-control" id="correction_deadline" name="correction_deadline" type="datetime-local"></div>
                        <button class="btn btn-warning" type="submit">Registrar observación</button>
                    </form>
                </div></section>

                <section class="card border-0 shadow-sm"><div class="card-body p-4">
                    <h2 class="h5">Rechazar solicitud</h2>
                    <form method="POST" action="{{ route('front-desk.reviews.reject', $procedureRequest) }}">@csrf
                        <div class="mb-3"><label class="form-label" for="reason">Justificación obligatoria</label><textarea class="form-control" id="reason" name="reason" rows="3" required></textarea></div>
                        <button class="btn btn-outline-danger" type="submit">Rechazar solicitud</button>
                    </form>
                </div></section>
            @endif
        </div>
    </div>
@endsection
