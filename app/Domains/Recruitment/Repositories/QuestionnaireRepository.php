<?php

namespace App\Domains\Recruitment\Repositories;

use App\Domains\Recruitment\Models\Questionnaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function updateOtp(Questionnaire $questionnaire, string $type, string $otp): Questionnaire
    {
        $field = $type === 'mobile' ? 'mobile_otp' : 'email_otp';
        $questionnaire->update([$field => $otp]);

        return $questionnaire->fresh();
    }

    public function verifyOtp(Questionnaire $questionnaire, string $type): Questionnaire
    {
        $field = $type === 'mobile' ? 'mobile_verified_at' : 'email_verified_at';
        $questionnaire->update([$field => Carbon::now()]);

        return $questionnaire->fresh();
    }

    public function getForAdmin(int $page, int $perPage, ?string $search = null): Collection
    {
        $query = Questionnaire::query()->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('national_id', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('mobile', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function incrementVersion(Questionnaire $questionnaire): Questionnaire
    {
        $questionnaire->incrementVersion();

        return $questionnaire->fresh();
    }
}
