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
function inboxItem(string $source, string $receivedAt, string $status = 'unseen', string $title = 'title'): UnifiedInboxItem
{
    return new UnifiedInboxItem(
        source: $source,
        senderName: 'sender',
        documentNumber: uniqid(),
        title: $title,
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

it('paginates the merged result with the standard meta envelope', function () {
    $items = [];

    foreach (range(1, 7) as $i) {
        $items[] = inboxItem('automation', sprintf('2023-06-04 %02d:00', $i));
    }

    $registry = makeRegistry([gatewaySource('automation', $items)]);

    $result = runInboxReport($registry, new InboxQuery(page: 1, perPage: 5));

    expect($result->items)->toHaveCount(5)
        ->and($result->meta['total'])->toBe(7)
        ->and($result->meta['last_page'])->toBe(2)
        ->and($result->meta['per_page'])->toBe(5)
        ->and($result->meta['current_page'])->toBe(1)
        // Newest first: hours 07..03 kept, 02 and 01 dropped.
        ->and($result->items[0]->receivedAt->format('H'))->toBe('07')
        ->and($result->items[4]->receivedAt->format('H'))->toBe('03');
});

it('serves a later page of the merged result', function () {
    $items = [];

    foreach (range(1, 7) as $i) {
        $items[] = inboxItem('automation', sprintf('2023-06-04 %02d:00', $i));
    }

    $registry = makeRegistry([gatewaySource('automation', $items)]);

    $result = runInboxReport($registry, new InboxQuery(page: 2, perPage: 5));

    expect($result->items)->toHaveCount(2)
        ->and($result->meta['current_page'])->toBe(2)
        // Descending: page 2 holds hours 02 and 01.
        ->and($result->items[0]->receivedAt->format('H'))->toBe('02');
});

it('sorts ascending when requested', function () {
    $items = [
        inboxItem('automation', '2023-06-04 10:10'),
        inboxItem('automation', '2023-06-04 09:30'),
    ];

    $registry = makeRegistry([gatewaySource('automation', $items)]);

    $result = runInboxReport($registry, new InboxQuery(order: 'asc'));

    expect($result->items[0]->receivedAt->format('H:i'))->toBe('09:30');
});

it('filters by unified status vocabulary', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [
            inboxItem('automation', '2023-06-04 10:10', status: 'seen'),
            inboxItem('automation', '2023-06-04 09:30', status: 'unseen'),
        ]),
    ]);

    $result = runInboxReport($registry, new InboxQuery(status: 'unseen'));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->status)->toBe('unseen');
});

it('searches the filter needle across titles', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [
            inboxItem('automation', '2023-06-04 10:10', title: 'قرارداد همکاری'),
            inboxItem('automation', '2023-06-04 09:30', title: 'نامه اداری'),
        ]),
    ]);

    $result = runInboxReport($registry, new InboxQuery(filter: 'قرارداد'));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->title)->toBe('قرارداد همکاری');
});

it('excludes a source through the negated filter', function () {
    $registry = makeRegistry([
        gatewaySource('automation', [inboxItem('automation', '2023-06-04 10:10')]),
        gatewaySource('rahkaran', [inboxItem('rahkaran', '2023-06-04 09:00')]),
    ]);

    $result = runInboxReport($registry, new InboxQuery(sourceNot: 'automation'));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->source)->toBe('rahkaran')
        ->and($result->sources)->toBe(['automation' => 'success', 'rahkaran' => 'success']);
});

it('rejects a foreign query object', function () {
    $registry = makeRegistry([]);

    $report = new UnifiedInboxReport($registry, new SenderIdentityResolver);

    $report->run(new ReportRequest(
        query: new stdClass,
        actor: stubUser(),
    ));
})->throws(InvalidArgumentException::class);
