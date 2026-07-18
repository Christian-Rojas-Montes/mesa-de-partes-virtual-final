<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\ProcedureRequest;
use App\Models\RequestDerivation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestDerivation> */
class RequestDerivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'procedure_request_id' => ProcedureRequest::factory(),
            'from_area_id' => null,
            'to_area_id' => Area::factory(),
            'user_id' => User::factory(),
            'reason' => 'Derivación ficticia al área encargada de la atención.',
            'derived_at' => now(),
            'received_at' => null,
        ];
    }
}
