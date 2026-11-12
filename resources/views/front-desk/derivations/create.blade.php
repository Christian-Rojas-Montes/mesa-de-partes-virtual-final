@extends('layouts.authenticated')

@section('title', 'Derivar '.$procedureRequest->tracking_code)

@section('content')
    <div class="mb-4"><a class="small" href="{{ route('front-desk.derivations.index') }}">← Volver a derivaciones</a><h1 class="h2 mt-2">Derivar expediente</h1><code class="tracking-code">{{ $procedureRequest->tracking_code }}</code></div>

    <div class="row g-4">
        <div class="col-xl-5">
            <section class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5">Destino</h2>
                <p class="text-secondary">{{ $procedureRequest->procedureType->name }} · {{ $procedureRequest->subject }}</p>
                @if($procedureRequest->latestDerivation)
                    <div class="alert alert-warning"><strong>Corrección de derivación.</strong> El envío anterior se conservará en el historial.</div>
                @endif
                @if($correctionBlockedByReceipt)
                    <div class="alert alert-info mb-0">El área destinataria registró la recepción el {{ $procedureRequest->latestDerivation->received_at->format('d/m/Y H:i') }}. Desde ese momento ya no se puede corregir el destino.</div>
                @elseif($configuredAreaUnavailable)
                    <div class="alert alert-danger mb-0">El área responsable configurada ({{ $configuredArea->name }}) está inactiva. Solicita a Administración que actualice el catálogo antes de derivar.</div>
                @elseif($alreadyAtConfiguredArea)
                    <div class="alert alert-info mb-0">El expediente ya está dirigido al área responsable configurada y su recepción está pendiente. No se requiere corregir el destino.</div>
                @else
                    <form method="POST" action="{{ route('front-desk.derivations.store', $procedureRequest) }}">@csrf
                        @if($configuredArea)
                            <div class="mb-3">
                                <span class="form-label d-block">Área responsable del trámite</span>
                                <p class="form-control-plaintext mb-0">{{ $configuredArea->name }}</p>
                                <input type="hidden" name="area_id" value="{{ $configuredArea->id }}">
                                <div class="form-text">El destino se define en el catálogo de este trámite.</div>
                            </div>
                        @else
                            <div class="mb-3"><label class="form-label" for="area_id">Área activa</label><select class="form-select @error('area_id') is-invalid @enderror" id="area_id" name="area_id" required><option value="">Selecciona un área</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>{{ $area->name }}</option>@endforeach</select>@error('area_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @endif
                        <div class="mb-3"><label class="form-label" for="reason">Motivo opcional</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="4" maxlength="2000">{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @error('action')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <button class="btn btn-primary" type="submit">{{ $procedureRequest->latestDerivation ? 'Registrar nueva derivación' : 'Derivar expediente' }}</button>
                    </form>
                @endif
            </div></section>
        </div>

        <div class="col-xl-7">
            <section class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5">Historial de derivaciones</h2>
                <div class="list-group list-group-flush border rounded">
                    @forelse($procedureRequest->derivations as $derivation)
                        <div class="list-group-item">
                            <strong>{{ $derivation->originArea?->name ?? 'Mesa de Partes' }} → {{ $derivation->destinationArea->name }}</strong>
                            <small class="d-block text-secondary">{{ $derivation->derived_at->format('d/m/Y H:i') }} · Derivado por: {{ $derivation->responsible->first_name }} {{ $derivation->responsible->last_name }}</small>
                            <p class="mb-1 mt-2">{{ $derivation->reason ?: 'Sin motivo adicional.' }}</p>
                            <span class="badge {{ $derivation->received_at ? 'text-bg-success' : 'text-bg-warning' }}">{{ $derivation->received_at ? 'Recibido el '.$derivation->received_at->format('d/m/Y H:i') : 'Recepción pendiente' }}</span>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">Este expediente todavía no tiene derivaciones.</div>
                    @endforelse
                </div>
            </div></section>
        </div>
    </div>
@endsection
