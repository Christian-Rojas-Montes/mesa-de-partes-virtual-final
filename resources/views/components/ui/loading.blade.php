@props(['label' => 'Cargando'])
<span {{ $attributes->class(['loading-indicator']) }} role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>{{ $label }}</span></span>
