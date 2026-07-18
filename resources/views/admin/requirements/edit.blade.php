@extends('layouts.authenticated')
@section('title', 'Editar requisito')
@section('content')
    <x-admin.catalog-header title="Editar requisito" description="Actualiza la descripción o condición del requisito." />
    <div class="alert alert-light border" role="note"><strong>Tipo de trámite:</strong> {{ $procedureType->name }}</div>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.procedure-types.requirements.update', [$procedureType, $requirement]) }}" novalidate>
            @csrf @method('PUT')
            @include('admin.requirements._form', ['submitLabel' => 'Guardar cambios'])
        </form>
    </div></div>
@endsection
