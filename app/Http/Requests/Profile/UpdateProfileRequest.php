<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => trim((string) $this->input('phone')) ?: null,
        ]);
    }
}
