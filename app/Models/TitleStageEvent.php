<?php

namespace App\Models;

use App\Enums\TitleProcessStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleStageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['title_process_id', 'from_stage', 'to_stage', 'action', 'description', 'snapshot', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['from_stage' => TitleProcessStage::class, 'to_stage' => TitleProcessStage::class, 'snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(TitleProcess::class, 'title_process_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
