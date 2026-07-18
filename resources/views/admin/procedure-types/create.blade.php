@extends('layouts.authenticated')
@section('title', 'Crear tipo de trámite')
@section('content')
    <x-admin.catalog-header title="Crear tipo de trámite" description="Registra un trámite y su plazo de atención." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.procedure-types.store') }}" novalidate>
            @csrf
            @include('admin.procedure-types._form', ['procedureType' => null, 'submitLabel' => 'Guardar trámite'])
        </form>
    </div></div>
@endsection
