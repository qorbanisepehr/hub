<?php

namespace App\Domains\BoardMeeting\Value;

/**
 * One item on a meeting's agenda (دستور جلسه), in the order the
 * invitation letters list it. `position` is the source's own numbering
 * so the registry keeps the official order rather than the import order.
 */
final readonly class AgendaItem
{
    public function __construct(
        public int $position,
        public string $title,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            position: (int) ($data['position'] ?? 0),
            title: trim((string) ($data['title'] ?? '')),
        );
    }

    /** @return array{position: int, title: string} */
    public function toArray(): array
    {
        return ['position' => $this->position, 'title' => $this->title];
    }
}
