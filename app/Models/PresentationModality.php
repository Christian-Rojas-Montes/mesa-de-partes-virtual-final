<?php

namespace App\Models;

use App\Enums\PresentationModeCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationModality extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'active'];

    protected function casts(): array
    {
        return ['code' => PresentationModeCode::class, 'active' => 'boolean'];
    }

    public function procedureTypes(): HasMany
    {
        return $this->hasMany(ProcedureType::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProcedureVariant::class);
    }
}
