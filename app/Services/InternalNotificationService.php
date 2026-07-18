<?php

namespace App\Services;

use App\Models\ProcedureRequest;
use App\Models\User;
use App\Notifications\InternalProcedureRequestNotification;
use Illuminate\Support\Collection;

class InternalNotificationService
{
    public const REGISTERED = 'request_registered';

    public const REVIEW_STARTED = 'review_started';

    public const VALIDATED = 'request_validated';

    public const OBSERVED = 'request_observed';

    public const REJECTED = 'request_rejected';

    public const CORRECTION_SUBMITTED = 'correction_submitted';

    public const DERIVED = 'request_derived';

    public const RECEIVED = 'request_received';

    public const ATTENTION_STARTED = 'attention_started';

    public const ATTENTION_ACTION = 'attention_action';

    public const RESPONSE_REGISTERED = 'response_registered';

    public const FINALIZED = 'request_finalized';

    public const APPOINTMENT_SCHEDULED = 'appointment_scheduled';

    public const APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';

    public const ORIGINALS_REQUIRED = 'originals_required';

    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const DOCUMENT_DELIVERED = 'document_delivered';

    public const PHYSICAL_RECEIVED = 'physical_reception_confirmed';

    public const INCOMPLETE = 'physical_documents_incomplete';

    public function dispatch(
        ProcedureRequest $procedureRequest,
        string $event,
        string $message,
        string $occurrenceKey,
        ?int $areaId = null,
        ?string $areaMessage = null,
        array $structuredData = [],
    ): void {
        $recipients = match ($event) {
            self::CORRECTION_SUBMITTED,
            self::RESPONSE_REGISTERED => $this->applicantAndRole($procedureRequest, 'Mesa de Partes'),
            self::DERIVED => $this->derivedRecipients($procedureRequest, $areaId),
            default => collect([$procedureRequest->user]),
        };

        $recipients->unique('id')->each(function (User $recipient) use ($procedureRequest, $event, $message, $occurrenceKey, $areaMessage, $structuredData) {
            $deduplicationKey = $event.':'.$procedureRequest->id.':'.$occurrenceKey.':'.$recipient->id;

            if ($recipient->notifications()->where('data->deduplication_key', $deduplicationKey)->exists()) {
                return;
            }

            $recipientMessage = match (true) {
                $recipient->id === $procedureRequest->user_id => $message,
                $event === self::DERIVED => $areaMessage ?? $message,
                $event === self::CORRECTION_SUBMITTED => 'El solicitante presentó una subsanación para el expediente '.$procedureRequest->tracking_code.'.',
                $event === self::RESPONSE_REGISTERED => 'El expediente '.$procedureRequest->tracking_code.' tiene una respuesta y está listo para revisión de cierre.',
                default => $message,
            };

            $recipient->notify(new InternalProcedureRequestNotification(
                $procedureRequest,
                $event,
                $recipientMessage,
                $deduplicationKey,
                $structuredData,
            ));
        });
    }

    /** @return Collection<int, User> */
    private function activeUsersByRole(string $roleName, ?int $areaId = null): Collection
    {
        return User::query()->active()
            ->when($areaId, fn ($query) => $query->where('area_id', $areaId))
            ->whereHas('role', fn ($query) => $query->where('name', $roleName)->where('active', true))
            ->get();
    }

    /** @return Collection<int, User> */
    private function derivedRecipients(ProcedureRequest $procedureRequest, ?int $areaId): Collection
    {
        return collect([$procedureRequest->user])->merge(
            $areaId === null ? collect() : $this->activeUsersByRole('Responsable de área', $areaId),
        );
    }

    /** @return Collection<int, User> */
    private function applicantAndRole(ProcedureRequest $procedureRequest, string $roleName): Collection
    {
        return collect([$procedureRequest->user])->merge($this->activeUsersByRole($roleName));
    }
}
