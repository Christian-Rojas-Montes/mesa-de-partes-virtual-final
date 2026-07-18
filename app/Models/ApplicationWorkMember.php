<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationWorkMember extends Model
{
    protected $fillable = ['application_work_project_id', 'name_snapshot', 'study_program_snapshot', 'is_lead'];

    protected function casts(): array
    {
        return ['is_lead' => 'boolean'];
    }
}
