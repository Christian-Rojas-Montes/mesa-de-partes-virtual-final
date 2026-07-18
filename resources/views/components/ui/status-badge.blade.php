@props(['status', 'type' => 'primary'])
<span {{ $attributes->class(['badge rounded-pill text-bg-'.$type]) }}><span aria-hidden="true">●</span> {{ $status }}</span>
