<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationWorkRequirement extends Model
{
    protected $fillable = ['application_work_project_id', 'stage', 'code', 'label_snapshot', 'physical', 'quantity', 'status', 'request_document_id', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['physical' => 'boolean', 'quantity' => 'integer', 'verified_at' => 'datetime'];
    }
}
