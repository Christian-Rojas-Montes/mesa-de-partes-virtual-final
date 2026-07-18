@props(['title', 'message' => null])
<div {{ $attributes->class(['empty-state']) }}><span class="empty-state-mark" aria-hidden="true">—</span><strong class="d-block">{{ $title }}</strong>@if($message)<p class="mb-0 mt-2">{{ $message }}</p>@endif{{ $slot }}</div>
