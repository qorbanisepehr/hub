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
 * then serves the standard list contract over the merged set — global
 * search, exact-match + negated single-value filters, one sort key, and an
 * explicit page slice with a standard meta envelope. Partial failure is
 * the contract (V2 §36): a failed source becomes a warning + `unavailable`
 * entry in source metadata, never a failed report.
 */
final class UnifiedInboxReport implements Report
{
    public const int MAX_PER_PAGE = 200;

    public const int MAX_RANGE_DAYS = 92;

    public function __construct(
        private readonly InboxSourceRegistry $sources,
        private readonly SenderIdentityResolver $senderResolver,
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

        $selected = $this->sources->forQuery($query->sourceKeySet());

        if ($selected === []) {
            return $this->emptyResult($request, $query);
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
                    $merged[] = $this->resolveSender($item);
                }
            }
        }

        $merged = $this->sort($merged, $query);

        return $this->paginate($request, $query, $merged, $sourceStatus, $warnings);
    }

    /**
     * Enriches the item with the resolved sender identity (username match);
     * unmatched senders keep the external name (V2 §54: Reporting reaches
     * internal data through a narrow capability, not raw repositories).
     */
    private function resolveSender(UnifiedInboxItem $item): UnifiedInboxItem
    {
        $user = $this->senderResolver->resolve($item->senderUsername);

        if ($user === null) {
            return $item->sender === null
                ? $item->withSender(ExternalSender::external($item->senderName))
                : $item;
        }

        return $item->withSender(ExternalSender::fromUser($user, $this->senderResolver->orgRole($user)));
    }

    /**
     * The single sort contract: `received_at` asc/desc (desc = newest first,
     * the default). A usort with the spaceship comparator is stable enough
     * for the merged window: ties fall back to the source iteration order.
     *
     * @param  list<UnifiedInboxItem>  $merged
     * @return list<UnifiedInboxItem>
     */
    private function sort(array $merged, InboxQuery $query): array
    {
        $direction = $query->order === 'asc' ? 1 : -1;

        usort(
            $merged,
            fn (UnifiedInboxItem $a, UnifiedInboxItem $b): int => $direction * ($a->receivedAt <=> $b->receivedAt),
        );

        return $merged;
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
     * Report-level filtering (V2 §32): source/type/status are unified
     * vocabulary the report owns, each with the `{field}_not` exclusion
     * convention shared by every list endpoint. A positive filter wins over
     * its negation when both arrive (the client never sends both). The
     * window itself was already applied at each source — filtering here
     * again only guards against source-side drift.
     */
    private function matchesFilters(UnifiedInboxItem $item, InboxQuery $query): bool
    {
        if ($query->source !== null && $item->source !== $query->source) {
            return false;
        }

        if ($query->sourceNot !== null && $item->source === $query->sourceNot) {
            return false;
        }

        if ($query->type !== null && $item->documentType !== $query->type) {
            return false;
        }

        if ($query->typeNot !== null && $item->documentType === $query->typeNot) {
            return false;
        }

        if ($query->status !== null && $item->status !== $query->status) {
            return false;
        }

        if ($query->statusNot !== null && $item->status === $query->statusNot) {
            return false;
        }

        if ($query->filter !== null && ! $this->matchesSearch($item, $query->filter)) {
            return false;
        }

        return true;
    }

    /**
     * Global search needle: substring match across the human-facing fields
     * (title, document number, sender name) — the same "filter" semantics
     * every other list endpoint serves. Case-folding keeps Latin document
     * numbers findable regardless of input case; Persian text is unaffected
     * by mb_strtolower.
     */
    private function matchesSearch(UnifiedInboxItem $item, string $needle): bool
    {
        $needle = mb_strtolower($needle);

        $haystacks = [
            $item->title,
            $item->documentNumber,
            $item->senderName ?? $item->sender?->displayName,
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== null && mb_strtolower($haystack) !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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
        $perPage = min(max($query->perPage, 1), self::MAX_PER_PAGE);
        $page = max($query->page, 1);
        $total = count($merged);
        $lastPage = (int) max(ceil($total / $perPage), 1);
        $items = array_slice($merged, ($page - 1) * $perPage, $perPage);

        return new UnifiedInboxResult(
            report: $this->key(),
            generatedAt: CarbonImmutable::now(),
            items: $items,
            sources: $sourceStatus,
            meta: [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
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

    private function emptyResult(ReportRequest $request, InboxQuery $query): UnifiedInboxResult
    {
        return new UnifiedInboxResult(
            report: $this->key(),
            generatedAt: CarbonImmutable::now(),
            items: [],
            sources: [],
            meta: [
                'current_page' => max($query->page, 1),
                'last_page' => 1,
                'per_page' => min(max($query->perPage, 1), self::MAX_PER_PAGE),
                'total' => 0,
            ],
            metadata: [
                'sources_labels' => $this->sources->labels(),
                'window' => ['from' => null, 'to' => null],
            ],
            warnings: [],
        );
    }
}
