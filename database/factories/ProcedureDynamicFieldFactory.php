<?php

namespace Database\Factories;

use App\Enums\DynamicFieldType;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureDynamicField> */
class ProcedureDynamicFieldFactory extends Factory
{
    public function definition(): array
    {
        return ['procedure_type_id' => ProcedureType::factory(), 'key' => fake()->unique()->slug(2), 'type' => DynamicFieldType::TEXT, 'label' => fake()->words(3, true), 'required' => false, 'sort_order' => fake()->numberBetween(1, 100), 'active' => true];
    }
}
