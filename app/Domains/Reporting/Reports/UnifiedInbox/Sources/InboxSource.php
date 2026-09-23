<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox\Sources;

use App\Domains\Reporting\Reports\UnifiedInbox\InboxSourceResult;
use Carbon\CarbonImmutable;

/**
 * Report-level source port (V2 §63): adapts an integration's InboxGateway
 * into a named, addressable source of the unified inbox. The gateway knows
 * *how to talk to a system*; the source knows *what that system is called
 * inside this report* (key/label) — the two layers stay separate (V2 §61).
 */
interface InboxSource
{
    /** Stable machine key, e.g. "automation" — used in filters and metadata. */
    public function key(): string;

    /** Human label shown to clients, e.g. "اتوماسیون اداری". */
    public function label(): string;

    public function fetch(?CarbonImmutable $from, ?CarbonImmutable $to): InboxSourceResult;
}
