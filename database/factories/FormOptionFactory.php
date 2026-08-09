<?php

namespace Database\Factories;

use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormOption>
 */
class FormOptionFactory extends Factory
{
    protected $model = FormOption::class;

    public function definition(): array
    {
        return [
            'group' => fake()->word(),
            'value' => fake()->unique()->slug(),
            'label' => fake()->word(),
            'parent_value' => null,
            'group_label' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
