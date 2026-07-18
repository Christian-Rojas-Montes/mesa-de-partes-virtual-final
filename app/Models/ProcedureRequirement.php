<?php

namespace App\Models;

use App\Casts\StructuredConditions;
use App\Enums\RequirementType;
use App\Enums\ValidityUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProcedureRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure_type_id',
        'procedure_variant_id',
        'name',
        'description',
        'type',
        'required',
        'sort_order',
        'active',
        'requires_original',
        'requires_simple_copy',
        'requires_authenticated_copy',
        'requires_legalized_copy',
        'requires_endorsement',
        'copy_count',
        'allowed_formats',
        'max_file_size_kb',
        'requires_issue_date',
        'validity_value',
        'validity_unit',
        'requires_physical_submission',
        'requires_digital_file',
        'sensitive',
        'observations',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequirementType::class,
            'required' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'requires_original' => 'boolean',
            'requires_simple_copy' => 'boolean',
            'requires_authenticated_copy' => 'boolean',
            'requires_legalized_copy' => 'boolean',
            'requires_endorsement' => 'boolean',
            'copy_count' => 'integer',
            'allowed_formats' => 'array',
            'max_file_size_kb' => 'integer',
            'requires_issue_date' => 'boolean',
            'validity_value' => 'integer',
            'validity_unit' => ValidityUnit::class,
            'requires_physical_submission' => 'boolean',
            'requires_digital_file' => 'boolean',
            'sensitive' => 'boolean',
            'conditions' => StructuredConditions::class,
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProcedureVariant::class, 'procedure_variant_id');
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class, 'procedure_type_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function requestDocuments(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
