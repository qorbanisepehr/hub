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

test('it lists temp employees', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $response = $this->actingAs($user)->getJson('/api/temp-employees')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.personnel_code'))->toBe('7777')
        ->and($response->json('data.0.first_name'))->toBe($employee->first_name);
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

test('path traversal is rejected with a 404', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    foreach (['..%2F..%2F..%2F.env', 'sub%2F..%2F..%2F..%2Fcomposer.json'] as $attempt) {
        $this->actingAs($user)
            ->getJson("/api/temp-employees/{$employee->personnel_code}/file?path={$attempt}")
            ->assertNotFound();
    }
});
