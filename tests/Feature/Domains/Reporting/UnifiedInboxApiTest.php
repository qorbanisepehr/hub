<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-09-22 10:00:00', 'Asia/Tehran');
});

describe('GET /api/reports/unified-inbox', function () {
    it('forbids a user without the report capability', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/reports/unified-inbox')
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson('/api/reports/unified-inbox')
            ->assertUnauthorized();
    });

    it('returns merged items from all registered fake sources', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $response = actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=2026-09-01&to=2026-09-21')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'source',
                    'sender_name',
                    'document_number',
                    'title',
                    'document_type',
                    'received_at',
                    'cartable_action',
                    'status',
                    'external_reference',
                    'external_url',
                ],
            ],
            'report',
            'generated_at',
            'pagination',
            'sources',
            'metadata',
            'warnings',
        ]);

        $sourcesUsed = collect($response->json('data'))
            ->pluck('source')
            ->unique()
            ->values();

        expect($response->json('report'))->toBe('unified-inbox')
            // All three fake sources contributed (order follows the global sort).
            ->and($sourcesUsed->toArray())->toHaveCount(3)
            ->and($sourcesUsed->toArray())->toEqualCanonicalizing(['automation', 'rahkaran', 'bizagi'])
            ->and($response->json('sources'))->toBe([
                'automation' => 'success',
                'rahkaran' => 'success',
                'bizagi' => 'success',
            ])
            ->and($response->json('warnings'))->toBe([]);
    });

    it('sorts the merged page newest first', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $received = actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=2026-09-01&to=2026-09-21')
            ->assertOk()
            ->json('data.*.received_at');

        $sorted = collect($received)->sortDesc()->values()->toArray();

        expect($received)->toBe($sorted);
    });

    it('narrows to a single source via the sources filter', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?sources[]=rahkaran')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['source'] === 'rahkaran'))->toBeTrue();
    });

    it('filters by unified status', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?statuses[]=unseen&from=2026-09-15&to=2026-09-21')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['status'] === 'unseen'))->toBeTrue();
    });

    it('validates the window when both bounds are given', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=2026-09-21&to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=nonsense&to=2026-09-21')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    });

    it('accepts jalali window bounds', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        // 1405/06/01 == 2026-08-23, 1405/06/30 == 2026-09-21.
        actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=1405/06/01&to=1405/06/30')
            ->assertOk()
            ->assertJsonPath('report', 'unified-inbox');
    });

    it('validates the limit bound', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    });

    it('paginates with a bounded page size', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $json = actingAs($user)
            ->getJson('/api/reports/unified-inbox?from=2026-09-01&to=2026-09-21&limit=5')
            ->assertOk()
            ->json();

        expect($json['pagination']['per_page'])->toBe(5)
            ->and($json['pagination']['page'])->toBe(1)
            ->and(count($json['data']))->toBeLessThanOrEqual(5)
            ->and($json['pagination']['has_more'])->toBeTrue();
    });
});
