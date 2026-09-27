<?php

namespace App\Domains\BoardMeeting\Value;

/**
 * A raw board-session form row (`Entity_heyatModire`) at the import
 * boundary — external vocabulary (dateSession, sharh, hazerin, ...)
 * dies in the projection mapping.
 */
final readonly class FarzinSessionRow
{
    /**
     * @param  array<string, mixed>  $raw  The complete untouched row (staging payload).
     */
    public function __construct(
        public int $entityCode,
        public ?string $dateSession,
        public ?string $sharh,
        public ?string $timeStart,
        public ?string $timeEnd,
        public ?string $location,
        public ?string $dastor,
        public ?string $hazerin,
        public ?string $dabirName,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromSourceRow(array $row): self
    {
        return new self(
            entityCode: (int) $row['EntityCode'],
            dateSession: $row['dateSession'] ?? null,
            sharh: $row['sharh'] ?? null,
            timeStart: $row['timeStart'] ?? null,
            timeEnd: $row['timeEnd'] ?? null,
            location: $row['location'] ?? null,
            dastor: $row['dastor'] ?? null,
            hazerin: $row['hazerin'] ?? null,
            dabirName: $row['dabirName'] ?? null,
            raw: $row,
        );
    }
}
