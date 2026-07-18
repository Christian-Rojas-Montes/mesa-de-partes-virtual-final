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
                <form method="POST" action="{{ route('front-desk.derivations.store', $procedureRequest) }}">@csrf
                    <div class="mb-3"><label class="form-label" for="area_id">Área activa</label><select class="form-select @error('area_id') is-invalid @enderror" id="area_id" name="area_id" required><option value="">Selecciona un área</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>{{ $area->name }}</option>@endforeach</select>@error('area_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label class="form-label" for="reason">Motivo opcional</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="4" maxlength="2000">{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @error('action')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <button class="btn btn-primary" type="submit">{{ $procedureRequest->latestDerivation ? 'Registrar nueva derivación' : 'Derivar expediente' }}</button>
                </form>
            </div></section>
        </div>

        <div class="col-xl-7">
            <section class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="h5">Historial de derivaciones</h2>
                <div class="list-group list-group-flush border rounded">
                    @forelse($procedureRequest->derivations as $derivation)
                        <div class="list-group-item"><strong>{{ $derivation->destinationArea->name }}</strong><small class="d-block text-secondary">{{ $derivation->derived_at->format('d/m/Y H:i') }} · Responsable: {{ $derivation->responsible->first_name }} {{ $derivation->responsible->last_name }}</small><p class="mb-1 mt-2">{{ $derivation->reason ?: 'Sin motivo adicional.' }}</p><span class="badge {{ $derivation->received_at ? 'text-bg-success' : 'text-bg-warning' }}">{{ $derivation->received_at ? 'Recibido el '.$derivation->received_at->format('d/m/Y H:i') : 'Recepción pendiente' }}</span></div>
                    @empty
                        <div class="list-group-item text-secondary">Este expediente todavía no tiene derivaciones.</div>
                    @endforelse
                </div>
            </div></section>
        </div>
    </div>
@endsection
