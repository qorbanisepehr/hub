<?php

namespace App\Integrations\Farzin\BoardMeeting;

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\IncomingMeeting;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Integrations\Farzin\FarzinVocabulary;
use App\Support\External\PersianDateTime;
use Illuminate\Support\Collection;

/**
 * Turns the generic ingest records the Farzin sync produced into the
 * registry's input contract. This is the whole source-specific half of
 * the import: everything below is Farzin/Farsi-shaped and none of it
 * leaks past this class, which returns nothing but `IncomingMeeting`.
 *
 * A meeting is keyed by its Jalali date. The Farzin source files one
 * meeting per day — the invitation letters carry the meeting date in
 * their subject, and every `Entity_heyatModire` form for that day is
 * that one meeting's attendance — so `sequence` is always 1 here. The
 * column exists because the *domain* allows a second meeting on the
 * same day (جلسهٔ فوق‌العاده); inventing a second one from two
 * attendance forms would lose the letters' attribution instead.
 *
 * Meeting date: a session form's own timestamp is authoritative when the
 * day has one, otherwise the Jalali key is converted. Letters group by
 * the meeting date in their subject, never by the letter's own date.
 *
 * Registry numbers are NOT projected here. They are not a Farzin column —
 * Farzin stores no file blobs and no مصوبات list — and they have never
 * been part of the registry's input contract. The one-time archive
 * migration recorded them on the hub Document system's usages, so a live
 * sync physically cannot touch them (ADR-011 decision 9).
 *
 * @phpstan-type LetterGroup Collection<int, IntegrationRecord>
 */
final class FarzinRegistryExtractor
{
    /**
     * The form-letter wrapper the invitation letters open and close with:
     * «با سلام … :» and the trailing «با تشکر». The greeting is matched
     * loosely and the payload is taken from the first colon, because
     * the invitation puts the agenda header («دستور جلسه») between the
     * verb and the colon.
     */
    private const GREETING_SHAPE = '/^\s*با\s*سلام\b.*?:\s*(?<body>.*?)(?:\s*با\s*تشکر\b.*)?$/us';

    /** A numbered agenda fragment: «1- تصویب صورت‌های مالی». */
    private const NUMBERING_SHAPE = '/(?:^|[^\d۰-۹])([1-9][\d۰-۹]*)\s*[-–]\s*(.+?)(?=(?:[^\d۰-۹][1-9][\d۰-۹]*\s*[-–])|$)/u';

    /** The same numbering, for stripping it out of prose. */
    private const NUMBERING_ONLY = '/\s*[1-9][\d۰-۹]*\s*[-–]\s*/u';

    /**
     * @return list<IncomingMeeting>
     */
    public function extract(): array
    {
        $letters = $this->invitationLetters();
        $sessions = $this->sessionForms();

        $dates = array_values(array_unique([
            ...$letters->keys()->all(),
            ...$sessions->keys()->all(),
        ]));

        sort($dates);

        $meetings = [];

        foreach ($dates as $date) {
            $meeting = $this->meeting(
                $date,
                $letters->get($date, collect()),
                $sessions->get($date, collect()),
            );

            if ($meeting !== null) {
                $meetings[] = $meeting;
            }
        }

        return $meetings;
    }

    /**
     * Invitation letters grouped by the meeting date in their subject —
     * the whole letter, not the date it was sent.
     *
     * @return Collection<string, LetterGroup>
     */
    private function invitationLetters(): Collection
    {
        return IntegrationRecord::forEntity('letter')
            ->where('data->has_meeting', true)
            ->whereNotNull('data->meeting_pdate')
            ->where('data->meeting_pdate', '!=', '')
            ->whereNotNull('data->entity_number')
            ->where('data->entity_number', '!=', '')
            ->get()
            ->groupBy(fn (IntegrationRecord $record) => (string) $record->data['meeting_pdate'])
            ->sortKeys();
    }

    /**
     * Board-session forms grouped by their own Jalali date — which the
     * session normalizer already derived from `dateSession`, so the
     * Gregorian side never has to be converted back here.
     *
     * @return Collection<string, Collection<int, IntegrationRecord>>
     */
    private function sessionForms(): Collection
    {
        return IntegrationRecord::forEntity('session')
            ->whereNotNull('pdate')
            ->where('pdate', '!=', '')
            ->get()
            ->groupBy(fn (IntegrationRecord $record) => (string) $record->pdate)
            ->sortKeys();
    }

    /**
     * @param  LetterGroup  $letters
     * @param  Collection<int, IntegrationRecord>  $sessions
     */
    private function meeting(string $heldOnJalali, Collection $letters, Collection $sessions): ?IncomingMeeting
    {
        $heldOn = $sessions->first()?->date?->toDateString()
            ?? PersianDateTime::parse($heldOnJalali)?->toDateString();

        if ($heldOn === null) {
            return null;
        }

        return new IncomingMeeting(
            heldOnJalali: $heldOnJalali,
            heldOn: $heldOn,
            sequence: 1,
            origin: FarzinVocabulary::SOURCE,
            agenda: $this->agendaFrom($letters),
            minutes: $this->minutesFrom($letters),
            attendees: $this->attendeesFrom($sessions),
            provenance: [
                'letter_ids' => $letters->pluck('id')->values()->all(),
                'session_ids' => $sessions->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * The meeting's minute text: every letter's body with the
     * form-letter wrapper and the list numbering stripped, deduplicated
     * by the folded remainder, and joined in the order the letters were
     * issued.
     *
     * @param  LetterGroup  $letters
     */
    private function minutesFrom(Collection $letters): ?string
    {
        $seen = [];
        $paragraphs = [];

        foreach ($letters as $letter) {
            $text = $this->substance((string) ($letter->data['text'] ?? ''));

            $fingerprint = preg_replace('/[\s\-.,،,\n\t()\[\]]+/u', '', $text) ?? $text;

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $paragraphs[] = $text;
        }

        return $paragraphs === [] ? null : implode("\n\n", $paragraphs);
    }

    /**
     * @param  LetterGroup  $letters
     * @return list<AgendaItem>
     */
    private function agendaFrom(Collection $letters): array
    {
        $seen = [];
        $items = [];

        foreach ($letters as $letter) {
            $text = $this->payload((string) ($letter->data['text'] ?? ''));

            if (preg_match_all(self::NUMBERING_SHAPE, $text, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $title = trim($match[2]);

                if ($title === '' || isset($seen[$title])) {
                    continue;
                }

                $seen[$title] = true;
                $items[] = new AgendaItem((int) $match[1], $title);
            }
        }

        usort($items, fn (AgendaItem $a, AgendaItem $b) => $a->position <=> $b->position);

        return $items;
    }

    /**
     * The حاضرین free text of every session form for the day, split on
     * the separators the form uses. A form only ever records who was
     * there, so every name is present and none is inferred absent.
     *
     * @param  Collection<int, IntegrationRecord>  $sessions
     * @return list<Attendee>
     */
    private function attendeesFrom(Collection $sessions): array
    {
        $attendees = [];
        $seen = [];

        foreach ($sessions as $session) {
            $raw = trim((string) ($session->data['hazerin'] ?? ''));

            if ($raw === '') {
                continue;
            }

            foreach (preg_split('/[،,؛;\n]+/u', $raw) ?: [] as $name) {
                $name = trim($name);

                if ($name === '' || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $attendees[] = new Attendee($name, AttendeeRole::Member);
            }
        }

        return $attendees;
    }

    /**
     * The letter text without markup. `PersianTextNormalizer` unwraps
     * CDATA but keeps the HTML inside it, and both readers below are
     * anchored to the text's shape — a leading `<p>` would silently
     * defeat them, so the tags go once, here.
     */
    private function plain(string $text): string
    {
        return strip_tags($text);
    }

    /**
     * What the letter says once the form-letter wrapper («با سلام …:»
     * and the closing «با تشکر») is off, with the list numbering left
     * in place. The shared normalizer folds the source's line breaks
     * into spaces, so the wrapper pattern has to be line-agnostic.
     */
    private function payload(string $raw): string
    {
        $plain = $this->plain($raw);

        if (preg_match(self::GREETING_SHAPE, $plain, $matches) === 1) {
            $plain = $matches['body'];
        }

        return preg_replace('/\s+/u', ' ', $plain) ?? $plain;
    }

    /** The payload as prose: the numbering stripped too. */
    private function substance(string $raw): string
    {
        $stripped = preg_replace(self::NUMBERING_ONLY, ' ', $this->payload($raw)) ?? $this->payload($raw);

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);
    }
}
