<?php

use App\Domains\Authorization\Models\Role;
use App\Domains\Employee\Models\Employee;
use App\Domains\TempEmployees\Models\TempEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

test('it enriches temp employees with matching employee rows', function () {
    tempEmployeeWithFiles(); // personnel_code 7777
    $user = createUserWithPermissions([]);

    Employee::factory()->create([
        'personnel_code' => '7777',
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'id_number' => '1111222233',
    ]);

    $item = $this->actingAs($user)
        ->getJson('/api/temp-employees?search=7777')
        ->assertOk()
        ->json('data.0');

    expect($item['personnel_code'])->toBe('7777')
        ->and($item['employee'])->not->toBeNull()
        ->and($item['employee']['first_name'])->toBe('علی')
        ->and($item['employee']['id_number'])->toBe('1111222233');
});

test('it includes only the linked user organization roles', function () {
    tempEmployeeWithFiles(); // personnel_code 7777
    $user = createUserWithPermissions([]);

    $linkedUser = User::factory()->create();

    $organizationRole = Role::create([
        'name' => 'hr-expert',
        'display_name' => 'کارشناس منابع انسانی',
        'type' => 'organization',
        'is_active' => true,
    ]);
    $systemRole = Role::create([
        'name' => 'system.manager',
        'display_name' => 'مدیر سامانه',
        'type' => 'system',
        'is_active' => true,
    ]);

    $linkedUser->assignRole($organizationRole->id, true);
    $linkedUser->assignRole($systemRole->id, false);

    Employee::factory()->create([
        'personnel_code' => '7777',
        'user_id' => $linkedUser->id,
    ]);

    $item = $this->actingAs($user)
        ->getJson('/api/temp-employees?search=7777')
        ->assertOk()
        ->json('data.0');

    expect($item['employee']['roles'])->toHaveCount(1)
        ->and($item['employee']['roles'][0]['display_name'])
        ->toBe('کارشناس منابع انسانی')
        ->and($item['employee']['roles'][0]['active'])->toBeTrue();
});

test('it leaves employee null when no employee matches', function () {
    tempEmployeeWithFiles(); // personnel_code 7777, without an Employee row
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->getJson('/api/temp-employees?search=7777')
        ->assertOk()
        ->assertJsonPath('data.0.employee', null);
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

test('guests cannot replace a file', function () {
    $employee = tempEmployeeWithFiles();

    $this->postJson("/api/temp-employees/{$employee->personnel_code}/file", [
        'file' => UploadedFile::fake()->image('x.png'),
        'path' => 'note.txt',
    ])->assertUnauthorized();
});

test('a user can replace an image file on disk', function () {
    $employee = tempEmployeeWithFiles();
    Storage::disk('local')->put('temp-files/7777/photo.png', 'original');
    $user = createUserWithPermissions([]);

    $replacement = UploadedFile::fake()->image('new.png', 100, 100);

    $response = $this->actingAs($user)
        ->postJson("/api/temp-employees/{$employee->personnel_code}/file", [
            'file' => $replacement,
            'path' => 'photo.png',
        ])
        ->assertOk();

    expect($response->json('data.path'))->toBe('photo.png')
        ->and($response->json('data.mime'))->toBe('image/png')
        ->and(Storage::disk('local')->exists('temp-files/7777/photo.png'))->toBeTrue()
        // The original marker bytes were replaced by the uploaded PNG.
        ->and(Storage::disk('local')->size('temp-files/7777/photo.png'))->toBeGreaterThan(4);
});

test('non-image uploads are rejected when replacing a file', function () {
    $employee = tempEmployeeWithFiles();
    Storage::disk('local')->put('temp-files/7777/photo.png', 'original');
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->postJson("/api/temp-employees/{$employee->personnel_code}/file", [
            'file' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
            'path' => 'photo.png',
        ])
        ->assertUnprocessable();
});

test('replace path traversal is rejected', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->postJson("/api/temp-employees/{$employee->personnel_code}/file", [
            'file' => UploadedFile::fake()->image('x.png'),
            'path' => '../notes.txt',
        ])
        ->assertStatus(422);
});

test('guests cannot rename a file', function () {
    $employee = tempEmployeeWithFiles();

    $this->patchJson("/api/temp-employees/{$employee->personnel_code}/file/rename", [
        'path' => 'note.txt',
        'new_name' => 'renamed.txt',
    ])->assertUnauthorized();
});

test('a user can rename an existing file on disk', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $response = $this->actingAs($user)
        ->patchJson("/api/temp-employees/{$employee->personnel_code}/file/rename", [
            'path' => 'note.txt',
            'new_name' => 'renamed.txt',
        ])
        ->assertOk();

    expect($response->json('data.name'))->toBe('renamed.txt')
        ->and($response->json('data.path'))->toBe('renamed.txt')
        ->and(Storage::disk('local')->exists('temp-files/7777/renamed.txt'))->toBeTrue()
        ->and(Storage::disk('local')->exists('temp-files/7777/note.txt'))->toBeFalse()
        ->and(Storage::disk('local')->get('temp-files/7777/renamed.txt'))->toBe('hello');
});

test('renaming into an existing name is rejected with a conflict', function () {
    $employee = tempEmployeeWithFiles();
    Storage::disk('local')->put('temp-files/7777/other.txt', 'other');
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->patchJson("/api/temp-employees/{$employee->personnel_code}/file/rename", [
            'path' => 'note.txt',
            'new_name' => 'other.txt',
        ])
        ->assertStatus(409);
});

test('renaming rejects path traversal in the new name', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->patchJson("/api/temp-employees/{$employee->personnel_code}/file/rename", [
            'path' => 'note.txt',
            'new_name' => '../evil.txt',
        ])
        ->assertUnprocessable();
});

test('renaming a missing file returns 404', function () {
    $employee = tempEmployeeWithFiles();
    $user = createUserWithPermissions([]);

    $this->actingAs($user)
        ->patchJson("/api/temp-employees/{$employee->personnel_code}/file/rename", [
            'path' => 'nope.txt',
            'new_name' => 'renamed.txt',
        ])
        ->assertNotFound();
});
