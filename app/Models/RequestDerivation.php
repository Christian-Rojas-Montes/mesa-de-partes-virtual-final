<?php

namespace App\Models;

use Database\Factories\RequestDerivationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDerivation extends Model
{
    public const UPDATED_AT = null;

    /** @use HasFactory<RequestDerivationFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_request_id',
        'from_area_id',
        'to_area_id',
        'user_id',
        'reason',
        'derived_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return ['derived_at' => 'datetime', 'received_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function originArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'from_area_id');
    }

    public function destinationArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'to_area_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
