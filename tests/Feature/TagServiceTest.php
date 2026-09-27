<?php

use App\Domains\Tagging\Concerns\Taggable;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('taggable_stubs', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $this->service = new TagService;
});

afterEach(function () {
    Schema::dropIfExists('taggable_stubs');
});

test('sync creates missing tags and attaches them', function () {
    $model = TaggableStub::create(['name' => 'a']);

    $this->service->syncTags($model, ['بودجه', 'قرارداد']);

    expect($model->tags()->pluck('slug')->all())->toBe(['بودجه', 'قرارداد'])
        ->and(Tag::count())->toBe(2);
});

test('sync detaches tags missing from the new list', function () {
    $model = TaggableStub::create(['name' => 'a']);
    $this->service->syncTags($model, ['one', 'two']);

    $this->service->syncTags($model, ['two', 'three']);

    expect($model->tags()->orderBy('slug')->pluck('slug')->all())->toBe(['three', 'two'])
        ->and(Tag::count())->toBe(3);
});

test('sync is idempotent and dedupes input', function () {
    $model = TaggableStub::create(['name' => 'a']);

    $this->service->syncTags($model, ['x', 'X', ' x ']);
    $this->service->syncTags($model, ['x']);

    expect(Tag::count())->toBe(1)
        ->and($model->tags()->count())->toBe(1);
});

test('resolve creates unknown tags and reuses known ones', function () {
    Tag::create(['name' => 'مالی', 'slug' => 'mali']);

    $tags = $this->service->resolve(['mali', 'jadid']);

    expect($tags)->toHaveCount(2)
        ->and(collect($tags)->map(fn ($t) => $t->slug)->all())->toBe(['mali', 'jadid'])
        ->and(Tag::count())->toBe(2);
});

test('and filter returns only models carrying every tag', function () {
    $both = TaggableStub::create(['name' => 'both']);
    $onlyOne = TaggableStub::create(['name' => 'one']);
    $none = TaggableStub::create(['name' => 'none']);

    $this->service->syncTags($both, ['a', 'b']);
    $this->service->syncTags($onlyOne, ['a']);

    $found = $this->service->filterQuery(new TaggableStub, ['a', 'b'])->get();

    expect($found->pluck('name')->all())->toBe(['both']);
});

test('and filter with empty list returns everything', function () {
    TaggableStub::create(['name' => 'a']);
    TaggableStub::create(['name' => 'b']);

    expect($this->service->filterQuery(new TaggableStub, [])->count())->toBe(2);
});

/** Minimal tagged model for service tests. */
final class TaggableStub extends Model
{
    use Taggable;

    protected $table = 'taggable_stubs';

    protected $fillable = ['name'];

    public $timestamps = false;
}
