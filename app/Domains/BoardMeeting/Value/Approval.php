<?php

namespace App\Domains\BoardMeeting\Value;

use InvalidArgumentException;

/**
 * One approval (مصوبه) passed by a board meeting. A meeting approves
 * zero or more of them, each with its own registry number — so the
 * collection is a list of these, never one joined string (ADR-011 D4).
 *
 * `number` is the approval's own registry number as the archive spells
 * it (`1234/ص/1404`); `documentId` is filled in once the approval's
 * PDF lives in the hub Document system.
 */
final readonly class Approval
{
    public function __construct(
        public string $number,
        public ?string $title = null,
        public ?int $documentId = null,
    ) {
        if (trim($number) === '') {
            throw new InvalidArgumentException('An approval needs a registry number.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            number: trim((string) ($data['number'] ?? '')),
            title: isset($data['title']) ? (string) $data['title'] : null,
            documentId: isset($data['document_id']) ? (int) $data['document_id'] : null,
        );
    }

    /** @return array{number: string, title: ?string, document_id: ?int} */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'document_id' => $this->documentId,
        ];
    }
}
