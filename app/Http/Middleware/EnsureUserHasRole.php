<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** @var array<string, string> */
    private const ROLE_NAMES = [
        'solicitante' => 'Solicitante',
        'mesa-partes' => 'Mesa de Partes',
        'responsable-area' => 'Responsable de área',
        'administrador' => 'Administrador',
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(
            fn (string $role): ?string => self::ROLE_NAMES[$role] ?? null,
            $roles,
        );

        $role = $request->user()?->role;

        abort_unless(
            $role?->active === true && in_array($role->name, $allowedRoles, true),
            Response::HTTP_FORBIDDEN,
            'No tienes permiso para acceder a esta sección.',
        );

        return $next($request);
    }
}
