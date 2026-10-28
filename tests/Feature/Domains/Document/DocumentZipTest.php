<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

describe('document zip', function () {
    describe('authorization & validation', function () {
        it('blocks unauthenticated access', function () {
            $this->postJson('/api/documents/zip', ['ids' => [1]])->assertStatus(401);
        });

        it('requires a non-empty list of usage ids', function () {
            $user = createUserWithPermissions(['employee.documents.download']);

            $this->actingAs($user)
                ->postJson('/api/documents/zip', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['ids']);

            $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => []])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['ids']);
        });

        it('404s an unknown usage id and never streams a partial bundle', function () {
            Storage::fake('local');

            $user = createUserWithPermissions(['employee.documents.download']);

            $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => [999999]])
                ->assertStatus(404)
                ->assertJson(['message' => __('messages.not_found')]);
        });

        it('404s a trashed usage in the selection', function () {
            Storage::fake('local');

            $user = createUserWithPermissions(['employee.documents.download']);
            $category = personnelDocumentCategory('resume');
            $trashed = placementUsage(categoryDocument($category), null);

            DocumentUsage::query()->whereKey($trashed->id)->delete();

            $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => [$trashed->id]])
                ->assertStatus(404);
        });

        it('rejects the whole bundle when a single document is not downloadable', function () {
            $user = User::factory()->create();
            zipScopedRole($user, 'employee.documents.download');

            $allowed = placementUsage(
                categoryDocument(policySectionCategory('contract-file')),
                'employment',
            );
            $denied = placementUsage(
                categoryDocument(policySectionCategory('insurance-file')),
                'insurance',
            );

            $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => [$allowed->id, $denied->id]])
                ->assertStatus(403)
                ->assertJson(['message' => __('messages.permission_denied')]);
        });

        it('over the count cap the whole bundle is refused with 422', function () {
            Storage::fake('local');

            $user = createUserWithPermissions([
                'employee.documents.upload',
                'employee.documents.download',
            ]);
            $category = personnelDocumentCategory('resume');
            $employee = Employee::factory()->create();

            $ids = [];

            foreach (range(1, 3) as $number) {
                $ids[] = $this->actingAs($user)
                    ->postJson('/api/documents', uploadPayload($category, $employee, 'file.pdf'))
                    ->assertCreated()
                    ->json('data.id');
            }

            config(['documents.zip.max_files' => 2]);

            $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => $ids])
                ->assertStatus(422)
                ->assertJson(['message' => __('document.zip_limit_exceeded')]);
        });
    });

    describe('structure', function () {
        it('streams one folder per owner with collision-suffixed ASCII names', function () {
            Storage::fake('local');

            $user = createUserWithPermissions([
                'employee.documents.upload',
                'employee.documents.download',
            ]);
            $category = personnelDocumentCategory('resume', 'رزومه');
            $employee = Employee::factory()->create();

            $ids = [
                $this->actingAs($user)
                    ->postJson('/api/documents', uploadPayload($category, $employee, 'first-resume.pdf', 'first resume'))
                    ->assertCreated()
                    ->json('data.id'),
                $this->actingAs($user)
                    ->postJson('/api/documents', uploadPayload($category, $employee, 'another-resume.pdf', 'second resume'))
                    ->assertCreated()
                    ->json('data.id'),
            ];

            $response = $this->actingAs($user)
                ->postJson('/api/documents/zip', ['ids' => $ids])
                ->assertOk()
                ->assertHeader('Content-Type', 'application/zip')
                ->assertHeader('Content-Disposition', 'attachment; filename=documents-'.now()->format('Y-m-d').'.zip');

            $bundle = zipInspect($response->streamedContent(), 2);

            $folder = Str::slug($employee->personnel_code);

            expect($bundle['names'])->toBe([
                "{$folder}/{$folder}-resume.pdf",
                "{$folder}/{$folder}-resume-2.pdf",
            ])
                ->and($bundle['contents'])->sequence('first resume', 'second resume');
        });

        it('keeps Persian names out of the archive entries', function () {
            Storage::fake('local');

            $user = createUserWithPermissions([
                'employee.documents.upload',
                'employee.documents.download',
            ]);
            $category = personnelDocumentCategory('birth-certificate', 'شناسنامه');
            $employee = Employee::factory()->create();

            $id = $this->actingAs($user)
                ->postJson('/api/documents', uploadPayload($category, $employee, 'گواهی-جدید.pdf'))
                ->assertCreated()
                ->json('data.id');

            $bundle = zipInspect(
                $this->actingAs($user)
                    ->postJson('/api/documents/zip', ['ids' => [$id]])
                    ->assertOk()
                    ->streamedContent(),
                1,
            );

            expect($bundle['names'])->toHaveCount(1)
                ->and($bundle['names'][0])->toMatch('/^[a-z0-9-]+\/[a-z0-9-]+\.pdf$/')
                ->and($bundle['names'][0])->not->toContain('شناسنامه');
        });
    });
});

/** Payload for an employee document upload through the regular endpoint. */
function uploadPayload(DocumentCategory $category, Employee $employee, string $fileName, string $content = 'file'): array
{
    return [
        'documentable_type' => 'employee',
        'documentable_id' => $employee->id,
        'document_category_id' => $category->id,
        'file' => UploadedFile::fake()->createWithContent($fileName, $content),
    ];
}

/** A document row belonging to the given category. */
function categoryDocument(DocumentCategory $category): Document
{
    return Document::factory()->create(['category_id' => $category->id]);
}

/**
 * A usage on a fresh employee without a real stored file: used by the
 * authorization tests, which stop at the deny-all check before any file is
 * read.
 */
function placementUsage(Document $document, ?string $sectionKey): DocumentUsage
{
    $employee = Employee::factory()->create();

    // A field placement always implies a section (model invariant), so the
    // field key only exists when the test asked for a section at all.
    return DocumentUsage::create([
        'document_id' => $document->id,
        'entity_type' => Employee::class,
        'entity_id' => $employee->id,
        'section_key' => $sectionKey,
        'field_key' => $sectionKey !== null ? 'contract' : null,
    ]);
}

function policySectionCategory(string $slug): DocumentCategory
{
    return DocumentCategory::create([
        'name' => 'Employment',
        'slug' => $slug.'-'.uniqid(),
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
}

/**
 * A role granting the given permission only on the `employment` section —
 * the same shape `documentScopedRole` builds in the policy tests, kept here
 * under its own name so this file runs standalone.
 */
function zipScopedRole(User $user, string $permissionName): Role
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee.documents'], ['name' => 'Employee Documents']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'zip-scoped-'.uniqid(),
        'display_name' => 'Zip Scoped',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'document_usage.section_key', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'employment'],
            ],
        ],
    ]);

    $user->assignRole($role->id, true);

    return $role;
}

/**
 * Open a streamed zip body, read every entry's name and content in archive
 * order, then dispose the artifacts. Asserting the count inside keeps every
 * consumer honest about what it expects instead of silently comparing fewer.
 *
 * @return array{names: list<string>, contents: list<string>}
 */
function zipInspect(string $body, int $expectedCount): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'zip-test-');
    file_put_contents($path, $body);

    $archive = new ZipArchive;

    try {
        expect($archive->open($path, ZipArchive::RDONLY))->toBeTrue();
        expect($archive->count())->toBe($expectedCount);

        $names = [];
        $contents = [];

        for ($index = 0; $index < $archive->count(); $index++) {
            $names[] = $archive->getNameIndex($index);
            $contents[] = $archive->getFromIndex($index);
        }
    } finally {
        $archive->close();
        @unlink($path);
    }

    return ['names' => $names, 'contents' => $contents];
}
