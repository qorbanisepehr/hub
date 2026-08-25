<?php

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Sections\DocumentInquiriesSection;
use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->employee = Employee::factory()->create();

    foreach ([
        ['group' => 'inquiry_status', 'value' => 'pending', 'label' => 'در انتظار استعلام', 'sort_order' => 1],
        ['group' => 'inquiry_status', 'value' => 'received', 'label' => 'پاسخ دریافت شد', 'sort_order' => 2],
        ['group' => 'inquiry_status', 'value' => 'mismatch', 'label' => 'مغایرت دارد', 'sort_order' => 3],
    ] as $option) {
        FormOption::create($option);
    }

    $this->inquiryResult = DocumentCategory::create([
        'name' => 'نتیجه استعلام',
        'slug' => 'inquiry-result',
        'type' => 'personnel',
    ]);
});

function uploadInquiryFile($employee, $category, string $fieldKey)
{
    $user = createUserWithPermissions([
        'employee.documents.upload',
        'employee.documents.view',
    ]);

    return test()->actingAs($user)->postJson(
        "/api/employees/{$employee->id}/documents",
        [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->image('result.jpg'),
            'section_key' => 'document_inquiries',
            'field_key' => $fieldKey,
        ],
    );
}

test('requirements endpoint exposes the document inquiries dynamic groups', function () {
    $user = createUserWithPermissions(['employee.list']);

    $response = $this->actingAs($user)
        ->getJson('/api/employees/document-requirements')
        ->assertOk();

    $groups = collect($response->json('dynamic_requirements'))
        ->where('section_key', 'document_inquiries')
        ->keyBy('pattern');

    expect($groups)->toHaveCount(2)
        ->and($groups->has(DocumentInquiriesSection::EDU_FIELD_KEY_PATTERN))->toBeTrue()
        ->and($groups->has(DocumentInquiriesSection::FIXED_FIELD_KEY_PATTERN))->toBeTrue()
        ->and($groups[DocumentInquiriesSection::FIXED_FIELD_KEY_PATTERN]['requirements']['inquiry-result']['max_files'])->toBe(5);
});

test('an inquiry placement accepts result uploads up to the cap', function () {
    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-criminal-record')->assertCreated();
    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-criminal-record')->assertCreated();
});

test('an inquiry placement rejects files beyond the cap', function () {
    for ($i = 0; $i < 5; $i++) {
        uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-edu-0')->assertCreated();
    }

    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-edu-0')->assertStatus(422);
});

test('caps are independent per inquiry node', function () {
    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-edu-0')->assertCreated();
    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-edu-1')->assertCreated();
    uploadInquiryFile($this->employee, $this->inquiryResult, 'inq-social-insurance')->assertCreated();
});

test('the section saves partial inquiry data as a draft', function () {
    $user = createUserWithPermissions(['employee.update', 'employee.view']);

    $this->actingAs($user)
        ->postJson("/api/employees/{$this->employee->id}/sections/document_inquiries", [
            'inquiries' => [
                'education' => ['0' => ['status' => 'pending', 'note' => 'در صف استعلام']],
                'criminal_record' => ['status' => 'received'],
            ],
        ])
        ->assertOk();

    expect($this->employee->fresh()->section_document_inquiries['inquiries']['criminal_record']['status'])
        ->toBe('received');
});
