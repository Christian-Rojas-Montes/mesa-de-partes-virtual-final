@props(['caption'])
<div class="table-responsive border rounded"><table {{ $attributes->class(['table table-hover align-middle mb-0']) }}><caption class="visually-hidden">{{ $caption }}</caption>{{ $slot }}</table></div>
