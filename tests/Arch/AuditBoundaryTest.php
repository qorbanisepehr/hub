<?php

/*
 * Audit boundary (v5 §5–6): Audit is a generic cross-cutting consumer of
 * domain events. It must never reach into other domains, and other domains
 * may consume Audit only through its public capability surface
 * (AuditEventDispatcher + shared App\Contracts\AuditEvent / BaseAuditEvent).
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

    arch("{$short} domain consumes audit only through its public surface")
        ->expect($domain)
        ->not->toUse([
            'App\Domains\Audit\Controllers',
            'App\Domains\Audit\Models',
            'App\Domains\Audit\Requests',
            'App\Domains\Audit\Resources',
            'App\Domains\Audit\Data',
            'App\Domains\Audit\Jobs',
            'App\Domains\Audit\Listeners',
        ]);
}
