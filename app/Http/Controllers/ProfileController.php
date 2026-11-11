<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfilePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\UserProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request, UserProfileService $profiles): RedirectResponse
    {
        $updated = $profiles->updatePhone($request->user(), $request->validated('phone'));

        return to_route('profile.show')->with(
            'status',
            $updated ? 'Tu teléfono se actualizó correctamente.' : 'No había cambios para guardar.',
        );
    }

    public function updatePassword(UpdateProfilePasswordRequest $request, UserProfileService $profiles): RedirectResponse
    {
        $profiles->updatePassword($request->user(), $request->validated('password'));
        $request->session()->regenerate();

        return to_route('profile.show')->with('status', 'Tu contraseña se actualizó y se cerraron las demás sesiones.');
    }
}
