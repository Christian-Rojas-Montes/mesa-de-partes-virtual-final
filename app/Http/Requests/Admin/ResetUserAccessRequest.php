<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResetUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resetAccess', $this->route('user')) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
