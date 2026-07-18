@props(['title', 'description', 'createRoute' => null, 'createLabel' => null])

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <nav aria-label="Ruta de navegación">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.administrator') }}">Administración</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
        <h1 class="h2 mb-2">{{ $title }}</h1>
        <p class="text-secondary mb-0">{{ $description }}</p>
    </div>

    @if ($createRoute)
        <a class="btn btn-primary flex-shrink-0" href="{{ $createRoute }}">{{ $createLabel }}</a>
    @endif
</div>
