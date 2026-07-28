<?php

namespace Database\Factories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revision>
 */
class RevisionFactory extends Factory
{
    protected $model = Revision::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'stored_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 5_242_880),
            'uploaded_by' => User::factory(),
        ];
    }
}
