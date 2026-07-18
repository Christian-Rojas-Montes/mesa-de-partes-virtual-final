<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalCatalogSyncRecord extends Model
{
    protected $fillable = ['entity_type', 'stable_key', 'entity_id', 'checksum', 'managed_values'];

    protected function casts(): array
    {
        return ['managed_values' => 'array'];
    }
}
