<?php

namespace Tests\Unit\Support\Repositories;

use App\Domains\Questionnaire\Models\Questionnaire;
use App\Support\Repositories\SectionedDocumentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionedDocumentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_merges_defaults_and_honors_uuid_lookup(): void
    {
        $repo = new class extends SectionedDocumentRepository
        {
            protected function modelClass(): string
            {
                return Questionnaire::class;
            }

            protected function createDefaults(): array
            {
                return [...parent::createDefaults(), 'gender' => 'male'];
            }
        };

        $questionnaire = $repo->performCreate(['first_name' => 'Ali', 'last_name' => 'Reza']);

        $this->assertSame('male', $questionnaire->gender);
        $this->assertSame(1, $questionnaire->version);
        $this->assertNotEmpty($questionnaire->uuid);

        $found = $repo->performFindByUuid($questionnaire->uuid);
        $this->assertNotNull($found);
        $this->assertSame($questionnaire->getKey(), $found->getKey());
    }

    public function test_custom_status_column_and_version_column_are_honored(): void
    {
        $repo = new class extends SectionedDocumentRepository
        {
            protected function modelClass(): string
            {
                return Questionnaire::class;
            }

            protected function versionColumn(): ?string
            {
                return null;
            }
        };

        $questionnaire = $repo->performCreate(['first_name' => 'Ali', 'last_name' => 'Reza']);
        $result = $repo->performIncrementVersion($questionnaire);

        $this->assertSame(1, $result->version);

        $updated = $repo->performUpdateStatus($questionnaire, 'submitted');
        $this->assertSame('submitted', $updated->status);
    }

    public function test_update_section_persists_jsonb_payload(): void
    {
        $repo = new class extends SectionedDocumentRepository
        {
            protected function modelClass(): string
            {
                return Questionnaire::class;
            }
        };

        $questionnaire = $repo->performCreate(['first_name' => 'Ali', 'last_name' => 'Reza']);

        $updated = $repo->performUpdateSection($questionnaire, 'section_education', [
            'education_records' => [['degree' => 'bachelor']],
        ]);
        $this->assertSame(
            ['education_records' => [['degree' => 'bachelor']]],
            $updated->section_education,
        );
    }

    public function test_null_uuid_column_makes_lookup_return_null(): void
    {
        $repo = new class extends SectionedDocumentRepository
        {
            protected function modelClass(): string
            {
                return Questionnaire::class;
            }

            protected function uuidColumn(): ?string
            {
                return null;
            }
        };

        $this->assertNull($repo->performFindByUuid('anything'));
    }
}
