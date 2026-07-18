<?php

namespace Database\Factories;

use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureVariant> */
class ProcedureVariantFactory extends Factory
{
    public function definition(): array
    {
        return ['procedure_type_id' => ProcedureType::factory(), 'code' => strtoupper(fake()->unique()->bothify('VAR-##??')), 'name' => fake()->unique()->words(3, true), 'description' => fake()->sentence(), 'sort_order' => fake()->numberBetween(1, 100), 'requires_payment' => false, 'currency' => 'PEN', 'active' => true];
    }
}
