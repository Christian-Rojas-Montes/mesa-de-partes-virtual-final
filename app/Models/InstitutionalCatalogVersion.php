<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalCatalogVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['version', 'checksum', 'summary', 'applied_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'applied_at' => 'datetime'];
    }
}
