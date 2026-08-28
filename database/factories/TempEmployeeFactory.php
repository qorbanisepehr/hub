<?php

namespace Database\Factories;

use App\Domains\TempEmployees\Models\TempEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TempEmployee>
 */
class TempEmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personnel_code' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'id_number' => (string) $this->faker->numerify('##########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ];
    }
}
