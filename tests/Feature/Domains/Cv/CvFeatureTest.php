<?php

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Services\CvService;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Models\PendingVerification;
use App\Services\OtpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Helper: create a draft CV, issue an edit grant for it, and attach the token
 * as the default request header for the current test.
 */
function createCvDraft(): string
{
    $cv = Cv::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile' => '09121234567',
        'status' => 'draft',
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ]);

    test()->withHeader('X-Access-Token', grantCvToken($cv->uuid));

    return $cv->uuid;
}

/**
 * Helper: issue (and cache) an access grant token for a CV by uuid.
 */
function grantCvToken(string $uuid, GrantPurpose $purpose = GrantPurpose::Edit): string
{
    static $tokens = [];

    $tokens[$uuid][$purpose->value] ??= app(OtpService::class)->issueGrant(
        Cv::where('uuid', $uuid)->firstOrFail(),
        'mobile',
        OtpContext::AccessProtected,
        $purpose,
    );

    return $tokens[$uuid][$purpose->value];
}

/**
 * Helper: save a section directly to DB for submit testing.
 */
function saveCvSectionToDb(string $uuid, string $sectionKey, array $data): void
{
    $cv = Cv::where('uuid', $uuid)->firstOrFail();
    $section = app(CvService::class)->getSection($sectionKey);
    $cv->update([$section->storage()['jsonb'] => $data]);
}

/**
 * Helper: attach the required CV documents so submit can pass.
 */
function attachCvDocuments(string $uuid): void
{
    $cv = Cv::where('uuid', $uuid)->firstOrFail();

    $category = DocumentCategory::firstOrCreate([
        'slug' => 'resume',
    ], [
        'name' => 'resume',
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);

    $document = Document::factory()->create(['category_id' => $category->id]);
    DocumentUsage::create([
        'document_id' => $document->id,
        'entity_type' => Cv::class,
        'entity_id' => $cv->id,
    ]);
}

function cvValidPersonal(): array
{
    return [
        'first_name' => 'Ali',
        'last_name' => 'Rezaei',
        'gender' => 'مرد',
        'birth_date' => '1990-01-15',
        'marital_status' => 'مجرد',
        'id_number' => '0123456789',
        'birth_place' => 'تهران-تهران',
        'birth_certificate_number' => '12345',
        'military_status' => [
            'status' => 'پایان خدمت',
            'organization' => 'Army',
            'from' => '2011-03-21',
            'to' => '2013-03-21',
            'reason' => 'Completed',
        ],
    ];
}

function cvValidContact(): array
{
    return [
        'email' => 'test@example.com',
        'mobile' => '09121234567',
        'phone' => '02112345678',
        'emergency_phone' => '09121234567',
        'address' => [
            'postal_code' => '1234567890',
            'province' => 'تهران',
            'city' => 'تهران',
            'address' => 'Test address',
            'plaque' => '12',
            'floor' => '3',
            'unit' => '2',
        ],
    ];
}

function cvEducationRecord(): array
{
    return [
        [
            'degree' => 'کارشناسی',
            'field' => 'Computer Science',
            'institution' => 'University of Tehran',
            'from' => '2009-09-01',
            'to' => '2013-06-15',
            'graduation_date' => '2013-06-15',
            'gpa' => '17.5',
        ],
    ];
}

function cvWorkExperience(): array
{
    return [
        'work_experiences' => [
            [
                'company' => 'Acme Corp',
                'position' => 'Developer',
                'from' => '2016-03-21',
                'to' => '2021-03-20',
            ],
        ],
    ];
}

function cvSkills(): array
{
    return [
        'languages' => [
            [
                'language' => 'English',
                'reading' => 4,
                'writing' => 3,
                'speaking' => 3,
                'comprehension' => 4,
            ],
        ],
        'software_skills' => [
            'specialized' => [
                ['name' => 'PHP', 'level' => 4],
            ],
            'general' => [
                ['name' => 'Word', 'level' => 4],
            ],
        ],
    ];
}

function cvTraining(): array
{
    return [
        'training_courses' => [
            ['course_name' => 'Laravel', 'duration' => '40 hours', 'institution' => 'Academy'],
        ],
    ];
}

function cvAdditionalInfo(): array
{
    return [
        'references' => [
            [
                'full_name' => 'Mohammad Karimi',
                'relationship' => 'Former Manager',
                'workplace_phone' => '02188888888',
            ],
        ],
    ];
}

function cvFillAllSections(string $uuid): void
{
    saveCvSectionToDb($uuid, 'personal_info', cvValidPersonal());
    saveCvSectionToDb($uuid, 'contact_info', cvValidContact());
    saveCvSectionToDb($uuid, 'education', ['education_records' => cvEducationRecord()]);
    saveCvSectionToDb($uuid, 'work_experience', cvWorkExperience());
    saveCvSectionToDb($uuid, 'skills', cvSkills());
    saveCvSectionToDb($uuid, 'training', cvTraining());
    saveCvSectionToDb($uuid, 'additional_info', cvAdditionalInfo());
}

describe('CV init', function () {
    it('requires first_name, last_name and mobile but keeps email optional', function () {
        $this->postJson('/api/cv/init', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'mobile'])
            ->assertJsonMissingValidationErrors(['email']);
    });

    it('validates email format', function () {
        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'email' => 'not-an-email',
            'mobile' => '09121234567',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('accepts valid data without an email and returns a pending uuid', function () {
        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
        ])->assertCreated()
            ->assertJson([
                'requires_otp' => true,
            ])
            ->assertJsonStructure([
                'data' => ['uuid'],
                'message',
                'requires_otp',
            ]);
    });

    it('returns code_sent=false on a repeated init within the cooldown', function () {
        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
        ])->assertCreated();

        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
        ])->assertOk()
            ->assertJsonPath('code_sent', false);
    });

    it('accepts +98 and 0098 prefixed mobiles and normalizes them', function () {
        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '+989121234567',
        ])->assertCreated();

        $this->postJson('/api/cv/init', [
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '00989123456789',
        ])->assertCreated();

        expect(PendingVerification::where('type', 'cv')->where('mobile', '09121234567')->exists())->toBeTrue()
            ->and(PendingVerification::where('type', 'cv')->where('mobile', '09123456789')->exists())->toBeTrue();
    });
});

function cvPending(array $payload = []): PendingVerification
{
    return PendingVerification::create([
        'type' => 'cv',
        'mobile' => '09121234567',
        'payload' => array_merge([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'email' => 'ali@example.com',
            'mobile' => '09121234567',
        ], $payload),
    ]);
}

describe('CV verifyInitOtp', function () {
    it('requires uuid and otp', function () {
        $this->postJson('/api/cv/verify-init-otp', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['uuid', 'otp']);
    });

    it('returns 404 for an invalid uuid', function () {
        $this->postJson('/api/cv/verify-init-otp', [
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'otp' => '123456',
        ])->assertNotFound();
    });

    it('creates a CV and issues an edit grant for a new mobile', function () {
        $pending = cvPending(['email' => null]);

        Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

        $this->postJson('/api/cv/verify-init-otp', [
            'uuid' => $pending->uuid,
            'otp' => '123456',
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['uuid', 'first_name', 'last_name'],
                'access_token',
                'expires_in',
                'message',
            ])
            ->assertJsonPath('data.email', null);

        $this->assertDatabaseHas('cvs', [
            'mobile' => '09121234567',
            'first_name' => 'Ali',
            'status' => 'draft',
            'version' => 1,
        ]);

        $this->assertNotNull(Cv::where('mobile', '09121234567')->value('mobile_verified_at'));
        $this->assertDatabaseMissing('pending_verifications', ['uuid' => $pending->uuid]);
    });

    it('keeps wizard-edited names but reapplies contact info when mobile already exists', function () {
        Cv::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'mobile' => '09121234567',
            'status' => 'draft',
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        $pending = cvPending();

        Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

        $this->postJson('/api/cv/verify-init-otp', [
            'uuid' => $pending->uuid,
            'otp' => '123456',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Existing')
            ->assertJsonPath('data.last_name', 'User')
            ->assertJsonPath('data.email', 'ali@example.com');

        $this->assertDatabaseHas('cvs', [
            'mobile' => '09121234567',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'ali@example.com',
            'email_verified_at' => null,
        ]);
    });

    it('returns 422 for an expired otp', function () {
        $pending = cvPending();

        Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->subMinute());

        $this->postJson('/api/cv/verify-init-otp', [
            'uuid' => $pending->uuid,
            'otp' => '123456',
        ])->assertUnprocessable();
    });
});

describe('CV OTP endpoints (existing record)', function () {
    it('validates the staged mobile on send', function () {
        $uuid = createCvDraft();

        $this->postJson("/api/cv/{$uuid}/send-mobile-otp", [
            'mobile' => 'not-a-phone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile']);
    });

    it('validates the staged email on send', function () {
        $uuid = createCvDraft();

        $this->postJson("/api/cv/{$uuid}/send-email-otp", [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('commits the staged mobile only after verification', function () {
        $uuid = createCvDraft();

        $this->postJson("/api/cv/{$uuid}/send-mobile-otp", [
            'mobile' => '09999999999',
        ])->assertOk()
            ->assertJsonPath('code_sent', true);

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'mobile' => '09121234567',
        ]);

        Cache::put("otp:verify_mobile:cv:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

        $this->postJson("/api/cv/{$uuid}/verify-mobile-otp", [
            'otp' => '123456',
        ])->assertOk();

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'mobile' => '09999999999',
        ]);

        $this->assertNotNull(Cv::where('uuid', $uuid)->value('mobile_verified_at'));
    });

    it('commits the staged email only after verification', function () {
        $uuid = createCvDraft();

        $this->postJson("/api/cv/{$uuid}/send-email-otp", [
            'email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('code_sent', true);

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'email' => 'test@example.com',
        ]);

        Cache::put("otp:verify_email:cv:{$uuid}:email", ['hash' => Hash::make('123456')], now()->addMinutes(5));

        $this->postJson("/api/cv/{$uuid}/verify-email-otp", [
            'otp' => '123456',
        ])->assertOk();

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'email' => 'new@example.com',
        ]);

        $this->assertNotNull(Cv::where('uuid', $uuid)->value('email_verified_at'));
    });
});

describe('CV section save (structural validation)', function () {
    beforeEach(function () {
        seedFormOptions(['gender', 'marital_status', 'military_status']);
        seedLocationOptions();
    });

    it('validates personal_info fields', function () {
        $uuid = createCvDraft();

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [
            'gender' => 'invalid',
            'marital_status' => 'not-a-status',
            'id_number' => str_repeat('1', 11),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'personal_info.gender',
                'personal_info.marital_status',
                'personal_info.id_number',
            ]);
    });

    it('validates military_status sub-fields', function () {
        $uuid = createCvDraft();

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [
            'military_status' => ['status' => 'invalid_status'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['personal_info.military_status.status']);
    });

    it('persists first_name and last_name on personal_info save', function () {
        $uuid = createCvDraft();

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [
            'first_name' => 'NewName',
            'last_name' => 'NewFamily',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'NewName')
            ->assertJsonPath('data.last_name', 'NewFamily');

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'first_name' => 'NewName',
            'last_name' => 'NewFamily',
        ]);
    });

    it('increments the version on every section edit', function () {
        $uuid = createCvDraft();

        expect(Cv::where('uuid', $uuid)->value('version'))->toBe(1);

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [
            'first_name' => 'NewName',
        ])->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->putJson("/api/cv/{$uuid}/sections/contact_info", [
            'phone' => '02112345678',
        ])->assertOk()
            ->assertJsonPath('data.version', 3);
    });

    it('does not persist email or mobile on contact save (staged until OTP verification)', function () {
        $uuid = createCvDraft();
        $cv = Cv::where('uuid', $uuid)->firstOrFail();

        $this->putJson("/api/cv/{$uuid}/sections/contact_info", [
            'email' => 'new@example.com',
            'mobile' => '09999999999',
            'phone' => '02112345678',
            'emergency_phone' => '09121234567',
        ])->assertOk();

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'email' => 'test@example.com',
            'mobile' => '09121234567',
        ]);

        expect($cv->fresh()->mobile_verified_at)->not->toBeNull()
            ->and($cv->fresh()->email_verified_at)->not->toBeNull();
    });

    it('accepts an empty body (all fields are optional in structural mode)', function () {
        $uuid = createCvDraft();

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [])
            ->assertOk();
    });
});

describe('CV submit', function () {
    beforeEach(function () {
        seedFormOptions(['gender', 'marital_status', 'military_status']);
        seedLocationOptions();
    });

    it('blocks submit when the email is present but unverified', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['email_verified_at' => null]);
        cvFillAllSections($uuid);
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('cv.not_verified'));
    });

    it('blocks submit when the mobile is unverified', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['mobile_verified_at' => null]);
        cvFillAllSections($uuid);
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('cv.not_verified'));
    });

    it('requires personal_info completion sub-fields', function () {
        $uuid = createCvDraft();
        saveCvSectionToDb($uuid, 'personal_info', []);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'personal_info.first_name',
                'personal_info.last_name',
                'personal_info.gender',
                'personal_info.birth_date',
                'personal_info.marital_status',
                'personal_info.id_number',
                'personal_info.birth_place',
                'personal_info.birth_certificate_number',
            ]);
    });

    it('requires military_status when gender is male', function () {
        $uuid = createCvDraft();
        $personal = cvValidPersonal();
        unset($personal['military_status']);
        saveCvSectionToDb($uuid, 'personal_info', $personal);
        saveCvSectionToDb($uuid, 'contact_info', cvValidContact());
        saveCvSectionToDb($uuid, 'education', ['education_records' => cvEducationRecord()]);
        saveCvSectionToDb($uuid, 'work_experience', cvWorkExperience());
        saveCvSectionToDb($uuid, 'skills', cvSkills());
        saveCvSectionToDb($uuid, 'training', cvTraining());
        saveCvSectionToDb($uuid, 'additional_info', cvAdditionalInfo());
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personal_info.military_status']);
    });

    it('does not require military_status when gender is female', function () {
        $uuid = createCvDraft();
        $personal = array_merge(cvValidPersonal(), ['gender' => 'زن']);
        unset($personal['military_status']);
        saveCvSectionToDb($uuid, 'personal_info', $personal);
        saveCvSectionToDb($uuid, 'contact_info', cvValidContact());
        saveCvSectionToDb($uuid, 'education', ['education_records' => cvEducationRecord()]);
        saveCvSectionToDb($uuid, 'work_experience', cvWorkExperience());
        saveCvSectionToDb($uuid, 'skills', cvSkills());
        saveCvSectionToDb($uuid, 'training', cvTraining());
        saveCvSectionToDb($uuid, 'additional_info', cvAdditionalInfo());
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")->assertOk();
    });

    it('skips student fields when the applicant is not a student', function () {
        $uuid = createCvDraft();
        saveCvSectionToDb($uuid, 'personal_info', cvValidPersonal());
        saveCvSectionToDb($uuid, 'contact_info', cvValidContact());
        saveCvSectionToDb($uuid, 'education', [
            'education_records' => cvEducationRecord(),
            'is_student' => false,
            'student_degree' => null,
            'student_field' => null,
            'student_university' => null,
            'student_country' => null,
            'student_city' => null,
            'student_gpa' => null,
            'study_start' => null,
            'expected_graduation' => null,
        ]);
        saveCvSectionToDb($uuid, 'work_experience', cvWorkExperience());
        saveCvSectionToDb($uuid, 'skills', cvSkills());
        saveCvSectionToDb($uuid, 'training', cvTraining());
        saveCvSectionToDb($uuid, 'additional_info', cvAdditionalInfo());
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")->assertOk();
    });

    it('requires the resume document on submit', function () {
        $uuid = createCvDraft();
        cvFillAllSections($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.resume']);
    });

    it('accepts a fully verified and complete CV', function () {
        $uuid = createCvDraft();
        cvFillAllSections($uuid);
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.version', 1);

        $cv = Cv::where('uuid', $uuid)->firstOrFail();
        $last = $cv->lastLifecycleEvent();

        expect($last['event'])->toBe('submitted')
            ->and($last['version'])->toBe(1)
            ->and($last['snapshot']['first_name'])->toBe('Test')
            ->and($last['snapshot']['sections']['personal_info']['id_number'])->toBe('0123456789');
    });

    it('submits a CV whose contact_info was never saved because email/mobile live on real columns', function () {
        $uuid = createCvDraft();
        saveCvSectionToDb($uuid, 'personal_info', cvValidPersonal());
        saveCvSectionToDb($uuid, 'education', ['education_records' => cvEducationRecord()]);
        saveCvSectionToDb($uuid, 'work_experience', cvWorkExperience());
        saveCvSectionToDb($uuid, 'skills', cvSkills());
        saveCvSectionToDb($uuid, 'training', cvTraining());
        saveCvSectionToDb($uuid, 'additional_info', cvAdditionalInfo());
        attachCvDocuments($uuid);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    });

    it('blocks editing after submit', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", ['gender' => 'مرد'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.only_draft_editable'));
    });
});

describe('CV admin review/reject', function () {
    beforeEach(function () {
        seedFormOptions(['gender', 'marital_status', 'military_status']);
        seedLocationOptions();
    });

    it('blocks unauthenticated access to approve and reject', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->postJson("/api/cv/{$uuid}/approve")->assertUnauthorized();
        $this->postJson("/api/cv/{$uuid}/reject")->assertUnauthorized();
    });

    it('blocks users without the approve permission', function () {
        $user = createUserWithPermissions(['cv.view']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)->postJson("/api/cv/{$uuid}/approve")->assertForbidden();
    });

    it('approves a submitted CV', function () {
        $user = createUserWithPermissions(['cv.approve']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/cv/{$uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $cv = Cv::where('uuid', $uuid)->firstOrFail();

        expect($cv->reviewed_by)->toBe($user->id)
            ->and($cv->lastLifecycleEvent()['event'])->toBe('approved')
            ->and($cv->lastLifecycleEvent()['by'])->toBe($user->id);
    });

    it('requires a reason when rejecting', function () {
        $user = createUserWithPermissions(['cv.reject']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/cv/{$uuid}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('rejects a submitted CV keeping the rejected status with the reason recorded', function () {
        $user = createUserWithPermissions(['cv.reject']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/cv/{$uuid}/reject", ['reason' => 'Missing documents'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $cv = Cv::where('uuid', $uuid)->firstOrFail();
        $last = $cv->lastLifecycleEvent();

        expect($last['event'])->toBe('rejected')
            ->and($last['reason'])->toBe('Missing documents')
            ->and($last['by'])->toBe($user->id)
            ->and($cv->isRejected())->toBeTrue();
    });

    it('cannot approve a CV that is not submitted', function () {
        $user = createUserWithPermissions(['cv.approve']);
        $uuid = createCvDraft();

        $this->actingAs($user)
            ->postJson("/api/cv/{$uuid}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.only_submitted_approvable'));
    });

    it('editing a rejected CV flips it back to draft', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'rejected']);

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", [
            'first_name' => 'NewName',
        ])->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('cvs', [
            'uuid' => $uuid,
            'status' => 'draft',
        ]);
    });

    it('resubmits a rejected CV straight back to submitted', function () {
        $uuid = createCvDraft();
        cvFillAllSections($uuid);
        attachCvDocuments($uuid);
        Cv::where('uuid', $uuid)->update(['status' => 'rejected']);

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    });

    it('blocks editing and submitting a reviewed/approved CV', function () {
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'approved']);

        $this->putJson("/api/cv/{$uuid}/sections/personal_info", ['gender' => 'مرد'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.only_draft_editable'));

        $this->postJson("/api/cv/{$uuid}/submit")
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.only_draft_submittable'));
    });
});

describe('CV bank', function () {
    it('blocks unauthenticated access to the bank', function () {
        $this->getJson('/api/cv/bank')->assertUnauthorized();
    });

    it('blocks users without cv.view', function () {
        $user = createUserWithPermissions();

        $this->actingAs($user)->getJson('/api/cv/bank')->assertForbidden();
    });

    it('lists CVs of every status by default, filterable by status', function () {
        $user = createUserWithPermissions(['cv.view']);

        $submitted = Cv::create([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
            'status' => 'submitted',
        ]);
        Cv::create([
            'first_name' => 'Draft',
            'last_name' => 'User',
            'mobile' => '09122222222',
            'status' => 'draft',
        ]);
        Cv::create([
            'first_name' => 'Rejected',
            'last_name' => 'User',
            'mobile' => '09123333333',
            'status' => 'rejected',
        ]);

        $this->actingAs($user)
            ->getJson('/api/cv/bank')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($user)
            ->getJson('/api/cv/bank?status=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Rejected');
    });

    it('shows a CV by uuid', function () {
        $user = createUserWithPermissions(['cv.view']);
        $cv = Cv::create([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $cv->uuid);
    });

    it('shows a CV by numeric id', function () {
        $user = createUserWithPermissions(['cv.view']);
        $cv = Cv::create([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cv->id);
    });

    it('embeds the uploaded documents in the bank detail and the resume download url in the list', function () {
        $user = createUserWithPermissions(['cv.view']);
        $cv = Cv::create([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
            'status' => 'submitted',
        ]);

        $resumeCategory = DocumentCategory::firstOrCreate([
            'slug' => 'resume',
        ], [
            'name' => 'resume',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);
        $coverCategory = DocumentCategory::firstOrCreate([
            'slug' => 'cover-letter',
        ], [
            'name' => 'cover-letter',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $resume = Document::factory()->create(['original_name' => 'resume.pdf', 'category_id' => $resumeCategory->id]);
        $cover = Document::factory()->create(['original_name' => 'cover.pdf', 'category_id' => $coverCategory->id]);
        DocumentUsage::create([
            'document_id' => $resume->id,
            'entity_type' => Cv::class,
            'entity_id' => $cv->id,
        ]);
        DocumentUsage::create([
            'document_id' => $cover->id,
            'entity_type' => Cv::class,
            'entity_id' => $cv->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->uuid}")
            ->assertOk()
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonPath('data.resume_document.category_slug', 'resume')
            ->assertJsonPath('data.resume_document.structure_name', 'resume')
            ->assertJsonPath('data.resume_document.uuid', $resume->uuid)
            ->assertJsonPath('data.resume_document.url', URL::signedRoute('cv.documents.serve', ['uuid' => $resume->uuid]))
            ->assertJsonPath('data.resume_document.download_url', URL::signedRoute('cv.documents.serve', ['uuid' => $resume->uuid, 'download' => 1]));

        $this->actingAs($user)
            ->getJson('/api/cv/bank')
            ->assertOk()
            ->assertJsonPath('data.0.resume_document.structure_name', 'resume');
    });

    it('returns empty documents and a null resume_document when no documents are uploaded', function () {
        $user = createUserWithPermissions(['cv.view']);
        $cv = Cv::create([
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'mobile' => '09121234567',
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->getJson("/api/cv/bank/{$cv->uuid}")
            ->assertOk()
            ->assertJsonPath('data.documents', [])
            ->assertJsonPath('data.resume_document', null)
            ->assertJsonPath('data.questionnaire', null);
    });

    it('exposes the linked questionnaire on the bank detail', function () {
        $admin = createUserWithPermissions(['cv.create-questionnaire']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($admin)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $questionnaire = Questionnaire::where('cv_id', Cv::where('uuid', $uuid)->value('id'))->firstOrFail();

        $viewer = createUserWithPermissions(['cv.view']);
        $this->actingAs($viewer)
            ->getJson("/api/cv/bank/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.questionnaire.uuid', $questionnaire->uuid)
            ->assertJsonPath('data.questionnaire.status', 'draft');
    });

    it('creates a draft questionnaire from a submitted CV and auto-approves the CV', function () {
        $user = createUserWithPermissions(['cv.create-questionnaire']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update([
            'status' => 'submitted',
            'section_personal' => cvValidPersonal(),
            'section_education' => ['education_records' => cvEducationRecord()],
        ]);

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.first_name', 'Test');

        $cv = Cv::where('uuid', $uuid)->firstOrFail();
        $questionnaire = Questionnaire::where('cv_id', $cv->id)->firstOrFail();

        expect($questionnaire->status)->toBe('draft')
            ->and($questionnaire->section_personal['id_number'])->toBe('0123456789')
            ->and($questionnaire->section_education)->not->toBeNull()
            ->and($questionnaire->mobile)->toBe('09121234567')
            ->and($cv->isApproved())->toBeTrue()
            ->and($cv->reviewed_by)->toBe($user->id)
            ->and($cv->lastLifecycleEvent()['event'])->toBe('approved');
    });

    it('keeps an already approved CV approved when creating a questionnaire', function () {
        $user = createUserWithPermissions(['cv.create-questionnaire']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'approved']);

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertCreated();

        $cv = Cv::where('uuid', $uuid)->firstOrFail();

        expect($cv->isApproved())->toBeTrue();
    });

    it('blocks creating a second questionnaire for the same CV', function () {
        $user = createUserWithPermissions(['cv.create-questionnaire']);
        $uuid = createCvDraft();
        Cv::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.already_linked'));
    });

    it('blocks creating a questionnaire from a non-submitted CV', function () {
        $user = createUserWithPermissions(['cv.create-questionnaire']);
        $uuid = createCvDraft();

        $this->actingAs($user)
            ->postJson("/api/cv/bank/{$uuid}/questionnaire")
            ->assertStatus(422)
            ->assertJsonPath('message', __('cv.only_submitted_creatable'));
    });
});

describe('CV grants', function () {
    it('request-access sends an OTP for the cv entity', function () {
        $uuid = createCvDraft();

        $this->postJson("/api/cv/{$uuid}/request-access")
            ->assertOk()
            ->assertJsonPath('code_sent', true)
            ->assertJsonStructure(['message', 'expires_in']);
    });

    it('verify-access-otp issues a reusable grant token', function () {
        $uuid = createCvDraft();

        Cache::put("otp:access_protected:cv:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

        $this->postJson("/api/cv/{$uuid}/verify-access-otp", [
            'otp' => '123456',
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'expires_in', 'message']);
    });

    it('exists returns 204 for an existing CV', function () {
        $uuid = createCvDraft();

        $this->getJson("/api/cv/{$uuid}/exists")->assertNoContent();
    });

    it('view grant cannot edit sections', function () {
        $uuid = createCvDraft();
        $viewToken = grantCvToken($uuid, GrantPurpose::View);

        $this->withHeader('X-Access-Token', $viewToken)
            ->putJson("/api/cv/{$uuid}/sections/personal_info", ['gender' => 'مرد'])
            ->assertUnauthorized();

        $this->withHeader('X-Access-Token', $viewToken)
            ->getJson("/api/cv/{$uuid}")
            ->assertOk();
    });
});

describe('CV section definitions', function () {
    it('registers the expected sections and labels', function () {
        $service = app(CvService::class);

        expect($service->getSectionKeys())->toBe([
            'personal_info',
            'contact_info',
            'additional_info',
            'education',
            'work_experience',
            'skills',
            'training',
        ]);

        foreach ($service->getSectionKeys() as $key) {
            expect($service->getSection($key)->label())->toBe(__("cv.sections.{$key}"));
        }
    });

    it('provides document requirements from the section definitions', function () {
        $requirements = app(CvService::class)->getDocumentRequirements();

        expect($requirements)->toHaveKey('resume')
            ->and($requirements['resume']['required'])->toBeTrue()
            ->and($requirements['resume']['max_files'])->toBe(1)
            ->and($requirements['resume']['section_key'])->toBe('documents')
            ->and($requirements['cover-letter']['required'])->toBeFalse()
            ->and($requirements['other-documents']['max_files'])->toBe(3)
            ->and($requirements['personnel-photo']['field_keys'])->toBe(['photo'])
            ->and($requirements['personnel-photo']['section_key'])->toBe('documents')
            ->and($requirements)->not->toHaveKey('national-card');
    });

    it('does not reuse the questionnaire job_request section', function () {
        $service = app(CvService::class);

        expect($service->getSectionKeys())->not->toContain('job_request');
    });
});

describe('CV documents', function () {
    it('stores uploads under the cv route type', function () {
        Storage::fake('local');
        $uuid = createCvDraft();
        $category = DocumentCategory::create([
            'name' => 'رزومه',
            'slug' => 'resume',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $this->postJson("/api/cv/{$uuid}/documents", [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->createWithContent('resume.pdf', 'resume-content'),
        ])->assertCreated()
            ->assertJsonPath('data.category_slug', 'resume')
            ->assertJsonPath('data.category_label', 'رزومه')
            ->assertJsonPath('data.structure_name', 'رزومه')
            ->assertJsonMissingPath('data.original_name');

        $document = Document::first();

        expect($document->path)->toMatch('#^cv/.*/documents/resume/document-[a-f0-9]{8}\.pdf$#');
        Storage::disk('local')->assertExists($document->path);
    });

    it('serves the document inline by default and as an attachment with the download flag', function () {
        Storage::fake('local');
        $uuid = createCvDraft();
        $category = DocumentCategory::create([
            'name' => 'رزومه',
            'slug' => 'resume',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $this->postJson("/api/cv/{$uuid}/documents", [
            'document_category_id' => $category->id,
            'file' => UploadedFile::fake()->createWithContent('resume.pdf', 'resume-content'),
        ])->assertCreated();

        $document = Document::first();

        $inlineUrl = URL::signedRoute('cv.documents.serve', ['uuid' => $document->uuid]);
        $downloadUrl = URL::signedRoute('cv.documents.serve', ['uuid' => $document->uuid, 'download' => 1]);

        $inline = $this->get($inlineUrl)->assertOk();
        expect($inline->headers->get('Content-Disposition'))->toContain('inline');

        $download = $this->get($downloadUrl)->assertOk();
        expect($download->headers->get('Content-Disposition'))->toContain('attachment');
    });

    it('rejects an unsigned serve request', function () {
        Storage::fake('local');
        $document = Document::factory()->create();

        $this->get("/api/cv/documents/{$document->uuid}/serve")
            ->assertForbidden();
    });
});
