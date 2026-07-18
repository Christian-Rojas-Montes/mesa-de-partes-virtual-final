@props(['title' => null])
<section {{ $attributes->class(['card border-0 shadow-sm institutional-card-accent']) }}>@if($title)<div class="card-header bg-white py-3"><h2 class="h5 mb-0">{{ $title }}</h2></div>@endif<div class="card-body p-4">{{ $slot }}</div></section>
