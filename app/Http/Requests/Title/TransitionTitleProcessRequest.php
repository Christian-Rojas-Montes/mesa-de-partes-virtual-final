<?php

namespace App\Http\Requests\Title;

use App\Enums\TitleProcessStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTitleProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        return ['stage' => ['required', Rule::enum(TitleProcessStage::class)], 'description' => ['required', 'string', 'max:1000']];
    }
}
