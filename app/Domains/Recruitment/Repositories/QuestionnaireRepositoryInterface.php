<?php

namespace App\Domains\Recruitment\Repositories;

use App\Domains\Recruitment\Models\Questionnaire;
use Illuminate\Support\Collection;

interface QuestionnaireRepositoryInterface
{
    public function create(array $data): Questionnaire;

    public function findByUuid(string $uuid): ?Questionnaire;

    public function updateSection(Questionnaire $questionnaire, string $sectionKey, array $data): Questionnaire;

    public function updateStatus(Questionnaire $questionnaire, string $status): Questionnaire;

    public function updateOtp(Questionnaire $questionnaire, string $type, string $otp): Questionnaire;

    public function verifyOtp(Questionnaire $questionnaire, string $type): Questionnaire;

    public function getForAdmin(int $page, int $perPage, ?string $search = null): Collection;

    public function incrementVersion(Questionnaire $questionnaire): Questionnaire;
}
