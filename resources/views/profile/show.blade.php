@extends('layouts.authenticated')

@section('title', 'Mi perfil')

@section('content')
    @php
        $documentNumber = (string) $user->document_number;
        $maskedDocument = str_repeat('*', max(0, mb_strlen($documentNumber) - 4)).mb_substr($documentNumber, -4);
    @endphp

    <header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="profile-identity-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</span>
            <div>
                <span class="section-eyebrow">Cuenta personal</span>
                <h1 class="h2 mt-1 mb-1">Mi perfil</h1>
                <p class="text-secondary mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
            </div>
        </div>
        <a class="btn btn-outline-primary align-self-md-start" href="{{ route('dashboard') }}">Volver al panel</a>
    </header>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="profile-data-title">
                <div class="card-body p-4">
                    <h2 class="h5 mb-4" id="profile-data-title">Datos de la cuenta</h2>
                    <dl class="row user-detail-list mb-0">
                        <dt class="col-sm-4">Correo electrónico</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                        <dt class="col-sm-4">Documento</dt><dd class="col-sm-8">{{ $user->document_type }} {{ $maskedDocument }}</dd>
                        <dt class="col-sm-4">Rol</dt><dd class="col-sm-8">{{ $user->role->name }}</dd>
                        <dt class="col-sm-4">Área</dt><dd class="col-sm-8">{{ $user->area?->name ?? 'Sin área asignada' }}</dd>
                        @if ($user->academic_program)
                            <dt class="col-sm-4">Carrera</dt><dd class="col-sm-8">{{ $user->academic_program }}</dd>
                        @endif
                    </dl>
                    <p class="small text-secondary mb-0">El correo, documento, rol y área se muestran solo como consulta. Para solicitar su actualización, comunícate con la administración.</p>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="profile-contact-title">
                <div class="card-body p-4">
                    <h2 class="h5" id="profile-contact-title">Teléfono de contacto</h2>
                    <p class="text-secondary">Actualiza el número asociado a las comunicaciones de tu cuenta.</p>
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <label class="form-label" for="phone">Teléfono <span class="text-secondary">(opcional)</span></label>
                        <input
                            class="form-control @error('phone') is-invalid @enderror"
                            id="phone"
                            name="phone"
                            type="tel"
                            autocomplete="tel"
                            maxlength="30"
                            value="{{ old('phone', $user->phone) }}"
                            aria-describedby="phone-help"
                        >
                        <div class="form-text mb-3" id="phone-help">Máximo 30 caracteres.</div>
                        @error('phone') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror
                        <button class="btn btn-primary" type="submit">Guardar teléfono</button>
                    </form>
                </div>
            </section>

            <section class="card border-0 shadow-sm" aria-labelledby="profile-password-title">
                <div class="card-body p-4">
                    <h2 class="h5" id="profile-password-title">Cambiar contraseña</h2>
                    <p class="text-secondary">Confirma tu contraseña actual. Se cerrarán las demás sesiones de tu cuenta.</p>
                    <form method="POST" action="{{ route('profile.password.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Contraseña actual</label>
                            <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Nueva contraseña</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                            <div class="form-text">Usa al menos 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</div>
                            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Actualizar contraseña</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
