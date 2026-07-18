@props(['authenticated' => false])

<header class="institutional-topbar">
    <nav class="navbar navbar-expand" aria-label="Barra superior">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                @if ($authenticated)
                    <button
                        class="btn btn-outline-light d-lg-none"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#mobile-sidebar"
                        aria-controls="mobile-sidebar"
                        aria-label="Abrir menú principal"
                    >
                        <span aria-hidden="true">☰</span>
                    </button>
                @endif

                <a class="navbar-brand d-flex align-items-center gap-2 text-white" href="{{ $authenticated ? route('dashboard') : route('home') }}">
                    <img
                        class="brand-mark"
                        src="{{ asset('images/logo-pedro-p-diaz.jpg') }}"
                        alt="Logotipo del IESTP Pedro P. Díaz"
                    >
                    <span class="brand-copy">
                        <strong>Mesa de Partes Virtual</strong>
                        <small>IESTP “Pedro P. Díaz”</small>
                    </span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if ($authenticated)
                    @php($unreadCount = auth()->user()->unreadNotifications()->count())
                    <a class="btn btn-sm btn-outline-light position-relative" href="{{ route('notifications.index') }}" aria-label="Notificaciones: {{ $unreadCount }} no leídas">
                        <span aria-hidden="true">🔔</span><span class="d-none d-sm-inline"> Notificaciones</span>
                        @if($unreadCount > 0)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $unreadCount > 99 ? '99+' : $unreadCount }}<span class="visually-hidden">notificaciones no leídas</span></span>@endif
                    </a>
                    <div class="user-summary d-none d-md-block text-end text-white">
                        <span>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                        <small>{{ auth()->user()->role->name }}</small>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-light" type="submit">Cerrar sesión</button>
                    </form>
                @else
                    @auth
                        <a class="btn btn-sm btn-light" href="{{ route('dashboard') }}">Ir a mi panel</a>
                    @else
                        <a class="btn btn-sm btn-outline-light d-none d-sm-inline-flex" href="{{ route('login') }}">Iniciar sesión</a>
                        <a class="btn btn-sm btn-light" href="{{ route('register') }}">Crear cuenta</a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>
</header>
