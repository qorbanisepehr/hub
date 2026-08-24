<?php

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();

    foreach ([
        ['group' => 'relationship_type', 'value' => 'spouse', 'label' => 'همسر', 'sort_order' => 1],
        ['group' => 'relationship_type', 'value' => 'child', 'label' => 'فرزند', 'sort_order' => 2],
        ['group' => 'relationship_type', 'value' => 'father', 'label' => 'پدر', 'sort_order' => 3],
        ['group' => 'relationship_type', 'value' => 'mother', 'label' => 'مادر', 'sort_order' => 4],
        ['group' => 'relationship_type', 'value' => 'other', 'label' => 'سایر', 'sort_order' => 5],
        ['group' => 'gender', 'value' => 'male', 'label' => 'مرد', 'sort_order' => 1],
        ['group' => 'gender', 'value' => 'female', 'label' => 'زن', 'sort_order' => 2],
    ] as $option) {
        FormOption::create($option);
    }
});

function validDependent(): array
{
    return [
        'relationship_type' => 'spouse',
        'first_name' => 'مریم',
        'last_name' => 'احمدی',
        'id_number' => '0123456789',
        'gender' => 'female',
        'birth_date' => '1990-04-12',
    ];
}

test('an employee can save the dependents section with a complete row', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [validDependent()],
        ])
        ->assertOk()
        ->assertJsonPath('data.section_dependents.dependents.0.relationship_type', 'spouse');
});

test('a partially filled dependent row is allowed on structural save', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [['first_name' => 'مریم']],
        ])
        ->assertOk();
});

test('structural save rejects an invalid national id', function () {
    $user = createUserWithPermissions(['employee.update']);

    $row = validDependent();
    $row['id_number'] = '1234567890'; // invalid checksum

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [$row],
        ])
        ->assertInvalid(['dependents.dependents.0.id_number']);
});

test('structural save rejects an unknown relationship type', function () {
    $user = createUserWithPermissions(['employee.update']);

    $row = validDependent();
    $row['relationship_type'] = 'cousin-not-seeded';

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [$row],
        ])
        ->assertInvalid(['dependents.dependents.0.relationship_type']);
});

test('submit raises no dependents completion errors when the section is empty', function () {
    $user = createUserWithPermissions(['employee.update']);

    // A fresh factory employee fails completion on other mandatory sections;
    // what matters here is that the optional dependents section contributes
    // no errors of its own when no rows exist.
    $response = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit");

    $errors = array_keys($response->json('errors') ?? []);

    expect($errors)->not->toBeEmpty()
        ->and(array_filter($errors, fn (string $key) => str_starts_with($key, 'dependents.')))->toBe([]);
});

test('submit blocks a partially filled dependent row', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [['first_name' => 'مریم']],
        ])
        ->assertOk();

    $response = $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/submit");

    // The completion error surfaces for the dependents section; other
    // sections may also be incomplete for a fresh factory employee, so only
    // assert the dependent keys are present.
    $errors = $response->json('errors') ?? [];
    expect(array_keys($errors))->toContain('dependents.dependents.0.last_name');
});

test('submit rejects a future dependent birth date', function () {
    $user = createUserWithPermissions(['employee.update']);

    $row = validDependent();
    $row['birth_date'] = now()->addYear()->toDateString();

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/dependents", [
            'dependents' => [$row],
        ])
        ->assertInvalid(['dependents.dependents.0.birth_date']);
});

test('dependents section is included in the section keys', function () {
    $service = app(EmployeeService::class);

    expect($service->getSectionKeys())->toContain('dependents');
});
