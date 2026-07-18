<?php

namespace Database\Factories;

use App\Models\ProcedureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureType> */
class ProcedureTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TR-##??')),
            'name' => fake()->unique()->bothify('Trámite ficticio ##??'),
            'description' => fake()->sentence(),
            'attention_days' => fake()->numberBetween(1, 30),
            'sort_order' => fake()->numberBetween(1, 100),
            'requires_payment' => false,
            'currency' => 'PEN',
            'active' => true,
        ];
    }
}
