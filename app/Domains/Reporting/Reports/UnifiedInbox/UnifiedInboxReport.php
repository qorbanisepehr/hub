<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use App\Domains\Reporting\Report;
use App\Domains\Reporting\ReportRequest;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSource;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSourceRegistry;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * The multi-source report (V2 §63): fetches each selected source, merges,
 * globally sorts by receivedAt (newest first), and serves an explicit,
 * bounded page from the merged window (V2 §34/§35). Partial failure is the
 * contract (V2 §36): a failed source becomes a warning + `unavailable`
 * entry in source metadata, never a failed report.
 */
final class UnifiedInboxReport implements Report
{
    public function __construct(
        private readonly InboxSourceRegistry $sources,
    ) {}

    public function key(): string
    {
        return 'unified-inbox';
    }

    public function run(ReportRequest $request): UnifiedInboxResult
    {
        $query = $request->query;

        if (! $query instanceof InboxQuery) {
            throw new InvalidArgumentException('Unified inbox report requires an InboxQuery.');
        }

        $selected = $this->sources->forQuery($query->sources);

        if ($selected === []) {
            return $this->emptyResult($request);
        }

        $merged = [];
        $sourceStatus = [];
        $warnings = [];

        foreach ($selected as $source) {
            $result = $this->fetchSource($source, $query);

            $sourceStatus[$source->key()] = $result->status;

            if ($result->status === 'unavailable') {
                $warnings[] = [
                    'source' => $source->key(),
                    'code' => 'unavailable',
                ];

                continue;
            }

            foreach ($result->items as $item) {
                if ($this->matchesFilters($item, $query)) {
                    $merged[] = $item;
                }
            }
        }

        usort($merged, fn (UnifiedInboxItem $a, UnifiedInboxItem $b): int => $b->receivedAt <=> $a->receivedAt);

        return $this->paginate($request, $query, $merged, $sourceStatus, $warnings);
    }

    private function fetchSource(InboxSource $source, InboxQuery $query): InboxSourceResult
    {
        try {
            return $source->fetch($query->from, $query->to);
        } catch (Throwable) {
            return InboxSourceResult::unavailable($source->key());
        }
    }

    /**
     * Report-level filtering (V2 §32): types/statuses are unified vocabulary
     * the report owns; sources are resolved by the registry. The window
     * itself was already applied at each source — filtering here again only
     * guards against source-side drift.
     */
    private function matchesFilters(UnifiedInboxItem $item, InboxQuery $query): bool
    {
        if ($query->types !== [] && ! in_array($item->documentType, $query->types, true)) {
            return false;
        }

        if ($query->statuses !== [] && ! in_array($item->status, $query->statuses, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<UnifiedInboxItem>  $merged
     * @param  array<string, string>  $sourceStatus
     * @param  list<array<string, mixed>>  $warnings
     */
    private function paginate(
        ReportRequest $request,
        InboxQuery $query,
        array $merged,
        array $sourceStatus,
        array $warnings,
    ): UnifiedInboxResult {
        $perPage = $query->limit ?? UnifiedInboxResult::DEFAULT_PAGE_SIZE;
        $total = count($merged);
        $hasMore = $total > $perPage;
        $items = array_slice($merged, 0, $perPage);

        return new UnifiedInboxResult(
            report: $this->key(),
            generatedAt: CarbonImmutable::now(),
            items: $items,
            sources: $sourceStatus,
            page: 1,
            perPage: $perPage,
            total: $total,
            hasMore: $hasMore,
            metadata: [
                'sources_labels' => $this->sources->labels(),
                'window' => [
                    'from' => $query->from?->toIso8601String(),
                    'to' => $query->to?->toIso8601String(),
                ],
            ],
            warnings: $warnings,
        );
    }

    private function emptyResult(ReportRequest $request): UnifiedInboxResult
    {
        return new UnifiedInboxResult(
            report: $this->key(),
            generatedAt: CarbonImmutable::now(),
            items: [],
            sources: [],
            page: 1,
            perPage: UnifiedInboxResult::DEFAULT_PAGE_SIZE,
            total: 0,
            hasMore: false,
            metadata: [
                'sources_labels' => $this->sources->labels(),
                'window' => ['from' => null, 'to' => null],
            ],
            warnings: [],
        );
    }
}
