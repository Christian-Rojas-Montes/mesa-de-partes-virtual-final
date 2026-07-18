@extends('layouts.authenticated')
@section('title', 'Editar usuario')
@section('content')
    <x-admin.catalog-header title="Editar usuario" description="Actualiza datos, rol y asignación de área." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
            @csrf @method('PUT')
            @include('admin.users._form', ['submitLabel' => 'Guardar cambios', 'cancelRoute' => route('admin.users.show', $user)])
        </form>
    </div></div>
@endsection
