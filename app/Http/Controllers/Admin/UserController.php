<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserAccessRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\ToggleUserStatusRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\Area;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): View
    {
        $filters = $request->validated();
        $terms = preg_split('/\s+/', $filters['buscar'] ?? '', flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $users = User::query()->with(['role', 'area'])
            ->when($terms, fn ($query) => $query->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->where(fn ($query) => $query
                        ->where('document_number', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
                }
            }))
            ->when($filters['rol'] ?? null, fn ($query, $role) => $query->where('role_id', $role))
            ->when($filters['area'] ?? null, fn ($query, $area) => $query->where('area_id', $area))
            ->when(isset($filters['estado']), fn ($query) => $query->where('active', (bool) $filters['estado']))
            ->orderBy('last_name')->orderBy('first_name')->paginate(10)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        $user->load(['role', 'area']);

        return view('admin.users.show', compact('user'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => Role::active()->where('name', '!=', 'Solicitante')->orderBy('name')->get(),
            'areas' => Area::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request, UserAdministrationService $service): RedirectResponse
    {
        $user = $service->createInternalUser($request->validated());
        $status = $service->sendAccessReset($user);

        if ($status !== Password::RESET_LINK_SENT) {
            return to_route('admin.users.show', $user)
                ->withErrors(['access' => 'El usuario fue creado, pero no se pudo enviar el enlace de acceso. Puedes reenviarlo desde su detalle.']);
        }

        return to_route('admin.users.show', $user)
            ->with('status', 'El usuario fue creado y recibió un enlace temporal para establecer su acceso.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::active()->orderBy('name')->get(),
            'areas' => Area::active()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return to_route('admin.users.show', $user)->with('status', 'Los datos del usuario fueron actualizados.');
    }

    public function toggle(ToggleUserStatusRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->toggleActive($user);

        return back()->with('status', $user->active ? 'La cuenta fue activada.' : 'La cuenta fue desactivada.');
    }

    public function resetAccess(ResetUserAccessRequest $request, User $user, UserAdministrationService $service): RedirectResponse
    {
        $status = $service->sendAccessReset($user);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Se envió un enlace temporal de restablecimiento al correo registrado.')
            : back()->withErrors(['access' => 'No fue posible enviar el enlace de restablecimiento.']);
    }

    /** @return array{roles: mixed, areas: mixed} */
    private function filterOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->get(),
            'areas' => Area::query()->orderBy('name')->get(),
        ];
    }
}
