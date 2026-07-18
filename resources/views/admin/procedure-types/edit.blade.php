@extends('layouts.authenticated')
@section('title', 'Editar tipo de trámite')
@section('content')
    <x-admin.catalog-header title="Editar tipo de trámite" description="Actualiza el trámite sin eliminar sus relaciones." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.procedure-types.update', $procedureType) }}" novalidate>
            @csrf @method('PUT')
            @include('admin.procedure-types._form', ['submitLabel' => 'Guardar cambios'])
        </form>
    </div></div>
@endsection
