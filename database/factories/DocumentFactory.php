<?php

namespace Database\Factories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
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
            'documentable_id' => Employee::factory(),
            'documentable_type' => Employee::class,
            'document_category_id' => DocumentCategory::factory(),
            'original_name' => fake()->word().'.pdf',
            'stored_path' => 'employee-documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 5_242_880),
            'uploaded_by' => User::factory(),
        ];
    }
}
