<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestRejection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['procedure_request_id', 'user_id', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }
}
