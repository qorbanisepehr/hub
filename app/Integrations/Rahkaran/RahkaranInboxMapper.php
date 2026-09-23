<?php

namespace App\Integrations\Rahkaran;

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use InvalidArgumentException;

/**
 * Vocabulary boundary for Rahkaran's workflow inbox (V2 §13/§61): single-
 * letter cartable codes, Y/N viewed flags, and Jalali flow dates die here.
 * Deliberately different external vocabulary from Automation — the unified
 * shape must not leak back into the integrations.
 */
final class RahkaranInboxMapper
{
    /** @var array<string, string> Rahkaran cartable code → unified bucket. */
    private const CARTABLE_MAP = [
        'A' => 'action_required',
        'S' => 'signature',
        'I' => 'information',
    ];

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws InvalidArgumentException when the row is unmappable.
     */
    public function map(array $row): UnifiedInboxItem
    {
        $receivedAt = PersianDateTime::parse(
            PersianText::foldDigits((string) $row['flow_date']),
            PersianText::foldDigits((string) $row['flow_time']),
        ) ?? throw new InvalidArgumentException('Unparseable Rahkaran flow_date/flow_time.');

        $cartableAction = self::CARTABLE_MAP[(string) $row['cartable_state']] ?? throw new InvalidArgumentException(
            "Unknown Rahkaran cartable_state [{$row['cartable_state']}].",
        );

        $requestId = (string) $row['request_id'];
        $viewed = strtoupper((string) $row['viewed']);

        if (! in_array($viewed, ['Y', 'N'], true)) {
            throw new InvalidArgumentException("Unknown Rahkaran viewed flag [{$row['viewed']}].");
        }

        return new UnifiedInboxItem(
            source: 'rahkaran',
            senderName: (string) $row['sender'],
            documentNumber: (string) $row['request_no'],
            title: (string) $row['subject'],
            documentType: (string) $row['request_kind'],
            receivedAt: $receivedAt,
            cartableAction: $cartableAction,
            status: $viewed === 'Y' ? 'seen' : 'unseen',
            externalReference: "rahkaran:{$requestId}",
        );
    }
}
