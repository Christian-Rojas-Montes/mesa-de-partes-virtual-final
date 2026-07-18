<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class CatalogAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $action = match (true) {
            $model->wasChanged('active') && $model->active => 'activated',
            $model->wasChanged('active') => 'deactivated',
            default => 'updated',
        };

        $this->record($model, $action, $model->getChanges());
    }

    /** @param array<string, mixed> $changes */
    private function record(Model $model, string $action, array $changes): void
    {
        if (! auth()->check()) {
            return;
        }

        unset($changes['updated_at'], $changes['created_at']);

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'details' => ['changes' => $changes],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
