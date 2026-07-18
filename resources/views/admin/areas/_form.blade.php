<div class="mb-3">
    <label class="form-label" for="code">Código</label>
    <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $area?->code) }}" maxlength="30" required>
    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="name">Nombre</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $area?->name) }}" maxlength="150" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-4">
    <label class="form-label" for="description">Descripción</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" maxlength="2000" required>{{ old('description', $area?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.areas.index') }}">Cancelar</a>
</div>
