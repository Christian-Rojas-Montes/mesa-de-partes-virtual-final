<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleFinalDossierEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['title_final_dossier_id', 'action', 'description', 'snapshot', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(TitleFinalDossier::class);
    }
}
