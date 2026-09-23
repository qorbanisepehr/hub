<?php

namespace App\Integrations\Automation;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use InvalidArgumentException;

/**
 * Vocabulary boundary for the automation system's inbox (V2 §13/§61):
 * letter_no / sender / receive date (Jalali) / seen flag / cartable wording
 * die here — what crosses into Reporting is a UnifiedInboxItem.
 */
final class AutomationInboxMapper
{
    /** @var array<string, string> external cartable wording → unified bucket. */
    private const CARTABLE_MAP = [
        'جهت اقدام' => 'action_required',
        'جهت امضا' => 'signature',
        'جهت اطلاع' => 'information',
    ];

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws InvalidArgumentException when the row is unmappable.
     */
    public function map(array $row): UnifiedInboxItem
    {
        $receivedAt = PersianDateTime::parse(
            PersianText::foldDigits((string) $row['receive_date']),
            PersianText::foldDigits((string) $row['receive_time']),
        ) ?? throw new InvalidArgumentException('Unparseable automation receive_date/receive_time.');

        $cartableAction = self::CARTABLE_MAP[(string) $row['cartable']] ?? throw new InvalidArgumentException(
            "Unknown automation cartable value [{$row['cartable']}].",
        );

        $letterNo = (string) $row['letter_no'];

        return new UnifiedInboxItem(
            source: 'automation',
            senderName: (string) $row['sender'],
            documentNumber: $letterNo,
            title: (string) $row['subject'],
            documentType: (string) $row['kind'],
            receivedAt: $receivedAt,
            cartableAction: $cartableAction,
            status: ((int) $row['seen']) === 1 ? 'seen' : 'unseen',
            externalReference: "automation:{$letterNo}",
        );
    }
}
