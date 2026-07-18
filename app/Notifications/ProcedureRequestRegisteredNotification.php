<?php

namespace App\Notifications;

use App\Models\ProcedureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcedureRequestRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ProcedureRequest $procedureRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'procedure_request_registered',
            'procedure_request_id' => $this->procedureRequest->id,
            'tracking_code' => $this->procedureRequest->tracking_code,
            'message' => 'Tu solicitud fue registrada correctamente.',
        ];
    }
}
