<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesProcedureConfiguration;
use App\Models\ProcedureType;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureTypeRequest extends FormRequest
{
    use ValidatesProcedureConfiguration;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcedureType::class) === true;
    }

    public function rules(): array
    {
        return $this->procedureTypeRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProcedureType();
    }
}
