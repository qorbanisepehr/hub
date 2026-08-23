<?php

use App\Domains\Audit\Models\AuditLog;

use function Pest\Laravel\actingAs;

describe('GET /api/audit-logs/export', function () {
    beforeEach(function () {
        $this->user = createUserWithPermissions(['audit.export']);

        AuditLog::factory()->count(3)->forEmployee()->create();
        AuditLog::factory()->forAuth()->create();
    });

    it('streams a csv download with a header row and every log', function () {
        $response = actingAs($this->user)
            ->get('/api/audit-logs/export?format=csv')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = array_filter(explode("\n", trim($response->streamedContent())));
        $header = str_getcsv(array_shift($rows));

        expect($header)->toContain('event')
            ->and($header)->toContain('trace_id')
            ->and(count($rows))->toBe(4);
    });

    it('applies the same filters as the index endpoint', function () {
        $response = actingAs($this->user)
            ->get('/api/audit-logs/export?format=csv&category=auth')
            ->assertOk();

        $rows = array_filter(array_map('trim', explode("\n", trim($response->streamedContent()))));
        array_shift($rows);

        expect(count($rows))->toBe(1)
            ->and(str_getcsv($rows[0]))->toContain('auth');
    });

    it('streams jsonl with one parseable object per line', function () {
        $response = actingAs($this->user)
            ->get('/api/audit-logs/export?format=jsonl')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson');

        $lines = array_filter(explode(PHP_EOL, trim($response->streamedContent())));

        expect(count($lines))->toBe(4);

        $decoded = json_decode($lines[0], true);

        expect($decoded)->toHaveKey('id')
            ->and($decoded)->toHaveKey('event');
    });

    it('defaults to csv when no format is given', function () {
        actingAs($this->user)
            ->get('/api/audit-logs/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('rejects an unknown format', function () {
        actingAs($this->user)
            ->get('/api/audit-logs/export?format=xlsx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    });

    it('requires the audit.export permission', function () {
        $viewer = createUserWithPermissions(['audit.view']);

        actingAs($viewer)
            ->get('/api/audit-logs/export')
            ->assertForbidden();
    });

    it('rejects guests', function () {
        $this->get('/api/audit-logs/export')->assertUnauthorized();
    });
});
