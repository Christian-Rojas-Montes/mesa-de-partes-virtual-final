<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestPhysicalReception extends Model
{
    protected $fillable = ['procedure_request_id', 'received_at', 'received_by', 'folio_count', 'document_count', 'presented_documents', 'observations', 'receiving_area_id', 'receipt_number', 'evidence_disk', 'evidence_path', 'verification_result'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'folio_count' => 'integer', 'document_count' => 'integer', 'presented_documents' => 'array'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receivingArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'receiving_area_id');
    }
}
