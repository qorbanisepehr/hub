<?php

/*
 * Audit boundary (v6 §2–14, §72): domains never depend on Audit. A domain
 * dispatches its own events via the event bus (`event(new X(...))`); the
 * shared `App\Contracts\AuditEvent` contract + `App\Events\BaseAuditEvent`
 * base live OUTSIDE all domains and are the only touchpoint. Audit is a pure
 * consumer: it subscribes once on the contract (RecordAuditEvent listener)
 * and persists without knowing any domain internals.
 *
 * Full pairwise isolation between business domains is tracked separately —
 * several pre-existing couplings exist outside the audit scope.
 */

$otherDomains = [
    'App\Domains\Auth',
    'App\Domains\Authorization',
    'App\Domains\Cv',
    'App\Domains\Document',
    'App\Domains\Employee',
    'App\Domains\FormOptions',
    'App\Domains\Questionnaire',
    'App\Domains\Settings',
];

arch('audit domain never depends on any other domain')
    ->expect('App\Domains\Audit')
    ->not->toUse($otherDomains);

foreach ($otherDomains as $domain) {
    $short = str_replace('App\Domains\\', '', $domain);

    arch("{$short} domain never depends on audit at all")
        ->expect($domain)
        ->not->toUse('App\Domains\Audit');
}
