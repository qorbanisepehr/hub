<?php

namespace Database\Factories;

use App\Domains\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected static ?string $lastPersonnelCode = null;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'personnel_code' => static::$lastPersonnelCode = str_pad(
                ((int) ltrim(static::$lastPersonnelCode ?? '0', '0')) + 1,
                5,
                '0',
                STR_PAD_LEFT,
            ),
            'first_name' => fake()->firstName($gender),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'birth_date' => fake()->date(max: '2000-01-01'),
            'id_number' => fake()->numerify('##########'),
            'marital_status' => fake()->randomElement(['single', 'married']),
            'education_level' => fake()->randomElement(['diploma', 'associate', 'bachelor', 'master', 'doctorate']),
            'education_field' => fake()->word(),
            'employment_type' => fake()->randomElement(['official', 'contractual', 'project-based']),
            'hire_date' => fake()->date(max: 'now'),
            'employment_status' => fake()->randomElement(['active', 'inactive', 'suspended']),
        ];
    }
}
