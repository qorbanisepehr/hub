<?php

namespace App\Domains\Cv\Repositories;

use App\Domains\Cv\Models\Cv;
use App\Support\Repositories\SectionedDocumentRepository;

/**
 * @extends SectionedDocumentRepository<Cv>
 */
class CvRepository extends SectionedDocumentRepository implements CvRepositoryInterface
{
    protected function modelClass(): string
    {
        return Cv::class;
    }

    public function create(array $data): Cv
    {
        return parent::performCreate($data);
    }

    public function findByUuid(string $uuid): ?Cv
    {
        return parent::performFindByUuid($uuid);
    }

    public function updateSection(Cv $cv, string $jsonbColumn, array $data): Cv
    {
        return parent::performUpdateSection($cv, $jsonbColumn, $data);
    }

    public function updateStatus(Cv $cv, string $status): Cv
    {
        return parent::performUpdateStatus($cv, $status);
    }

    public function incrementVersion(Cv $cv): Cv
    {
        return parent::performIncrementVersion($cv);
    }
}
