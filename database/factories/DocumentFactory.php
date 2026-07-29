<?php

namespace Database\Factories;

use App\Domains\Document\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'original_name' => fake()->word().'.'.fake()->fileExtension(),
            'mime_type' => fake()->mimeType(),
            'size' => fake()->numberBetween(1024, 10240000),
            'disk' => 'local',
            'path' => 'documents/'.fake()->uuid(),
            'hash' => hash('sha256', fake()->sha256()),
        ];
    }
}
