<?php

use App\Models\TempEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function tempEmployeeWithFiles(): TempEmployee
{
    Storage::fake('local');

    $employee = TempEmployee::factory()->create([
        'personnel_code' => '7777',
    ]);

    $base = 'temp-files/7777';
    Storage::disk('local')->makeDirectory("{$base}/sub");
    Storage::disk('local')->put("{$base}/note.txt", 'hello');
    Storage::disk('local')->put("{$base}/sub/doc.pdf", '%PDF-1.4 fake');

    return $employee;
}

test('guests cannot list temp employees', function () {
    $this->getJson('/api/temp-employees')->assertUnauthorized();
});

test('it lists temp employees paginated', function () {
    tempEmployeeWithFiles();
    TempEmployee::factory()->count(20)->create();
    $user = createUserWithPermissions([]);

    $response = $this->actingAs($user)
        ->getJson('/api/temp-employees?per_page=10')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.total'))->toBe(21)
        ->and($response->json('meta.last_page'))->toBe(3);
});

test('search filters by code, id number, and name fragments', function () {
    tempEmployeeWithFiles();
    TempEmployee::factory()->create([
        'personnel_code' => '9001',
        'id_number' => '1111222233',
        'first_name' => 'سارا',
        'last_name' => 'مرادی',
    ]);
    $user = createUserWithPermissions([]);

    // By personnel-code fragment.
    $byCode = $this->actingAs($user)->getJson('/api/temp-employees?search=9001');
    expect($byCode->json('data'))->toHaveCount(1)
        ->and($byCode->json('data.0.personnel_code'))->toBe('9001');

    // By last-name fragment.
    $byName = $this->actingAs($user)->getJson('/api/temp-employees?search='.urlencode('مراد'));
    expect($byName->json('data'))->toHaveCount(1)
        ->and($byName->json('data.0.last_name'))->toBe('مرادی');

    // No match.
    $this->actingAs($user)
        ->getJson('/api/temp-employees?search=zzz')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

test('sync creates employees from patterned folders and skips the rest', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->makeDirectory('temp-files/5555 - علی رضایی');
    $disk->put('temp-files/5555 - علی رضایی/a.txt', 'x');
    $disk->makeDirectory('temp-files/نامعتبر بدون الگو');

    $response = $this->actingAs(createUserWithPermissions([]))
        ->postJson('/api/temp-employees/sync')
        ->assertOk();

    expect($response->json('data.created'))->toBe(1)
        ->and($response->json('data.skipped'))->toBe(['نامعتبر بدون الگو']);

    $employee = TempEmployee::query()->where('personnel_code', '5555')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->first_name)->toBe('علی')
        ->and($employee->last_name)->toBe('رضایی')
        ->and($employee->files_directory)->toBe('temp-files/5555 - علی رضایی');

    // The tree endpoint reads from the REAL synced folder.
    $tree = $this->actingAs(createUserWithPermissions([]))
        ->getJson('/api/temp-employees/5555/tree')
        ->assertOk()
        ->json('data');

    expect(collect($tree)->pluck('path'))->toContain('a.txt');
});

test('it returns the recursive file tree', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $tree = $this->actingAs($user)
        ->getJson("/api/temp-employees/{$employee->personnel_code}/tree")
        ->assertOk()
        ->json('data');

    $dirs = array_values(array_filter($tree, fn (array $n) => $n['type'] === 'dir'));
    $files = array_values(array_filter($tree, fn (array $n) => $n['type'] === 'file'));

    expect($tree)->toHaveCount(3)
        ->and(count($dirs))->toBe(1)
        ->and(count($files))->toBe(2)
        // Dirs sort before files.
        ->and($tree[0]['type'])->toBe('dir')
        ->and(collect($files)->pluck('path')->toArray())
        ->toContain('note.txt')
        ->toContain('sub/doc.pdf');
});

test('it serves a file inline for preview', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    // BinaryFileResponse content is not introspectable through the test
    // client; the status plus the inline disposition/mime prove the stream.
    $this->actingAs($user)
        ->getJson("/api/temp-employees/{$employee->personnel_code}/file?path=".urlencode('note.txt'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="note.txt"');
});

test('it serves a file as an attachment download when requested', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->getJson("/api/temp-employees/{$employee->personnel_code}/file?path=".urlencode('note.txt').'&download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="note.txt"');
});

test('path traversal is rejected with a 404', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    foreach (['..%2F..%2F..%2F.env', 'sub%2F..%2F..%2F..%2Fcomposer.json'] as $attempt) {
        $this->actingAs($user)
            ->getJson("/api/temp-employees/{$employee->personnel_code}/file?path={$attempt}")
            ->assertNotFound();
    }
});
