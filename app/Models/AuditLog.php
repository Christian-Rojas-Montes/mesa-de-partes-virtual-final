<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AuditLog $log) {
            $log->details = self::sanitizeDetails($log->details ?? []);
        });
    }

    /** @param array<string, mixed> $details
     *  @return array<string, mixed>
     */
    private static function sanitizeDetails(array $details): array
    {
        $safe = [];

        foreach ($details as $key => $value) {
            if (preg_match('/password|token|secret|(^|_)content($|_)|path|checksum|stored_name|file_name/i', (string) $key)) {
                continue;
            }

            $safe[$key] = is_array($value) ? self::sanitizeDetails($value) : $value;
        }

        return $safe;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
