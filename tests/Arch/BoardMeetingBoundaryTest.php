<?php

/*
 * The board meeting registry is a leaf: it knows what a meeting is, and
 * nothing about where meetings come from. The import direction is
 * Integrations → BoardMeeting, so a snapshot in and a write out is the
 * whole contract. The old design inverted this — the domain imported the
 * gateway and read the ingest table's JSONB — which is what these rules
 * now make impossible.
 */

arch('board meeting domain never depends on integrations, ingest, or the external contracts')
    ->expect('App\Domains\BoardMeeting')
    ->not->toUse([
        'App\Integrations',
        'App\Domains\Ingest',
        'App\Contracts\External',
        'App\Domains\Reporting',
    ]);

arch('board meeting domain never names a source')
    ->expect('App\Domains\BoardMeeting')
    ->not->toUse('App\Contracts\External\FarzinGateway');

arch('the Farzin source port never borrows types from a business domain')
    ->expect('App\Contracts\External\FarzinGateway')
    ->not->toUse('App\Domains');

/*
 * `App\Contracts\Ai` is the one thing a business domain's agent and the Ai
 * domain's runner both speak, so it has to stay ownerless. The moment it
 * imports a business domain it becomes a back door for the dependency
 * AiBoundaryTest forbids, and the whole point of D28 was that the runner can
 * run an agent whose owner it does not know.
 */
arch('the ai contracts never depend on a domain that implements them')
    ->expect('App\Contracts\Ai')
    ->not->toUse('App\Domains');
