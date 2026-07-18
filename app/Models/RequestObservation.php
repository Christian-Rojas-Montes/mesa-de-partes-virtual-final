<?php

namespace App\Models;

use Database\Factories\RequestObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RequestObservation extends Model
{
    /** @use HasFactory<RequestObservationFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_request_id',
        'user_id',
        'description',
        'correction_instructions',
        'correction_deadline',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['correction_deadline' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function correction(): HasOne
    {
        return $this->hasOne(RequestCorrection::class);
    }
}
