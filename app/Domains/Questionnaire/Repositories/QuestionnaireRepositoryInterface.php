<?php

namespace App\Domains\Questionnaire\Repositories;

use App\Domains\Questionnaire\Models\Questionnaire;

interface QuestionnaireRepositoryInterface
{
    public function create(array $data): Questionnaire;

    public function findByUuid(string $uuid): ?Questionnaire;

    public function updateSection(Questionnaire $questionnaire, string $sectionKey, array $data): Questionnaire;

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire;

    public function incrementVersion(Questionnaire $questionnaire): Questionnaire;
}
