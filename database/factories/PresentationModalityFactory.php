<?php

namespace Database\Factories;

use App\Enums\PresentationModeCode;
use App\Models\PresentationModality;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PresentationModality> */
class PresentationModalityFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->randomElement(PresentationModeCode::cases()), 'name' => fake()->unique()->words(2, true), 'description' => fake()->sentence(), 'active' => true];
    }
}
