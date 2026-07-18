<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalExamRequirement extends Model
{
    protected $fillable = ['professional_exam_profile_id', 'code', 'label_snapshot', 'physical', 'sensitive', 'quantity', 'status', 'request_document_id', 'observation', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['physical' => 'boolean', 'sensitive' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalExamProfile::class, 'professional_exam_profile_id');
    }
}
