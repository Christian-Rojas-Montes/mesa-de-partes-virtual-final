@extends('layouts.authenticated')

@section('title', 'Notificaciones')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div><span class="section-eyebrow">Actividad</span><h1 class="h2 mt-2 mb-2">Notificaciones</h1><p class="text-secondary mb-0">Avisos relacionados con los expedientes a los que tienes acceso.</p></div>
        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}" class="align-self-md-start">@csrf @method('PATCH')<button class="btn btn-outline-primary" type="submit">Marcar todas como leídas</button></form>
        @endif
    </div>

    @if($nextAppointment || $readyPickup)<div class="row g-3 mb-4">@if($nextAppointment)<div class="col-md-6"><div class="alert alert-info h-100 mb-0"><strong>Próxima cita</strong><span class="d-block">{{ $nextAppointment->appointment_date->format('d/m/Y') }} de {{ $nextAppointment->starts_at }} a {{ $nextAppointment->ends_at }} · {{ $nextAppointment->office }}</span></div></div>@endif @if($readyPickup)<div class="col-md-6"><div class="alert alert-success h-100 mb-0"><strong>Documento listo para recoger</strong><span class="d-block">Disponible desde {{ $readyPickup->available_at->format('d/m/Y H:i') }} · {{ $readyPickup->office }}</span></div></div>@endif</div>@endif

    <section class="card border-0 shadow-sm" aria-labelledby="notifications-list-title"><div class="card-body p-0"><h2 class="visually-hidden" id="notifications-list-title">Listado de notificaciones</h2>
        <div class="list-group list-group-flush">
            @forelse($notifications as $notification)
                <article class="list-group-item p-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div><div class="d-flex align-items-center gap-2 mb-2"><strong>{{ $notification->data['tracking_code'] ?? 'Expediente' }}</strong>@if(!$notification->read_at)<span class="badge text-bg-primary">No leída</span>@else<span class="badge text-bg-secondary">Leída</span>@endif</div><p class="mb-1">{{ $notification->data['message'] ?? 'Hay una actualización relacionada con un expediente.' }}</p><time class="small text-secondary" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time></div>
                        <div class="d-flex flex-wrap align-items-start gap-2"><form method="POST" action="{{ route('notifications.open', $notification->id) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Ver expediente</button></form>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">Marcar como leída</button></form>@endif</div>
                    </div>
                </article>
            @empty
                <x-ui.empty-state title="No tienes notificaciones" message="Los avisos de tus trámites aparecerán aquí." />
            @endforelse
        </div>
    </div>@if($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif</section>
@endsection
