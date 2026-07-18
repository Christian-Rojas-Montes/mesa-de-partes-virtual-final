<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleFinalDossierRequirement extends Model
{
    protected $fillable = ['title_final_dossier_id', 'code', 'label_snapshot', 'physical', 'original', 'sensitive', 'conditional', 'quantity', 'validity_days', 'status', 'request_document_id', 'observation', 'subsanated_at', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['physical' => 'boolean', 'original' => 'boolean', 'sensitive' => 'boolean', 'conditional' => 'boolean', 'subsanated_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(TitleFinalDossier::class, 'title_final_dossier_id');
    }
}
