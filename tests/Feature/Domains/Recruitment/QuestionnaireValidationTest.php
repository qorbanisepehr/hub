<?php

use App\Domains\Recruitment\Models\Questionnaire;

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
        'mobile_otp' => '123456',
        'email_otp' => '654321',
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ])->uuid;
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

        it('accepts valid data', function () {
            $this->postJson('/api/questionnaire/init', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'email' => 'ali@example.com',
                'mobile' => '09121234567',
            ])->assertCreated();
        });
    });

    // ────────────────────────────────────────
    //  VerifyQuestionnaireRequest
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

            $this->postJson("/api/questionnaire/{$uuid}/verify-mobile-otp", [
                'otp' => '123456',
            ])->assertOk();
        });
    });

    // ────────────────────────────────────────
    //  SaveQuestionnaireRequest (step-by-step)
    // ────────────────────────────────────────
    describe('save', function () {
        it('validates personal_info fields', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'personal_info' => [
                    'gender' => 'invalid',
                    'blood_group' => 'X+',
                    'marital_status' => 'unknown',
                    'national_id' => str_repeat('1', 11),
                    'spouse_employment_status' => 'invalid',
                ],
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'personal_info' => [
                    'military_status' => [
                        'status' => 'invalid_status',
                    ],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.military_status.status']);
        });

        it('validates education records wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'education' => [
                    'education_records' => [
                        [
                            'degree' => str_repeat('D', 51),
                            'field' => str_repeat('F', 101),
                            'institution' => str_repeat('I', 101),
                        ],
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'education' => [
                    'is_student' => true,
                    'student_degree' => str_repeat('D', 51),
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['education.student_degree']);
        });

        it('validates work experience wildcard rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'work_experience' => [
                    'work_experiences' => [
                        [
                            'company' => str_repeat('C', 101),
                            'position' => str_repeat('P', 101),
                            'last_salary' => -1,
                        ],
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'skills' => [
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'training' => [
                    'training_courses' => [
                        ['course_name' => str_repeat('C', 101), 'duration' => str_repeat('D', 51)],
                    ],
                    'researches' => [
                        ['title' => str_repeat('T', 256)],
                    ],
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'additional_info' => [
                    'references' => [
                        [
                            'full_name' => str_repeat('N', 101),
                            'relationship' => str_repeat('R', 51),
                            'workplace_phone' => str_repeat('P', 16),
                        ],
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

            $this->putJson("/api/questionnaire/{$uuid}", [
                'additional_info' => [
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
                ],
            ])->assertOk();
        });

        it('allows null descriptions when booleans are false', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'additional_info' => [
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
                ],
            ])->assertOk();
        });

        it('validates job_request in-rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'job_request' => [
                    'employment_type' => 'invalid',
                    'preferred_workplace' => ['invalid_place'],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'job_request.employment_type',
                    'job_request.preferred_workplace.0',
                ]);
        });

        it('validates integer min rules', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'personal_info' => [
                    'dependents_count' => -1,
                    'children_count' => -1,
                ],
                'education' => [
                    'student_semester' => 0,
                    'free_days_per_week' => 8,
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.dependents_count',
                    'personal_info.children_count',
                    'education.student_semester',
                    'education.free_days_per_week',
                ]);
        });

        it('accepts empty body (all fields are sometimes)', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [])
                ->assertOk();
        });

        it('accepts valid personal_info data', function () {
            $uuid = createDraft();

            $this->putJson("/api/questionnaire/{$uuid}", [
                'personal_info' => [
                    'gender' => 'male',
                    'blood_group' => 'A+',
                    'birth_date' => '1990-01-15',
                    'birth_place' => 'Tehran',
                    'father_name' => 'Ahmad',
                    'religion' => 'Islam',
                    'marital_status' => 'single',
                    'national_id' => '0123456789',
                ],
            ])->assertOk();
        });
    });

    // ────────────────────────────────────────
    //  SubmitQuestionnaireRequest
    // ────────────────────────────────────────
    describe('submit', function () {
        it('requires all top-level fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'first_name', 'last_name', 'email', 'mobile',
                    'personal_info', 'contact_info', 'education', 'work_experience',
                    'skills', 'training', 'additional_info', 'job_request',
                ]);
        });

        it('requires personal_info sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'personal_info' => [],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'personal_info.gender',
                    'personal_info.blood_group',
                    'personal_info.birth_date',
                    'personal_info.national_id',
                ]);
        });

        it('requires contact_info sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'contact_info' => [],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'contact_info.phone',
                    'contact_info.emergency_phone',
                    'contact_info.address',
                ]);
        });

        it('requires contact_info.address sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'contact_info' => [
                    'phone' => '02112345678',
                    'emergency_phone' => '09121234567',
                    'address' => [],
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'contact_info.address.postal_code',
                    'contact_info.address.province',
                    'contact_info.address.city',
                    'contact_info.address.address',
                ]);
        });

        it('requires education records', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'education' => ['education_records' => []],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['education.education_records']);
        });

        it('requires education record sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'education' => [
                    'education_records' => [
                        ['degree' => ''],
                    ],
                ],
            ]))->assertUnprocessable()
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

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'education' => [
                    'education_records' => validEducationRecord(),
                    'is_student' => true,
                ],
            ]))->assertUnprocessable()
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

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'education' => [
                    'education_records' => validEducationRecord(),
                    'thesis_submitted' => true,
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['education.student_thesis_title']);
        });

        it('requires work experience sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'work_experience' => [
                    'work_experiences' => [
                        ['company' => ''],
                    ],
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'work_experience.work_experiences.0.company',
                    'work_experience.work_experiences.0.position',
                    'work_experience.work_experiences.0.from',
                    'work_experience.work_experiences.0.to',
                ]);
        });

        it('requires language name when languages provided', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'skills' => [
                    'languages' => [
                        ['language' => ''],
                    ],
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['skills.languages.0.language']);
        });

        it('requires reference sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'additional_info' => [
                    'references' => [
                        ['full_name' => ''],
                    ],
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'additional_info.references.0.full_name',
                    'additional_info.references.0.relationship',
                    'additional_info.references.0.workplace_phone',
                ]);
        });

        it('requires job_request sub-fields', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'job_request' => [],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'job_request.employment_type',
                    'job_request.accept_information',
                    'job_request.job_priority_1',
                    'job_request.available_start_date',
                ]);
        });

        it('validates employment_type in-rule', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'job_request' => [
                    'employment_type' => 'invalid',
                    'accept_information' => true,
                    'job_priority_1' => 'Developer',
                    'available_start_date' => '2025-03-21',
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['job_request.employment_type']);
        });

        it('requires accept_information to be accepted (truthy)', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'job_request' => [
                    'employment_type' => 'full_time',
                    'accept_information' => false,
                    'job_priority_1' => 'Developer',
                    'available_start_date' => '2025-03-21',
                ],
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['job_request.accept_information']);
        });

        it('requires spouse_employment_status when married', function () {
            $uuid = createDraft();

            $data = validSubmitData([
                'personal_info' => array_merge(validPersonalInfo(), [
                    'marital_status' => 'married',
                ]),
            ]);
            unset($data['personal_info']['spouse_employment_status']);

            $this->postJson("/api/questionnaire/{$uuid}/submit", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.spouse_employment_status']);
        });

        it('does not require spouse_employment_status when single', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'personal_info' => array_merge(validPersonalInfo(), [
                    'marital_status' => 'single',
                ]),
            ]))->assertOk();
        });

        it('requires military_status when gender is male', function () {
            $uuid = createDraft();

            $data = validSubmitData([
                'personal_info' => array_merge(validPersonalInfo(), [
                    'gender' => 'male',
                ]),
            ]);
            unset($data['personal_info']['military_status']);

            $this->postJson("/api/questionnaire/{$uuid}/submit", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.military_status']);
        });

        it('does not require military_status when gender is female', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'personal_info' => array_merge(validPersonalInfo(), [
                    'gender' => 'female',
                ]),
            ]))->assertOk();
        });

        it('validates national_id size is exactly 10', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData([
                'personal_info' => array_merge(validPersonalInfo(), [
                    'national_id' => '123456789',
                ]),
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['personal_info.national_id']);
        });

        it('accepts valid complete submit data', function () {
            $uuid = createDraft();

            $this->postJson("/api/questionnaire/{$uuid}/submit", validSubmitData())
                ->assertOk();
        });
    });
});

// ─── Helper data builders ────────────────

function validPersonalInfo(): array
{
    return [
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

function validContactInfo(): array
{
    return [
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

function validSubmitData(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ali',
        'last_name' => 'Rezaei',
        'email' => 'ali@example.com',
        'mobile' => '09121234567',
        'personal_info' => validPersonalInfo(),
        'contact_info' => validContactInfo(),
        'education' => [
            'education_records' => validEducationRecord(),
        ],
        'work_experience' => validWorkExperience(),
        'skills' => validSkills(),
        'training' => validTraining(),
        'additional_info' => validAdditionalInfo(),
        'job_request' => validJobRequest(),
    ], $overrides);
}
