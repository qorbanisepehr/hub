<?php

namespace App\Domains\BoardMeeting\Value;

use App\Domains\BoardMeeting\Enums\AttendeeRole;

/**
 * Someone who attended a meeting. Attendance is human-owned data: the
 * import may seed it when it creates the row, but a human edit locks the
 * meeting and the import never touches it again (ADR-011 §5).
 *
 * `isPresent` distinguishes a member who was absent from a name the
 * source listed as attending — the archive form only ever records the
 * حاضرین free text, so a missing name is not evidence of absence.
 */
final readonly class Attendee
{
    public function __construct(
        public string $name,
        public AttendeeRole $role = AttendeeRole::Member,
        public bool $isPresent = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            role: AttendeeRole::tryFrom((string) ($data['role'] ?? '')) ?? AttendeeRole::Member,
            isPresent: (bool) ($data['is_present'] ?? true),
        );
    }

    /** @return array{name: string, role: string, is_present: bool} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role->value,
            'is_present' => $this->isPresent,
        ];
    }
}
