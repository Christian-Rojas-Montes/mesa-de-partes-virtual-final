@extends('layouts.public')

@section('title', 'Iniciar sesión')

@section('content')
    <section class="py-5" aria-labelledby="login-title">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h1 id="login-title" class="h3 mb-4">Iniciar sesión</h1>

                        <form method="POST" action="{{ route('login') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="email">Correo electrónico</label>
                                <input
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    autocomplete="email"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Contraseña</label>
                                <input
                                    class="form-control @error('password') is-invalid @enderror"
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                                <label class="form-check-label" for="remember">Recordar sesión</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" type="submit">Ingresar</button>
                                <a class="btn btn-link" href="{{ route('password.request') }}">Olvidé mi contraseña</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
