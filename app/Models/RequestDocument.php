<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDocument extends Model
{
    protected $fillable = [
        'procedure_request_id',
        'request_correction_id',
        'procedure_requirement_id',
        'disk',
        'path',
        'stored_name',
        'extension',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequirement::class, 'procedure_requirement_id');
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(RequestCorrection::class, 'request_correction_id');
    }
}
