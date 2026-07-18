<?php

namespace App\Http\Controllers;

use App\Models\ProcedureRequest;
use App\Models\RequestAppointment;
use App\Models\RequestPickup;
use App\Services\NotificationDestinationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(15);
        $nextAppointment = RequestAppointment::query()->whereHas('procedureRequest', fn ($q) => $q->where('user_id', $request->user()->id))->whereIn('status', ['scheduled', 'confirmed'])->whereDate('appointment_date', '>=', today())->orderBy('appointment_date')->orderBy('starts_at')->first();
        $readyPickup = RequestPickup::query()->whereHas('procedureRequest', fn ($q) => $q->where('user_id', $request->user()->id))->where('status', 'ready')->latest('available_at')->first();

        return view('notifications.index', compact('notifications', 'nextAppointment', 'readyPickup'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->ownedNotification($request, $notification)->markAsRead();

        return back()->with('status', 'La notificación fue marcada como leída.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Todas las notificaciones fueron marcadas como leídas.');
    }

    public function open(
        Request $request,
        string $notification,
        NotificationDestinationResolver $resolver,
    ): RedirectResponse {
        $owned = $this->ownedNotification($request, $notification);
        $owned->markAsRead();
        $procedureRequest = ProcedureRequest::query()->find($owned->data['procedure_request_id'] ?? null);

        if ($procedureRequest === null) {
            return to_route('notifications.index')->with('status', 'El expediente relacionado ya no está disponible.');
        }

        return redirect()->to($resolver->route($request->user(), $procedureRequest));
    }

    private function ownedNotification(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->findOrFail($id);
    }
}
