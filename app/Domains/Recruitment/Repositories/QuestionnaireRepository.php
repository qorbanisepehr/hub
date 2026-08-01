<?php

namespace App\Domains\Recruitment\Repositories;

use App\Domains\Recruitment\Models\Questionnaire;

class QuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    public function create(array $data): Questionnaire
    {
        return Questionnaire::create([
            'status' => 'draft',
            'version' => 1,
        ] + $data);
    }

    public function findByUuid(string $uuid): ?Questionnaire
    {
        return Questionnaire::where('uuid', $uuid)->first();
    }

    public function updateSection(Questionnaire $questionnaire, string $jsonbColumn, array $data): Questionnaire
    {
        $questionnaire->update([
            $jsonbColumn => $data,
        ]);

        return $questionnaire->fresh();
    }

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire
    {
        $questionnaire->update(['status' => $status]);

        return $questionnaire->fresh();
    }

    public function incrementVersion(Questionnaire $questionnaire): Questionnaire
    {
        $questionnaire->incrementVersion();

        return $questionnaire->fresh();
    }
}
