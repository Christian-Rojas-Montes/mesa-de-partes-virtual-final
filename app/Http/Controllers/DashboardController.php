<?php

namespace App\Http\Controllers;

use App\Services\Authentication\RoleDashboardRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request, RoleDashboardRedirector $redirector): RedirectResponse
    {
        return redirect()->route($redirector->routeName($request->user()));
    }

    public function applicant(): View
    {
        return $this->dashboard('Panel del solicitante', 'Espacio personal para la futura presentación y consulta de trámites.', [
            ['title' => 'Registrar solicitud', 'description' => 'Presentar un nuevo trámite y adjuntar sus requisitos.', 'route' => 'applicant.procedure-requests.create'],
            ['title' => 'Mis trámites', 'description' => 'Consultar las solicitudes registradas y su estado.', 'route' => 'applicant.procedure-requests.index'],
            ['title' => 'Notificaciones', 'description' => 'Revisar avisos relacionados con los trámites.', 'route' => 'notifications.index'],
        ]);
    }

    public function frontDesk(): View
    {
        return $this->dashboard('Panel de Mesa de Partes', 'Espacio de trabajo para la futura revisión y derivación documentaria.', [
            ['title' => 'Registrar expediente presencial', 'description' => 'Vincular al ciudadano, registrar documentos físicos y emitir constancia.', 'route' => 'front-desk.physical-registration.create'],
            ['title' => 'Solicitudes recibidas', 'description' => 'Revisar expedientes pendientes de validación.', 'route' => 'front-desk.reviews.index'],
            ['title' => 'Observaciones', 'description' => 'Gestionar observaciones desde la bandeja de revisión.', 'route' => 'front-desk.reviews.index'],
            ['title' => 'Derivaciones', 'description' => 'Enviar expedientes válidos al área responsable.', 'route' => 'front-desk.derivations.index'],
            ['title' => 'Cierre de expedientes', 'description' => 'Verificar respuestas y finalizar trámites atendidos.', 'route' => 'front-desk.closures.index'],
        ]);
    }

    public function areaManager(): View
    {
        return $this->dashboard('Panel del responsable de área', 'Espacio de trabajo para la futura atención de expedientes derivados.', [
            ['title' => 'Expedientes asignados', 'description' => 'Consultar los trámites derivados al área.', 'route' => 'area-manager.assignments.index'],
            ['title' => 'Registrar atención', 'description' => 'Actualizar el avance de un expediente.', 'route' => 'area-manager.assignments.index'],
            ['title' => 'Emitir respuesta', 'description' => 'Registrar el resultado final del trámite.', 'route' => 'area-manager.assignments.index'],
        ]);
    }

    public function administrator(): View
    {
        return $this->dashboard('Panel de administración', 'Gestiona los catálogos habilitados y consulta las próximas funciones administrativas.', [
            ['title' => 'Registrar expediente presencial', 'description' => 'Registrar una recepción física con trazabilidad.', 'route' => 'front-desk.physical-registration.create'],
            ['title' => 'Usuarios y roles', 'description' => 'Gestionar cuentas, responsabilidades y acceso.', 'route' => 'admin.users.index'],
            ['title' => 'Áreas', 'description' => 'Gestionar las áreas responsables.', 'route' => 'admin.areas.index'],
            ['title' => 'Tipos de trámite', 'description' => 'Configurar trámites y sus requisitos.', 'route' => 'admin.procedure-types.index'],
            ['title' => 'Estados', 'description' => 'Consultar el flujo de estados del trámite.', 'route' => 'admin.statuses.index'],
            ['title' => 'Reportes básicos', 'description' => 'Consultar indicadores documentarios.', 'route' => 'reports.index'],
            ['title' => 'Auditoría', 'description' => 'Consultar la trazabilidad de acciones del sistema.', 'route' => 'admin.audit-logs.index'],
        ]);
    }

    /** @param array<int, array{title: string, description: string, route?: string}> $options */
    private function dashboard(string $title, string $description, array $options): View
    {
        return view('dashboard.index', compact('title', 'description', 'options'));
    }
}
