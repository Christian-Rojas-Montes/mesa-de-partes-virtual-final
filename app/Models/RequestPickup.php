<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestPickup extends Model
{
    protected $fillable = ['procedure_request_id', 'available_at', 'office', 'pickup_requirement', 'marked_ready_by', 'delivered_by', 'received_by_name', 'identity_document_verified', 'delivered_at', 'observation', 'status'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'delivered_at' => 'datetime', 'identity_document_verified' => 'boolean'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_ready_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
