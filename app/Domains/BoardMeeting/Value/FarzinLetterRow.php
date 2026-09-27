<?php

namespace App\Domains\BoardMeeting\Value;

/**
 * A raw A4 letter row exactly as the Farzin source presents it. Field
 * names mirror the external vocabulary (EntityCode, CreatorRoleID, ...)
 * on purpose — this DTO is the import boundary; the vocabulary dies in
 * the projection's mapping layer, never inside domain logic.
 */
final readonly class FarzinLetterRow
{
    /**
     * @param  array<string, mixed>  $raw  The complete untouched row (staging payload).
     */
    public function __construct(
        public int $entityCode,
        public ?string $subject,
        public ?string $text,
        public ?string $date,
        public ?int $entityNumber,
        public ?int $exportEntityNumber,
        public ?string $rTitle,
        public ?string $rName,
        public ?string $companyName,
        public ?int $creatorRoleId,
        public ?string $creationDate,
        public ?string $lastEditDate,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromSourceRow(array $row): self
    {
        return new self(
            entityCode: (int) $row['EntityCode'],
            subject: $row['subject'] ?? null,
            text: $row['text'] ?? null,
            date: $row['date'] ?? null,
            entityNumber: isset($row['EntityNumber']) ? (int) $row['EntityNumber'] : null,
            exportEntityNumber: isset($row['ExportEntityNumber']) ? (int) $row['ExportEntityNumber'] : null,
            rTitle: $row['r_title'] ?? null,
            rName: $row['r_name'] ?? null,
            companyName: $row['company_name'] ?? null,
            creatorRoleId: isset($row['CreatorRoleID']) ? (int) $row['CreatorRoleID'] : null,
            creationDate: $row['CreationDate'] ?? null,
            lastEditDate: $row['LastEditDate'] ?? null,
            raw: $row,
        );
    }
}
