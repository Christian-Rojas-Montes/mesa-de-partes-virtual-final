@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="status">
        <strong>Operación completada.</strong> {{ session('status') }}
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Cerrar mensaje"></button>
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="alert alert-danger mt-3" role="alert" tabindex="-1">
        <strong>Revisa la información ingresada.</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
