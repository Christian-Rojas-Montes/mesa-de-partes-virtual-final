<?php

namespace App\Http\Controllers;

use App\Http\Requests\Communications\AppointmentStatusRequest;
use App\Http\Requests\Communications\DeliverPickupRequest;
use App\Http\Requests\Communications\ReadyPickupRequest;
use App\Http\Requests\Communications\SaveAppointmentRequest;
use App\Http\Requests\Communications\StructuredNotificationRequest;
use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\RequestAppointment;
use App\Services\ProcedureCommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureCommunicationController extends Controller
{
    public function show(ProcedureRequest $procedureRequest): View
    {
        Gate::authorize('manageCommunications', $procedureRequest);
        $procedureRequest->load(['user', 'procedureType', 'status', 'appointments.area', 'pickup']);
        $notificationHistory = $procedureRequest->user->notifications()->where('data->procedure_request_id', $procedureRequest->id)->latest()->get();

        return view('communications.show', ['procedureRequest' => $procedureRequest, 'notificationHistory' => $notificationHistory, 'areas' => Area::active()->orderBy('name')->get()]);
    }

    public function schedule(SaveAppointmentRequest $request, ProcedureRequest $procedureRequest, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->schedule($procedureRequest, $request->user(), $request->validated());

        return back()->with('status', 'La cita fue programada.');
    }

    public function reschedule(SaveAppointmentRequest $request, ProcedureRequest $procedureRequest, RequestAppointment $appointment, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->schedule($procedureRequest, $request->user(), $request->validated(), $appointment);

        return back()->with('status', 'La cita fue reprogramada.');
    }

    public function appointmentStatus(AppointmentStatusRequest $request, ProcedureRequest $procedureRequest, RequestAppointment $appointment, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->appointmentStatus($procedureRequest, $appointment, $request->user(), $request->validated('status'));

        return back()->with('status', 'El estado de la cita fue actualizado.');
    }

    public function ready(ReadyPickupRequest $request, ProcedureRequest $procedureRequest, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->readyForPickup($procedureRequest, $request->user(), $request->validated());

        return back()->with('status', 'El documento quedó listo para recojo.');
    }

    public function deliver(DeliverPickupRequest $request, ProcedureRequest $procedureRequest, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->deliver($procedureRequest, $request->user(), $request->validated());

        return back()->with('status', 'La entrega fue registrada.');
    }

    public function notify(StructuredNotificationRequest $request, ProcedureRequest $procedureRequest, ProcedureCommunicationService $service): RedirectResponse
    {
        $service->notify($procedureRequest, $request->user(), $request->validated('type'), $request->validated('message'));

        return back()->with('status', 'La notificación fue enviada.');
    }
}
