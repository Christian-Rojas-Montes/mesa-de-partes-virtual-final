@extends('layouts.authenticated')
@section('title', 'Crear área')
@section('content')
    <x-admin.catalog-header title="Crear área" description="Registra una nueva área administrativa." />
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('admin.areas.store') }}" novalidate>
            @csrf
            @include('admin.areas._form', ['area' => null, 'submitLabel' => 'Guardar área'])
        </form>
    </div></div>
@endsection
