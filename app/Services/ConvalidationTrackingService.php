<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcedureRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvalidationTrackingService
{
    public function assignAcademicFileNumber(ProcedureRequest $request, User $actor, string $number): void
    {
        if ($request->procedureType->category?->code !== 'CONVALIDACIONES') {
            throw ValidationException::withMessages(['academic_file_number' => 'Esta acción solo corresponde a Convalidaciones.']);
        }
        DB::transaction(function () use ($request, $actor, $number): void {
            $request->update(['academic_file_number' => $number, 'academic_file_assigned_at' => now(), 'academic_file_assigned_by' => $actor->id]);
            $request->histories()->create(['status_id' => $request->status_id, 'user_id' => $actor->id, 'action' => 'academic_file_assigned', 'description' => "Se asignó el expediente académico o externo {$number}."]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'academic_file_assigned', 'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id, 'details' => ['academic_file_number' => $number], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
        });
    }
}
