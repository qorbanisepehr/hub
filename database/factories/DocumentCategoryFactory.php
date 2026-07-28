<?php

namespace Database\Factories;

use App\Domains\Document\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    protected $model = DocumentCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(),
            'sort_order' => fake()->randomDigit(),
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ];
    }
}
