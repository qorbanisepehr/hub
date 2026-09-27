<?php

namespace App\Integrations\Farzin;

use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Value\FarzinLetterRow;
use App\Domains\BoardMeeting\Value\FarzinSessionRow;
use Throwable;

/**
 * The port implementation bound when FARZIN_DRIVER is configured. Pure
 * pass-through: the source contract already returns external-vocabulary
 * arrays; this adapter lifts them into typed boundary DTOs.
 */
final class FarzinGatewayAdapter implements FarzinGateway
{
    public function __construct(
        private readonly FarzinGatewayContract $source,
    ) {}

    public function letters(?string $afterLastEdit = null): array
    {
        return $this->mapRows(
            $this->source->letters($afterLastEdit),
            FarzinLetterRow::fromSourceRow(...),
        );
    }

    public function sessions(?string $afterLastEdit = null): array
    {
        return $this->mapRows(
            $this->source->sessions($afterLastEdit),
            FarzinSessionRow::fromSourceRow(...),
        );
    }

    /**
     * Map raw rows to boundary DTOs, skipping unmappable rows: a row
     * without its primary key cannot be staged anyway and must not kill
     * the whole batch (same stance as the fake Rahkaran gateway).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<FarzinLetterRow|FarzinSessionRow>
     */
    private function mapRows(array $rows, callable $mapper): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            try {
                $mapped[] = $mapper($row);
            } catch (Throwable) {
                continue;
            }
        }

        return $mapped;
    }
}
