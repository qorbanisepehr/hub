<?php

/*
 * Section decoupling (ADR-007): the per-domain section classes must never
 * import each other's section classes. Genuinely shared section shapes live
 * in App\Support\Sections\Definitions (a neutral, non-domain namespace) and
 * are reused from there; each domain keeps thin delta subclasses in its own
 * Sections namespace. Cross-domain section coupling is therefore a violation.
 */

foreach (['Cv', 'Employee', 'Questionnaire'] as $domain) {
    $others = array_diff(['Cv', 'Employee', 'Questionnaire'], [$domain])
        ?: [];

    $expect = "App\Domains\\{$domain}\Sections";

    $otherSectionNamespaces = array_map(
        fn (string $other) => "App\Domains\\{$other}\Sections",
        $others,
    );

    arch("{$domain} sections never depend on another domain's sections")
        ->expect($expect)
        ->not->toUse($otherSectionNamespaces);
}
