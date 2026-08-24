<?php

namespace App\Providers;

use App\Contracts\AuditEvent;
use App\Domains\Audit\Contracts\ArchiveStore;
use App\Domains\Audit\Listeners\RecordAuditEvent;
use App\Domains\Audit\Services\NullArchiveStore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the audit domain to the event bus.
 *
 * Domains dispatch their own events; Audit subscribes once, on the shared
 * AuditEvent contract. No domain ever imports an Audit class to be audited.
 */
final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArchiveStore::class, function ($app) {
            $store = config('audit.archive_store');

            return $app->make($store ?? NullArchiveStore::class);
        });
    }

    public function boot(): void
    {
        Event::listen(AuditEvent::class, RecordAuditEvent::class);
    }
}
