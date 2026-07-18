<?php

namespace App\Models;

use App\Enums\ApplicationWorkStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationWorkProject extends Model
{
    protected $fillable = ['title_process_id', 'current_stage', 'title', 'problem', 'objective', 'study_program', 'proposed_advisor', 'project_document_id', 'proposal_date', 'review_result', 'approval_observations', 'approval_resolution_document_id', 'assigned_advisor', 'approved_at', 'execution_deadline', 'similarity_percent', 'originality_result', 'grade', 'result_minutes_document_id'];

    protected function casts(): array
    {
        return ['current_stage' => ApplicationWorkStage::class, 'proposal_date' => 'date', 'approved_at' => 'date', 'execution_deadline' => 'date', 'similarity_percent' => 'decimal:2', 'grade' => 'decimal:2'];
    }

    public function titleProcess(): BelongsTo
    {
        return $this->belongsTo(TitleProcess::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ApplicationWorkMember::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ApplicationWorkRequirement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationWorkEvent::class);
    }
}
