<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('GET /api/tags', function () {
    it('forbids a user without tag.view', function () {
        $user = User::factory()->create();

        actingAs($user)->getJson('/api/tags')->assertForbidden();
    });

    it('lists tags with usage counts, most-used first', function () {
        $a = BoardMeeting::factory()->create();
        $b = BoardMeeting::factory()->create();
        $service = app(TagService::class);

        $service->syncTags($a, ['پروژه سونگون', 'قرارداد']);
        $service->syncTags($b, ['پروژه سونگون']);

        $user = createUserWithPermissions(['tag.view']);
        $response = actingAs($user)->getJson('/api/tags')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        // The TagService normalizes slugs (trim/lowercase, spaces kept).
        expect($slugs)->toBe(['پروژه سونگون', 'قرارداد'])
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0.usage_count'))->toBeGreaterThanOrEqual(2);
    });
});

describe('POST /api/tags', function () {
    it('creates a tag with the manage permission', function () {
        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)
            ->postJson('/api/tags', ['name' => 'تغییرات قراردادی', 'slug' => 'contractual-changes'])
            ->assertCreated();

        expect($response->json('data.slug'))->toBe('contractual-changes')
            ->and(Tag::where('slug', 'contractual-changes')->exists())->toBeTrue();
    });

    it('rejects duplicate slugs', function () {
        Tag::create(['name' => 'موجود', 'slug' => 'existing']);
        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)
            ->postJson('/api/tags', ['name' => 'جدید', 'slug' => 'existing'])
            ->assertUnprocessable();
    });

    it('forbids a user without tag.manage', function () {
        $user = createUserWithPermissions(['tag.view']);

        actingAs($user)
            ->postJson('/api/tags', ['name' => 'x', 'slug' => 'x'])
            ->assertForbidden();
    });
});

describe('PUT+DELETE /api/tags/{tag}', function () {
    it('renames but never re-slugs', function () {
        $tag = Tag::create(['name' => 'قدیم', 'slug' => 'old']);
        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)
            ->putJson("/api/tags/{$tag->id}", ['name' => 'نو', 'slug' => 'new'])
            ->assertOk();

        expect($response->json('data.name'))->toBe('نو')
            ->and($response->json('data.slug'))->toBe('old');
    });

    it('deletes a tag and its attachments', function () {
        $meeting = BoardMeeting::factory()->create();
        $tag = Tag::create(['name' => 'حذف', 'slug' => 'delete-me']);
        $meeting->tags()->attach($tag);
        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)->deleteJson("/api/tags/{$tag->id}")->assertOk();

        expect(Tag::find($tag->id))->toBeNull()
            ->and($meeting->tags()->count())->toBe(0);
    });
});

describe('POST /api/tags/attach', function () {
    it('syncs a meeting tag set through the service', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['tag.manage', 'board-meeting.update']);

        $response = actingAs($user)
            ->postJson('/api/tags/attach', [
                'taggable_type' => 'board-meeting',
                'taggable_id' => $meeting->id,
                'slugs' => ['پروژه سونگون', 'خرید'],
            ])
            ->assertOk();

        expect($response->json('tags'))->toHaveCount(2)
            ->and($meeting->tags()->count())->toBe(2);

        actingAs($user)
            ->postJson('/api/tags/attach', [
                'taggable_type' => 'board-meeting',
                'taggable_id' => $meeting->id,
                'slugs' => ['خرید'],
            ])
            ->assertOk();

        expect($meeting->refresh()->tags()->pluck('slug')->all())->toBe(['خرید']);
    });

    it('forbids a tag manager without the meeting update permission (P5)', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)
            ->postJson('/api/tags/attach', [
                'taggable_type' => 'board-meeting',
                'taggable_id' => $meeting->id,
                'slugs' => ['خرید'],
            ])
            ->assertForbidden();

        expect($meeting->tags()->count())->toBe(0);
    });

    it('rejects unknown taggable types', function () {
        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)
            ->postJson('/api/tags/attach', [
                'taggable_type' => 'spaceship',
                'taggable_id' => 1,
                'slugs' => ['x'],
            ])
            ->assertUnprocessable();
    });
});
