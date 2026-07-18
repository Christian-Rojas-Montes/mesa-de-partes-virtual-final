<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesProcedureConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureTypeRequest extends FormRequest
{
    use ValidatesProcedureConfiguration;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('procedureType')) === true;
    }

    public function rules(): array
    {
        return $this->procedureTypeRules($this->route('procedureType'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProcedureType();
    }
}
