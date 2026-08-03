<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Recruitment\Models\Questionnaire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function usageCategory(string $slug, string $name = 'Test Category'): DocumentCategory
{
    return DocumentCategory::create([
        'name' => $name,
        'slug' => $slug,
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
}

function createUsageDraft(): string
{
    $suffix = str_pad(substr((string) hrtime(true), -7), 7, '0', STR_PAD_LEFT);

    $questionnaire = Questionnaire::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test'.$suffix.'@example.com',
        'mobile' => '0912'.$suffix,
        'status' => 'draft',
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ]);

    test()->withHeader('X-Access-Token', grantToken($questionnaire->uuid));

    return $questionnaire->uuid;
}

describe('questionnaire document usages (shared files)', function () {
    it('reuses one file across categories and creates a usage per category', function () {
        Storage::fake('local');
        $uuid = createUsageDraft();
        $skills = usageCategory('language-certificate');
        $license = usageCategory('course-certificates');

        $shared = UploadedFile::fake()->createWithContent('cert.pdf', 'shared-cert-content');

        $first = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $skills->id,
            'file' => $shared,
        ])->assertCreated()->json('data');

        $second = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $license->id,
            'file' => $shared,
        ])->assertCreated()->json('data');

        expect($second['id'])->toBe($first['id'])
            ->and($second['usage_id'])->not->toBe($first['usage_id'])
            ->and($second['category_slug'])->toBe('course-certificates');

        expect(Document::count())->toBe(1)
            ->and(DocumentUsage::count())->toBe(2);
    });

    it('returns one index entry per usage so a shared file appears under both categories', function () {
        Storage::fake('local');
        $uuid = createUsageDraft();
        $skills = usageCategory('language-certificate');
        $license = usageCategory('course-certificates');

        $shared = UploadedFile::fake()->createWithContent('cert.pdf', 'shared-cert-content');

        $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $skills->id,
            'file' => $shared,
        ])->assertCreated();

        $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $license->id,
            'file' => $shared,
        ])->assertCreated();

        $data = $this->getJson("/api/questionnaire/{$uuid}/documents")
            ->assertOk()
            ->json('data');

        expect($data)->toHaveCount(2)
            ->and(collect($data)->pluck('category_slug')->all())
            ->toMatchArray(['language-certificate', 'course-certificates'])
            ->and(collect($data)->pluck('usage_id')->unique()->count())->toBe(2)
            ->and(collect($data)->pluck('id')->unique()->count())->toBe(1)
            ->and(collect($data)->pluck('usage_id')->every(fn ($id) => is_int($id)))->toBeTrue();
    });

    it('detaches only the requested usage when a file is shared', function () {
        Storage::fake('local');
        $uuid = createUsageDraft();
        $skills = usageCategory('language-certificate');
        $license = usageCategory('course-certificates');

        $shared = UploadedFile::fake()->createWithContent('cert.pdf', 'shared-cert-content');

        $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $skills->id,
            'file' => $shared,
        ])->assertCreated();

        $licenseDoc = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $license->id,
            'file' => $shared,
        ])->assertCreated()->json('data');

        $this->deleteJson("/api/questionnaire/{$uuid}/documents/{$licenseDoc['usage_id']}")
            ->assertOk();

        $data = $this->getJson("/api/questionnaire/{$uuid}/documents")->json('data');

        expect($data)->toHaveCount(1)
            ->and($data[0]['category_slug'])->toBe('language-certificate');

        expect(Document::count())->toBe(1);
        $document = Document::first();
        Storage::disk('local')->assertExists($document->path);
    });

    it('removes the file and document when the last usage is detached', function () {
        Storage::fake('local');
        $uuid = createUsageDraft();
        $skills = usageCategory('language-certificate');

        $doc = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $skills->id,
            'file' => UploadedFile::fake()->createWithContent('cert.pdf', 'single-cert-content'),
        ])->assertCreated()->json('data');

        $document = Document::first();
        $path = $document->path;

        $this->deleteJson("/api/questionnaire/{$uuid}/documents/{$doc['usage_id']}")
            ->assertOk();

        $this->getJson("/api/questionnaire/{$uuid}/documents")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        expect(Document::count())->toBe(0)
            ->and(DocumentUsage::count())->toBe(0);
        Storage::disk('local')->assertMissing($path);

        $this->deleteJson("/api/questionnaire/{$uuid}/documents/{$doc['usage_id']}")
            ->assertNotFound();
    });

    it('404s when detaching a usage that belongs to another questionnaire', function () {
        Storage::fake('local');
        $uuidA = createUsageDraft();
        $uuidB = createUsageDraft();
        $skills = usageCategory('language-certificate');

        $doc = $this->postJson("/api/questionnaire/{$uuidA}/documents", [
            'document_category_id' => $skills->id,
            'file' => UploadedFile::fake()->createWithContent('cert.pdf', 'own-cert-content'),
        ], ['X-Access-Token' => grantToken($uuidA)])->assertCreated()->json('data');

        $this->deleteJson("/api/questionnaire/{$uuidB}/documents/{$doc['usage_id']}")
            ->assertNotFound();

        $this->getJson("/api/questionnaire/{$uuidA}/documents", ['X-Access-Token' => grantToken($uuidA)])
            ->assertJsonCount(1, 'data');
    });
});
