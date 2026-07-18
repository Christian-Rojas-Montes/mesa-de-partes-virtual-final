<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationWorkEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['application_work_project_id', 'from_stage', 'to_stage', 'action', 'description', 'snapshot', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }
}
