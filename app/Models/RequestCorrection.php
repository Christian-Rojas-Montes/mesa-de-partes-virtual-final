<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestCorrection extends Model
{
    protected $fillable = [
        'procedure_request_id',
        'request_observation_id',
        'user_id',
        'message',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(RequestObservation::class, 'request_observation_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }
}
