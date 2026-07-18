<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalExamAttempt extends Model
{
    protected $fillable = ['professional_exam_profile_id', 'opportunity', 'title_schedule_id', 'theory_weight', 'practical_weight', 'theory_grade', 'practical_grade', 'final_grade', 'result', 'observation', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'theory_weight' => 'decimal:2', 'practical_weight' => 'decimal:2', 'theory_grade' => 'decimal:2', 'practical_grade' => 'decimal:2', 'final_grade' => 'decimal:2'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalExamProfile::class, 'professional_exam_profile_id');
    }
}
