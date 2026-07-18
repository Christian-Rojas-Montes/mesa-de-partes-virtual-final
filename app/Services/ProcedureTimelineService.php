<?php

namespace App\Services;

use App\Models\ProcedureRequest;
use Illuminate\Support\Collection;

class ProcedureTimelineService
{
    private const DOMAIN_ACTIONS = ['observed', 'correction_submitted', 'derived', 'rederived', 'derivation_received', 'physical_documents_received', 'physical_documents_incomplete', 'originals_verified', 'appointment_scheduled', 'appointment_rescheduled', 'appointment_confirmed', 'appointment_attended', 'appointment_cancelled', 'ready_for_pickup', 'delivered', 'response_registered'];

    private const PUBLIC_HISTORY = ['registered', 'pre_registration_created', 'pending_physical_delivery', 'physical_case_registered', 'review_started', 'validated', 'rejected', 'attention_started', 'attention_action', 'academic_file_assigned', 'request_finalized', 'finalized'];

    public function applicant(ProcedureRequest $request): Collection
    {
        $this->load($request);

        return $this->build($request, false);
    }

    public function staff(ProcedureRequest $request): Collection
    {
        $this->load($request);

        return $this->build($request, true);
    }

    private function load(ProcedureRequest $request): void
    {
        $request->loadMissing([
            'histories.status', 'histories.user.role', 'observations.correction', 'corrections',
            'derivations.originArea', 'derivations.destinationArea', 'derivations.responsible',
            'attentionActions.author', 'physicalReception.receiver', 'physicalReception.receivingArea',
            'appointments.area', 'appointments.creator', 'response.author', 'pickup.readyBy', 'pickup.deliveredBy',
        ]);
    }

    private function build(ProcedureRequest $request, bool $staff): Collection
    {
        $events = collect();
        $previousStatus = null;
        foreach ($request->histories->sortBy(fn ($h) => [$h->created_at->timestamp, $h->id]) as $history) {
            $newStatus = $history->status?->name;
            if (! in_array($history->action, self::DOMAIN_ACTIONS, true) && ($staff || in_array($history->action, self::PUBLIC_HISTORY, true))) {
                $events->push($this->event('history-'.$history->id, 'status', $this->title($history->action), $history->description, $history->created_at, '●', $newStatus, $staff ? $history->user?->first_name.' '.$history->user?->last_name : null, $staff ? ['Estado anterior' => $previousStatus ?: 'Sin estado previo', 'Estado nuevo' => $newStatus] : []));
            }
            $previousStatus = $newStatus;
        }
        foreach ($request->observations as $item) {
            $events->push($this->event('observation-'.$item->id, 'observation', 'Observación registrada', $item->description, $this->historyDate($request, 'observed', $item->created_at), '!', $item->resolved_at ? 'Subsanada' : 'Observado', null, ['Instrucciones' => $item->correction_instructions, 'Plazo' => $item->correction_deadline?->format('d/m/Y H:i')]));
        }
        foreach ($request->corrections as $item) {
            $events->push($this->event('correction-'.$item->id, 'correction', 'Subsanación recibida', $item->message, $item->submitted_at, '✓', 'Recibida'));
        }
        foreach ($request->derivations as $item) {
            $events->push($this->event('derivation-'.$item->id, 'derivation', 'Expediente derivado', 'El expediente fue enviado a '.$item->destinationArea->name.'.', $this->historyDate($request, 'derived', $item->derived_at), '→', 'Derivado', $staff ? trim($item->responsible?->first_name.' '.$item->responsible?->last_name) : null, $staff ? ['Área de origen' => $item->originArea?->name, 'Área de destino' => $item->destinationArea->name, 'Motivo' => $item->reason] : ['Área responsable' => $item->destinationArea->name, 'Motivo' => $item->reason]));
            if ($item->received_at) {
                $events->push($this->event('derivation-received-'.$item->id, 'derivation', 'Recepción por el área', 'El área responsable recibió el expediente.', $item->received_at, '✓', 'Recibido', null, ['Área' => $item->destinationArea->name]));
            }
        }
        if ($request->physicalReception) {
            $reception = $request->physicalReception;
            $details = ['Oficina' => $reception->receivingArea?->name ?? 'Mesa de Partes', 'Resultado' => $reception->verification_result];
            if ($staff) {
                $details['Documentos recibidos'] = collect($reception->presented_documents)->map(fn ($d) => ($d['name'] ?? 'Documento').' ('.($d['presentation'] ?? 'presentación').', '.($d['quantity'] ?? 1).')')->implode('; ');
                $details['Faltantes'] = collect(data_get($request->configuration_snapshot, 'pending_requirements', []))->pluck('name')->implode('; ') ?: 'Ninguno';
                $details['Observación interna'] = $reception->observations;
            }
            $events->push($this->event('physical-'.$reception->id, 'physical', 'Documentación física recibida', 'Mesa de Partes confirmó la recepción física.', $reception->received_at, '▣', $reception->verification_result, $staff ? trim($reception->receiver?->first_name.' '.$reception->receiver?->last_name) : null, $details));
        }
        foreach ($request->appointments as $item) {
            $events->push($this->event('appointment-'.$item->id, 'appointment', $item->rescheduled_from_id ? 'Cita reprogramada' : 'Cita programada', "{$item->appointment_date->format('d/m/Y')} de {$item->starts_at} a {$item->ends_at} en {$item->office}.", $item->created_at, '◷', $item->status, $staff ? trim($item->creator?->first_name.' '.$item->creator?->last_name) : null, ['Motivo' => $item->reason, 'Instrucciones' => $item->instructions, 'Área' => $item->area?->name]));
        }
        if ($request->response) {
            $events->push($this->event('response-'.$request->response->id, 'response', 'Respuesta emitida', $request->response->summary, $request->response->responded_at, '✓', 'Disponible'));
        }
        if ($request->pickup) {
            $pickup = $request->pickup;
            $events->push($this->event('pickup-ready-'.$pickup->id, 'pickup', 'Documento listo para recoger', "Disponible en {$pickup->office}.", $pickup->available_at, '▤', 'Listo', $staff ? trim($pickup->readyBy?->first_name.' '.$pickup->readyBy?->last_name) : null, ['Requisito para recoger' => $pickup->pickup_requirement]));
            if ($pickup->delivered_at) {
                $events->push($this->event('pickup-delivered-'.$pickup->id, 'delivery', 'Documento entregado', 'La entrega fue registrada.', $pickup->delivered_at, '✓', 'Entregado', $staff ? trim($pickup->deliveredBy?->first_name.' '.$pickup->deliveredBy?->last_name) : null, $staff ? ['Persona que recibió' => $pickup->received_by_name, 'Identidad verificada' => $pickup->identity_document_verified ? 'Sí' : 'No', 'Observación interna' => $pickup->observation] : []));
            }
        }
        if ($staff) {
            foreach ($request->attentionActions as $item) {
                $events->push($this->event('attention-'.$item->id, 'internal', 'Acción interna de atención', $item->description, $item->created_at, '•', 'Interna', trim($item->author?->first_name.' '.$item->author?->last_name)));
            }
        }
        if ($staff) {
            foreach ($request->user->notifications()->where('data->procedure_request_id', $request->id)->get() as $item) {
                $events->push($this->event('notification-'.$item->id, 'notification', 'Notificación enviada', $item->data['message'] ?? 'Notificación del expediente.', $item->created_at, '✉', $item->read_at ? 'Leída' : 'No leída'));
            }
        }

        $priority = ['status' => 10, 'observation' => 20, 'correction' => 30, 'derivation' => 40, 'physical' => 50, 'appointment' => 60, 'response' => 70, 'pickup' => 80, 'delivery' => 90, 'internal' => 100, 'notification' => 110];

        return $events->filter(fn ($event) => $event['occurred_at'] !== null)
            ->sortBy(fn ($event) => [$event['occurred_at']->timestamp, $priority[$event['type']] ?? 999, $event['id']])
            ->values();
    }

    private function event(string $id, string $type, string $title, ?string $description, $occurredAt, string $icon, ?string $status = null, ?string $actor = null, array $details = []): array
    {
        return ['id' => $id, 'type' => $type, 'title' => $title, 'description' => $description, 'occurred_at' => $occurredAt, 'icon' => $icon, 'status' => $status, 'actor' => $actor, 'details' => array_filter($details, fn ($value) => filled($value))];
    }

    private function title(string $action): string
    {
        return ['registered' => 'Solicitud registrada', 'pre_registration_created' => 'Preinscripción creada', 'pending_physical_delivery' => 'Pendiente de presentación física', 'physical_case_registered' => 'Expediente presencial registrado', 'review_started' => 'Revisión iniciada', 'validated' => 'Trámite aprobado', 'rejected' => 'Trámite rechazado', 'attention_started' => 'Trámite en atención', 'attention_action' => 'Avance de atención', 'academic_file_assigned' => 'Expediente académico asignado', 'request_finalized' => 'Trámite finalizado', 'finalized' => 'Trámite finalizado'][$action] ?? 'Actualización del trámite';
    }

    private function historyDate(ProcedureRequest $request, string $action, $fallback)
    {
        return $request->histories->firstWhere('action', $action)?->created_at ?? $fallback;
    }
}
