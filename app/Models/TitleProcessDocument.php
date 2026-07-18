<?php

namespace App\Models;

use App\Enums\TitleProcessStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleProcessDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['title_process_id', 'request_document_id', 'stage', 'document_kind', 'label_snapshot', 'registered_by', 'created_at'];

    protected function casts(): array
    {
        return ['stage' => TitleProcessStage::class, 'created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RequestDocument::class, 'request_document_id');
    }
}
