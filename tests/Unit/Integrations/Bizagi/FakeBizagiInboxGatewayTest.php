<?php

use App\Integrations\Bizagi\BizagiInboxMapper;
use App\Integrations\Bizagi\FakeBizagiInboxGateway;
use Carbon\CarbonImmutable;

it('maps bizagi bpm tasks into unified items with iso datetimes', function () {
    $items = (new FakeBizagiInboxGateway(new BizagiInboxMapper))
        ->inbox(
            CarbonImmutable::parse('2023-06-04', 'Asia/Tehran'),
            CarbonImmutable::parse('2023-06-07', 'Asia/Tehran'),
        );

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->source)->toBe('bizagi')
            ->and($item->externalReference)->toStartWith('bizagi:')
            // OPEN→unseen, CLOSED→seen translation happened.
            ->and($item->status)->toBeIn(['unseen', 'seen'])
            // Carbon keeps the ISO string's offset; +03:30 == Asia/Tehran.
            ->and($item->receivedAt->getTimezone()->getName())->toBeIn(['Asia/Tehran', '+03:30']);
    }
});

it('emits items on weekends too, unlike the office systems', function () {
    // 2023-06-08 (Thu) .. 2023-06-09 (Fri) — Bizagi works weekends.
    $items = (new FakeBizagiInboxGateway(new BizagiInboxMapper))
        ->inbox(
            CarbonImmutable::parse('2023-06-08', 'Asia/Tehran'),
            CarbonImmutable::parse('2023-06-09', 'Asia/Tehran'),
        );

    expect($items)->not->toBeEmpty();
});
