<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestAppointment extends Model
{
    protected $fillable = ['procedure_request_id', 'rescheduled_from_id', 'appointment_date', 'starts_at', 'ends_at', 'office', 'area_id', 'reference_person', 'reason', 'instructions', 'deadline', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['appointment_date' => 'date', 'deadline' => 'date'];
    }

    public function procedureRequest(): BelongsTo
    {
        return $this->belongsTo(ProcedureRequest::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function previousAppointment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(self::class, 'rescheduled_from_id');
    }
}
