<?php

namespace App\Domains\Questionnaire\Repositories;

use App\Domains\Questionnaire\Models\Questionnaire;
use App\Support\Repositories\SectionedDocumentRepository;

/**
 * @extends SectionedDocumentRepository<Questionnaire>
 */
class QuestionnaireRepository extends SectionedDocumentRepository implements QuestionnaireRepositoryInterface
{
    protected function modelClass(): string
    {
        return Questionnaire::class;
    }

    public function create(array $data): Questionnaire
    {
        return parent::performCreate($data);
    }

    public function findByUuid(string $uuid): ?Questionnaire
    {
        return parent::performFindByUuid($uuid);
    }

    public function updateSection(
        Questionnaire $questionnaire,
        string $jsonbColumn,
        array $data,
    ): Questionnaire {
        return parent::performUpdateSection($questionnaire, $jsonbColumn, $data);
    }

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire
    {
        return parent::performUpdateStatus($questionnaire, $status);
    }

    public function incrementVersion(Questionnaire $questionnaire): Questionnaire
    {
        return parent::performIncrementVersion($questionnaire);
    }
}
