<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Cv\Models\Cv;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function scopedCvPermission(User $user, string $permissionName, array $policy): void
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'cv'], ['name' => 'CV']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'cv-endpoint-scoped-'.uniqid(),
        'display_name' => 'CV Endpoint Scoped',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => $policy,
    ]);

    $user->assignRole($role->id, true);
}

function cvBankRecord(string $status, array $overrides = []): Cv
{
    return Cv::create(array_merge([
        'first_name' => 'Ali',
        'last_name' => 'Rezaei',
        'mobile' => '0912'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        'status' => $status,
    ], $overrides));
}

function scopedCvStatusPolicy(string $status): array
{
    return [
        'all' => [
            ['attribute' => 'cv.status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => $status],
        ],
    ];
}

describe('CV bank endpoint authorization', function () {
    it('scopes the CV bank list to CVs matching the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.view', scopedCvStatusPolicy('submitted'));

        $allowed = cvBankRecord('submitted');
        cvBankRecord('draft');

        $this->actingAs($user)
            ->getJson('/api/cv/bank')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed->id);
    });

    it('scopes the CV bank list to CVs reviewed by the acting user', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.view', [
            'all' => [
                ['attribute' => 'cv.reviewed_by', 'operator' => 'equals', 'value_source' => 'actor', 'value' => 'id'],
            ],
        ]);

        $mine = cvBankRecord('submitted', ['reviewed_by' => $user->id]);
        $other = User::factory()->create();
        cvBankRecord('submitted', ['reviewed_by' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/cv/bank')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });

    it('shows a CV whose status matches the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.view', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('submitted');

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $cv->uuid);
    });

    it('denies showing a CV that fails the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.view', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('draft');

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->uuid}")
            ->assertStatus(403);
    });

    it('denies creating a questionnaire for a CV that fails the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.create-questionnaire', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('draft');

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$cv->uuid}/questionnaire")
            ->assertStatus(403);
    });

    it('creates a questionnaire for a CV matching the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.create-questionnaire', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('submitted');

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$cv->uuid}/questionnaire")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    });

    it('resolves lifecycle users for the CV bank list in a single batched query', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.view', scopedCvStatusPolicy('submitted'));

        foreach (range(1, 15) as $i) {
            $reviewer = User::factory()->create();
            cvBankRecord('submitted', [
                'lifecycle' => [
                    ['action' => 'submitted', 'by' => $reviewer->id, 'at' => now()->toISOString()],
                ],
            ]);
        }

        $queries = [];
        DB::listen(fn ($query) => $queries[] = $query->sql);

        $this->actingAs($user)
            ->getJson('/api/cv/bank')
            ->assertOk();

        // All lifecycle users (across every row) load in one batched lookup
        // instead of one per CV. Without the batch, 15 rows would fire 15
        // identical user lookups.
        $batchedUserQueries = collect($queries)
            ->filter(fn (string $sql) => str_contains($sql, 'from "users"') && str_contains($sql, 'where "users"."id" in'))
            ->count();

        expect($batchedUserQueries)->toBeLessThanOrEqual(1);
    });
});

describe('CV review endpoint authorization', function () {
    it('denies approving a CV that fails the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.approve', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('draft');

        $this->actingAs($user)
            ->postJson("/api/cv/{$cv->uuid}/approve")
            ->assertStatus(403);
    });

    it('approves a submitted CV matching the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.approve', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('submitted');

        $this->actingAs($user)
            ->postJson("/api/cv/{$cv->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        expect(Cv::findOrFail($cv->id)->reviewed_by)->toBe($user->id);
    });

    it('denies rejecting a CV that fails the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.reject', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('draft');

        $this->actingAs($user)
            ->postJson("/api/cv/{$cv->uuid}/reject", ['reason' => 'Incomplete'])
            ->assertStatus(403);
    });

    it('rejects a submitted CV matching the policy', function () {
        $user = User::factory()->create();
        scopedCvPermission($user, 'cv.reject', scopedCvStatusPolicy('submitted'));

        $cv = cvBankRecord('submitted');

        $this->actingAs($user)
            ->postJson("/api/cv/{$cv->uuid}/reject", ['reason' => 'Missing documents'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    });
});
