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

test('vocabulary lists every tag name, for a model to reuse', function () {
    Tag::create(['name' => 'شرکت سمتک', 'slug' => 'samtak-company']);
    Tag::create(['name' => 'بودجه', 'slug' => 'budget']);

    expect($this->service->vocabulary())
        ->toEqualCanonicalizing(['شرکت سمتک', 'بودجه']);
});

test('canonicalize snaps a proposed label onto the hub tag it means', function () {
    Tag::create(['name' => 'شرکت سمتک', 'slug' => 'samtak-company']);

    // The model was shown the name and guessed its own key; the existing pair
    // is what has to win, or one company becomes two tags.
    $canonical = $this->service->canonicalize([
        ['name' => 'شرکت سمتک', 'slug' => 'samtak'],
    ]);

    expect($canonical)->toBe([
        ['name' => 'شرکت سمتک', 'slug' => 'samtak-company'],
    ]);
});

test('canonicalize folds letter variants and separators when matching', function () {
    Tag::create(['name' => 'شرکت سمتک', 'slug' => 'samtak-company']);

    // Arabic yeh/kaf and an underscore: a different spelling, the same tag.
    $canonical = $this->service->canonicalize([
        ['name' => 'شركت_سمتك', 'slug' => 'whatever'],
    ]);

    expect($canonical)->toBe([
        ['name' => 'شرکت سمتک', 'slug' => 'samtak-company'],
    ]);
});

test('canonicalize makes a safe key for a new tag and keeps its label', function () {
    $canonical = $this->service->canonicalize([
        ['name' => 'آموزش رایگان', 'slug' => 'Free Training!'],
    ]);

    expect($canonical)->toBe([
        ['name' => 'آموزش رایگان', 'slug' => 'free-training'],
    ]);
});

test('canonicalize dedupes by slug and reads a bare string as both halves', function () {
    $canonical = $this->service->canonicalize([
        'budget',
        ['name' => 'بودجه', 'slug' => 'budget'],
    ]);

    expect($canonical)->toHaveCount(1)
        ->and($canonical[0]['slug'])->toBe('budget');
});

test('sync creates a proposed tag under the label and key it was given', function () {
    $model = TaggableStub::create(['name' => 'a']);

    $this->service->syncTagProposals($model, [
        ['name' => 'آموزش رایگان', 'slug' => 'free-training'],
    ]);

    expect(Tag::where('slug', 'free-training')->value('name'))->toBe('آموزش رایگان')
        ->and($model->tags()->pluck('slug')->all())->toBe(['free-training']);
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
