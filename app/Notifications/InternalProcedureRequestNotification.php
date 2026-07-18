<?php

namespace App\Notifications;

use App\Models\ProcedureRequest;
use App\Services\NotificationPrivacySanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InternalProcedureRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProcedureRequest $procedureRequest,
        private readonly string $event,
        private readonly string $message,
        private readonly string $deduplicationKey,
        private readonly array $structuredData = [],
    ) {
        $this->onConnection(config('internal-notifications.queue_connection', 'sync'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('internal-notifications.email_enabled', false) && filled($notifiable->email)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'procedure_request_event',
            'event' => $this->event,
            'procedure_request_id' => $this->procedureRequest->id,
            'tracking_code' => $this->procedureRequest->tracking_code,
            'message' => NotificationPrivacySanitizer::sanitize($this->message),
            'deduplication_key' => $this->deduplicationKey,
            ...$this->structuredData,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Actualización del trámite '.$this->procedureRequest->tracking_code)
            ->line(NotificationPrivacySanitizer::sanitize($this->message))
            ->action('Ver notificación', route('notifications.index'));
    }
}
