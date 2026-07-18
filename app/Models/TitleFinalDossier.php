<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TitleFinalDossier extends Model
{
    protected $fillable = ['title_process_id', 'status', 'observations', 'submitted_at', 'registration_code', 'registered_at', 'issued_at', 'pickup_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'registered_at' => 'date', 'issued_at' => 'date', 'pickup_at' => 'date', 'delivered_at' => 'datetime'];
    }

    public function titleProcess(): BelongsTo
    {
        return $this->belongsTo(TitleProcess::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(TitleFinalDossierRequirement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TitleFinalDossierEvent::class);
    }
}
