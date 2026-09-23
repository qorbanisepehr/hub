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
            ->getJson('/api/reports/unified-inbox?date_from=2026-09-01&date_to=2026-09-21')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'source',
                    'sender' => [
                        'matched',
                        'display_name',
                        'username',
                        'avatar_url',
                        'org_role',
                        'user_id',
                        'external_name',
                    ],
                    'document_number',
                    'title',
                    'document_type',
                    'cartable_action',
                    'status',
                    'external_reference',
                    'external_url',
                ],
            ],
            'report',
            'generated_at',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
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
            ->and($response->json('warnings'))->toBe([])
            ->and($response->json('meta.per_page'))->toBe(50);
    });

    it('sorts the merged page newest first by default', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $received = actingAs($user)
            ->getJson('/api/reports/unified-inbox?date_from=2026-09-01&date_to=2026-09-21')
            ->assertOk()
            ->json('data.*.received_at');

        $sorted = collect($received)->sortDesc()->values()->toArray();

        expect($received)->toBe($sorted);
    });

    it('sorts ascending when order=asc is given', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $received = actingAs($user)
            ->getJson('/api/reports/unified-inbox?order=asc')
            ->assertOk()
            ->json('data.*.received_at');

        $sorted = collect($received)->sort()->values()->toArray();

        expect($received)->toBe($sorted);
    });

    it('resolves senders matched by username into the matched shape', function () {
        User::factory()->create(['username' => 'admin', 'name' => 'مدیر سیستم']);

        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $rows = actingAs($user)
            ->getJson('/api/reports/unified-inbox?source=automation')
            ->assertOk()
            ->json('data');

        $matched = collect($rows)->filter(fn ($row) => $row['sender']['matched']);

        expect($matched)->not->toBeEmpty();

        foreach ($matched as $row) {
            expect($row['sender']['username'])->toBe('admin')
                ->and($row['sender']['display_name'])->toBe('مدیر سیستم')
                ->and($row['sender']['user_id'])->toBeInt();
        }

        // Unmatched senders keep the external name.
        $external = collect($rows)->filter(fn ($row) => ! $row['sender']['matched']);

        expect($external->isEmpty())->toBeFalse()
            ->and($external->first()['sender']['external_name'])->not->toBeNull();
    });

    it('narrows to a single source via the source filter', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?source=rahkaran')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['source'] === 'rahkaran'))->toBeTrue();
    });

    it('excludes a source via the source_not filter', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?source_not=automation')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['source'] !== 'automation'))->toBeTrue();
    });

    it('excludes a status via the status_not filter', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?status_not=unseen')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['status'] !== 'unseen'))->toBeTrue();
    });

    it('searches the global filter needle across titles', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?filter='.urlencode('قرارداد'))
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(
                fn ($row) => str_contains($row['title'], 'قرارداد')
                    || str_contains((string) $row['document_number'], 'قرارداد')
                    || str_contains((string) ($row['sender']['external_name'] ?? ''), 'قرارداد'),
            ))->toBeTrue();
    });

    it('filters by unified status', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $data = actingAs($user)
            ->getJson('/api/reports/unified-inbox?status=unseen&date_from=2026-09-15&date_to=2026-09-21')
            ->assertOk()
            ->json('data');

        expect($data)->not->toBeEmpty()
            ->and(collect($data)->every(fn ($row) => $row['status'] === 'unseen'))->toBeTrue();
    });

    it('validates the window when both bounds are given', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?date_from=2026-09-21&date_to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?date_from=nonsense&date_to=2026-09-21')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    });

    it('accepts jalali window bounds', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        // 1405/06/01 == 2026-08-23, 1405/06/30 == 2026-09-21.
        actingAs($user)
            ->getJson('/api/reports/unified-inbox?date_from=1405/06/01&date_to=1405/06/30')
            ->assertOk()
            ->assertJsonPath('report', 'unified-inbox');
    });

    it('validates per_page and rejects sort on other columns', function () {
        $user = createUserWithPermissions(['report.unified-inbox.reports']); // wrong permission on purpose

        actingAs($user)
            ->getJson('/api/reports/unified-inbox?per_page=500')
            ->assertForbidden();

        $authorized = createUserWithPermissions(['report.unified-inbox.view']);

        actingAs($authorized)
            ->getJson('/api/reports/unified-inbox?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        actingAs($authorized)
            ->getJson('/api/reports/unified-inbox?sort=title')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    });

    it('paginates with the standard meta envelope', function () {
        $user = createUserWithPermissions(['report.unified-inbox.view']);

        $json = actingAs($user)
            ->getJson('/api/reports/unified-inbox?per_page=5')
            ->assertOk()
            ->json();

        expect($json['meta']['per_page'])->toBe(5)
            ->and($json['meta']['current_page'])->toBe(1)
            ->and(count($json['data']))->toBeLessThanOrEqual(5)
            ->and($json['meta']['total'])->toBeGreaterThan(5)
            ->and($json['meta']['last_page'])->toBeGreaterThan(1);

        // Page 2 keeps the descending order.
        $page2 = actingAs($user)
            ->getJson('/api/reports/unified-inbox?per_page=5&page=2')
            ->assertOk()
            ->json();

        expect($page2['meta']['current_page'])->toBe(2)
            ->and($page2['data'])->not->toBeEmpty()
            ->and($page2['data'][0]['received_at'])->not->toBe($json['data'][0]['received_at']);
    });
});
