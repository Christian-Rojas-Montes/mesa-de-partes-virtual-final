@extends('layouts.authenticated')

@section('title', 'Subsanar observación')

@section('content')
    <div class="mb-4">
        <a class="small" href="{{ route('applicant.procedure-requests.show', $procedureRequest) }}">← Volver al seguimiento</a>
        <h1 class="h2 mt-2">Subsanar observación</h1>
        <p class="text-secondary">Solicitud <code class="tracking-code">{{ $procedureRequest->tracking_code }}</code></p>
    </div>
    <section class="alert alert-warning" aria-labelledby="pending-observation-title">
        <h2 class="h5" id="pending-observation-title">Observación pendiente</h2>
        <p>{{ $observation->description }}</p>
        @if($observation->correction_instructions)<p class="mb-1"><strong>Indicaciones:</strong> {{ $observation->correction_instructions }}</p>@endif
        @if($observation->correction_deadline)<small>Plazo indicado: {{ $observation->correction_deadline->format('d/m/Y H:i') }}</small>@endif
    </section>
    <section class="card border-0 shadow-sm"><div class="card-body p-4">
        <h2 class="h5">Documentos corregidos</h2>
        <p class="text-secondary small">Adjunta entre uno y cinco archivos PDF, JPG o PNG, de máximo 5 MB cada uno. Los documentos anteriores se conservarán.</p>
        <form method="POST" action="{{ route('applicant.procedure-requests.corrections.store', $procedureRequest) }}" enctype="multipart/form-data" data-confirm-submit="¿Confirmas el envío de la subsanación?">
            @csrf
            <input name="observation_id" type="hidden" value="{{ $observation->id }}">
            <div class="mb-4"><label class="form-label" for="message">Mensaje adicional <span class="text-secondary">(opcional)</span></label><textarea class="form-control" id="message" name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea></div>
            @for($index = 1; $index <= 5; $index++)
                <div class="mb-3"><label class="form-label" for="corrected-document-{{ $index }}">Documento corregido {{ $index }} {{ $index === 1 ? '(obligatorio)' : '(opcional)' }}</label><input class="form-control" id="corrected-document-{{ $index }}" name="documents[general{{ $index }}]" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @required($index === 1)></div>
            @endfor
            <button class="btn btn-primary" type="submit">Enviar subsanación</button>
        </form>
    </div></section>
@endsection
