@props(['title', 'date' => null])
<article {{ $attributes->class(['timeline-item']) }} role="listitem"><div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $title }}</strong>@if($date)<time class="small text-secondary">{{ $date }}</time>@endif</div><div class="mt-1 text-secondary">{{ $slot }}</div></article>
