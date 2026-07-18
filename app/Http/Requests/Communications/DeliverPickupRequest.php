<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class DeliverPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['received_by_name' => ['required', 'string', 'max:200'], 'identity_document_verified' => ['accepted'], 'delivered_at' => ['required', 'date', 'before_or_equal:now'], 'observation' => ['nullable', 'string', 'max:2000']];
    }
}
