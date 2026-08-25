<?php

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();

    foreach ([
        ['group' => 'inquiry_status', 'value' => 'pending', 'label' => 'در انتظار استعلام', 'sort_order' => 1],
        ['group' => 'inquiry_status', 'value' => 'received', 'label' => 'پاسخ دریافت شد', 'sort_order' => 2],
        ['group' => 'inquiry_status', 'value' => 'mismatch', 'label' => 'مغایرت دارد', 'sort_order' => 3],
    ] as $option) {
        FormOption::create($option);
    }
});

test('an employee can save the document inquiries section', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'education' => [
                    '0' => ['status' => 'received', 'note' => 'مطابقت دارد'],
                ],
                'criminal_record' => ['status' => 'pending'],
                'social_insurance' => ['status' => 'mismatch', 'note' => 'سابقه ناقص'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.section_document_inquiries.inquiries.criminal_record.status', 'pending');
});

test('structural save rejects an unknown inquiry status', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'criminal_record' => ['status' => 'telepathy'],
            ],
        ])
        ->assertInvalid(['document_inquiries.inquiries.criminal_record.status']);
});

test('field key labels map every inquiry placement', function () {
    $section = app(EmployeeService::class)->getSection('document_inquiries');

    expect($section->documentFieldKeyLabel($this->employee, 'inq-edu-0'))
        ->toBe('استعلام مدرک تحصیلی 1')
        ->and($section->documentFieldKeyLabel($this->employee, 'inq-edu-4'))
        ->toBe('استعلام مدرک تحصیلی 5')
        ->and($section->documentFieldKeyLabel($this->employee, 'inq-criminal-record'))
        ->toBe('استعلام عدم سوء پیشینه')
        ->and($section->documentFieldKeyLabel($this->employee, 'inq-social-insurance'))
        ->toBe('استعلام بیمه تأمین اجتماعی')
        ->and($section->documentFieldKeyLabel($this->employee, 'dependent-0'))
        ->toBeNull();
});

test('field key slugs stay ascii for every inquiry placement', function () {
    $section = app(EmployeeService::class)->getSection('document_inquiries');

    expect($section->documentFieldKeySlug($this->employee, 'inq-edu-2'))
        ->toBe('edu-inquiry-3')
        ->and($section->documentFieldKeySlug($this->employee, 'inq-criminal-record'))
        ->toBe('criminal-record-inquiry')
        ->and($section->documentFieldKeySlug($this->employee, 'inq-social-insurance'))
        ->toBe('social-insurance-inquiry')
        ->and($section->documentFieldKeySlug($this->employee, 'unknown-key'))
        ->toBeNull();
});

test('changed inquiry nodes are stamped with user, role, and date', function () {
    $user = createUserWithPermissions(['employee.update']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'criminal_record' => ['status' => 'received'],
            ],
        ])
        ->assertOk();

    $node = $this->employee->fresh()->section_document_inquiries['inquiries']['criminal_record'];

    expect($node['updated_by'])->toBe($user->id)
        ->and($node['updated_by_name'])->toBe($user->name)
        ->and($node['updated_by_role'])->not->toBeNull()
        ->and($node['updated_at'])->toBeString();
});

test('unchanged nodes keep their previous stamp', function () {
    $first = createUserWithPermissions(['employee.update']);
    $this->actingAs($first)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'education' => ['0' => ['status' => 'received', 'note' => 'مطابقت دارد']],
            ],
        ])
        ->assertOk();

    $second = createUserWithPermissions(['employee.update']);
    $this->actingAs($second)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'education' => ['0' => ['status' => 'received', 'note' => 'مطابقت دارد']],
                'social_insurance' => ['status' => 'mismatch'],
            ],
        ])
        ->assertOk();

    $inquiries = $this->employee->fresh()->section_document_inquiries['inquiries'];

    expect($inquiries['education']['0']['updated_by'])->toBe($first->id)
        ->and($inquiries['social_insurance']['updated_by'])->toBe($second->id);
});
