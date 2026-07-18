<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcedureRequestSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true
            && $this->user()?->role?->active === true
            && in_array($this->user()?->role?->name, [
                'Solicitante', 'Mesa de Partes', 'Responsable de área', 'Administrador', 'Personal académico',
            ], true);
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:30'],
            'documento' => ['nullable', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', 'integer', 'exists:statuses,id'],
            'tramite' => ['nullable', 'integer', 'exists:procedure_types,id'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'carrera' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', 'integer', 'exists:procedure_categories,id'],
            'variante' => ['nullable', 'integer', 'exists:procedure_variants,id'],
            'modalidad' => ['nullable', 'in:digital,hybrid,in_person'],
            'etapa' => ['nullable', 'string', 'max:50'],
            'canal' => ['nullable', 'in:digital,hybrid,in_person'],
            'entrega_fisica' => ['nullable', 'boolean'],
            'cita' => ['nullable', 'boolean'],
            'documento_listo' => ['nullable', 'boolean'],
            'convalidacion' => ['nullable', 'in:internal,external'],
            'modalidad_titulacion' => ['nullable', 'in:application_work,professional_exam'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))) ?: null,
            'documento' => trim((string) $this->input('documento')) ?: null,
            'nombre' => trim((string) $this->input('nombre')) ?: null,
            'estado' => $this->input('estado') ?: null,
            'tramite' => $this->input('tramite') ?: null,
            'area' => $this->input('area') ?: null,
            'desde' => $this->input('desde') ?: null,
            'hasta' => $this->input('hasta') ?: null,
            'responsable' => trim((string) $this->input('responsable')) ?: null,
            'carrera' => trim((string) $this->input('carrera')) ?: null,
            'categoria' => $this->input('categoria') ?: null,
            'variante' => $this->input('variante') ?: null,
            'modalidad' => $this->input('modalidad') ?: null,
            'etapa' => $this->input('etapa') ?: null,
            'canal' => $this->input('canal') ?: null,
            'entrega_fisica' => $this->boolean('entrega_fisica') ?: null,
            'cita' => $this->boolean('cita') ?: null,
            'documento_listo' => $this->boolean('documento_listo') ?: null,
            'convalidacion' => $this->input('convalidacion') ?: null,
            'modalidad_titulacion' => $this->input('modalidad_titulacion') ?: null,
        ]);
    }
}
