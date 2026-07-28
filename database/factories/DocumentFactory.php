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
            'status' => Document::STATUS_PENDING,
            'uploaded_by' => User::factory(),
        ];
    }
}
