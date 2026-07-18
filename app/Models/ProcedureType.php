<?php

namespace App\Models;

use App\Enums\PaymentTiming;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProcedureType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'procedure_category_id',
        'presentation_modality_id',
        'responsible_area_id',
        'continuation_department',
        'name',
        'description',
        'instructions',
        'attention_days',
        'available_from',
        'available_until',
        'academic_period',
        'unavailable_message',
        'reception_open',
        'allows_digital_registration',
        'requires_physical_delivery',
        'physical_submission_location',
        'physical_submission_schedule',
        'physical_submission_deadline_days',
        'sort_order',
        'requires_payment',
        'amount',
        'currency',
        'payment_concept',
        'payment_timing',
        'payment_observation',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'attention_days' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'sort_order' => 'integer',
            'reception_open' => 'boolean',
            'allows_digital_registration' => 'boolean',
            'requires_physical_delivery' => 'boolean',
            'physical_submission_deadline_days' => 'integer',
            'requires_payment' => 'boolean',
            'amount' => 'decimal:2',
            'payment_timing' => PaymentTiming::class,
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcedureCategory::class, 'procedure_category_id');
    }

    public function presentationModality(): BelongsTo
    {
        return $this->belongsTo(PresentationModality::class);
    }

    public function responsibleArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'responsible_area_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProcedureVariant::class);
    }

    public function dynamicFields(): HasMany
    {
        return $this->hasMany(ProcedureDynamicField::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(ProcedurePrerequisite::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProcedureRequirement::class, 'procedure_type_id');
    }

    public function activeRequirements(): HasMany
    {
        return $this->requirements()->active()->whereNull('procedure_variant_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function procedureRequests(): HasMany
    {
        return $this->hasMany(ProcedureRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
