<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="first_name">Nombres</label>
        <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $user?->first_name) }}" maxlength="100" required>
        @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="last_name">Apellidos</label>
        <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $user?->last_name) }}" maxlength="100" required>
        @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" for="document_type">Tipo de documento</label>
        <select class="form-select @error('document_type') is-invalid @enderror" id="document_type" name="document_type" required>
            @foreach (['DNI' => 'DNI', 'CE' => 'Carné de extranjería', 'PASAPORTE' => 'Pasaporte'] as $value => $label)
                <option value="{{ $value }}" @selected(old('document_type', $user?->document_type ?? 'DNI') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('document_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label" for="document_number">Número de documento</label>
        <input class="form-control @error('document_number') is-invalid @enderror" id="document_number" name="document_number" value="{{ old('document_number', $user?->document_number) }}" maxlength="30" required>
        @error('document_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-7 mb-3">
        <label class="form-label" for="email">Correo electrónico</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" maxlength="255" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5 mb-3">
        <label class="form-label" for="phone">Teléfono <span class="text-secondary">(opcional)</span></label>
        <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user?->phone) }}" maxlength="30">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="role_id">Rol</label>
        <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
            <option value="">Selecciona un rol</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" data-requires-area="{{ $role->name === 'Responsable de área' ? '1' : '0' }}" @selected(old('role_id', $user?->role_id) == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-4">
        <label class="form-label" for="area_id">Área</label>
        <select class="form-select @error('area_id') is-invalid @enderror" id="area_id" name="area_id">
            <option value="">Sin área asignada</option>
            @foreach ($areas as $area)
                <option value="{{ $area->id }}" @selected(old('area_id', $user?->area_id) == $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Obligatoria para responsables de área; no corresponde a solicitantes.</div>
        @error('area_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-light border" role="note">
    <strong>Acceso seguro:</strong> no se mostrará ninguna contraseña. El acceso se establecerá mediante un enlace temporal enviado al correo registrado.
</div>
<div class="d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ $cancelRoute }}">Cancelar</a>
</div>
