<?php

namespace App\Integrations\Farzin;

use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Value\FarzinLetterRow;
use App\Domains\BoardMeeting\Value\FarzinSessionRow;

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
        return array_map(
            fn (array $row) => FarzinLetterRow::fromSourceRow($row),
            $this->source->letters($afterLastEdit),
        );
    }

    public function sessions(?string $afterLastEdit = null): array
    {
        return array_map(
            fn (array $row) => FarzinSessionRow::fromSourceRow($row),
            $this->source->sessions($afterLastEdit),
        );
    }
}
