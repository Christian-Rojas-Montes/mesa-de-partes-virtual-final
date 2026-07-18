<?php

namespace Database\Factories;

use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Status> */
class StatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('ES-##??')),
            'name' => fake()->unique()->bothify('Estado ficticio ##??'),
            'description' => fake()->sentence(),
            'sort_order' => fake()->unique()->numberBetween(1, 1000),
            'active' => true,
        ];
    }
}
