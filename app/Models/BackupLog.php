<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $fillable = ['started_at', 'finished_at', 'type', 'size_bytes', 'checksum_sha256', 'responsible', 'result', 'error', 'logical_location'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'size_bytes' => 'integer'];
    }
}
