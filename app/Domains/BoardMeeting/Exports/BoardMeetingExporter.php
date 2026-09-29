<?php

namespace App\Domains\BoardMeeting\Exports;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Approval;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Contract\ProvidesDetailSheets;
use App\Support\Exports\Contract\ProvidesDocumentTitle;
use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Value\DetailColumn;
use App\Support\Exports\Value\DetailSheetSpec;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Exports\Value\ValuePresentation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabular export of the meeting registry (F5).
 *
 * The base sheet mirrors the list page one-to-one: the registry key
 * (held_on + sequence), the human-owned title/minutes, the tags, the
 * origin, and the two source-readiness facts — last sync and whether a
 * person has taken over the row. The three JSONB lists that cannot live
 * in a single cell become repeater sheets addressed by the parent keys
 * (approvals / attendees / agenda), the same kernel capability the
 * employee export uses.
 *
 * The catalog is hand-written (there is no section registry for this
 * aggregate; the model columns and value objects ARE the contract), and
 * the rows() generator is locked to the pre-built query the controller
 * scopes — the same filters as the index endpoint, so an export never
 * shows more than the list the user may see.
 */
final class BoardMeetingExporter implements ProvidesDetailSheets, ProvidesDocumentTitle, ProvidesOptionLabels, TabularExporter
{
    /**
     * Base-sheet keys every row must carry even when deselected: the
     * detail sheets repeat them on every line so a resolution can be
     * traced back to its meeting.
     *
     * @var list<string>
     */
    private const PARENT_KEYS = ['held_on', 'sequence'];

    /** @var array<string, string> */
    private const ROLE_LABELS = [
        'chair' => 'رئیس',
        'member' => 'عضو',
        'secretary' => 'دبیر',
        'observer' => 'ناظر',
        'guest' => 'مهمان',
    ];

    private ?BoardMeeting $current = null;

    public function __construct(
        private readonly Builder $query,
    ) {}

    /**
     * @return list<ExportColumn>
     */
    public function columns(): array
    {
        return [
            new ExportColumn(
                key: 'held_on',
                faLabel: 'تاریخ جلسه',
                column: 'held_on',
                type: ExportColumnType::Date,
                presentation: ValuePresentation::Date,
                bothSibling: new ExportColumn(
                    key: 'held_on@jalali',
                    faLabel: 'تاریخ جلسه (شمسی)',
                    column: 'held_on@jalali',
                    type: ExportColumnType::Text,
                    presentation: ValuePresentation::Date,
                ),
            ),
            new ExportColumn(
                key: 'sequence',
                faLabel: 'شمارهٔ جلسه',
                column: 'sequence',
                type: ExportColumnType::Number,
                presentation: ValuePresentation::Number,
            ),
            new ExportColumn('title', 'عنوان', 'title'),
            new ExportColumn('minutes', 'صورتجلسه', 'minutes'),
            new ExportColumn('tags', 'برچسب‌ها', 'tags'),
            new ExportColumn('origin', 'منبع', 'origin'),
            new ExportColumn(
                key: 'imported_at',
                faLabel: 'آخرین همگام‌سازی',
                column: 'imported_at',
                type: ExportColumnType::Date,
                presentation: ValuePresentation::Date,
            ),
            new ExportColumn(
                key: 'source_locked',
                faLabel: 'مالکیت انسانی',
                column: 'source_locked',
                type: ExportColumnType::Boolean,
                presentation: ValuePresentation::Boolean,
            ),
        ];
    }

    /**
     * @return list<ExportColumn>
     */
    public function columnsFor(ExportRequest $request): array
    {
        $columns = $this->columns();

        // Empty selection = the full catalog: a registry export is the
        // whole registry unless the user says otherwise.
        if ($request->fields === []) {
            return $columns;
        }

        $selected = array_flip($request->fields);

        return array_values(array_filter(
            $columns,
            fn (ExportColumn $column) => isset($selected[$column->key]),
        ));
    }

    /**
     * @return list<DetailSheetSpec>
     */
    public function detailSheets(): array
    {
        return [
            new DetailSheetSpec(
                key: 'approvals',
                label: 'مصوبات',
                columns: [
                    new DetailColumn('approvals.approvals.*.number', 'شماره مصوبه'),
                    new DetailColumn('approvals.approvals.*.title', 'عنوان مصوبه'),
                    new DetailColumn('approvals.approvals.*.document_id', 'شناسهٔ سند', ExportColumnType::Number),
                ],
                parentKeys: self::PARENT_KEYS,
                parentLabels: ['held_on' => 'تاریخ جلسه', 'sequence' => 'شمارهٔ جلسه'],
                path: 'approvals',
                countLabel: 'تعداد مصوبات',
            ),
            new DetailSheetSpec(
                key: 'attendees',
                label: 'حاضران',
                columns: [
                    new DetailColumn('attendees.attendees.*.name', 'نام'),
                    new DetailColumn('attendees.attendees.*.role', 'نقش'),
                    new DetailColumn('attendees.attendees.*.is_present', 'حاضر', ExportColumnType::Boolean, ValuePresentation::Boolean),
                ],
                parentKeys: self::PARENT_KEYS,
                parentLabels: ['held_on' => 'تاریخ جلسه', 'sequence' => 'شمارهٔ جلسه'],
                path: 'attendees',
                countLabel: 'تعداد حاضران',
            ),
            new DetailSheetSpec(
                key: 'agenda',
                label: 'دستور جلسه',
                columns: [
                    new DetailColumn('agenda.agenda.*.position', 'ردیف', ExportColumnType::Number),
                    new DetailColumn('agenda.agenda.*.title', 'عنوان'),
                ],
                parentKeys: self::PARENT_KEYS,
                parentLabels: ['held_on' => 'تاریخ جلسه', 'sequence' => 'شمارهٔ جلسه'],
                path: 'agenda',
                countLabel: 'تعداد دستورها',
            ),
        ];
    }

    public function rows(ExportRequest $request): iterable
    {
        $selected = array_map(
            fn (ExportColumn $column) => $column->key,
            $this->columnsFor($request),
        );

        foreach ($this->query->lazy() as $meeting) {
            $this->current = $meeting;

            $full = $this->rowFor($meeting);

            $row = [];
            foreach ($selected as $key) {
                $row[$key] = $full[$key] ?? null;
            }

            // The detail sheets need their parent address even when the
            // user deselected those columns.
            foreach (self::PARENT_KEYS as $key) {
                if (! array_key_exists($key, $row)) {
                    $row[$key] = $full[$key] ?? null;
                }
            }

            yield $row;
        }
    }

    public function currentEntity(): ?object
    {
        return $this->current;
    }

    public function documentTitle(): string
    {
        return 'فهرست جلسات هیئت مدیره';
    }

    public function baseFilename(): string
    {
        return 'board-meetings';
    }

    public static function defaultOptions(string $format = 'csv'): ExportOptions
    {
        return new ExportOptions(bom: $format === 'csv', formulaGuard: true);
    }

    public function optionLabel(string $columnKey, string $value): ?string
    {
        return match ($columnKey) {
            'attendees.attendees.*.role' => self::ROLE_LABELS[$value] ?? null,
            default => null,
        };
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private function rowFor(BoardMeeting $meeting): array
    {
        return [
            'held_on' => $meeting->held_on?->toDateString(),
            'sequence' => $meeting->sequence,
            'title' => $meeting->title,
            'minutes' => $meeting->minutes,
            'tags' => $meeting->tags->map(fn ($tag) => $tag->name)->implode('، '),
            'origin' => $meeting->origin,
            'imported_at' => $meeting->imported_at?->toDateString(),
            'source_locked' => $meeting->isSourceLocked(),
        ];
    }

    /**
     * @return array<string, list<array<string, string|int|float|bool|null>>>
     */
    public function detailRowsFor(?object $entity): array
    {
        if (! $entity instanceof BoardMeeting) {
            return ['approvals' => [], 'attendees' => [], 'agenda' => []];
        }

        return [
            'approvals' => array_map(
                fn (Approval $approval) => [
                    'number' => $approval->number,
                    'title' => $approval->title,
                    'document_id' => $approval->documentId,
                ],
                $entity->approvals,
            ),
            'attendees' => array_map(
                fn (Attendee $attendee) => [
                    'name' => $attendee->name,
                    'role' => $attendee->role->value,
                    'is_present' => $attendee->isPresent,
                ],
                $entity->attendees,
            ),
            'agenda' => array_map(
                fn (AgendaItem $item) => [
                    'position' => $item->position,
                    'title' => $item->title,
                ],
                $entity->agenda,
            ),
        ];
    }
}
