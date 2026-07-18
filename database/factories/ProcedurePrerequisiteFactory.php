<?php

namespace Database\Factories;

use App\Enums\PrerequisiteType;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedurePrerequisite> */
class ProcedurePrerequisiteFactory extends Factory
{
    public function definition(): array
    {
        return ['procedure_type_id' => ProcedureType::factory(), 'required_procedure_type_id' => ProcedureType::factory(), 'type' => PrerequisiteType::APPROVED_PROCEDURE, 'name' => fake()->words(3, true), 'description' => fake()->sentence(), 'required' => true, 'active' => true, 'sort_order' => fake()->numberBetween(1, 100)];
    }
}
