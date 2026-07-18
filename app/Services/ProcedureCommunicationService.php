<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\RequestAppointment;
use App\Models\RequestPickup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcedureCommunicationService
{
    public function __construct(private readonly InternalNotificationService $notifications) {}

    public function schedule(ProcedureRequest $request, User $actor, array $data, ?RequestAppointment $previous = null): RequestAppointment
    {
        return DB::transaction(function () use ($request, $actor, $data, $previous) {
            $locked = ProcedureRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($previous && ($previous->procedure_request_id !== $locked->id || in_array($previous->status, ['attended', 'cancelled', 'rescheduled'], true))) {
                throw ValidationException::withMessages(['appointment_date' => 'La cita no puede reprogramarse.']);
            }
            if ($previous) {
                $previous->update(['status' => 'rescheduled']);
            }
            $appointment = $locked->appointments()->create([...$data, 'rescheduled_from_id' => $previous?->id, 'status' => $data['status'] ?? 'scheduled', 'created_by' => $actor->id]);
            $event = $previous ? InternalNotificationService::APPOINTMENT_RESCHEDULED : InternalNotificationService::APPOINTMENT_SCHEDULED;
            $action = $previous ? 'appointment_rescheduled' : 'appointment_scheduled';
            $message = ($previous ? 'Tu cita fue reprogramada' : 'Se programó una cita')." para el {$appointment->appointment_date->format('d/m/Y')} de {$appointment->starts_at} a {$appointment->ends_at} en {$appointment->office}.";
            $this->history($locked, $actor, $action, $message);
            $this->audit($locked, $actor, $action, ['appointment_id' => $appointment->id]);
            $this->notifications->dispatch($locked, $event, $message, (string) $appointment->id, structuredData: ['appointment_id' => $appointment->id, 'appointment_at' => $appointment->appointment_date->format('Y-m-d').' '.$appointment->starts_at]);

            return $appointment;
        });
    }

    public function readyForPickup(ProcedureRequest $request, User $actor, array $data): RequestPickup
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $locked = ProcedureRequest::query()->lockForUpdate()->with('pickup')->findOrFail($request->id);
            if ($locked->pickup) {
                throw ValidationException::withMessages(['available_at' => 'El recojo ya fue registrado.']);
            }
            $pickup = $locked->pickup()->create([...$data, 'marked_ready_by' => $actor->id, 'status' => 'ready']);
            $message = "Tu documento está listo para recoger desde el {$pickup->available_at->format('d/m/Y H:i')} en {$pickup->office}.";
            $this->history($locked, $actor, 'ready_for_pickup', $message);
            $this->audit($locked, $actor, 'ready_for_pickup', ['pickup_id' => $pickup->id]);
            $this->notifications->dispatch($locked, InternalNotificationService::READY_FOR_PICKUP, $message, (string) $pickup->id, structuredData: ['pickup_id' => $pickup->id, 'pickup_ready' => true]);

            return $pickup;
        });
    }

    public function appointmentStatus(ProcedureRequest $request, RequestAppointment $appointment, User $actor, string $status): void
    {
        DB::transaction(function () use ($request, $appointment, $actor, $status) {
            if ($appointment->procedure_request_id !== $request->id || in_array($appointment->status, ['attended', 'cancelled', 'rescheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'La cita ya no admite cambios.']);
            }
            $appointment->update(['status' => $status]);
            $this->history($request, $actor, 'appointment_'.$status, 'La cita cambió al estado '.$status.'.');
            $this->audit($request, $actor, 'appointment_'.$status, ['appointment_id' => $appointment->id]);
        });
    }

    public function deliver(ProcedureRequest $request, User $actor, array $data): RequestPickup
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $locked = ProcedureRequest::query()->lockForUpdate()->with('pickup')->findOrFail($request->id);
            if (! $locked->pickup || $locked->pickup->status !== 'ready') {
                throw ValidationException::withMessages(['delivered_at' => 'No existe un documento pendiente de entrega.']);
            }
            $locked->pickup->update([...$data, 'identity_document_verified' => true, 'delivered_by' => $actor->id, 'status' => 'delivered']);
            $message = 'La entrega del documento fue registrada correctamente.';
            $this->history($locked, $actor, 'delivered', $message);
            $this->audit($locked, $actor, 'document_delivered', ['pickup_id' => $locked->pickup->id, 'identity_verified' => true]);
            $this->notifications->dispatch($locked, InternalNotificationService::DOCUMENT_DELIVERED, $message, (string) $locked->pickup->id, structuredData: ['pickup_id' => $locked->pickup->id, 'delivered' => true]);

            return $locked->pickup;
        });
    }

    public function notify(ProcedureRequest $request, User $actor, string $type, string $message): void
    {
        DB::transaction(function () use ($request, $actor, $type, $message) {
            $safe = NotificationPrivacySanitizer::sanitize($message);
            $this->notifications->dispatch($request, $type, $safe, 'manual-'.now()->format('YmdHisv'), structuredData: ['structured' => true]);
            $this->audit($request, $actor, 'structured_notification_sent', ['notification_type' => $type]);
        });
    }

    private function history(ProcedureRequest $request, User $actor, string $action, string $description): void
    {
        $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => $action, 'description' => $description]);
    }

    private function audit(ProcedureRequest $request, User $actor, string $action, array $details): void
    {
        AuditLog::query()->create(['user_id' => $actor->id, 'action' => $action, 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => $details, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
