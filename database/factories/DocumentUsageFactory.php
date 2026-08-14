<?php

namespace Database\Factories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentUsage>
 */
class DocumentUsageFactory extends Factory
{
    protected $model = DocumentUsage::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'entity_type' => Employee::class,
            'entity_id' => Employee::factory(),
            'section_key' => 'personal_info',
            'field_key' => 'birth_certificate',
        ];
    }
}
