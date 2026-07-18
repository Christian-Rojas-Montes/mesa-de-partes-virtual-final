<?php

namespace App\Models;

use Database\Factories\RequestResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestResponse extends Model
{
    /** @use HasFactory<RequestResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_request_id',
        'user_id',
        'summary',
        'responded_at',
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
        return ['responded_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
