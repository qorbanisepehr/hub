<?php

namespace App\Casts;

use App\Domains\BoardMeeting\Value\Approval;

/**
 * `list<Approval>` — the registry numbers a meeting passed, each with its
 * own title and (once imported) its Document id.
 *
 * @extends ValueObjectListCast<Approval>
 */
class ApprovalListCast extends ValueObjectListCast
{
    /**
     * @param  array<string, mixed>  $data
     * @return Approval
     */
    protected function makeValue(array $data): object
    {
        return Approval::fromArray($data);
    }

    /**
     * @param  Approval  $value
     * @return array{number: string, title: ?string, document_id: ?int}
     */
    protected function valueToArray(object $value): array
    {
        return $value->toArray();
    }
}
