<?php

use App\Rules\BankCardNumberRule;
use App\Rules\IbanNumberRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Run a ValidationRule against a value and return its failure messages.
 *
 * @return string[]
 */
function ruleFailures(ValidationRule $rule, mixed $value): array
{
    $failures = [];
    $rule->validate('field', $value, function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    return $failures;
}

describe('BankCardNumberRule', function () {
    it('accepts a 16-digit card starting with 6 that passes Luhn', function () {
        expect(ruleFailures(new BankCardNumberRule, '6037991123456786'))->toBe([])
            ->and(ruleFailures(new BankCardNumberRule, '6104337840040008'))->toBe([]);
    });

    it('rejects a card with a broken Luhn checksum', function () {
        expect(ruleFailures(new BankCardNumberRule, '6037991123456787'))->not->toBeEmpty();
    });

    it('rejects cards that are not 16 digits or do not start with 6', function () {
        expect(ruleFailures(new BankCardNumberRule, '603799112345678'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new BankCardNumberRule, '1111222233334444'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new BankCardNumberRule, '603799112345678a'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new BankCardNumberRule, ''))
            ->not->toBeEmpty();
    });
});

describe('IbanNumberRule', function () {
    it('accepts a valid Iranian IBAN (mod-97)', function () {
        expect(ruleFailures(new IbanNumberRule, 'IR830610000000000000000000'))->toBe([])
            ->and(ruleFailures(new IbanNumberRule, 'IR446104337840040010003610'))->toBe([]);
    });

    it('normalizes lowercase and space-separated input', function () {
        expect(ruleFailures(new IbanNumberRule, 'ir830610000000000000000000'))->toBe([])
            ->and(ruleFailures(new IbanNumberRule, 'IR83 0610 0000 0000 0000 0000 00'))->toBe([]);
    });

    it('rejects IBANs with a broken checksum or malformed prefix', function () {
        expect(ruleFailures(new IbanNumberRule, 'IR123456789012345678901234'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new IbanNumberRule, 'DE89370400440532013000'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new IbanNumberRule, 'IR83061000000000000000000'))
            ->not->toBeEmpty()
            ->and(ruleFailures(new IbanNumberRule, ''))
            ->not->toBeEmpty();
    });
});
