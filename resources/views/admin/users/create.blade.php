@extends('layouts.authenticated')
@section('title', 'Crear usuario interno')
@section('content')
    <x-admin.catalog-header title="Crear usuario interno" description="Registra una cuenta para personal autorizado del sistema." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
            @csrf
            @include('admin.users._form', ['user' => null, 'submitLabel' => 'Crear usuario', 'cancelRoute' => route('admin.users.index')])
        </form>
    </div></div>
@endsection
