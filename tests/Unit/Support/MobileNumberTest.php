<?php

use App\Support\MobileNumber;

it('normalizes +98 prefixed mobiles to 09 form', function () {
    expect(MobileNumber::normalize('+989121234567'))->toBe('09121234567');
});

it('normalizes 0098 prefixed mobiles to 09 form', function () {
    expect(MobileNumber::normalize('00989123456789'))->toBe('09123456789');
});

it('trims whitespace before normalizing', function () {
    expect(MobileNumber::normalize(' 09121234567 '))->toBe('09121234567');
});

it('leaves already canonical mobiles unchanged', function () {
    expect(MobileNumber::normalize('09121234567'))->toBe('09121234567');
});

it('leaves non-mobile inputs unchanged', function () {
    expect(MobileNumber::normalize('not-a-phone'))->toBe('not-a-phone');
});

it('accepts only 09, +98 and 0098 prefixed mobiles', function () {
    expect(MobileNumber::isAccepted('09121234567'))->toBeTrue()
        ->and(MobileNumber::isAccepted('+989121234567'))->toBeTrue()
        ->and(MobileNumber::isAccepted('00989123456789'))->toBeTrue()
        ->and(MobileNumber::isAccepted('09999999999'))->toBeTrue()
        ->and(MobileNumber::isAccepted('02123456789'))->toBeFalse()
        ->and(MobileNumber::isAccepted('0912'))->toBeFalse()
        ->and(MobileNumber::isAccepted('not-a-phone'))->toBeFalse();
});
