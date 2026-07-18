@props(['title', 'eyebrow' => null, 'description' => null])
<header {{ $attributes->class(['page-heading mb-4']) }}>@if($eyebrow)<span class="section-eyebrow">{{ $eyebrow }}</span>@endif<h1 class="h2 mt-2 mb-2">{{ $title }}</h1>@if($description)<p class="text-secondary mb-0">{{ $description }}</p>@endif</header>
