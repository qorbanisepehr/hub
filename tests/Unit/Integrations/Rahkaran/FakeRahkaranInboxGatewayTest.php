<?php

use App\Integrations\Rahkaran\FakeRahkaranInboxGateway;
use App\Integrations\Rahkaran\RahkaranInboxMapper;
use Carbon\CarbonImmutable;

it('maps rahkaran workflow requests into unified items', function () {
    $items = (new FakeRahkaranInboxGateway(new RahkaranInboxMapper))
        ->inbox(
            CarbonImmutable::parse('2023-06-04', 'Asia/Tehran'),
            CarbonImmutable::parse('2023-06-07', 'Asia/Tehran'),
        );

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->source)->toBe('rahkaran')
            ->and($item->cartableAction)->toBeIn(['action_required', 'signature', 'information'])
            ->and($item->externalReference)->toStartWith('rahkaran:')
            ->and($item->documentNumber)->not->toBe('');
    }
});

it('honors the window', function () {
    $items = (new FakeRahkaranInboxGateway(new RahkaranInboxMapper))
        ->inbox(
            CarbonImmutable::parse('2023-06-04', 'Asia/Tehran'),
            CarbonImmutable::parse('2023-06-05', 'Asia/Tehran'),
        );

    foreach ($items as $item) {
        expect($item->receivedAt->greaterThanOrEqualTo('2023-06-04 00:00'))->toBeTrue()
            ->and($item->receivedAt->lessThan('2023-06-06 00:00'))->toBeTrue();
    }
});
