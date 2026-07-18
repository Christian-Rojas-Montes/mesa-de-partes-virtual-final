<?php

namespace App\Models;

use App\Casts\StructuredConditions;
use App\Enums\DynamicFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureDynamicField extends Model
{
    use HasFactory;

    protected $fillable = ['procedure_type_id', 'procedure_variant_id', 'key', 'type', 'label', 'help_text', 'required', 'min_length', 'max_length', 'min_value', 'max_value', 'options', 'validation_rule', 'visibility_conditions', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['type' => DynamicFieldType::class, 'required' => 'boolean', 'min_length' => 'integer', 'max_length' => 'integer', 'min_value' => 'decimal:4', 'max_value' => 'decimal:4', 'options' => 'array', 'visibility_conditions' => StructuredConditions::class, 'sort_order' => 'integer', 'active' => 'boolean'];
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(ProcedureType::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProcedureVariant::class, 'procedure_variant_id');
    }
}
