<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Services\DocumentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * A user who may drive the import surface.
 */
function bundleArchiveUser(): User
{
    return createUserWithPermissions(['employee.list', 'employee.import']);
}

/**
 * A named fake upload — the name is the contract being tested.
 */
function bundleArchiveFile(string $name, string $contents = 'pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

/**
 * A board meeting filed under the given Jalali date (the key the archive
 * resolves a file's meeting by).
 */
function bundleMeeting(string $jalali = '1401-10-12'): BoardMeeting
{
    return BoardMeeting::factory()->create([
        'held_on_jalali' => $jalali,
        'held_on' => '2023-01-02',
    ]);
}

/**
 * The fixed approvals category (the same firstOrCreate boundary the
 * upload requests resolve through the shared support class).
 */
function bundleMeetingCategory(): DocumentCategory
{
    return DocumentCategory::query()->firstOrCreate(
        ['slug' => 'board-meeting-approvals'],
        ['name' => 'مصوبات جلسات هیئت مدیره', 'type' => DocumentCategory::TYPE_PERSONNEL, 'sort_order' => 90],
    );
}

describe('document bundle import (imports/{entity}/documents/*)', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');
        Storage::fake('local');
    });

    it('blocks unauthenticated and permission-less access', function () {
        $this->postJson('/api/imports/board-meeting-documents/documents/dry-run')->assertStatus(401);
        $this->postJson('/api/imports/board-meeting-documents/documents/confirm')->assertStatus(401);

        $user = createUserWithPermissions(['employee.list']);

        actingAs($user)
            ->postJson('/api/imports/board-meeting-documents/documents/dry-run')
            ->assertStatus(403);
    });

    it('404s unknown and tabular entities on the document arm', function () {
        $user = bundleArchiveUser();

        $body = ['files' => [bundleArchiveFile('1001(1401-10-12).pdf')]];

        actingAs($user)
            ->post('/api/imports/nope/documents/dry-run', $body, ['Accept' => 'application/json'])
            ->assertStatus(404);

        actingAs($user)
            ->post('/api/imports/employees/documents/dry-run', $body, ['Accept' => 'application/json'])
            ->assertStatus(404);
    });

    it('lists the document entity in the import catalog with its kind and cap', function () {
        $user = bundleArchiveUser();

        $response = actingAs($user)->getJson('/api/imports/entities');
        $entities = collect($response->json('data'));

        $document = $entities->firstWhere('entity', 'board-meeting-documents');

        // The catalog advertises the effective cap: the lower of the entity
        // config and the host's PHP max_file_uploads (0 = unlimited).
        $hostFileCap = (int) ini_get('max_file_uploads');
        $entityCap = (int) config('documents.board-meeting.max_files');
        $expectedCap = $hostFileCap > 0 ? min($entityCap, $hostFileCap) : $entityCap;

        expect($entities->pluck('entity'))->toContain('board-meeting-documents')
            ->and($document['kind'])->toBe('document')
            ->and($document['max_files'])->toBe($expectedCap)
            ->and($entities->firstWhere('entity', 'employees')['kind'])->toBe('tabular');
    });

    it('dry-runs a batch and reports every status without persisting', function () {
        $meeting = bundleMeeting();
        $user = bundleArchiveUser();

        // The meeting already holds 1001/ص/1401 (uploaded the normal way).
        app(DocumentService::class)->upload(
            $meeting,
            bundleArchiveFile('existing.pdf'),
            bundleMeetingCategory(),
            metadata: ['registry_number' => '1001/ص/1401'],
        );

        $response = actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [
                    bundleArchiveFile('1001(1401-10-12).pdf'),
                    bundleArchiveFile('1002-2(1401-10-12).pdf'),
                    bundleArchiveFile('1003(1401-01-01).pdf'),
                    bundleArchiveFile('misc.pdf'),
                    bundleArchiveFile('1008(1401-10-12).pdf'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $data = $response->json('data');
        $rows = collect($data['rows']);

        expect($data['total'])->toBe(5)
            ->and($data['importable'])->toBe(2)
            ->and($data['rejected'])->toBe(3);

        expect($rows->firstWhere('file', '1001(1401-10-12).pdf')['status'])->toBe('duplicate')
            ->and($rows->firstWhere('file', '1001(1401-10-12).pdf')['reason'])->toContain('از قبل')

            ->and($rows->firstWhere('file', '1002-2(1401-10-12).pdf')['status'])->toBe('ready')
            ->and($rows->firstWhere('file', '1002-2(1401-10-12).pdf')['number'])->toBe('2/1002/ص/1401')
            ->and($rows->firstWhere('file', '1002-2(1401-10-12).pdf')['date'])->toBe('1401-10-12')
            ->and($rows->firstWhere('file', '1002-2(1401-10-12).pdf')['meeting']['label'])->toContain('1401/10/12')

            ->and($rows->firstWhere('file', '1003(1401-01-01).pdf')['status'])->toBe('no_meeting')
            ->and($rows->firstWhere('file', '1003(1401-01-01).pdf')['reason'])->toContain('1401-01-01')
            ->and($rows->firstWhere('file', '1003(1401-01-01).pdf')['number'])->toBe('1003')

            ->and($rows->firstWhere('file', 'misc.pdf')['status'])->toBe('pattern')
            ->and($rows->firstWhere('file', 'misc.pdf')['reason'])->toContain('الگوی')

            ->and($rows->firstWhere('file', '1008(1401-10-12).pdf')['status'])->toBe('ready')
            ->and($rows->firstWhere('file', '1008(1401-10-12).pdf')['number'])->toBe('1008/ص/1401');

        // Dry-run wrote nothing beyond the seeded usage.
        expect(Document::whereHas('usages', fn ($query) => $query->where('entity_type', BoardMeeting::class))->count())->toBe(1);
    });

    it('flags two files claiming the same meeting and number inside one batch', function () {
        bundleMeeting();
        $user = bundleArchiveUser();

        $response = actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [
                    bundleArchiveFile('1007(1401-10-12).pdf'),
                    bundleArchiveFile('1007(1401-10-12).pdf'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $rows = collect($response->json('data.rows'));

        expect($rows[0]['status'])->toBe('ready')
            ->and($rows[1]['status'])->toBe('duplicate')
            ->and($rows[1]['reason'])->toContain('دسته');
    });

    it('confirms a batch: imports the ready files and reports the rest', function () {
        $meeting = bundleMeeting();
        $user = bundleArchiveUser();

        app(DocumentService::class)->upload(
            $meeting,
            bundleArchiveFile('existing.pdf'),
            bundleMeetingCategory(),
            metadata: ['registry_number' => '1001/ص/1401'],
        );

        $response = actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/confirm', [
                'files' => [
                    bundleArchiveFile('1001(1401-10-12).pdf'),
                    bundleArchiveFile('1002-2(1401-10-12).pdf'),
                    bundleArchiveFile('1003(1401-01-01).pdf'),
                    bundleArchiveFile('misc.pdf'),
                    bundleArchiveFile('1008(1401-10-12).pdf'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $data = $response->json('data');

        expect($data['imported'])->toHaveCount(2)
            ->and($data['imported'][0]['source_file'])->toBe('1002-2(1401-10-12).pdf')
            ->and($data['imported'][0]['meeting']['label'])->toContain('1401/10/12')
            ->and($data['imported'][0]['document']['metadata']['registry_number'])->toBe('2/1002/ص/1401')
            ->and($data['imported'][0]['document']['url'])->toContain('/board-meetings/documents/')
            ->and($data['imported'][1]['source_file'])->toBe('1008(1401-10-12).pdf')
            ->and($data['imported'][1]['document']['metadata']['registry_number'])->toBe('1008/ص/1401')

            ->and($data['skipped'])->toHaveCount(3)
            ->and(collect($data['skipped'])->pluck('reason_code')->sort()->values()->all())
            ->toBe(['duplicate', 'no_meeting', 'pattern'])
            ->and($data['skipped'])->each->toHaveKeys(['file', 'reason_code', 'reason'])
            ->and($data['failed'])->toBe([]);

        // The seeded usage plus the two imported مصوب.
        expect(Document::whereHas('usages', fn ($query) => $query->where('entity_type', BoardMeeting::class))->count())->toBe(3);
    });

    it('accepts a user-defined pattern and resolves the same parts', function () {
        bundleMeeting();
        $user = bundleArchiveUser();

        $pattern = '/^doc_(?<number>\d{3})_(?<date>\d{4}-\d{2}-\d{2})\.pdf$/';

        $response = actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [bundleArchiveFile('doc_123_1401-10-12.pdf')],
                'pattern' => $pattern,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $row = collect($response->json('data.rows'))->first();

        expect($row['status'])->toBe('ready')
            ->and($row['number'])->toBe('123/ص/1401')
            ->and($row['date'])->toBe('1401-10-12')
            ->and($response->json('data.pattern'))->toBe($pattern);
    });

    it('rejects an invalid or group-less pattern with 422', function () {
        $user = bundleArchiveUser();

        actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [bundleArchiveFile('1001(1401-10-12).pdf')],
                'pattern' => '[',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pattern']);

        actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [bundleArchiveFile('1001(1401-10-12).pdf')],
                'pattern' => '^abc$',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pattern']);
    });

    it('caps the batch size', function () {
        $user = bundleArchiveUser();

        $files = [];

        for ($i = 1; $i <= 51; $i++) {
            $files[] = bundleArchiveFile("{$i}(1401-10-12).pdf");
        }

        actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', ['files' => $files], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);
    });

    it('validates each file type', function () {
        $user = bundleArchiveUser();

        actingAs($user)
            ->post('/api/imports/board-meeting-documents/documents/dry-run', [
                'files' => [
                    bundleArchiveFile('1001(1401-10-12).pdf'),
                    bundleArchiveFile('script.php', '<?php'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.1']);
    });
});
