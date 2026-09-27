<?php

namespace App\Integrations\Farzin;

use App\Domains\BoardMeeting\Value\FarzinLetterRow;
use App\Domains\Ingest\Contracts\Normalizer;
use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use App\Support\Text\PersianTextNormalizer;
use Carbon\CarbonImmutable;

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

    public function normalize(array $payload): array
    {
        $row = FarzinLetterRow::fromSourceRow($payload);

        $title = $this->normalizer->clean($row->subject);
        $meetingPdate = $this->extractMeetingPdate($title);
        $pdate = $this->jalaliDate($row->date);

        return [
            'title' => $title,
            'date' => $this->gregorianDate($row->date),
            'pdate' => $pdate,
            'data' => [
                'text' => $this->normalizer->clean($row->text),
                'entity_number' => $row->entityNumber,
                'export_entity_number' => $row->exportEntityNumber,
                'r_title' => $this->normalizer->clean($row->rTitle),
                'r_name' => $this->normalizer->clean($row->rName),
                'company_name' => $this->normalizer->clean($row->companyName),
                'creator_role_id' => $row->creatorRoleId,
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

        $date = PersianText::foldDigits($m[1]);
        [$y, $m2, $d] = explode('/', $date);

        return $y.'-'.str_pad($m2, 2, '0', STR_PAD_LEFT).'-'.str_pad($d, 2, '0', STR_PAD_LEFT);
    }

    private function gregorianDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function jalaliDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        $jalali = PersianDateTime::toJalaliDate(
            CarbonImmutable::createFromTimestamp($timestamp, PersianDateTime::TIMEZONE),
        );

        return str_replace('/', '-', $jalali);
    }
}
