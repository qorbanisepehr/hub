<?php

namespace App\Domains\Cv\Repositories;

use App\Domains\Cv\Models\Cv;

class CvRepository implements CvRepositoryInterface
{
    public function create(array $data): Cv
    {
        return Cv::create([
            'status' => 'draft',
            'version' => 1,
        ] + $data);
    }

    public function findByUuid(string $uuid): ?Cv
    {
        return Cv::where('uuid', $uuid)->first();
    }

    public function updateSection(Cv $cv, string $jsonbColumn, array $data): Cv
    {
        $cv->update([
            $jsonbColumn => $data,
        ]);

        return $cv->fresh();
    }

    public function updateStatus(Cv $cv, string $status): Cv
    {
        $cv->update(['status' => $status]);

        return $cv->fresh();
    }

    public function incrementVersion(Cv $cv): Cv
    {
        $cv->incrementVersion();

        return $cv->fresh();
    }
}
