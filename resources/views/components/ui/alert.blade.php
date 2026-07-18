@props(['type' => 'info', 'title' => null])
<div role="alert" {{ $attributes->class(['alert alert-'.$type]) }}>@if($title)<strong class="d-block mb-1">{{ $title }}</strong>@endif{{ $slot }}</div>
