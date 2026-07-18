@extends('layouts.authenticated')

@section('title', 'Detalle del usuario')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Usuarios</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detalle</li>
            </ol></nav>
            <h1 class="h2 mb-2">{{ $user->first_name }} {{ $user->last_name }}</h1>
            <x-catalog-status :active="$user->active" />
        </div>
        <a class="btn btn-outline-primary align-self-md-start" href="{{ route('admin.users.edit', $user) }}">Editar usuario</a>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="user-data-title">
                <div class="card-body p-4">
                    <h2 class="h5 mb-4" id="user-data-title">Datos de la cuenta</h2>
                    <dl class="row mb-0 user-detail-list">
                        <dt class="col-sm-4">Documento</dt><dd class="col-sm-8">{{ $user->document_type }} {{ $user->document_number }}</dd>
                        <dt class="col-sm-4">Correo</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                        <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ $user->phone ?? 'No registrado' }}</dd>
                        <dt class="col-sm-4">Rol</dt><dd class="col-sm-8">{{ $user->role->name }}</dd>
                        <dt class="col-sm-4">Área</dt><dd class="col-sm-8">{{ $user->area?->name ?? 'Sin área asignada' }}</dd>
                        <dt class="col-sm-4">Creación</dt><dd class="col-sm-8">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="access-title">
                <div class="card-body p-4">
                    <h2 class="h5" id="access-title">Restablecimiento de acceso</h2>
                    <p class="text-secondary">Envía un enlace temporal al correo registrado. La contraseña actual no se muestra ni se comunica al administrador.</p>
                    @if ($user->active)
                        <form method="POST" action="{{ route('admin.users.reset-access', $user) }}">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">Enviar enlace de restablecimiento</button>
                        </form>
                    @else
                        <p class="mb-0"><strong>Cuenta inactiva:</strong> actívala antes de enviar un enlace de acceso.</p>
                    @endif
                </div>
            </section>

            <section class="card border-0 shadow-sm" aria-labelledby="status-action-title">
                <div class="card-body p-4">
                    <h2 class="h5" id="status-action-title">Estado de la cuenta</h2>
                    @if (auth()->id() === $user->id)
                        <p class="mb-0"><strong>Protección activa:</strong> no puedes desactivar tu propia cuenta administrativa.</p>
                    @else
                        <p class="text-secondary">{{ $user->active ? 'Al desactivar, se cerrarán sus sesiones y no podrá iniciar sesión.' : 'Al activar, el usuario recuperará la posibilidad de iniciar sesión.' }}</p>
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                            @csrf @method('PATCH')
                            <button class="btn {{ $user->active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">
                                {{ $user->active ? 'Desactivar cuenta' : 'Activar cuenta' }}
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
