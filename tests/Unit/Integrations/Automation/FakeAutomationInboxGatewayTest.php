<?php

use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxItem;
use App\Integrations\Automation\AutomationInboxMapper;
use App\Integrations\Automation\FakeAutomationInboxGateway;
use Carbon\CarbonImmutable;

it('maps automation letters into unified items with the automation source key', function () {
    $items = (new FakeAutomationInboxGateway(new AutomationInboxMapper))
        ->inbox(null, null);

    expect($items)->not->toBeEmpty()
        ->and($items[0])->toBeInstanceOf(UnifiedInboxItem::class);
});

it('translates jalali receive dates and cartable wording through the mapper', function () {
    $items = (new FakeAutomationInboxGateway(new AutomationInboxMapper))
        ->inbox(
            CarbonImmutable::parse('2023-06-04', 'Asia/Tehran'),
            CarbonImmutable::parse('2023-06-07', 'Asia/Tehran'),
        );

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->source)->toBe('automation')
            ->and($item->cartableAction)->toBeIn(['action_required', 'signature', 'information'])
            ->and($item->status)->toBeIn(['unseen', 'seen'])
            ->and($item->externalReference)->toStartWith('automation:')
            // Jalali source window lands inside the Gregorian window.
            ->and($item->receivedAt->greaterThanOrEqualTo('2023-06-04 00:00'))->toBeTrue()
            ->and($item->receivedAt->lessThan('2023-06-08 00:00'))->toBeTrue();
    }
});

it('is deterministic for the same window', function () {
    $gateway = new FakeAutomationInboxGateway(new AutomationInboxMapper);

    $first = $gateway->inbox(null, null);
    $second = $gateway->inbox(null, null);

    $serialize = fn (array $items) => array_map(
        fn ($item) => [$item->externalReference, $item->receivedAt->toIso8601String()],
        $items,
    );

    expect($serialize($first))->toBe($serialize($second));
});
