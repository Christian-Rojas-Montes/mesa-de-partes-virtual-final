@extends('layouts.authenticated')
@section('title', 'Editar área')
@section('content')
    <x-admin.catalog-header title="Editar área" description="Actualiza los datos del área sin eliminar su historial." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.areas.update', $area) }}" novalidate>
            @csrf @method('PUT')
            @include('admin.areas._form', ['submitLabel' => 'Guardar cambios'])
        </form>
    </div></div>
@endsection
