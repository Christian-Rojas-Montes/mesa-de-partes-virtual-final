<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const GENERIC_STATUS = 'Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer la contraseña.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $user = User::query()->active()->where('email', $email)->first();

        if ($user !== null) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', self::GENERIC_STATUS);
    }
}
