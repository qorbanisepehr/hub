<?php

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Sections\ContractsSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->employee = Employee::factory()->create();

    $this->contract = DocumentCategory::create([
        'name' => 'قرارداد',
        'slug' => 'contract',
        'type' => 'personnel',
    ]);
});

function uploadContractFile($employee, $category, string $fieldKey)
{
    $user = createUserWithPermissions([
        'employee.documents.upload',
        'employee.documents.view',
    ]);

    return test()->actingAs($user)->postJson(
        "/api/employees/{$employee->id}/documents",
        [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->image('contract.jpg'),
            'section_key' => 'contracts',
            'field_key' => $fieldKey,
        ],
    );
}

test('a contract placement accepts scans up to the cap', function () {
    for ($i = 0; $i < 5; $i++) {
        uploadContractFile($this->employee, $this->contract, 'con-0')->assertCreated();
    }

    uploadContractFile($this->employee, $this->contract, 'con-0')->assertStatus(422);
});

test('caps are independent per contract row', function () {
    uploadContractFile($this->employee, $this->contract, 'con-0')->assertCreated();
    uploadContractFile($this->employee, $this->contract, 'con-1')->assertCreated();
});

test('requirements endpoint exposes the contracts dynamic requirement group', function () {
    $user = createUserWithPermissions(['employee.list']);

    $response = $this->actingAs($user)
        ->getJson('/api/employees/document-requirements')
        ->assertOk();

    $group = collect($response->json('dynamic_requirements'))
        ->firstWhere('section_key', 'contracts');

    expect($group)->not->toBeNull()
        ->and($group['pattern'])->toBe(ContractsSection::FIELD_KEY_PATTERN)
        ->and($group['requirements']['contract']['required'])->toBeFalse()
        ->and($group['requirements']['contract']['min_files'])->toBe(0)
        ->and($group['requirements']['contract']['max_files'])->toBe(5);
});

test('contract document structure name carries owner, category and row label', function () {
    $this->employee->update([
        'section_contracts' => ['contracts' => [
            ['start_date' => '2023-06-01', 'end_date' => '2024-06-01'],
        ]],
    ]);

    uploadContractFile($this->employee, $this->contract, 'con-0')->assertCreated();

    $user = createUserWithPermissions(['employee.documents.view']);

    $response = $this->actingAs($user)
        ->getJson("/api/employees/{$this->employee->id}/documents")
        ->assertOk();

    expect($response->json('data.0.structure_name'))
        ->toBe("{$this->employee->personnel_code} — قرارداد — قرارداد از 2023-06-01 تا 2024-06-01")
        ->and($response->json('data.0.structure_name_slug'))
        ->toBe("{$this->employee->personnel_code}-contract-contract-2023-06-01-to-2024-06-01");
});

test('an unlabeled contract row falls back to the numbered label', function () {
    $this->employee->update([
        'section_contracts' => ['contracts' => [
            ['start_date' => null, 'end_date' => null],
        ]],
    ]);

    uploadContractFile($this->employee, $this->contract, 'con-0')->assertCreated();

    $user = createUserWithPermissions(['employee.documents.view']);

    $response = $this->actingAs($user)
        ->getJson("/api/employees/{$this->employee->id}/documents")
        ->assertOk();

    expect($response->json('data.0.structure_name'))
        ->toBe("{$this->employee->personnel_code} — قرارداد — قرارداد 1")
        ->and($response->json('data.0.structure_name_slug'))
        ->toBe("{$this->employee->personnel_code}-contract-contract-1");
});
