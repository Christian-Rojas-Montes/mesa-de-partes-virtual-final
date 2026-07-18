@extends('layouts.authenticated')
@section('title', 'Crear requisito')
@section('content')
    <x-admin.catalog-header title="Crear requisito" description="Añade un requisito para el trámite seleccionado." />
    <div class="alert alert-light border" role="note"><strong>Tipo de trámite:</strong> {{ $procedureType->name }}</div>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.procedure-types.requirements.store', $procedureType) }}" novalidate>
            @csrf
            @include('admin.requirements._form', ['requirement' => null, 'submitLabel' => 'Guardar requisito'])
        </form>
    </div></div>
@endsection
