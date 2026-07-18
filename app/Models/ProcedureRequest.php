<?php

namespace App\Models;

use Database\Factories\ProcedureRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProcedureRequest extends Model
{
    /** @use HasFactory<ProcedureRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'procedure_type_id',
        'procedure_variant_id',
        'status_id',
        'validated_by',
        'validated_at',
        'tracking_code',
        'academic_file_number',
        'academic_file_assigned_at',
        'academic_file_assigned_by',
        'subject',
        'description',
        'dynamic_responses',
        'configuration_snapshot',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'validated_at' => 'datetime', 'academic_file_assigned_at' => 'datetime', 'dynamic_responses' => 'array', 'configuration_snapshot' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProcedureVariant::class, 'procedure_variant_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RequestHistory::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(RequestObservation::class);
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(RequestDerivation::class);
    }

    public function latestDerivation(): HasOne
    {
        return $this->hasOne(RequestDerivation::class)->latestOfMany('derived_at');
    }

    public function response(): HasOne
    {
        return $this->hasOne(RequestResponse::class);
    }

    public function physicalReception(): HasOne
    {
        return $this->hasOne(RequestPhysicalReception::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(RequestAppointment::class);
    }

    public function pickup(): HasOne
    {
        return $this->hasOne(RequestPickup::class);
    }

    public function titleProcess(): HasOne
    {
        return $this->hasOne(TitleProcess::class);
    }

    public function attentionActions(): HasMany
    {
        return $this->hasMany(RequestAttentionAction::class);
    }

    public function rejection(): HasOne
    {
        return $this->hasOne(RequestRejection::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(RequestCorrection::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
