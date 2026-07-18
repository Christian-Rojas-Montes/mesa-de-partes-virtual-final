@extends('layouts.public')

@section('title', 'Restablecer contraseña')

@section('content')
    <section class="py-5" aria-labelledby="reset-title">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h1 id="reset-title" class="h3 mb-4">Restablecer contraseña</h1>

                        <form method="POST" action="{{ route('password.update') }}" novalidate>
                            @csrf
                            <input name="token" type="hidden" value="{{ $token }}">

                            <div class="mb-3">
                                <label class="form-label" for="email">Correo electrónico</label>
                                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Nueva contraseña</label>
                                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit">Guardar contraseña</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
