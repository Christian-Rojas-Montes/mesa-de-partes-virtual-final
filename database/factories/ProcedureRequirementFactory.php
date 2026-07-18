<?php

namespace Database\Factories;

use App\Enums\RequirementType;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcedureRequirement> */
class ProcedureRequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'procedure_type_id' => ProcedureType::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'type' => RequirementType::DIGITAL_FILE,
            'required' => true,
            'sort_order' => fake()->numberBetween(1, 20),
            'copy_count' => 1,
            'requires_digital_file' => true,
            'requires_physical_submission' => false,
            'sensitive' => false,
            'active' => true,
        ];
    }
}
