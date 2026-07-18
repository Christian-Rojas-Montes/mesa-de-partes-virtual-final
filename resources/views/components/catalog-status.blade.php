@props(['active'])

@if ($active)
    <span class="badge text-bg-success"><span aria-hidden="true">✓</span> Activo</span>
@else
    <span class="badge text-bg-secondary"><span aria-hidden="true">—</span> Inactivo</span>
@endif
