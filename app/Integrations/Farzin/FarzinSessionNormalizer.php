<?php

namespace App\Integrations\Farzin;

use App\Domains\Ingest\Contracts\Normalizer;
use App\Support\Text\PersianTextNormalizer;

/**
 * Farzin board-session form → generic record (`entity_type = "session"`).
 * The form's own date (`dateSession`) is both the record date and the
 * meeting's date; description and attendance ride `data`.
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

    /**
     * @param  array<string, mixed>  $payload  A raw `Entity_heyatModire` row.
     * @return array{title: ?string, date: ?string, pdate: ?string, data: array<string, mixed>}
     */
    public function normalize(array $payload): array
    {
        return [
            'title' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'dastor')),
            'date' => FarzinSourceRow::gregorianDate($payload, 'dateSession'),
            'pdate' => FarzinSourceRow::jalaliDate($payload, 'dateSession'),
            'data' => [
                'sharh' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'sharh')),
                'time_start' => FarzinSourceRow::text($payload, 'timeStart'),
                'time_end' => FarzinSourceRow::text($payload, 'timeEnd'),
                'location' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'location')),
                'hazerin' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'hazerin')),
                'dabir_name' => $this->normalizer->clean(FarzinSourceRow::text($payload, 'dabirName')),
            ],
        ];
    }
}
