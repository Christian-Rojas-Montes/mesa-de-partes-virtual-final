@props(['status'])
@php($styles = ['available' => 'text-bg-success', 'upcoming' => 'text-bg-info', 'closed' => 'text-bg-secondary', 'suspended' => 'text-bg-warning'])
<span {{ $attributes->class(['badge', $styles[$status->value] ?? 'text-bg-secondary']) }} aria-label="Estado: {{ $status->label() }}">
    <span aria-hidden="true">{{ match($status->value) {'available' => '✓', 'upcoming' => '◷', 'closed' => '■', default => '!' } }}</span>
    {{ $status->label() }}
</span>
