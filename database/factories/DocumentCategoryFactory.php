<?php

namespace Database\Factories;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    protected $model = DocumentCategory::class;

    protected static ?int $lastSortOrder = null;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(),
            'sort_order' => static::$lastSortOrder = (static::$lastSortOrder ?? 0) + 1,
            'documentable_type' => Employee::class,
        ];
    }
}
