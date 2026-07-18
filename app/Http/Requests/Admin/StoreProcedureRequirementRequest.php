<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesProcedureConfiguration;
use App\Models\ProcedureRequirement;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureRequirementRequest extends FormRequest
{
    use ValidatesProcedureConfiguration;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcedureRequirement::class) === true;
    }

    public function rules(): array
    {
        return $this->requirementRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRequirement();
    }
}
