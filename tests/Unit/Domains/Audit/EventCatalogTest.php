<?php

use App\Events\BaseAuditEvent;
use Tests\TestCase;

/*
 * Unit tests default to the plain PHPUnit case; bind the application
 * container so config()/base_path() resolve.
 */
uses(TestCase::class);

/**
 * v6 PHASE 6: the event catalog in config/audit.php must mirror the domain
 * event classes exactly. Event sources are scanned from disk and their
 * eventName()/category() literals extracted, so no event can be added,
 * renamed, or re-categorized without this test failing.
 */
/**
 * Absolute paths of all domain event classes, resolved without the app
 * container so the dataset can be built eagerly at file load.
 */
function auditEventClassPaths(): array
{
    $appDir = dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'app';

    return array_map(
        strval(...),
        glob($appDir.DIRECTORY_SEPARATOR.'Domains'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'Events'.DIRECTORY_SEPARATOR.'*.php') ?: [],
    );
}

function fqcnFromEventPath(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $relative = substr($normalized, strlen(base_path('app').'/'));
    $relative = substr($relative, 0, -strlen('.php'));

    return 'App\\'.str_replace('/', '\\', $relative);
}

/**
 * Extract a literal `return 'x';` statement from an instance method.
 */
function literalFromAuditMethod(string $source, string $method): ?string
{
    if (preg_match("/function {$method}\(\):\s*string\s*\{\s*return '([^']+)';\s*\}/", $source, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

dataset('audit_event_classes', function () {
    return auditEventClassPaths();
});

it('maps every domain event class into the catalog', function (string $path) {
    $catalog = config('audit.event_catalog');
    $fqcn = fqcnFromEventPath($path);
    $source = file_get_contents($path);
    $domain = strtolower(basename(dirname(dirname($path))));

    expect(is_subclass_of($fqcn, BaseAuditEvent::class))->toBeTrue("{$fqcn} must extend ".BaseAuditEvent::class);

    $eventName = literalFromAuditMethod($source, 'eventName');
    expect($eventName)->not->toBeNull("{$fqcn}::eventName() must return a string literal");

    // Naming convention: {domain}.{subject}.{action}, lowercase dot notation.
    expect($eventName)->toMatch('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,3}$/')
        ->and(explode('.', $eventName)[0])->toBe($domain, "{$eventName} must start with its domain '{$domain}'")
        ->and($catalog)->toHaveKey($eventName);

    $category = literalFromAuditMethod($source, 'category');
    expect($category)->not->toBeNull("{$fqcn}::category() must return a string literal")
        ->and($catalog[$eventName]['category'])->toBe($category)
        ->and($catalog[$eventName]['sensitivity'])->toBeIn(['low', 'medium', 'high']);
})->with(auditEventClassPaths());

it('keeps event names unique across all domains', function () {
    $names = [];

    foreach (auditEventClassPaths() as $path) {
        $names[] = literalFromAuditMethod(file_get_contents($path), 'eventName');
    }

    expect(count($names))->toBe(count(array_unique(array_filter($names))));
});

it('contains no catalog entries without a matching event class', function () {
    $known = [];

    foreach (auditEventClassPaths() as $path) {
        $eventName = literalFromAuditMethod(file_get_contents($path), 'eventName');

        if ($eventName !== null) {
            $known[] = $eventName;
        }
    }

    $orphans = array_diff(array_keys(config('audit.event_catalog')), $known);

    expect($orphans)->toBe([], 'Catalog entries without an event class: '.implode(', ', $orphans));
});
