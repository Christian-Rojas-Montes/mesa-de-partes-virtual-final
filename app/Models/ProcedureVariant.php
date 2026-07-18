<?php

namespace App\Models;

use App\Casts\StructuredConditions;
use App\Enums\PaymentTiming;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureVariant extends Model
{
    use HasFactory;

    protected $fillable = ['procedure_type_id', 'presentation_modality_id', 'code', 'name', 'description', 'conditions', 'available_from', 'available_until', 'academic_period', 'unavailable_message', 'reception_open', 'allows_digital_registration', 'requires_physical_delivery', 'physical_submission_location', 'physical_submission_schedule', 'physical_submission_deadline_days', 'attention_days', 'sort_order', 'requires_payment', 'amount', 'currency', 'payment_concept', 'payment_timing', 'payment_observation', 'active'];

    protected function casts(): array
    {
        return ['conditions' => StructuredConditions::class, 'available_from' => 'datetime', 'available_until' => 'datetime', 'reception_open' => 'boolean', 'allows_digital_registration' => 'boolean', 'requires_physical_delivery' => 'boolean', 'physical_submission_deadline_days' => 'integer', 'attention_days' => 'integer', 'sort_order' => 'integer', 'requires_payment' => 'boolean', 'amount' => 'decimal:2', 'payment_timing' => PaymentTiming::class, 'active' => 'boolean'];
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }

    public function presentationModality(): BelongsTo
    {
        return $this->belongsTo(PresentationModality::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProcedureRequirement::class);
    }

    public function dynamicFields(): HasMany
    {
        return $this->hasMany(ProcedureDynamicField::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(ProcedurePrerequisite::class);
    }
}
