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
        $this->faker = \Faker\Factory::create('fa_IR');

        $genderKey = $this->faker->randomElement(['male', 'female']);

        return [
            'personnel_code' => static::$lastPersonnelCode = str_pad(
                ((int) ltrim(static::$lastPersonnelCode ?? '0', '0')) + 1,
                5,
                '0',
                STR_PAD_LEFT,
            ),
            'first_name' => $this->faker->firstName($genderKey),
            'last_name' => $this->faker->lastName(),
            'gender' => $genderKey === 'male' ? 'مرد' : 'زن',
            'birth_date' => $this->faker->date('Y-m-d', '2000-01-01'),
            'id_number' => $this->generateValidIranianNationalId(),
            'marital_status' => $this->faker->randomElement(['مجرد', 'متاهل']),
            'email' => $this->faker->unique()->safeEmail(),
            'mobile' => '09'.$this->faker->numberBetween(100000000, 999999999),
            'employment_type' => $this->faker->randomElement(['official', 'contractual', 'project-based']),
            'hire_date' => $this->faker->date('Y-m-d', 'now'),
            'employment_status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    private function generateValidIranianNationalId(): string
    {
        do {
            $code = (string) $this->faker->numberBetween(100000000, 999999999);

        } while (strlen(count_chars($code, 3)) === 1);

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;

        $controlDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return $code.$controlDigit;
    }
}
