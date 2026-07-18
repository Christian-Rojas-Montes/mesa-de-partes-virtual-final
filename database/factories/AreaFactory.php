<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Area> */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('AR-##??')),
            'name' => fake()->unique()->bothify('Área ficticia ##??'),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }
}
