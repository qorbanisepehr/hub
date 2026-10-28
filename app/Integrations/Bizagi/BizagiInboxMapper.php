<?php

namespace App\Integrations\Bizagi;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Vocabulary boundary for Bizagi's BPM inbox (V2 §13/§61): case numbers,
 * OPEN/CLOSED task states, and ISO datetimes die here. Third distinct
 * external vocabulary — proof the unified shape is integration-neutral.
 */
final class BizagiInboxMapper
{
    private const TIMEZONE = 'Asia/Tehran';

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws InvalidArgumentException when the row is unmappable.
     */
    public function map(array $row): UnifiedInboxItem
    {
        try {
            $receivedAt = CarbonImmutable::parse((string) $row['creation_date'], self::TIMEZONE);
        } catch (\Exception $parseFailure) {
            throw new InvalidArgumentException('Unparseable Bizagi creation_date.', 0, $parseFailure);
        }

        $state = strtoupper((string) $row['task_state']);

        $status = match ($state) {
            'OPEN' => 'unseen',
            'CLOSED' => 'seen',
            default => throw new InvalidArgumentException("Unknown Bizagi task_state [{$row['task_state']}]."),
        };

        $caseNumber = (string) $row['case_number'];

        return new UnifiedInboxItem(
            source: 'bizagi',
            senderName: (string) $row['process_name'],
            // Username that may match an HR Hub user — resolved by the report.
            senderUsername: (string) $row['sender_username'],
            documentNumber: $caseNumber,
            title: (string) $row['task_name'],
            documentType: (string) $row['process_display'],
            receivedAt: $receivedAt,
            cartableAction: 'action_required',
            status: $status,
            externalReference: "bizagi:{$caseNumber}",
        );
    }
}
