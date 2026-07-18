<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Services\AuditLogPresenter;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogPresenter $presenter): View
    {
        $filters = $request->validated();
        $logs = AuditLog::query()->with('user')
            ->when($filters['usuario'] ?? null, function ($query, $value) {
                foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                    $query->whereHas('user', fn ($query) => $query
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('document_number', 'like', "%{$term}%"));
                }
            })
            ->when($filters['accion'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($filters['entidad'] ?? null, fn ($query, $value) => $query->where('auditable_type', $value))
            ->when($filters['desde'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['hasta'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')->latest('id')->paginate(20)->withQueryString();

        $logs->getCollection()->each(function (AuditLog $log) use ($presenter) {
            $log->setAttribute('display_action', $presenter->action($log->action));
            $log->setAttribute('display_entity', $presenter->entity($log->auditable_type));
            $log->setAttribute('safe_details', $presenter->safeDetails($log->details));
        });

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'entities' => $presenter->entities(),
            'presenter' => $presenter,
        ]);
    }
}
