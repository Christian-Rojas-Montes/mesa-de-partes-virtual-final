@props(['user'])

@php
    $futureOptions = match ($user->role->name) {
        'Solicitante' => [],
        'Mesa de Partes' => [],
        'Responsable de área' => [],
        'Administrador' => [],
        default => [],
    };

    $adminOptions = [
        ['label' => 'Usuarios', 'route' => 'admin.users.index'],
        ['label' => 'Áreas', 'route' => 'admin.areas.index'],
        ['label' => 'Categorías de trámite', 'route' => 'admin.procedure-categories.index'],
        ['label' => 'Modalidades', 'route' => 'admin.presentation-modalities.index'],
        ['label' => 'Tipos de trámite', 'route' => 'admin.procedure-types.index'],
        ['label' => 'Estados', 'route' => 'admin.statuses.index'],
        ['label' => 'Auditoría', 'route' => 'admin.audit-logs.index'],
    ];

    $applicantOptions = [
        ['label' => 'Registrar solicitud', 'route' => 'applicant.procedure-requests.create'],
        ['label' => 'Mis trámites', 'route' => 'applicant.procedure-requests.index'],
    ];

    $frontDeskOptions = [
        ['label' => 'Bandeja de revisión', 'route' => 'front-desk.reviews.index'],
        ['label' => 'Derivaciones', 'route' => 'front-desk.derivations.index'],
        ['label' => 'Cierre de expedientes', 'route' => 'front-desk.closures.index'],
    ];

    $areaManagerOptions = [
        ['label' => 'Expedientes asignados', 'route' => 'area-manager.assignments.index'],
    ];
@endphp

<div class="sidebar-panel">
    <div class="sidebar-identity border-bottom">
        <span class="sidebar-label">Sesión activa</span>
        <strong>{{ $user->role->name }}</strong>
        @if ($user->area)<small>{{ $user->area->name }}</small>@endif
    </div>

    <nav class="p-3" aria-label="Opciones del rol {{ $user->role->name }}">
        <a class="sidebar-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard.*')) aria-current="page" @endif>
            <span aria-hidden="true">⌂</span><span>Inicio del panel</span>
        </a>

        <p class="sidebar-section-title">Consulta</p>
        <a class="sidebar-link {{ request()->routeIs('search.*') ? 'active' : '' }}" href="{{ route('search.index') }}"><span aria-hidden="true">›</span><span>Búsqueda</span></a>
        <a class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span aria-hidden="true">›</span><span>Reportes básicos</span></a>

        @if ($user->role->name === 'Solicitante')
            <p class="sidebar-section-title">Solicitudes</p>
            @foreach ($applicantOptions as $option)
                <a class="sidebar-link {{ request()->routeIs($option['route']) ? 'active' : '' }}" href="{{ route($option['route']) }}">
                    <span aria-hidden="true">›</span><span>{{ $option['label'] }}</span>
                </a>
            @endforeach
        @endif

        @if ($user->role->name === 'Administrador')
            <p class="sidebar-section-title">Administración</p>
            @foreach ($adminOptions as $option)
                <a class="sidebar-link {{ request()->routeIs($option['route']) || request()->routeIs(str_replace('.index', '.*', $option['route'])) ? 'active' : '' }}" href="{{ route($option['route']) }}">
                    <span aria-hidden="true">›</span><span>{{ $option['label'] }}</span>
                </a>
            @endforeach
        @endif

        @if ($user->role->name === 'Mesa de Partes')
            <p class="sidebar-section-title">Revisión</p>
            @foreach ($frontDeskOptions as $option)
                <a class="sidebar-link {{ request()->routeIs($option['route']) || request()->routeIs(str_replace('.index', '.*', $option['route'])) ? 'active' : '' }}" href="{{ route($option['route']) }}">
                    <span aria-hidden="true">›</span><span>{{ $option['label'] }}</span>
                </a>
            @endforeach
        @endif

        @if ($user->role->name === 'Responsable de área')
            <p class="sidebar-section-title">Expedientes</p>
            @foreach ($areaManagerOptions as $option)
                <a class="sidebar-link {{ request()->routeIs('area-manager.assignments.*') ? 'active' : '' }}" href="{{ route($option['route']) }}">
                    <span aria-hidden="true">›</span><span>{{ $option['label'] }}</span>
                </a>
            @endforeach
        @endif

        @if ($futureOptions)
            <p class="sidebar-section-title">Próximos módulos</p>
            @foreach ($futureOptions as $option)
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">
                    <span aria-hidden="true">○</span><span class="flex-grow-1">{{ $option }}</span><small>Próximamente</small>
                </span>
            @endforeach
        @endif
    </nav>
</div>
