<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalExamProfile extends Model
{
    protected $fillable = ['title_process_id', 'experience_months', 'experience_basis', 'current_stage'];

    public function titleProcess(): BelongsTo
    {
        return $this->belongsTo(TitleProcess::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProfessionalExamRequirement::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ProfessionalExamAttempt::class);
    }
}
