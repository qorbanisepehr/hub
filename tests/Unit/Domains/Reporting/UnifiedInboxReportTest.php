<?php

use App\Contracts\External\InboxGateway;
use App\Domains\Reporting\ReportRequest;
use App\Domains\Reporting\Reports\UnifiedInbox\InboxQuery;
use App\Domains\Reporting\Reports\UnifiedInbox\SenderIdentityResolver;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\GatewayInboxSource;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSourceRegistry;
use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxReport;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * In-memory source for report-level tests — fakes the gateway layer
 * entirely so sorting/merging behaviors are isolated.
 */
function inboxItem(string $source, string $receivedAt, string $status = 'unseen'): UnifiedInboxItem
{
    return new UnifiedInboxItem(
        source: $source,
        senderName: 'sender',
        documentNumber: uniqid(),
        title: 'title',
        documentType: 'نامه',
        receivedAt: CarbonImmutable::parse($receivedAt, 'Asia/Tehran'),
        cartableAction: 'action_required',
        status: $status,
        externalReference: "{$source}:".uniqid(),
    );
}

function stubUser(): User
{
    // Plain instance — unit tests here never touch the database or factories.
    $user = new User;
    $user->id = 1;

    return $user;
}

function makeRegistry(array $sources): InboxSourceRegistry
{
    $registry = new InboxSourceRegistry;

    foreach ($sources as $source) {
        $registry->register($source);
    }

    return $registry;
}

function gatewaySource(string $key, array $items, bool $throws = false): GatewayInboxSource
{
    $gateway = new class($items, $throws) implements InboxGateway
    {
        public function __construct(
            private readonly array $items,
            private readonly bool $throws,
        ) {}

        public function inbox(?CarbonImmutable $from, ?CarbonImmutable $to): array
        {
            if ($this->throws) {
                throw new RuntimeException('external system down');
            }

            return $this->items;
        }
    };

    return new GatewayInboxSource($key, ucfirst($key), $gateway);
}

function runInboxReport(InboxSourceRegistry $registry, InboxQuery $query)
{
    $report = new UnifiedInboxReport($registry, new SenderIdentityResolver);

    return $report->run(new ReportRequest(
        query: $query,
        actor: stubUser(),
    ));
}

it('merges and globally sorts items from multiple sources by receivedAt desc', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [
            inboxItem('automation', '2023-06-04 10:10'),
            inboxItem('automation', '2023-06-04 09:30'),
        ]),
        gatewaySource('rahkaran', [
            inboxItem('rahkaran', '2023-06-04 10:05'),
            inboxItem('rahkaran', '2023-06-04 09:50'),
        ]),
        gatewaySource('bizagi', [
            inboxItem('bizagi', '2023-06-04 10:07'),
            inboxItem('bizagi', '2023-06-04 09:20'),
        ]),
    ]);

    $result = runInboxReport($registry, new InboxQuery);

    $times = array_map(fn ($item) => $item->receivedAt->format('H:i'), $result->items);

    // V2 §35 sample: exact interleaving across sources.
    expect($times)->toBe(['10:10', '10:07', '10:05', '09:50', '09:30', '09:20'])
        ->and($result->sources)->toBe([
            'automation' => 'success',
            'rahkaran' => 'success',
            'bizagi' => 'success',
        ])
        ->and($result->warnings)->toBe([]);
});

it('serves a partial result when a source is unavailable', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [
            inboxItem('automation', '2023-06-04 10:10'),
        ]),
        gatewaySource('rahkaran', [], throws: true),
    ]);

    $result = runInboxReport($registry, new InboxQuery);

    expect($result->items)->toHaveCount(1)
        ->and($result->sources['rahkaran'])->toBe('unavailable')
        ->and($result->sources['automation'])->toBe('success')
        ->and($result->warnings)->toBe([['source' => 'rahkaran', 'code' => 'unavailable']]);
});

it('paginates the merged result and reports hasMore', function () {
    $items = [];

    foreach (range(1, 7) as $i) {
        $items[] = inboxItem('automation', sprintf('2023-06-04 %02d:00', $i));
    }

    $registry = makeRegistry([gatewaySource('automation', $items)]);

    $result = runInboxReport($registry, new InboxQuery(limit: 5));

    expect($result->items)->toHaveCount(5)
        ->and($result->total)->toBe(7)
        ->and($result->hasMore)->toBeTrue()
        // Newest first: hours 07..03 kept, 02 and 01 dropped.
        ->and($result->items[0]->receivedAt->format('H'))->toBe('07')
        ->and($result->items[4]->receivedAt->format('H'))->toBe('03');
});

it('filters by unified status vocabulary', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [
            inboxItem('automation', '2023-06-04 10:10', status: 'seen'),
            inboxItem('automation', '2023-06-04 09:30', status: 'unseen'),
        ]),
    ]);

    $result = runInboxReport($registry, new InboxQuery(statuses: ['unseen']));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->status)->toBe('unseen');
});

it('narrows sources through the registry when requested', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [inboxItem('automation', '2023-06-04 10:10')]),
        gatewaySource('rahkaran', [inboxItem('rahkaran', '2023-06-04 09:00')]),
    ]);

    $result = runInboxReport($registry, new InboxQuery(sources: ['rahkaran']));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->source)->toBe('rahkaran')
        ->and($result->sources)->toBe(['rahkaran' => 'success']);
});

it('rejects a foreign query object', function () {
    $registry = makeRegistry([]);

    $report = new UnifiedInboxReport($registry, new SenderIdentityResolver);

    $report->run(new ReportRequest(
        query: new stdClass,
        actor: stubUser(),
    ));
})->throws(InvalidArgumentException::class);
