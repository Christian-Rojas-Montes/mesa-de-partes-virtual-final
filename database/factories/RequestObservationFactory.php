<?php

namespace Database\Factories;

use App\Models\ProcedureRequest;
use App\Models\RequestObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestObservation> */
class RequestObservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'procedure_request_id' => ProcedureRequest::factory(),
            'user_id' => User::factory(),
            'description' => 'Observación ficticia para completar información.',
            'correction_instructions' => 'Adjunta un documento ficticio legible.',
            'correction_deadline' => now()->addDays(3),
            'resolved_at' => null,
        ];
    }
}
