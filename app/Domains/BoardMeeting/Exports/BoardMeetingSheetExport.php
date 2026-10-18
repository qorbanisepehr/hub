<?php

namespace App\Domains\BoardMeeting\Exports;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Document\Models\DocumentUsage;
use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\Templates\TemplateResolver;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;
use App\Support\Exports\ValuePresenter;

/**
 * The printable form of ONE meeting (D24's detail page, on paper).
 *
 * Named for what it produces rather than for the kernel contract it answers:
 * `DocumentSource` is the kernel's word for "a document", and in this hub a
 * document is an uploaded file owned by the Document domain — an export that
 * borrowed that noun would read as a second way to spell attachments. Nor is it
 * named for a format: the same sheet answers to PDF and to Word, and a class
 * called `...PdfExport` would be lying the moment a second renderer was added.
 * So: `BoardMeetingExporter` is the registry as a table, `BoardMeetingSheetExport`
 * is one meeting as paper.
 *
 * Everything below is presentation the API deliberately does not do: the
 * registry export hands the kernel raw values and lets it shape them, whereas a
 * document source owns its own shaping, because a printed sheet has decisions
 * the table has no way to express — above all WHICH summary is on the page. A
 * meeting carries two: what the meeting is (`summary`) and what the last run
 * proposed and nobody has decided about (`summary.pending`). Printing the
 * undecided one as if it were the record would be a lie in the one direction
 * nobody can undo, so the accepted content is preferred, the candidate is
 * labelled as a candidate, and a meeting with neither says so.
 *
 * Attachments are a LIST and never their bytes: a meeting's documents are
 * served by the Document domain under their own authorization, and pasting
 * megabytes of other people's files into a print sheet would both duplicate
 * them and quietly widen who can read them.
 */
final class BoardMeetingSheetExport implements DocumentSource
{
    /**
     * The templates group's domain name for this sheet. Where its overrides are
     * addressed, not a display name - renaming the class or the sheet must not
     * silently orphan every template an administrator has written.
     */
    public const TEMPLATE_DOMAIN = 'boardmeeting';

    public function __construct(
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * The summary's value keys, in the order the reader should meet them, with
     * whether the model returns prose or a list. Read off the enum rather than
     * hardcoded: `MeetingSummaryField` is the contract for what a run may
     * produce, and a new field that reached the row without a line here would
     * print as nothing at all.
     *
     * @var list<array{0: string, 1: bool}>
     */
    private const SUMMARY_FIELDS = [
        ['executive_summary', false],
        ['topics', true],
        ['bullet_points', true],
        ['key_decisions', true],
        ['suggested_tags', true],
    ];

    /** The meeting this instance was asked about, kept so the filename can name it. */
    private ?BoardMeeting $meeting = null;

    /**
     * Every section name a template may use, in the order this sheet prints
     * them by default.
     *
     * Public because it is a contract, not an implementation detail: the
     * request that validates a submitted template and any settings screen both
     * need to know what is legal, and a second hand-written list of these names
     * would be a second place for them to go stale.
     *
     * @return list<string>
     */
    public function sectionNames(): array
    {
        return array_keys($this->sectionBuilders());
    }

    /**
     * Every section with the label a person sees, for a picker or a cheat sheet.
     *
     * The label travels with the key rather than being looked up in the client.
     * A client-side map of these names would be a second copy of the domain's
     * vocabulary: it would fall behind this class silently, and the fallback
     * would be a raw key shown where a person expects a word.
     *
     * @return list<array{key: string, label: string}>
     */
    public function sectionDefinitions(): array
    {
        return array_values(array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => $this->label('sections.'.$key, $key),
            ],
            $this->sectionNames(),
        ));
    }

    /**
     * Every section this sheet can print, as a name to a builder.
     *
     * Named rather than positional because a name is what a template stores: a
     * template that said "the third one" would be rewritten by every edit here,
     * and one that said `summary` survives this class gaining a section. It is
     * also the vocabulary {@see resolveSections()} filters templates against, so
     * the set of printable sections and the set of legal template names are the
     * same list and cannot drift apart.
     *
     * @return array<string, callable(BoardMeeting): DocumentSection>
     */
    private function sectionBuilders(): array
    {
        return [
            'identity' => $this->identitySection(...),
            'attendees' => $this->attendeesSection(...),
            'agenda' => $this->agendaSection(...),
            'minutes' => $this->minutesSection(...),
            'summary' => $this->summarySection(...),
            'approvals' => $this->approvalsSection(...),
            'tags' => $this->tagsSection(...),
            'attachments' => $this->attachmentsSection(...),
        ];
    }

    /**
     * Which of the above to actually build, in which order.
     *
     * The template decides; the built-in list is the fallback and also the
     * vocabulary a stored template is filtered against. An entity's template
     * beats its domain's, and a domain's beats this list — see
     * {@see TemplateResolver} for why each answer is a whole template rather
     * than a patch of one.
     */
    private function resolveSections(BoardMeeting $meeting): array
    {
        $default = array_keys($this->sectionBuilders());

        $sections = $this->templates->sections(
            self::TEMPLATE_DOMAIN,
            'session/'.$meeting->getKey(),
            $default,
        );

        // Nothing stored for the entity, and nothing for the domain either: the
        // domain list, which is the section order this sheet has always printed
        // in. Restated rather than assumed so that the built-in default is one
        // list in this file instead of two that can disagree.
        return $sections === [] ? $default : $sections;
    }

    public function document(mixed $entity): DocumentSpec
    {
        $this->meeting = $entity instanceof BoardMeeting
            ? $entity
            : BoardMeeting::query()->findOrFail((int) $entity);

        $meeting = $this->meeting->loadMissing(['tags', 'documentUsages.document']);

        $builders = $this->sectionBuilders();
        $sections = [];

        foreach ($this->resolveSections($meeting) as $name) {
            // Built one at a time and only for the names the template asked
            // for, so a template that hides the agenda table never assembles it.
            $section = $builders[$name]($meeting);

            if (! $section->isEmpty()) {
                $sections[] = $section;
            }
        }

        return new DocumentSpec(
            title: $this->label('title'),
            subtitle: (string) $meeting->registryLabel(),
            meta: array_values(array_filter([
                DocumentField::from(
                    $this->label('fields.generated_at'),
                    ValuePresenter::generatedAtStamp(),
                ),
            ])),
            sections: $sections,
        );
    }

    /**
     * The kernel composes `{base}-{Ymd-His}.pdf`, so the base is where the
     * meeting's identity goes: two exports of different meetings must not
     * collide in one downloads folder, and a timestamp alone is too weak to
     * tell them apart later.
     */
    public function baseFilename(): string
    {
        return 'board-meeting-'.(string) $this->meetingKey();
    }

    /**
     * What a person reads first: the meeting's own identity, as the registry
     * spells it (the Jalali date plus the sequence) rather than as the detail
     * page's title block.
     */
    private function identitySection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.identity'),
            fields: $this->fields([
                ['title', $meeting->title],
                ['held_on', $meeting->held_on?->format('Y/m/d')],
                ['sequence', $meeting->sequence !== null ? (string) $meeting->sequence : null],
                ['origin', $meeting->origin],
            ]),
        );
    }

    private function attendeesSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.attendees'),
            tables: [$this->table(
                columns: ['name', 'role', 'present'],
                rows: array_map(
                    fn (Attendee $attendee): array => [
                        $attendee->name,
                        $this->label('roles.'.$attendee->role->value, $attendee->role->value),
                        $attendee->isPresent
                            ? $this->label('yes')
                            : $this->label('no'),
                    ],
                    $meeting->attendees,
                ),
            )],
        );
    }

    private function agendaSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.agenda'),
            tables: [$this->table(
                columns: ['position', 'agenda_title'],
                rows: array_map(
                    fn (AgendaItem $item): array => [
                        ValuePresenter::persianDigits((string) $item->position),
                        $item->title,
                    ],
                    $meeting->agenda,
                ),
            )],
        );
    }

    private function minutesSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.minutes'),
            fields: $this->fields([['', (string) $meeting->minutes]]),
        );
    }

    /**
     * The section whose whole job is choosing between two summaries.
     *
     * The accepted content wins whenever it exists, because it is the meeting's
     * record. A candidate is printed when there is no accepted content and is
     * labelled the thing it is, at the top, where a reader meets it before the
     * prose: a sheet whose first word is not "accepted" must not be archivable
     * as if it were.
     */
    private function summarySection(BoardMeeting $meeting): DocumentSection
    {
        $stored = (array) $meeting->summary;
        $candidate = (array) ($stored['pending'] ?? []);

        // "The row is the record" is not the same question as "does it hold any
        // prose": a failed run holds none at all, and its news is exactly that.
        // `none` is the one status meaning "asked, and nothing accepted yet", so
        // it is the one that hands the page to the candidate instead.
        $isRecord = ($stored['status'] ?? null) !== null
            && $stored['status'] !== MeetingSummary::STATUS_NONE;
        $hasCandidate = $this->hasContent($candidate);

        $source = $isRecord ? $stored : ($hasCandidate ? $candidate : []);
        $status = (string) ($source['status'] ?? MeetingSummary::STATUS_NONE);

        $fields = $this->fields([
            ['status', $this->label('statuses.'.$status, $status)],
        ]);

        // The note is what makes the sheet honest: a reader who only skims
        // still learns whether this was ever a decision or only a proposal.
        $note = match (true) {
            $isRecord && $this->hasContent($stored) => $this->label('notes.accepted'),
            ! $isRecord && $hasCandidate => $this->label('notes.pending'),
            default => $this->label('notes.none'),
        };

        $fields[] = new DocumentField($this->label('fields.note'), $note);

        if ($reason = $this->reasonOf($source)) {
            $fields[] = new DocumentField($this->label('fields.error'), $this->label('reasons.'.$reason, $reason));
        }

        if ($run = $this->runFieldsOf($source)) {
            $fields = [...$fields, ...$run];
        }

        $tables = [];

        foreach (self::SUMMARY_FIELDS as [$key, $isList]) {
            $value = $source[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $heading = $this->label('fields.'.$key, $key);

            if (! $isList) {
                $fields[] = new DocumentField($heading, (string) $value);

                continue;
            }

            $tables[] = $this->table(
                columns: ['value'],
                rows: array_map(
                    fn (mixed $entry): array => [$this->entryText($entry)],
                    array_values((array) $value),
                ),
                caption: $heading,
            );
        }

        return new DocumentSection(
            heading: $this->label('sections.summary'),
            fields: $fields,
            tables: $tables,
        );
    }

    /**
     * Registry numbers live on the meeting's document usages — the same
     * source the registry export reads, so a resolution reads identically in a
     * sheet and in a spreadsheet.
     */
    private function approvalsSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.approvals'),
            tables: [$this->table(
                columns: ['registry_number', 'document_title'],
                rows: $meeting->documentUsages
                    ->filter(fn (DocumentUsage $usage): bool => ! $usage->trashed()
                        && isset($usage->metadata['registry_number']))
                    ->map(fn (DocumentUsage $usage): array => [
                        ValuePresenter::persianDigits((string) $usage->metadata['registry_number']),
                        (string) ($usage->metadata['title'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            )],
        );
    }

    private function tagsSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.tags'),
            tables: [$this->table(
                columns: ['tag'],
                rows: $meeting->tags
                    ->map(fn ($tag): array => [$tag->name])
                    ->values()
                    ->all(),
            )],
        );
    }

    /**
     * Attachments by name only. See the class docblock for why their bytes
     * are never inlined.
     */
    private function attachmentsSection(BoardMeeting $meeting): DocumentSection
    {
        return new DocumentSection(
            heading: $this->label('sections.attachments'),
            tables: [$this->table(
                columns: ['file_name', 'size', 'mime', 'uploaded_at'],
                rows: $meeting->documentUsages
                    ->filter(fn (DocumentUsage $usage): bool => ! $usage->trashed())
                    ->map(fn (DocumentUsage $usage): array => [
                        (string) $usage->document?->original_name,
                        $usage->document?->size !== null
                            ? ValuePresenter::persianDigits(number_format($usage->document->size / 1024, 1).' KB')
                            : '',
                        (string) $usage->document?->mime_type,
                        $usage->created_at?->format('Y/m/d H:i') ?? '',
                    ])
                    ->values()
                    ->all(),
            )],
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $stored
     */
    private function hasContent(array $stored): bool
    {
        if (($stored['status'] ?? null) === null) {
            return false;
        }

        foreach (self::SUMMARY_FIELDS as [$key, $isList]) {
            $value = $stored[$key] ?? null;

            if ($isList ? $value !== null && $value !== [] : is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function reasonOf(array $source): ?string
    {
        $error = $source['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    /**
     * Who produced the sheet's summary, for provenance on an archived page.
     *
     * @param  array<string, mixed>  $source
     * @return list<DocumentField>
     */
    private function runFieldsOf(array $source): array
    {
        return $this->fields([
            ['provider', $source['provider'] ?? null],
            ['model', $source['model'] ?? null],
        ]);
    }

    /**
     * A summary list entry as one line: tags arrive as `{name, slug}` objects
     * and everything else as strings, and a slug is not something a reader of
     * a printed sheet needs.
     */
    private function entryText(mixed $entry): string
    {
        if (is_array($entry)) {
            return (string) ($entry['name'] ?? '');
        }

        return (string) $entry;
    }

    /**
     * A label for this surface, with the raw key as the fallback.
     *
     * The fallback matters because the summary's own vocabulary is a set of
     * stable keys a future model run may widen: a status or reason this sheet
     * has no copy for prints as its key rather than as a blank cell, so an
     * unknown state is visible instead of silently missing.
     */
    private function label(string $key, ?string $fallback = null): string
    {
        $full = 'board_meeting.print.'.$key;
        $text = (string) __($full);

        return $text === $full ? (string) ($fallback ?? $key) : $text;
    }

    /**
     * @param  list<array{0: string, 1: ?string}>  $pairs
     * @return list<DocumentField>
     */
    private function fields(array $pairs): array
    {
        $fields = [];

        foreach ($pairs as [$key, $value]) {
            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = new DocumentField(
                $key === '' ? '' : $this->label('fields.'.$key, $key),
                (string) $value,
            );
        }

        return $fields;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    private function table(array $columns, array $rows, string $caption = ''): DocumentTable
    {
        return new DocumentTable(
            caption: $caption,
            headers: array_map(
                fn (string $column): string => $this->label('columns.'.$column, $column),
                $columns,
            ),
            rows: $rows,
        );
    }

    /**
     * A stable, filesystem-safe stem: the meeting's registry sequence when it
     * has one — that is the meeting's own name — and its id otherwise. Latin
     * digits on purpose: a Persian-digit filename survives some mail clients
     * and some Windows shells poorly, and the kernel already appends a stamp.
     */
    private function meetingKey(): string
    {
        $meeting = $this->meeting;

        if ($meeting instanceof BoardMeeting) {
            return $meeting->sequence !== null && $meeting->sequence > 0
                ? 'session-'.$meeting->sequence
                : 'id-'.$meeting->getKey();
        }

        return 'meeting';
    }
}
