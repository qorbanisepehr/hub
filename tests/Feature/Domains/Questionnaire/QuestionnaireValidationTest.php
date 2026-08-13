<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Enums\OtpContext;
use App\Models\PendingVerification;
use App\Services\OtpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Helper: create a draft questionnaire, issue an edit grant for it, and attach
 * the token as the default request header for the current test.
 */
function createDraft(): string
{
    $questionnaire = Questionnaire::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile' => '09121234567',
        'status' => 'draft',
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ]);

    test()->withHeader('X-Access-Token', grantToken($questionnaire->uuid));

    return $questionnaire->uuid;
}

/**
 * Helper: save a section directly to DB for submit testing.
 */
function saveSectionToDb(string $uuid, string $sectionKey, array $data): void
{
    $questionnaire = Questionnaire::where('uuid', $uuid)->first();
    $service = app(QuestionnaireService::class);
    $section = $service->getSection($sectionKey);
    $questionnaire->update([$section->storage()['jsonb'] => $data]);
}

/**
 * Helper: attach all required documents so submit can pass.
 */
function attachRequiredDocuments(string $uuid): void
{
    $questionnaire = Questionnaire::where('uuid', $uuid)->first();

    foreach (['national-card', 'birth-certificate', 'resume', 'personnel-photo'] as $slug) {
        $category = DocumentCategory::firstOrCreate([
            'slug' => $slug,
        ], [
            'name' => $slug,
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $document = Document::factory()->create(['category_id' => $category->id]);
        DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Questionnaire::class,
            'entity_id' => $questionnaire->id,
        ]);
    }
}

describe('Questionnaire validation', function () {
    beforeEach(function () {
        seedFormOptions([
            'gender', 'blood_group', 'marital_status', 'military_status',
            'spouse_employment_status', 'employment_type', 'preferred_workplace',
            'religion', 'religion_sect', 'degree', 'university',
        ]);
        seedLocationOptions();
    });

    // ────────────────────────────────────────
    //  InitQuestionnaireRequest
    // ────────────────────────────────────────
    describe('init', function () {
        it('requires first_name, last_name, email, mobile', function () {
            $this->postJson('/api/questionnaire/init', [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'mobile']);
        });

        it('validates email format', function () {
            $this->postJson('/api/questionnaire/init', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'email' => 'not-an-email',
                'mobile' => '09121234567',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        });

        it('validates max lengths', function () {
            $this->postJson('/api/questionnaire/init', [
                'first_name' => str_repeat('A', 101),
                'last_name' => str_repeat('B', 101),
                'email' => str_repeat('a', 247).'@test.com',
                'mobile' => str_repeat('9', 16),
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'mobile']);
        });

        it('accepts valid data and returns pending verification uuid', function () {
            $this->postJson('/api/questionnaire/init', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'email' => 'ali@example.com',
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

        it('accepts +98 and 0098 prefixed mobiles and normalizes them', function () {
            $this->postJson('/api/questionnaire/init', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'email' => 'ali@example.com',
                'mobile' => '+989121234567',
            ])->assertCreated();

            $this->postJson('/api/questionnaire/init', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'email' => 'ali@example.com',
                'mobile' => '00989123456789',
            ])->assertCreated();

            expect(PendingVerification::where('type', 'questionnaire')->where('mobile', '09121234567')->exists())->toBeTrue()
                ->and(PendingVerification::where('type', 'questionnaire')->where('mobile', '09123456789')->exists())->toBeTrue();
        });
    });

    // ────────────────────────────────────────
    //  VerifyInitOtpRequest
    // ────────────────────────────────────────
    describe('verifyInitOtp', function () {
        it('requires uuid and otp', function () {
            $this->postJson('/api/questionnaire/verify-init-otp', [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['uuid', 'otp']);
        });

        it('validates otp size is exactly 6', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '12345',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['otp']);

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '1234567',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['otp']);
        });

        it('returns 404 for invalid uuid', function () {
            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => '00000000-0000-0000-0000-000000000000',
                'otp' => '123456',
            ])->assertNotFound();
        });

        it('accepts valid otp and creates questionnaire for new mobile', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '123456',
            ])->assertOk()
                ->assertJsonStructure([
                    'data' => ['uuid', 'first_name', 'last_name'],
                    'message',
                ]);

            $this->assertDatabaseHas('questionnaires', [
                'mobile' => '09121234567',
                'first_name' => 'Ali',
            ]);

            $this->assertDatabaseMissing('pending_verifications', [
                'uuid' => $pending->uuid,
            ]);
        });

        it('keeps wizard-edited names but reapplies contact info when mobile already exists', function () {
            Questionnaire::create([
                'first_name' => 'Existing',
                'last_name' => 'User',
                'email' => 'existing@example.com',
                'mobile' => '09121234567',
                'status' => 'draft',
                'mobile_verified_at' => now(),
                'email_verified_at' => now(),
            ]);

            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '123456',
            ])->assertOk()
                ->assertJsonPath('data.first_name', 'Existing')
                ->assertJsonPath('data.last_name', 'User')
                ->assertJsonPath('data.email', 'ali@example.com');

            $this->assertDatabaseHas('questionnaires', [
                'mobile' => '09121234567',
                'first_name' => 'Existing',
                'last_name' => 'User',
                'email' => 'ali@example.com',
                'email_verified_at' => null,
            ]);
        });

        it('returns 422 for expired otp', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->subMinute());

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '123456',
            ])->assertUnprocessable();
        });

        it('returns 422 for invalid otp', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            Cache::put("otp:register:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '000000',
            ])->assertUnprocessable();
        });
    });

    // ────────────────────────────────────────
    //  VerifyQuestionnaireRequest (existing questionnaire OTP)
    // ────────────────────────────────────────
    describe('verify', function () {
        it('requires otp', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['otp']);
        });

        it('validates otp size is exactly 6', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '12345',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['otp']);

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '1234567',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['otp']);
        });

        it('accepts valid 6-digit otp', function () {
            $uuid = createDraft();

            Cache::put("otp:verify_mobile:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '123456',
            ])->assertOk();
        });

        it('validates the staged mobile on send', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp", [
                'mobile' => 'not-a-phone',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['mobile']);
        });

        it('accepts a prefixed staged mobile and stores it canonically', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp", [
                'mobile' => '+989121234567',
            ])->assertOk()
                ->assertJsonPath('code_sent', true);

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'mobile' => '09121234567',
            ]);
        });

        it('validates the staged email on send', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-email-otp", [
                'email' => 'not-an-email',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        });

        it('commits the staged mobile to the db only after verification', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp", [
                'mobile' => '09999999999',
            ])->assertOk()
                ->assertJsonPath('code_sent', true);

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'mobile' => '09121234567',
            ]);

            Cache::put("otp:verify_mobile:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '123456',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'mobile' => '09999999999',
            ]);

            $this->assertNotNull(Questionnaire::where('uuid', $uuid)->value('mobile_verified_at'));
        });

        it('commits the staged email to the db only after verification', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-email-otp", [
                'email' => 'new@example.com',
            ])->assertOk()
                ->assertJsonPath('code_sent', true);

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'email' => 'test@example.com',
            ]);

            Cache::put("otp:verify_email:questionnaire:{$uuid}:email", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-email-otp", [
                'otp' => '123456',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'email' => 'new@example.com',
            ]);

            $this->assertNotNull(Questionnaire::where('uuid', $uuid)->value('email_verified_at'));
        });

        it('keeps the stored mobile when verifying without a staged change', function () {
            $uuid = createDraft();

            Cache::put("otp:verify_mobile:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '123456',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'mobile' => '09121234567',
            ]);

            $this->assertNotNull(Questionnaire::where('uuid', $uuid)->value('mobile_verified_at'));
        });
    });

    // ────────────────────────────────────────
    //  Protected access (grant token flow)
    // ────────────────────────────────────────
    describe('protected access', function () {
        it('request-access sends an OTP for the mobile channel', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/request-access")
                ->assertOk()
                ->assertJsonPath('code_sent', true)
                ->assertJsonStructure(['message', 'expires_in']);
        });

        it('verify-access-otp issues a reusable grant token on valid code', function () {
            $uuid = createDraft();

            Cache::put("otp:access_protected:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-access-otp", [
                'otp' => '123456',
            ])->assertOk()
                ->assertJsonStructure(['access_token', 'expires_in', 'message']);
        });

        it('verify-access-otp rejects an invalid code', function () {
            $uuid = createDraft();

            Cache::put("otp:access_protected:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-access-otp", [
                'otp' => '000000',
            ])->assertUnprocessable();
        });

        it('request-access returns a clean 404 for an unknown uuid', function () {
            $this->postJson('/api/questionnaire/00000000-0000-0000-0000-000000000000/request-access')
                ->assertNotFound()
                ->assertJson(['message' => __('messages.not_found')]);
        });

        it('verify-access-otp returns a clean 404 for an unknown uuid', function () {
            $this->postJson('/api/questionnaire/00000000-0000-0000-0000-000000000000/verify-access-otp', [
                'otp' => '123456',
            ])->assertNotFound()
                ->assertJson(['message' => __('messages.not_found')]);
        });

        it('exists returns 204 for an existing questionnaire', function () {
            $uuid = createDraft();

            $this->getJson("/api/questionnaire/{$uuid}/exists")
                ->assertNoContent();
        });

        it('exists returns a clean 404 for an unknown uuid', function () {
            $this->getJson('/api/questionnaire/00000000-0000-0000-0000-000000000000/exists')
                ->assertNotFound()
                ->assertJson(['message' => __('messages.not_found')]);
        });
    });

    // ────────────────────────────────────────
    //  Section-based save (structural validation)
    // ────────────────────────────────────────
    describe('save', function () {
        it('validates personal_info fields', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'gender' => 'invalid',
                'blood_group' => 'X+',
                'marital_status' => 'not-a-status',
                'id_number' => str_repeat('1', 11),
                'spouse_employment_status' => 'invalid',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.gender',
                    'personal_info.blood_group',
                    'personal_info.marital_status',
                    'personal_info.id_number',
                    'personal_info.spouse_employment_status',
                ]);
        });

        it('validates military_status sub-fields', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'military_status' => [
                    'status' => 'invalid_status',
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.military_status.status']);
        });

        it('validates education records wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/education", [
                'education_records' => [
                    [
                        'degree' => str_repeat('D', 51),
                        'field' => str_repeat('F', 101),
                        'institution' => str_repeat('I', 101),
                    ],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.education_records.0.degree',
                    'education.education_records.0.field',
                    'education.education_records.0.institution',
                ]);
        });

        it('validates education student conditional fields', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/education", [
                'is_student' => true,
                'student_degree' => str_repeat('D', 51),
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['education.student_degree']);
        });

        it('rejects education record dates that are not Y-m-d on save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/education", [
                'education_records' => [
                    [
                        'from' => '2013/06/15',
                        'to' => 'not-a-date',
                        'graduation_date' => '2013.06.15',
                    ],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.education_records.0.from',
                    'education.education_records.0.to',
                    'education.education_records.0.graduation_date',
                ]);
        });

        it('rejects study dates that are not Y-m-d on save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/education", [
                'study_start' => '1400/01/01',
                'expected_graduation' => '1404.07.01',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.study_start',
                    'education.expected_graduation',
                ]);
        });

        it('rejects a phone that is not a landline on save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'phone' => '1234567890',
                'emergency_phone' => '09121234567',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['contact_info.phone']);
        });

        it('rejects an emergency phone that is neither a mobile nor a landline on save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'phone' => '02112345678',
                'emergency_phone' => '12345',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['contact_info.emergency_phone']);
        });

        it('accepts a mobile number as the emergency phone on save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'phone' => '02112345678',
                'emergency_phone' => '09123456789',
            ])->assertOk();
        });

        it('validates work experience wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/work_experience", [
                'work_experiences' => [
                    [
                        'company' => str_repeat('C', 101),
                        'position' => str_repeat('P', 101),
                        'last_salary' => -1,
                    ],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'work_experience.work_experiences.0.company',
                    'work_experience.work_experiences.0.position',
                    'work_experience.work_experiences.0.last_salary',
                ]);
        });

        it('validates skills wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/skills", [
                'languages' => [
                    [
                        'language' => str_repeat('L', 51),
                        'reading' => 5,
                        'writing' => 0,
                    ],
                ],
                'software_skills' => [
                    'specialized' => [
                        ['name' => str_repeat('S', 101), 'level' => 5],
                    ],
                    'general' => [
                        ['name' => str_repeat('G', 101), 'level' => 0],
                    ],
                ],
                'certificates' => [
                    ['title' => str_repeat('T', 101)],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'skills.languages.0.language',
                    'skills.languages.0.reading',
                    'skills.languages.0.writing',
                    'skills.software_skills.specialized.0.name',
                    'skills.software_skills.specialized.0.level',
                    'skills.software_skills.general.0.name',
                    'skills.software_skills.general.0.level',
                    'skills.certificates.0.title',
                ]);
        });

        it('validates training wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/training", [
                'training_courses' => [
                    ['course_name' => str_repeat('C', 101), 'duration' => str_repeat('D', 51)],
                ],
                'researches' => [
                    ['title' => str_repeat('T', 256)],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'training.training_courses.0.course_name',
                    'training.training_courses.0.duration',
                    'training.researches.0.title',
                ]);
        });

        it('validates additional_info wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/additional_info", [
                'references' => [
                    [
                        'full_name' => str_repeat('N', 101),
                        'relationship' => str_repeat('R', 51),
                        'workplace_phone' => str_repeat('P', 16),
                    ],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'additional_info.references.0.full_name',
                    'additional_info.references.0.relationship',
                    'additional_info.references.0.workplace_phone',
                ]);
        });

        it('allows null descriptions on save even when booleans are true (required only on submit)', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/additional_info", [
                'has_chronic_disease' => true,
                'chronic_disease_description' => null,
                'has_major_surgery' => true,
                'major_surgery_description' => null,
                'has_disability' => true,
                'disability_description' => null,
                'can_travel' => true,
                'travel_description' => null,
                'has_criminal_record' => true,
                'criminal_record_description' => null,
            ])->assertOk();
        });

        it('allows null descriptions when booleans are false', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/additional_info", [
                'has_chronic_disease' => false,
                'chronic_disease_description' => null,
                'has_major_surgery' => false,
                'major_surgery_description' => null,
                'has_disability' => false,
                'disability_description' => null,
                'can_travel' => false,
                'travel_description' => null,
                'has_criminal_record' => false,
                'criminal_record_description' => null,
            ])->assertOk();
        });

        it('validates job_request in-rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/job_request", [
                'employment_type' => 'invalid',
                'preferred_workplace' => ['invalid_place'],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'job_request.employment_type',
                    'job_request.preferred_workplace.0',
                ]);
        });

        it('validates integer min rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'dependents_count' => -1,
                'children_count' => -1,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.dependents_count',
                    'personal_info.children_count',
                ]);
        });

        it('validates education integer min rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/education", [
                'student_semester' => 0,
                'free_days_per_week' => 8,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.student_semester',
                    'education.free_days_per_week',
                ]);
        });

        it('accepts empty body (all fields are sometimes)', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [])
                ->assertOk();
        });

        it('accepts valid personal_info data', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'gender' => 'مرد',
                'blood_group' => 'A+',
                'birth_date' => '1990-01-15',
                'birth_place' => 'تهران-تهران',
                'father_name' => 'Ahmad',
                'religion' => 'اسلام',
                'marital_status' => 'مجرد',
                'id_number' => '0123456789',
            ])->assertOk();
        });

        it('rejects a birth_place that is not a city option', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'gender' => 'مرد',
                'birth_date' => '1990-01-15',
                'birth_place' => 'Tehran',
                'marital_status' => 'مجرد',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.birth_place']);
        });

        it('persists first_name and last_name on personal_info save', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'first_name' => 'NewName',
                'last_name' => 'NewFamily',
                'gender' => 'مرد',
                'blood_group' => 'A+',
                'birth_date' => '1990-01-15',
                'birth_place' => 'تهران-تهران',
                'father_name' => 'Ahmad',
                'religion' => 'اسلام',
                'marital_status' => 'مجرد',
                'id_number' => '0123456789',
            ])
                ->assertOk()
                ->assertJsonPath('data.first_name', 'NewName')
                ->assertJsonPath('data.last_name', 'NewFamily');

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'first_name' => 'NewName',
                'last_name' => 'NewFamily',
            ]);
        });

        it('does not persist email or mobile on section save (staged until OTP verification)', function () {
            $uuid = createDraft();
            $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'email' => 'new@example.com',
                'mobile' => '09999999999',
                'phone' => '02112345678',
                'emergency_phone' => '09121234567',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'email' => 'test@example.com',
                'mobile' => '09121234567',
                'email_verified_at' => $questionnaire->email_verified_at,
                'mobile_verified_at' => $questionnaire->mobile_verified_at,
            ]);
        });

        it('keeps verified timestamps when email and mobile are unchanged', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'email' => 'test@example.com',
                'mobile' => '09121234567',
                'phone' => '02112345678',
                'emergency_phone' => '09121234567',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'email_verified_at' => now(),
                'mobile_verified_at' => now(),
            ]);
        });
    });

    // ────────────────────────────────────────
    //  SubmitQuestionnaireRequest (completion validation — reads from DB)
    // ────────────────────────────────────────
    describe('submit', function () {
        it('requires personal_info sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', []);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.gender',
                    'personal_info.blood_group',
                    'personal_info.birth_date',
                    'personal_info.id_number',
                ]);
        });

        it('requires contact_info sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', []);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'contact_info.phone',
                    'contact_info.emergency_phone',
                    'contact_info.address',
                ]);
        });

        it('requires contact_info.address sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', [
                'phone' => '02112345678',
                'emergency_phone' => '09121234567',
                'address' => [],
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'contact_info.address.postal_code',
                    'contact_info.address.province',
                    'contact_info.address.city',
                    'contact_info.address.address',
                ]);
        });

        it('requires education records', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => []]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['education.education_records']);
        });

        it('requires education record sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => [
                    ['degree' => ''],
                ],
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.education_records.0.degree',
                    'education.education_records.0.field',
                    'education.education_records.0.institution',
                    'education.education_records.0.from',
                    'education.education_records.0.to',
                    'education.education_records.0.graduation_date',
                    'education.education_records.0.gpa',
                ]);
        });

        it('rejects education record dates that are not Y-m-d on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => [
                    array_merge(validEducationRecord()[0], [
                        'from' => '2013/06/15',
                        'to' => '2013/06/15',
                        'graduation_date' => '2013/06/15',
                    ]),
                ],
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.education_records.0.from',
                    'education.education_records.0.to',
                    'education.education_records.0.graduation_date',
                ]);
        });

        it('rejects study dates that are not Y-m-d when the applicant is a student on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => validEducationRecord(),
                'is_student' => true,
                'student_degree' => 'MSc',
                'student_field' => 'Computer Science',
                'student_university' => 'University of Tehran',
                'student_country' => 'Iran',
                'student_city' => 'Tehran',
                'student_gpa' => '18.5',
                'study_start' => '1400/01/01',
                'expected_graduation' => '2026-06-15',
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['education.study_start']);
        });

        it('rejects a phone that is not a landline on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', array_merge(validContactInfo(), [
                'phone' => '1234567890',
            ]));
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['contact_info.phone']);
        });

        it('rejects an emergency phone that is neither a mobile nor a landline on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', array_merge(validContactInfo(), [
                'emergency_phone' => '12345',
            ]));
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['contact_info.emergency_phone']);
        });

        it('accepts a mobile number as the emergency phone on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', array_merge(validContactInfo(), [
                'emergency_phone' => '09123456789',
            ]));
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('skips student fields when the applicant is not a student', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => validEducationRecord(),
                'is_student' => false,
                'student_degree' => null,
                'student_field' => null,
                'student_university' => null,
                'student_country' => null,
                'student_city' => null,
                'student_semester' => null,
                'passed_units' => null,
                'remaining_units' => null,
                'student_gpa' => null,
                'study_start' => null,
                'expected_graduation' => null,
                'thesis_submitted' => false,
                'student_thesis_title' => null,
                'free_days_per_week' => null,
                'education_description' => null,
            ]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('skips conditional descriptions when the flags are false', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', [
                'has_chronic_disease' => false,
                'chronic_disease_description' => null,
                'has_major_surgery' => false,
                'major_surgery_description' => null,
                'has_disability' => false,
                'disability_description' => null,
                'can_travel' => false,
                'travel_description' => null,
                'has_criminal_record' => false,
                'criminal_record_description' => null,
                'references' => [],
            ]);
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('requires student fields when is_student is true', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => validEducationRecord(),
                'is_student' => true,
                'student_degree' => null,
                'student_field' => null,
                'student_university' => null,
                'student_country' => null,
                'student_city' => null,
                'student_gpa' => null,
                'study_start' => null,
                'expected_graduation' => null,
            ]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $response = $this->postJson("/api/questionnaire/{$uuid}/submit");
            $response->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'education.student_degree',
                    'education.student_field',
                    'education.student_university',
                    'education.student_country',
                    'education.student_city',
                    'education.student_gpa',
                    'education.study_start',
                    'education.expected_graduation',
                ]);

            $errors = $response->json('errors');
            expect($errors['education.student_degree'])
                ->toContain('مقطع تحصیلی الزامی است.')
                ->and($errors['education.study_start'])
                ->toContain('تاریخ شروع تحصیل الزامی است.')
                ->not->toContain('باید یک رشته باشد');
        });

        it('requires thesis title when thesis_submitted is true', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => validEducationRecord(),
                'thesis_submitted' => true,
            ]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['education.student_thesis_title']);
        });

        it('requires work experience sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', [
                'work_experiences' => [
                    ['company' => ''],
                ],
            ]);
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'work_experience.work_experiences.0.company',
                    'work_experience.work_experiences.0.position',
                    'work_experience.work_experiences.0.from',
                    'work_experience.work_experiences.0.to',
                ]);
        });

        it('requires language name when languages provided', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', [
                'languages' => [
                    ['language' => ''],
                ],
            ]);
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['skills.languages.0.language']);
        });

        it('requires reference sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', [
                'references' => [
                    ['full_name' => ''],
                ],
            ]);
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'additional_info.references.0.full_name',
                    'additional_info.references.0.relationship',
                    'additional_info.references.0.workplace_phone',
                ]);
        });

        it('requires job_request sub-fields', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', []);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'job_request.employment_type',
                    'job_request.accept_information',
                    'job_request.job_priority_1',
                    'job_request.available_start_date',
                ]);
        });

        it('validates employment_type in-rule', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', [
                'employment_type' => 'invalid',
                'accept_information' => true,
                'job_priority_1' => 'Developer',
                'available_start_date' => '2025-03-21',
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['job_request.employment_type']);
        });

        it('requires accept_information to be accepted (truthy)', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', [
                'employment_type' => 'تمام وقت',
                'accept_information' => false,
                'job_priority_1' => 'Developer',
                'available_start_date' => '2025-03-21',
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['job_request.accept_information']);
        });

        it('requires spouse_employment_status when married', function () {
            $uuid = createDraft();
            $personalInfo = array_merge(validPersonalInfo(), [
                'marital_status' => 'متاهل',
            ]);
            unset($personalInfo['spouse_employment_status']);
            saveSectionToDb($uuid, 'personal_info', $personalInfo);
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.spouse_employment_status']);
        });

        it('does not require spouse_employment_status when single', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'marital_status' => 'مجرد',
            ]));
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('requires military_status when gender is male', function () {
            $uuid = createDraft();
            $personalInfo = array_merge(validPersonalInfo(), [
                'gender' => 'مرد',
            ]);
            unset($personalInfo['military_status']);
            saveSectionToDb($uuid, 'personal_info', $personalInfo);
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.military_status']);
        });

        it('does not require military_status when gender is female', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'gender' => 'زن',
            ]));
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('requires spouse_job when the spouse is employed', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'marital_status' => 'متاهل',
                'spouse_employment_status' => 'شاغل',
            ]));
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.spouse_job']);
        });

        it('does not require spouse_job when the spouse is a housewife', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'marital_status' => 'متاهل',
                'spouse_employment_status' => 'خانه‌دار',
            ]));
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('clears military_status when gender changes from male to female', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());

            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            expect($questionnaire->section_personal)->toHaveKey('military_status');

            $questionnaire->update(['gender' => 'زن']);

            expect($questionnaire->fresh()->section_personal)->not->toHaveKey('military_status');
        });

        it('keeps military_status when gender stays male', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());

            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            $questionnaire->update(['gender' => 'مرد']);

            expect($questionnaire->fresh()->section_personal)->toHaveKey('military_status');
        });

        it('strips military_status when a personal-info save marks gender female', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'gender' => 'زن',
                'military_status' => validPersonalInfo()['military_status'],
            ])->assertOk();

            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            expect($questionnaire->section_personal)->not->toHaveKey('military_status');
        });

        it('validates id_number size is exactly 10', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'id_number' => '123456789',
            ]));
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.id_number']);
        });

        it('accepts valid complete submit data', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());
            attachRequiredDocuments($uuid);

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertOk();
        });

        it('requires required documents on submit', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', ['education_records' => validEducationRecord()]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'documents.national-card',
                    'documents.birth-certificate',
                    'documents.resume',
                    'documents.personnel-photo',
                ]);
        });
    });
});

describe('admin review/reject authorization', function () {
    it('blocks unauthenticated access to review and reject', function () {
        $uuid = createDraft();

        $this->postJson("/api/questionnaire/{$uuid}/review")->assertUnauthorized();
        $this->postJson("/api/questionnaire/{$uuid}/reject")->assertUnauthorized();
    });

    it('reviews a submitted questionnaire when authenticated', function () {
        $user = createUserWithPermissions();
        $uuid = createDraft();
        Questionnaire::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/questionnaire/{$uuid}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
    });

    it('rejects a submitted questionnaire when authenticated', function () {
        $user = createUserWithPermissions();
        $uuid = createDraft();
        Questionnaire::where('uuid', $uuid)->update(['status' => 'submitted']);

        $this->actingAs($user)
            ->postJson("/api/questionnaire/{$uuid}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    });
});

describe('questionnaire regression coverage', function () {
    describe('OTP code_sent flag', function () {
        it('returns code_sent=true on pending send-otp', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            $this->postJson("/api/questionnaire/pending/{$pending->uuid}/send-otp")
                ->assertOk()
                ->assertJsonPath('code_sent', true);
        });

        it('returns code_sent=false when otp was already sent', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            $this->postJson("/api/questionnaire/pending/{$pending->uuid}/send-otp")->assertOk();

            $this->postJson("/api/questionnaire/pending/{$pending->uuid}/send-otp")
                ->assertOk()
                ->assertJsonPath('code_sent', false);
        });

        it('returns code_sent=true on send-mobile-otp', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp")
                ->assertOk()
                ->assertJsonPath('code_sent', true);
        });

        it('returns code_sent=true on send-email-otp', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/send-email-otp")
                ->assertOk()
                ->assertJsonPath('code_sent', true);
        });
    });

    describe('rate limiting on OTP endpoints', function () {
        it('429s with Retry-After after exceeding the send limit', function () {
            $uuid = createDraft();

            foreach (range(1, 5) as $i) {
                $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp")->assertOk();
            }

            $this->postJson("/api/questionnaire/{$uuid}/send-mobile-otp")
                ->assertStatus(429)
                ->assertJsonStructure(['message', 'retry_after'])
                ->assertHeader('Retry-After');
        });

        it('429s with Retry-After after exceeding the verify limit', function () {
            $uuid = createDraft();
            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            $code = app(OtpService::class)->send($questionnaire, 'mobile', OtpContext::VerifyMobile);

            foreach (range(1, 5) as $i) {
                $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", ['otp' => $code]);
            }

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", ['otp' => $code])
                ->assertStatus(429)
                ->assertJsonStructure(['message', 'retry_after'])
                ->assertHeader('Retry-After');
        });
    });

    describe('document upload limits', function () {
        it('rejects uploads beyond the per-category max_files', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $category = DocumentCategory::create([
                'name' => 'سایر مدارک',
                'slug' => 'other-documents',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);

            foreach (range(1, 3) as $i) {
                $this->postJson("/api/questionnaire/{$uuid}/documents", [
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent("doc-{$i}.pdf", "content-{$i}"),
                ])->assertCreated();
            }

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('doc-4.pdf', 'content-4'),
            ])->assertStatus(422);
        });

        it('rejects uploads beyond the global max_files', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $category = DocumentCategory::create([
                'name' => 'تست',
                'slug' => 'test-category',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);
            $totalMax = config('documents.questionnaire.max_files', 10);

            foreach (range(1, $totalMax) as $i) {
                $this->postJson("/api/questionnaire/{$uuid}/documents", [
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent("doc-{$i}.pdf", "content-{$i}"),
                ])->assertCreated();
            }

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('doc-over.pdf', 'content-over'),
            ])->assertStatus(422);
        });

        it('enforces the limit per record key slot', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $category = DocumentCategory::create([
                'name' => 'کارت ملی',
                'slug' => 'national-card',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
                'file' => UploadedFile::fake()->createWithContent('front.pdf', 'front'),
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'personal_info',
                'field_key' => 'back',
                'file' => UploadedFile::fake()->createWithContent('back.pdf', 'back'),
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
                'file' => UploadedFile::fake()->createWithContent('front-2.pdf', 'front-2'),
            ])->assertStatus(422);
        });

        it('allows a new notes group for multi-document categories', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $category = DocumentCategory::create([
                'name' => 'گواهینامه دوره‌ها',
                'slug' => 'course-certificates',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);

            foreach (range(1, 5) as $i) {
                $this->postJson("/api/questionnaire/{$uuid}/documents", [
                    'document_category_id' => $category->id,
                    'notes' => 'دوره PHP',
                    'file' => UploadedFile::fake()->createWithContent("php-{$i}.pdf", "php-{$i}"),
                ])->assertCreated();
            }

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'notes' => 'دوره Laravel',
                'file' => UploadedFile::fake()->createWithContent('laravel-1.pdf', 'laravel-1'),
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'notes' => 'دوره PHP',
                'file' => UploadedFile::fake()->createWithContent('php-6.pdf', 'php-6'),
            ])->assertCreated();
        });

        it('counts the limit per usage slot, not across a document reused for another category', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $birthCertificate = DocumentCategory::create([
                'name' => 'شناسنامه',
                'slug' => 'birth-certificate',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);
            $languageCertificate = DocumentCategory::create([
                'name' => 'گواهینامه زبان',
                'slug' => 'language-certificate',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);

            $shared = UploadedFile::fake()->createWithContent('scan.pdf', 'same-content');

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $birthCertificate->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
                'file' => $shared,
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $languageCertificate->id,
                'section_key' => 'personal_info',
                'field_key' => 'back',
                'file' => $shared,
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $birthCertificate->id,
                'section_key' => 'personal_info',
                'field_key' => 'back',
                'file' => UploadedFile::fake()->createWithContent('back.pdf', 'back-content'),
            ])->assertCreated();
        });
    });

    describe('document storage naming', function () {
        it('stores uploads under a category folder with a placement-based name', function () {
            Storage::fake('local');
            $uuid = createDraft();
            $category = DocumentCategory::create([
                'name' => 'سایر مدارک',
                'slug' => 'other-documents',
                'type' => DocumentCategory::TYPE_PERSONNEL,
            ]);

            $response = $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('My Resume (Final).pdf', 'resume-content'),
            ])->assertCreated();

            $document = Document::where('uuid', $response->json('data.uuid'))->firstOrFail();

            expect($document->original_name)->toBe('My Resume (Final).pdf');
            expect($document->path)->toMatch('#questionnaire/.*/documents/other-documents/document-[a-f0-9]{8}\.pdf#');
            Storage::disk('local')->assertExists($document->path);
        });

        it('names files after the field placement', function () {
            Storage::fake('local');
            $questionnaire = Questionnaire::where('uuid', createDraft())->firstOrFail();

            $document = app(DocumentService::class)->upload(
                $questionnaire,
                UploadedFile::fake()->image('scan.jpg', 10, 10),
                'national-card',
                'personal_info',
                'front',
            );

            expect($document->original_name)->toBe('scan.jpg');
            expect($document->path)->toMatch('#documents/national-card/front-[a-f0-9]{8}\.jpg$#');
            Storage::disk('local')->assertExists($document->path);
        });
    });

    describe('dropped questionnaire columns', function () {
        it('does not create the unused questionnaire section columns', function () {
            expect(Schema::hasColumn('questionnaires', 'military_status'))->toBeFalse();
            expect(Schema::hasColumn('questionnaires', 'section_military_details'))->toBeFalse();
            expect(Schema::hasColumn('questionnaires', 'section_spouse'))->toBeFalse();
            expect(Schema::hasColumn('questionnaires', 'section_documents_metadata'))->toBeFalse();
        });
    });
});

describe('section definitions', function () {
    it('provides labels from the language files', function () {
        $service = app(QuestionnaireService::class);

        foreach ($service->getSectionKeys() as $key) {
            expect($service->getSection($key)->label())->toBe(__("questionnaire.sections.{$key}"));
        }
    });

    it('provides document requirements from the section definitions', function () {
        $requirements = app(QuestionnaireService::class)->getDocumentRequirements();

        expect($requirements)->toHaveKey('national-card')
            ->and($requirements['national-card']['required'])->toBeTrue()
            ->and($requirements['national-card']['max_files'])->toBe(1)
            ->and($requirements['national-card']['field_keys'])->toBe(['front', 'back'])
            ->and($requirements['national-card']['section_key'])->toBe('personal_info')
            ->and($requirements['birth-certificate']['required'])->toBeTrue()
            ->and($requirements['birth-certificate']['field_keys'])->toBe(['page-1', 'page-2', 'page-3'])
            ->and($requirements['personnel-photo']['required'])->toBeTrue()
            ->and($requirements['resume']['required'])->toBeTrue()
            ->and($requirements['resume']['section_key'])->toBe('job_request')
            ->and($requirements['other-documents']['max_files'])->toBe(3)
            ->and($requirements['course-certificates'])->not->toHaveKey('max_files')
            ->and($requirements['skill-certificate']['max_files'])->toBe(1)
            ->and($requirements['employment-certificate']['max_files'])->toBe(1)
            ->and($requirements['research-documents']['max_files'])->toBe(1);
    });

    it('removes per-category requirements from config/documents.php', function () {
        expect(config('documents.questionnaire.requirements'))->toBeNull();
    });
});

describe('document serve via uuid', function () {
    it('serves an attached document through a signed url keyed by uuid', function () {
        Storage::fake('local');
        $uuid = createDraft();
        $category = DocumentCategory::create([
            'name' => 'تصویر پرسنلی',
            'slug' => 'personnel-photo',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $response = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $category->id,
            'file' => fakePhotoUpload(),
        ])->assertCreated();

        $docUuid = $response->json('data.uuid');
        $url = $response->json('data.url');

        expect($url)->toContain($docUuid)
            ->and($url)->not->toContain("/documents/{$response->json('data.id')}/");

        $this->get($url)->assertOk();
    });

    it('serves a stable signed url across document fetches', function () {
        Storage::fake('local');
        $uuid = createDraft();
        $category = DocumentCategory::create([
            'name' => 'تصویر پرسنلی',
            'slug' => 'personnel-photo',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $category->id,
            'file' => fakePhotoUpload(),
        ])->assertCreated();

        $first = $this->getJson("/api/questionnaire/{$uuid}/documents")->json('data.0.url');
        $second = $this->getJson("/api/questionnaire/{$uuid}/documents")->json('data.0.url');

        expect($first)->toBe($second)
            ->and($first)->not->toContain('expires=');

        $this->get($first)->assertOk();
    });

    it('serves the thumbnail variant when requested', function () {
        Storage::fake('local');
        $uuid = createDraft();
        $category = DocumentCategory::create([
            'name' => 'تصویر پرسنلی',
            'slug' => 'personnel-photo',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $response = $this->postJson("/api/questionnaire/{$uuid}/documents", [
            'document_category_id' => $category->id,
            'file' => fakePhotoUpload(),
        ])->assertCreated();

        $this->get($response->json('data.url').'&thumbnail=1')->assertOk();
    });

    it('returns 404 for an unknown or unattached document uuid', function () {
        Storage::fake('local');
        $url = URL::temporarySignedRoute(
            'questionnaire.documents.serve',
            now()->addHours(2),
            ['uuid' => Str::uuid()->toString()],
        );

        $this->get($url)->assertStatus(404);
    });
});

// ─── Helper data builders ────────────────

function validPersonalInfo(): array
{
    return [
        'first_name' => 'Ali',
        'last_name' => 'Rezaei',
        'gender' => 'مرد',
        'blood_group' => 'A+',
        'birth_date' => '1990-01-15',
        'birth_place' => 'تهران-تهران',
        'birth_certificate_number' => '12345',
        'father_name' => 'Ahmad',
        'religion' => 'اسلام',
        'marital_status' => 'مجرد',
        'id_number' => '0123456789',
        'military_status' => [
            'status' => 'پایان خدمت',
            'organization' => 'Army',
            'from' => '2011-03-21',
            'to' => '2013-03-21',
            'reason' => 'Completed',
        ],
    ];
}

function fakePhotoUpload(string $name = 'photo.jpg', int $width = 600, int $height = 800): UploadedFile
{
    $fake = UploadedFile::fake()->image($name, $width, $height);

    // Laravel's fake images compress far below the 20KB minimum, so append
    // random bytes after the JPEG EOI marker to make the file realistic in size.
    file_put_contents($fake->getRealPath(), random_bytes(32 * 1024), FILE_APPEND);

    return $fake;
}

function validContactInfo(): array
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

function validEducationRecord(): array
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

function validWorkExperience(): array
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

function validSkills(): array
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

function validTraining(): array
{
    return [
        'training_courses' => [
            ['course_name' => 'Laravel', 'duration' => '40 hours', 'institution' => 'Academy'],
        ],
    ];
}

function validAdditionalInfo(): array
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

function validJobRequest(): array
{
    return [
        'employment_type' => 'تمام وقت',
        'accept_information' => true,
        'job_priority_1' => 'Developer',
        'available_start_date' => '2025-03-21',
        'preferred_workplace' => ['دفتر تهران'],
    ];
}
