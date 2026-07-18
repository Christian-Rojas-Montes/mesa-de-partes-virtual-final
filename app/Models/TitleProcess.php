<?php

namespace App\Models;

use App\Enums\TitleModality;
use App\Enums\TitleProcessStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TitleProcess extends Model
{
    protected $fillable = ['procedure_request_id', 'modality', 'current_stage', 'attempt_or_call', 'eligibility_declared', 'eligibility_verified', 'eligibility_verified_by', 'eligibility_verified_at', 'responsible_id', 'result', 'result_observation', 'result_recorded_by', 'result_recorded_at', 'final_file_completed_at'];

    protected function casts(): array
    {
        return ['modality' => TitleModality::class, 'current_stage' => TitleProcessStage::class, 'eligibility_declared' => 'array', 'eligibility_verified' => 'array', 'eligibility_verified_at' => 'datetime', 'result_recorded_at' => 'datetime', 'final_file_completed_at' => 'datetime'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TitleStageEvent::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TitleSchedule::class);
    }

    public function stageDocuments(): HasMany
    {
        return $this->hasMany(TitleProcessDocument::class);
    }

    public function applicationWorkProject(): HasOne
    {
        return $this->hasOne(ApplicationWorkProject::class);
    }

    public function professionalExamProfile(): HasOne
    {
        return $this->hasOne(ProfessionalExamProfile::class);
    }

    public function finalDossier(): HasOne
    {
        return $this->hasOne(TitleFinalDossier::class);
    }
}
