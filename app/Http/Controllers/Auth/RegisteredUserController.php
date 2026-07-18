<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Authentication\RoleDashboardRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RoleDashboardRedirector $redirector): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $applicantRole = Role::query()
                ->active()
                ->where('name', 'Solicitante')
                ->firstOrFail();

            return User::query()->create([
                ...$request->safe()->except(['password_confirmation']),
                'role_id' => $applicantRole->id,
                'area_id' => null,
                'active' => true,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($redirector->routeName($user));
    }
}
