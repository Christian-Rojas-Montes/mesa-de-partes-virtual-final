<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesProcedureConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRequirementRequest extends FormRequest
{
    use ValidatesProcedureConfiguration;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('requirement')) === true;
    }

    public function rules(): array
    {
        return $this->requirementRules($this->route('requirement'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRequirement();
    }
}
