<?php

namespace App\Models;

use App\Casts\StructuredConditions;
use App\Enums\PrerequisiteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedurePrerequisite extends Model
{
    use HasFactory;

    protected $fillable = ['procedure_type_id', 'procedure_variant_id', 'required_procedure_type_id', 'type', 'name', 'description', 'conditions', 'required', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => PrerequisiteType::class, 'conditions' => StructuredConditions::class, 'required' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProcedureVariant::class, 'procedure_variant_id');
    }

    public function requiredProcedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class, 'required_procedure_type_id');
    }
}
