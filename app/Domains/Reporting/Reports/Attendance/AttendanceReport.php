<?php

namespace App\Domains\Reporting\Reports\Attendance;

use App\Contracts\External\AttendanceGateway;
use App\Domains\Reporting\Report;
use App\Domains\Reporting\ReportRequest;
use InvalidArgumentException;

/**
 * The first report (V2 §85): single-source, single employee — the actor's
 * own data at this stage. It knows only the AttendanceGateway capability;
 * which external system serves it is invisible here (V2 §8/§80).
 *
 * The capability is a full-window read; the report serves the standard list
 * contract over it — global search, one sort key (date), an explicit page
 * slice, and the standard meta envelope (same shape as the unified inbox).
 */
final class AttendanceReport implements Report
{
    public function __construct(
        private readonly AttendanceGateway $gateway,
    ) {}

    public function key(): string
    {
        return 'attendance';
    }

    public function run(ReportRequest $request): AttendanceResult
    {
        $query = $request->query;

        if (! $query instanceof AttendanceQuery) {
            throw new InvalidArgumentException('Attendance report requires an AttendanceQuery.');
        }

        $source = $this->gateway->attendance($query);

        $records = $this->search($source->records, $query->filter);
        $records = $this->sort($records, $query->order);

        $total = count($records);
        $perPage = min(max($query->perPage, 1), 200);
        $page = max($query->page, 1);

        return new AttendanceResult(
            report: $source->report,
            generatedAt: $source->generatedAt,
            records: array_slice($records, ($page - 1) * $perPage, $perPage),
            freshness: $source->freshness,
            meta: [
                'current_page' => $page,
                'last_page' => (int) max(ceil($total / $perPage), 1),
                'per_page' => $perPage,
                'total' => $total,
            ],
            metadata: $source->metadata,
            warnings: $source->warnings,
        );
    }

    /**
     * Global search needle across the human-facing fields (employee
     * identifier, ISO date) — the same "filter" semantics every list
     * endpoint serves.
     *
     * @param  list<AttendanceRecord>  $records
     * @return list<AttendanceRecord>
     */
    private function search(array $records, ?string $needle): array
    {
        if ($needle === null || $needle === '') {
            return $records;
        }

        $needle = mb_strtolower($needle);

        return array_values(array_filter(
            $records,
            fn (AttendanceRecord $record): bool => str_contains(mb_strtolower($record->employeeIdentifier), $needle)
                || str_contains($record->date->toDateString(), $needle)
                || str_contains($record->date->format('Y/m/d'), $needle),
        ));
    }

    /**
     * The single sort contract: `date` asc/desc (desc = newest first, the
     * default) — the URL `order` param mirrors the unified inbox's.
     *
     * @param  list<AttendanceRecord>  $records
     * @return list<AttendanceRecord>
     */
    private function sort(array $records, string $order): array
    {
        $direction = $order === 'asc' ? 1 : -1;

        usort(
            $records,
            fn (AttendanceRecord $a, AttendanceRecord $b): int => $direction * ($a->date <=> $b->date),
        );

        return $records;
    }
}
