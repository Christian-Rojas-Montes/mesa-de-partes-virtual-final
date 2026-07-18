<?php

namespace App\Http\Requests\Admin;

use App\Enums\PresentationModeCode;
use App\Models\PresentationModality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePresentationModalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('presentationModality');

        return $this->user()?->can($model ? 'update' : 'create', $model ?: PresentationModality::class) === true;
    }

    public function rules(): array
    {
        return ['code' => ['required', Rule::enum(PresentationModeCode::class), Rule::unique('presentation_modalities', 'code')->ignore($this->route('presentationModality'))], 'name' => ['required', 'string', 'max:100', Rule::unique('presentation_modalities', 'name')->ignore($this->route('presentationModality'))], 'description' => ['nullable', 'string', 'max:2000']];
    }
}
