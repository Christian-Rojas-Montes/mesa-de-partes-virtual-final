@props(['type' => 'button', 'variant' => 'primary', 'href' => null])
@if($href)<a href="{{ $href }}" {{ $attributes->class(['btn btn-'.$variant]) }}>{{ $slot }}</a>@else<button type="{{ $type }}" {{ $attributes->class(['btn btn-'.$variant]) }}>{{ $slot }}</button>@endif
