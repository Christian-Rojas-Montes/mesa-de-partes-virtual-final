@extends('layouts.public')

@section('title', 'Registro de solicitante')

@section('content')
    <section class="py-5" aria-labelledby="register-title">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h1 id="register-title" class="h3">Registro de solicitante</h1>
                        <p class="text-secondary mb-4">La cuenta creada tendrá únicamente el rol Solicitante.</p>

                        <form method="POST" action="{{ route('register') }}" novalidate>
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="document_type">Tipo de documento</label>
                                    <select class="form-select @error('document_type') is-invalid @enderror" id="document_type" name="document_type" required>
                                        <option value="">Seleccionar</option>
                                        @foreach (['DNI', 'CE', 'PASAPORTE', 'OTRO'] as $documentType)
                                            <option value="{{ $documentType }}" @selected(old('document_type') === $documentType)>
                                                {{ $documentType }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('document_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label" for="document_number">Número de documento</label>
                                    <input class="form-control @error('document_number') is-invalid @enderror" id="document_number" name="document_number" type="text" value="{{ old('document_number') }}" maxlength="30" required>
                                    @error('document_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">Nombres</label>
                                    <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" maxlength="100" required>
                                    @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">Apellidos</label>
                                    <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" maxlength="100" required>
                                    @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-7">
                                    <label class="form-label" for="email">Correo electrónico</label>
                                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label" for="phone">Teléfono <span class="text-secondary">(opcional)</span></label>
                                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="30">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password">Contraseña</label>
                                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required>
                                    <div class="form-text">Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.</div>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button class="btn btn-primary" type="submit">Crear cuenta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
