<?php

namespace Database\Factories;

use App\Models\ProcedureRequest;
use App\Models\ProcedureType;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureRequest> */
class ProcedureRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'procedure_type_id' => ProcedureType::factory(),
            'status_id' => Status::factory(),
            'tracking_code' => fake()->unique()->numerify('MPV-2026-######'),
            'subject' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }
}
