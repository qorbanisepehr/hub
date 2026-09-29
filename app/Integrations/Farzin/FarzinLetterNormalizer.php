<?php

namespace App\Integrations\Farzin;

use App\Domains\Ingest\Contracts\Normalizer;
use App\Support\PersianText;
use App\Support\Text\PersianTextNormalizer;

/**
 * Farzin A4 letter → generic record (`entity_type = "letter"`). All Farzin
 * vocabulary dies here: CDATA/Arabic text is folded by the shared
 * normalizer, the meeting-date fragment is extracted from the subject,
 * and the letter-specific fields ride `data` JSONB.
 */
final class FarzinLetterNormalizer implements Normalizer
{
    /** TCK parity: invitation subjects read «… مورخ YYYY/MM/DD». */
    private const MEETING_DATE_SHAPE = '/مورخ\s*([\d۰-۹]{4}\/[\d۰-۹]{1,2}\/[\d۰-۹]{1,2})/u';

    public function __construct(
        private readonly PersianTextNormalizer $normalizer,
    ) {}

    public function entityType(): string
    {
        return 'letter';
    }

    /**
     * @param  array<string, mixed>  $payload  A raw `Entity_A4letter` row.
     * @return array{title: ?string, date: ?string, pdate: ?string, data: array<string, mixed>}
     */
    public function normalize(array $payload): array
    {
        $title = $this->normalizer->clean(FarzinSourceRow::text($payload, 'subject'));
        $meetingPdate = $this->extractMeetingPdate($title);

        return [
            'title' => $title,
            'date' => FarzinSourceRow::gregorianDate($payload, 'date'),
            'pdate' => FarzinSourceRow::jalaliDate($payload, 'date'),
            'data' => [
                'text' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'text')),
                'entity_number' => FarzinSourceRow::integer($payload, 'EntityNumber'),
                'export_entity_number' => FarzinSourceRow::integer($payload, 'ExportEntityNumber'),
                'r_title' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'r_title')),
                'r_name' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'r_name')),
                'company_name' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'company_name')),
                'creator_role_id' => FarzinSourceRow::integer($payload, 'CreatorRoleID'),
                'has_meeting' => $meetingPdate !== null,
                'meeting_pdate' => $meetingPdate,
            ],
        ];
    }

    private function extractMeetingPdate(?string $subject): ?string
    {
        if ($subject === null || preg_match(self::MEETING_DATE_SHAPE, $subject, $m) !== 1) {
            return null;
        }

        [$year, $month, $day] = explode('/', PersianText::foldDigits($m[1]));

        return sprintf('%s-%02d-%02d', $year, (int) $month, (int) $day);
    }
}
