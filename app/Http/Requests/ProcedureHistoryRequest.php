<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcedureHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunications', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return ['tipo' => ['nullable', Rule::in(['status', 'observation', 'correction', 'derivation', 'physical', 'appointment', 'response', 'pickup', 'delivery', 'internal', 'notification'])], 'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde']];
    }
}
