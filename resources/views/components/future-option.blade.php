@props(['title', 'description', 'href' => null])

<article class="card {{ $href ? 'available-option-card' : 'future-option-card' }} h-100 border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <h2 class="h5 mb-0">{{ $title }}</h2>
            <span class="badge {{ $href ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $href ? 'Disponible' : 'No disponible' }}</span>
        </div>
        <p class="text-secondary mb-3">{{ $description }}</p>
        @if ($href)
            <a class="btn btn-sm btn-outline-primary" href="{{ $href }}">Abrir módulo</a>
        @else
            <span class="future-option-notice" aria-label="Función próxima">
                <span aria-hidden="true">○</span> Próximamente
            </span>
        @endif
    </div>
</article>
