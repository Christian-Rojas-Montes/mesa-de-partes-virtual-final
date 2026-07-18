<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleSchedule extends Model
{
    protected $fillable = ['title_process_id', 'rescheduled_from_id', 'scheduled_at', 'place', 'jury_or_responsibles', 'reason', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'jury_or_responsibles' => 'array'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(TitleProcess::class, 'title_process_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
