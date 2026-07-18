<?php

namespace App\Services;

use App\Models\ProcedureRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProcedureRequestQueryService
{
    public function scoped(User $user): Builder
    {
        $query = ProcedureRequest::query();

        return match ($user->role?->name) {
            'Solicitante' => $query->where('procedure_requests.user_id', $user->id),
            'Mesa de Partes', 'Administrador' => $query,
            'Responsable de área', 'Personal académico' => $query->whereHas(
                'latestDerivation',
                fn (Builder $query) => $query->where('to_area_id', $user->area_id),
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @param array<string, mixed> $filters */
    public function filtered(User $user, array $filters): Builder
    {
        return $this->applyFilters($this->scoped($user), $filters);
    }

    /** @param array<string, mixed> $filters */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['codigo'] ?? null, fn (Builder $query, string $value) => $query->where('tracking_code', 'like', "%{$value}%"))
            ->when($filters['documento'] ?? null, fn (Builder $query, string $value) => $query->whereHas('user', fn (Builder $query) => $query->where('document_number', 'like', "%{$value}%")))
            ->when($filters['nombre'] ?? null, function (Builder $query, string $value) {
                foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                    $query->whereHas('user', fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"));
                }
            })
            ->when($filters['estado'] ?? null, fn (Builder $query, int $value) => $query->where('status_id', $value))
            ->when($filters['tramite'] ?? null, fn (Builder $query, int $value) => $query->where('procedure_type_id', $value))
            ->when($filters['area'] ?? null, fn (Builder $query, int $value) => $query->whereHas('latestDerivation', fn (Builder $query) => $query->where('to_area_id', $value)))
            ->when($filters['desde'] ?? null, fn (Builder $query, string $value) => $query->whereDate('submitted_at', '>=', $value))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $value) => $query->whereDate('submitted_at', '<=', $value))
            ->when($filters['responsable'] ?? null, function (Builder $query, string $value) {
                $query->whereHas('latestDerivation.destinationArea.users', function (Builder $query) use ($value) {
                    $query->whereHas('role', fn (Builder $query) => $query->where('name', 'Responsable de área'));
                    foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                        $query->where(fn (Builder $query) => $query
                            ->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                    }
                });
            })
            ->when($filters['carrera'] ?? null, fn (Builder $q, string $v) => $q->whereHas('user', fn (Builder $u) => $u->where('academic_program', 'like', "%{$v}%")))
            ->when($filters['categoria'] ?? null, fn (Builder $q, int $v) => $q->whereHas('procedureType', fn (Builder $t) => $t->where('procedure_category_id', $v)))
            ->when($filters['variante'] ?? null, fn (Builder $q, int $v) => $q->where('procedure_variant_id', $v))
            ->when($filters['modalidad'] ?? null, fn (Builder $q, string $v) => $q->where(fn (Builder $x) => $x->whereHas('variant.presentationModality', fn (Builder $m) => $m->where('code', $v))->orWhere(fn (Builder $y) => $y->whereNull('procedure_variant_id')->whereHas('procedureType.presentationModality', fn (Builder $m) => $m->where('code', $v)))))
            ->when($filters['etapa'] ?? null, fn (Builder $q, string $v) => $q->whereHas('titleProcess', fn (Builder $t) => $t->where('current_stage', $v)))
            ->when($filters['canal'] ?? null, fn (Builder $q, string $v) => $q->where('configuration_snapshot->submission_channel', $v))
            ->when($filters['entrega_fisica'] ?? null, fn (Builder $q) => $q->whereDoesntHave('physicalReception')->where(fn (Builder $x) => $x->where('configuration_snapshot->modality_code', 'hybrid')->orWhere('configuration_snapshot->submission_channel', 'in_person')))
            ->when($filters['cita'] ?? null, fn (Builder $q) => $q->whereHas('appointments', fn (Builder $a) => $a->whereIn('status', ['scheduled', 'confirmed', 'rescheduled'])))
            ->when($filters['documento_listo'] ?? null, fn (Builder $q) => $q->whereHas('pickup', fn (Builder $p) => $p->where('status', 'ready')))
            ->when($filters['convalidacion'] ?? null, fn (Builder $q, string $v) => $q->whereHas('procedureType', fn (Builder $type) => $type->where('code', $v === 'internal' ? 'CONV_INTERNA' : 'CONV_EXTERNA')))
            ->when($filters['modalidad_titulacion'] ?? null, fn (Builder $q, string $v) => $q->whereHas('titleProcess', fn (Builder $t) => $t->where('modality', $v)));
    }

    /** @param array{desde?: string|null, hasta?: string|null} $filters */
    public function report(User $user, array $filters): array
    {
        $base = $this->scoped($user)
            ->when($filters['desde'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '<=', $date));

        $byStatus = (clone $base)->join('statuses', 'statuses.id', '=', 'procedure_requests.status_id')
            ->selectRaw('statuses.name as label, COUNT(*) as total')->groupBy('statuses.id', 'statuses.name')->orderBy('statuses.sort_order')->get();
        $byType = (clone $base)->join('procedure_types', 'procedure_types.id', '=', 'procedure_requests.procedure_type_id')
            ->selectRaw('procedure_types.name as label, COUNT(*) as total')->groupBy('procedure_types.id', 'procedure_types.name')->orderByDesc('total')->get();
        $byArea = (clone $base)
            ->join('request_derivations as report_derivations', function ($join) {
                $join->on('report_derivations.procedure_request_id', '=', 'procedure_requests.id')
                    ->whereRaw('report_derivations.id = (SELECT MAX(rd2.id) FROM request_derivations rd2 WHERE rd2.procedure_request_id = procedure_requests.id)');
            })
            ->join('areas', 'areas.id', '=', 'report_derivations.to_area_id')
            ->selectRaw('areas.name as label, COUNT(*) as total')->groupBy('areas.id', 'areas.name')->orderByDesc('total')->get();
        $byCategory = (clone $base)->join('procedure_types as category_types', 'category_types.id', '=', 'procedure_requests.procedure_type_id')->leftJoin('procedure_categories', 'procedure_categories.id', '=', 'category_types.procedure_category_id')->selectRaw("COALESCE(procedure_categories.name, 'Sin categoría') as label, COUNT(*) as total")->groupBy('procedure_categories.id', 'procedure_categories.name')->orderByDesc('total')->get();
        $byCareer = (clone $base)->join('users as applicants', 'applicants.id', '=', 'procedure_requests.user_id')->selectRaw("COALESCE(applicants.academic_program, 'No declarada') as label, COUNT(*) as total")->groupBy('applicants.academic_program')->orderByDesc('total')->get();
        $byModality = (clone $base)->join('procedure_types as modality_types', 'modality_types.id', '=', 'procedure_requests.procedure_type_id')->leftJoin('presentation_modalities', 'presentation_modalities.id', '=', 'modality_types.presentation_modality_id')->selectRaw("COALESCE(presentation_modalities.name, 'No definida') as label, COUNT(*) as total")->groupBy('presentation_modalities.id', 'presentation_modalities.name')->orderByDesc('total')->get();
        $byTitleModality = (clone $base)->join('title_processes', 'title_processes.procedure_request_id', '=', 'procedure_requests.id')->selectRaw('title_processes.modality as label, COUNT(*) as total')->groupBy('title_processes.modality')->orderByDesc('total')->get();
        $byTitleStage = (clone $base)->join('title_processes as stage_processes', 'stage_processes.procedure_request_id', '=', 'procedure_requests.id')->selectRaw('stage_processes.current_stage as label, COUNT(*) as total')->groupBy('stage_processes.current_stage')->orderByDesc('total')->get();
        $appointments = (clone $base)->whereHas('appointments')->count();
        $readyNotCollected = (clone $base)->whereHas('pickup', fn (Builder $q) => $q->where('status', 'ready'))->count();
        $pendingPhysical = (clone $base)->whereDoesntHave('physicalReception')->where(fn (Builder $q) => $q->where('configuration_snapshot->modality_code', 'hybrid')->orWhere('configuration_snapshot->submission_channel', 'in_person'))->count();
        $convalidations = (clone $base)->whereHas('procedureType', fn (Builder $q) => $q->whereIn('code', ['CONV_INTERNA', 'CONV_EXTERNA']))->count();

        $periodExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', procedure_requests.submitted_at)"
            : "DATE_FORMAT(procedure_requests.submitted_at, '%Y-%m')";
        $byPeriod = (clone $base)->selectRaw("{$periodExpression} as label, COUNT(*) as total")
            ->groupByRaw($periodExpression)->orderBy('label')->get();
        $summary = (clone $base)->join('statuses', 'statuses.id', '=', 'procedure_requests.status_id')
            ->selectRaw("SUM(CASE WHEN statuses.code IN ('ATENDIDO', 'FINALIZADO') THEN 1 ELSE 0 END) as attended")
            ->selectRaw("SUM(CASE WHEN statuses.code NOT IN ('ATENDIDO', 'FINALIZADO', 'RECHAZADO') THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COUNT(*) as total')->first();

        $durationExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'AVG((julianday(request_responses.responded_at) - julianday(procedure_requests.submitted_at)) * 24)'
            : 'AVG(TIMESTAMPDIFF(SECOND, procedure_requests.submitted_at, request_responses.responded_at) / 3600)';
        $averageHours = (clone $base)->join('request_responses', 'request_responses.procedure_request_id', '=', 'procedure_requests.id')
            ->selectRaw("{$durationExpression} as average_hours")->first()?->average_hours;

        return compact('byStatus', 'byType', 'byArea', 'byPeriod', 'byCategory', 'byCareer', 'byModality', 'byTitleModality', 'byTitleStage', 'appointments', 'readyNotCollected', 'pendingPhysical', 'convalidations', 'summary', 'averageHours');
    }
}
