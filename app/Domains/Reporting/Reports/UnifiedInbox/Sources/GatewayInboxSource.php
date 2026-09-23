<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox\Sources;

use App\Contracts\External\InboxGateway;
use App\Domains\Reporting\Reports\UnifiedInbox\InboxSourceResult;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The one InboxSource implementation: wraps any InboxGateway adapter into
 * a named source, translating integration errors into an `unavailable`
 * source result (V2 §37) — raw external errors never escape the source
 * boundary, and one failed system never fails the whole report (V2 §36).
 */
final class GatewayInboxSource implements InboxSource
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly InboxGateway $gateway,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function fetch(?CarbonImmutable $from, ?CarbonImmutable $to): InboxSourceResult
    {
        try {
            return InboxSourceResult::success(
                $this->key,
                $this->gateway->inbox($from, $to),
            );
        } catch (Throwable) {
            return InboxSourceResult::unavailable($this->key);
        }
    }
}
