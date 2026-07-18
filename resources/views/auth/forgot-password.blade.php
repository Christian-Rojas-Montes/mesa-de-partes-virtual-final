@extends('layouts.public')

@section('title', 'Recuperar contraseña')

@section('content')
    <section class="py-5" aria-labelledby="forgot-title">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h1 id="forgot-title" class="h3">Recuperar contraseña</h1>
                        <p class="text-secondary">Ingresa tu correo para recibir un enlace temporal.</p>

                        <form method="POST" action="{{ route('password.email') }}" novalidate>
                            @csrf

                            <div class="mb-4">
                                <label class="form-label" for="email">Correo electrónico</label>
                                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit">Enviar enlace</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
