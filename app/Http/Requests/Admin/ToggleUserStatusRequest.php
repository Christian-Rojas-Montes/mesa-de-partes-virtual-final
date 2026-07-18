<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('toggle', $this->route('user')) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
