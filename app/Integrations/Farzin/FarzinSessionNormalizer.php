<?php

namespace App\Integrations\Farzin;

use App\Domains\BoardMeeting\Value\FarzinSessionRow;
use App\Domains\Ingest\Contracts\Normalizer;
use App\Support\External\PersianDateTime;
use App\Support\Text\PersianTextNormalizer;
use Carbon\CarbonImmutable;

/**
 * Farzin board-session form → generic record (`entity_type = "session"`).
 * The session's own date (`dateSession`) is the record date; description
 * and attendance ride `data`.
 */
final class FarzinSessionNormalizer implements Normalizer
{
    public function __construct(
        private readonly PersianTextNormalizer $normalizer,
    ) {}

    public function entityType(): string
    {
        return 'session';
    }

    public function normalize(array $payload): array
    {
        $row = FarzinSessionRow::fromSourceRow($payload);

        $pdate = $this->jalaliDate($row->dateSession);

        return [
            'title' => $this->normalizer->clean($row->dastor),
            'date' => $this->gregorianDate($row->dateSession),
            'pdate' => $pdate,
            'data' => [
                'sharh' => $this->normalizer->clean($row->sharh),
                'time_start' => $row->timeStart,
                'time_end' => $row->timeEnd,
                'location' => $this->normalizer->clean($row->location),
                'hazerin' => $this->normalizer->clean($row->hazerin),
                'dabir_name' => $this->normalizer->clean($row->dabirName),
            ],
        ];
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
