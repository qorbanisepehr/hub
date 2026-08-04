<?php

namespace App\Domains\Cv\Repositories;

use App\Domains\Cv\Models\Cv;

interface CvRepositoryInterface
{
    public function create(array $data): Cv;

    public function findByUuid(string $uuid): ?Cv;

    public function updateSection(Cv $cv, string $jsonbColumn, array $data): Cv;

    public function updateStatus(Cv $cv, string $status): Cv;

    public function incrementVersion(Cv $cv): Cv;
}
