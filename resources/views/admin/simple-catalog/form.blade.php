@extends('layouts.authenticated')
@section('title', $title)
@section('content')
<x-admin.catalog-header :title="$title" description="Completa los datos configurables del catálogo." />
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ $item ? route($routeBase.'.update', $item) : route($routeBase.'.store') }}" novalidate>@csrf @if($item) @method('PUT') @endif
<div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="code">Código</label>@if($codes)<select class="form-select" id="code" name="code" required>@foreach($codes as $code)<option value="{{ $code->value }}" @selected(old('code', $item?->code?->value) === $code->value)>{{ $code->value }}</option>@endforeach</select>@else<input class="form-control" id="code" name="code" value="{{ old('code', $item?->code) }}" maxlength="30" required>@endif @error('code')<div class="text-danger small">{{ $message }}</div>@enderror</div><div class="col-md-8 mb-3"><label class="form-label" for="name">Nombre</label><input class="form-control" id="name" name="name" value="{{ old('name', $item?->name) }}" maxlength="150" required>@error('name')<div class="text-danger small">{{ $message }}</div>@enderror</div></div>
<div class="mb-3"><label class="form-label" for="description">Descripción</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $item?->description) }}</textarea></div>
@if($hasOrder)<div class="mb-3"><label class="form-label" for="sort_order">Orden</label><input class="form-control" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item?->sort_order ?? 0) }}" required></div>@endif
<div class="d-flex gap-2"><button class="btn btn-primary">Guardar</button><a class="btn btn-outline-secondary" href="{{ route($routeBase.'.index') }}">Cancelar</a></div></form></div></div>
@endsection
