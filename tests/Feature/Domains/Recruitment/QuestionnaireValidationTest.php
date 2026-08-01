<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Services\QuestionnaireService;
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
 * Helper: create a draft questionnaire and return its UUID.
 */
function createDraft(): string
{
    return Questionnaire::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile' => '09121234567',
        'status' => 'draft',
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ])->uuid;
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
        $document = Document::factory()->create();
        DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Questionnaire::class,
            'entity_id' => $questionnaire->id,
            'category_slug' => $slug,
        ]);
    }
}

describe('Questionnaire validation', function () {
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

            Cache::put("otp:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

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

        it('returns existing questionnaire when mobile already exists', function () {
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

            Cache::put("otp:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson('/api/questionnaire/verify-init-otp', [
                'uuid' => $pending->uuid,
                'otp' => '123456',
            ])->assertOk()
                ->assertJsonPath('data.first_name', 'Ali');
        });

        it('returns 422 for expired otp', function () {
            $pending = PendingVerification::create([
                'type' => 'questionnaire',
                'mobile' => '09121234567',
                'payload' => ['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com', 'mobile' => '09121234567'],
            ]);

            Cache::put("otp:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->subMinute());

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

            Cache::put("otp:pending-verification:{$pending->uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

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

            Cache::put("otp:questionnaire:{$uuid}:mobile", ['hash' => Hash::make('123456')], now()->addMinutes(5));

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '123456',
            ])->assertOk();
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
                'marital_status' => 'unknown',
                'national_id' => str_repeat('1', 11),
                'spouse_employment_status' => 'invalid',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.gender',
                    'personal_info.blood_group',
                    'personal_info.marital_status',
                    'personal_info.national_id',
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
                'gender' => 'male',
                'blood_group' => 'A+',
                'birth_date' => '1990-01-15',
                'birth_place' => 'Tehran',
                'father_name' => 'Ahmad',
                'religion' => 'Islam',
                'marital_status' => 'single',
                'national_id' => '0123456789',
            ])->assertOk();
        });

        it('resets verified timestamps when email or mobile changes', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/contact_info", [
                'email' => 'new@example.com',
                'mobile' => '09999999999',
                'phone' => '02112345678',
                'emergency_phone' => '09121234567',
            ])->assertOk();

            $this->assertDatabaseHas('questionnaires', [
                'uuid' => $uuid,
                'email' => 'new@example.com',
                'mobile' => '09999999999',
                'email_verified_at' => null,
                'mobile_verified_at' => null,
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
                    'personal_info.national_id',
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

        it('requires student fields when is_student is true', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());
            saveSectionToDb($uuid, 'contact_info', validContactInfo());
            saveSectionToDb($uuid, 'education', [
                'education_records' => validEducationRecord(),
                'is_student' => true,
            ]);
            saveSectionToDb($uuid, 'work_experience', validWorkExperience());
            saveSectionToDb($uuid, 'skills', validSkills());
            saveSectionToDb($uuid, 'training', validTraining());
            saveSectionToDb($uuid, 'additional_info', validAdditionalInfo());
            saveSectionToDb($uuid, 'job_request', validJobRequest());

            $this->postJson("/api/questionnaire/{$uuid}/submit")
                ->assertUnprocessable()
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
                'employment_type' => 'full_time',
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
                'marital_status' => 'married',
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
                'marital_status' => 'single',
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
                'gender' => 'male',
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
                'gender' => 'female',
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
                'marital_status' => 'married',
                'spouse_employment_status' => 'employed',
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
                'marital_status' => 'married',
                'spouse_employment_status' => 'housewife',
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

            $questionnaire->update(['gender' => 'female']);

            expect($questionnaire->fresh()->section_personal)->not->toHaveKey('military_status');
        });

        it('keeps military_status when gender stays male', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', validPersonalInfo());

            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            $questionnaire->update(['gender' => 'male']);

            expect($questionnaire->fresh()->section_personal)->toHaveKey('military_status');
        });

        it('strips military_status when a personal-info save marks gender female', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}/sections/personal_info", [
                'gender' => 'female',
                'military_status' => validPersonalInfo()['military_status'],
            ])->assertOk();

            $questionnaire = Questionnaire::where('uuid', $uuid)->first();
            expect($questionnaire->section_personal)->not->toHaveKey('military_status');
        });

        it('validates national_id size is exactly 10', function () {
            $uuid = createDraft();
            saveSectionToDb($uuid, 'personal_info', array_merge(validPersonalInfo(), [
                'national_id' => '123456789',
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
                ->assertJsonValidationErrors(['personal_info.national_id']);
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

describe('recruitment regression coverage', function () {
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
            $code = app(OtpService::class)->send($questionnaire, 'mobile');

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
            $totalMax = config('documents.recruitment.max_files', 10);

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
                'record_key' => 'front',
                'file' => UploadedFile::fake()->createWithContent('front.pdf', 'front'),
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'record_key' => 'back',
                'file' => UploadedFile::fake()->createWithContent('back.pdf', 'back'),
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $category->id,
                'record_key' => 'front',
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
                'record_key' => 'front',
                'file' => $shared,
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $languageCertificate->id,
                'record_key' => 'back',
                'file' => $shared,
            ])->assertCreated();

            $this->postJson("/api/questionnaire/{$uuid}/documents", [
                'document_category_id' => $birthCertificate->id,
                'record_key' => 'back',
                'file' => UploadedFile::fake()->createWithContent('back.pdf', 'back-content'),
            ])->assertCreated();
        });
    });

    describe('document storage naming', function () {
        it('stores uploads under a category-slug name with a content fingerprint', function () {
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
            expect($document->path)->toMatch('#questionnaire/.*/documents/other-documents/other-documents-[a-f0-9]{8}\.pdf#');
            Storage::disk('local')->assertExists($document->path);
        });

        it('names files after the category and record key', function () {
            Storage::fake('local');
            $questionnaire = Questionnaire::where('uuid', createDraft())->firstOrFail();

            $document = app(DocumentService::class)->upload(
                $questionnaire,
                UploadedFile::fake()->image('scan.jpg', 10, 10),
                'national-card',
                'front',
            );

            expect($document->original_name)->toBe('scan.jpg');
            expect($document->path)->toMatch('#documents/national-card/national-card-front-[a-f0-9]{8}\.jpg$#');
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
            expect($service->getSection($key)->label())->toBe(__("recruitment.sections.{$key}"));
        }
    });

    it('provides document requirements from the section definitions', function () {
        $requirements = app(QuestionnaireService::class)->getDocumentRequirements();

        expect($requirements)->toHaveKey('national-card')
            ->and($requirements['national-card']['required'])->toBeTrue()
            ->and($requirements['national-card']['max_files'])->toBe(1)
            ->and($requirements['national-card']['record_keys'])->toBe(['front', 'back'])
            ->and($requirements['birth-certificate']['required'])->toBeTrue()
            ->and($requirements['birth-certificate']['record_keys'])->toBe(['page-1', 'page-2', 'page-3'])
            ->and($requirements['personnel-photo']['required'])->toBeTrue()
            ->and($requirements['resume']['required'])->toBeTrue()
            ->and($requirements['other-documents']['max_files'])->toBe(3)
            ->and($requirements['course-certificates'])->not->toHaveKey('max_files');
    });

    it('removes per-category requirements from config/documents.php', function () {
        expect(config('documents.recruitment.requirements'))->toBeNull();
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
        'gender' => 'male',
        'blood_group' => 'A+',
        'birth_date' => '1990-01-15',
        'birth_place' => 'Tehran',
        'birth_certificate_number' => '12345',
        'father_name' => 'Ahmad',
        'religion' => 'Islam',
        'marital_status' => 'single',
        'national_id' => '0123456789',
        'military_status' => [
            'status' => 'completed',
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
            'province' => 'Tehran',
            'city' => 'Tehran',
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
            'degree' => 'BSc',
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
        'employment_type' => 'full_time',
        'accept_information' => true,
        'job_priority_1' => 'Developer',
        'available_start_date' => '2025-03-21',
        'preferred_workplace' => ['tehran'],
    ];
}
