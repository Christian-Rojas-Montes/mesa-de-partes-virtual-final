<?php

namespace Database\Factories;

use App\Models\ProcedureCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureCategory> */
class ProcedureCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('CAT-##??')), 'name' => fake()->unique()->words(3, true), 'description' => fake()->sentence(), 'sort_order' => fake()->numberBetween(1, 100), 'active' => true];
    }
}
