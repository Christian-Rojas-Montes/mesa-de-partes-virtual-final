@props(['name', 'label', 'help' => null, 'required' => false])
<div {{ $attributes->class(['mb-3']) }}><label class="form-label" for="{{ $name }}">{{ $label }}@if($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>{{ $slot }}@if($help)<div class="form-text">{{ $help }}</div>@endif@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
