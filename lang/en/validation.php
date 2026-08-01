<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute field must be accepted.',
    'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    'active_url' => 'The :attribute field must be a valid URL.',
    'after' => 'The :attribute field must be a date after :date.',
    'after_or_equal' => 'The :attribute field must be a date after or equal to :date.',
    'alpha' => 'The :attribute field must only contain letters.',
    'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    'any_of' => 'The :attribute field is invalid.',
    'array' => 'The :attribute field must be an array.',
    'ascii' => 'The :attribute field must only contain single-byte alphanumeric characters and symbols.',
    'before' => 'The :attribute field must be a date before :date.',
    'before_or_equal' => 'The :attribute field must be a date before or equal to :date.',
    'between' => [
        'array' => 'The :attribute field must have between :min and :max items.',
        'file' => 'The :attribute field must be between :min and :max kilobytes.',
        'numeric' => 'The :attribute field must be between :min and :max.',
        'string' => 'The :attribute field must be between :min and :max characters.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'can' => 'The :attribute field contains an unauthorized value.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'contains' => 'The :attribute field is missing a required value.',
    'current_password' => 'The password is incorrect.',
    'date' => 'The :attribute field must be a valid date.',
    'date_equals' => 'The :attribute field must be a date equal to :date.',
    'date_format' => 'The :attribute field must match the format :format.',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'declined' => 'The :attribute field must be declined.',
    'declined_if' => 'The :attribute field must be declined when :other is :value.',
    'different' => 'The :attribute field and :other must be different.',
    'digits' => 'The :attribute field must be :digits digits.',
    'digits_between' => 'The :attribute field must be between :min and :max digits.',
    'dimensions' => 'The :attribute field has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'doesnt_contain' => 'The :attribute field must not contain any of the following: :values.',
    'doesnt_end_with' => 'The :attribute field must not end with one of the following: :values.',
    'doesnt_start_with' => 'The :attribute field must not start with one of the following: :values.',
    'email' => 'The :attribute field must be a valid email address.',
    'encoding' => 'The :attribute field must be encoded in :encoding.',
    'ends_with' => 'The :attribute field must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'extensions' => 'The :attribute field must have one of the following extensions: :values.',
    'file' => 'The :attribute field must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute field must have more than :value items.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than :value.',
        'string' => 'The :attribute field must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute field must have :value items or more.',
        'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than or equal to :value.',
        'string' => 'The :attribute field must be greater than or equal to :value characters.',
    ],
    'hex_color' => 'The :attribute field must be a valid hexadecimal color.',
    'image' => 'The :attribute field must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field must exist in :other.',
    'in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    'integer' => 'The :attribute field must be an integer.',
    'ip' => 'The :attribute field must be a valid IP address.',
    'ipv4' => 'The :attribute field must be a valid IPv4 address.',
    'ipv6' => 'The :attribute field must be a valid IPv6 address.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'list' => 'The :attribute field must be a list.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'lt' => [
        'array' => 'The :attribute field must have less than :value items.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'numeric' => 'The :attribute field must be less than :value.',
        'string' => 'The :attribute field must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute field must not have more than :value items.',
        'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be less than or equal to :value.',
        'string' => 'The :attribute field must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The :attribute field must be a valid MAC address.',
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'max_digits' => 'The :attribute field must not have more than :max digits.',
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'mimetypes' => 'The :attribute field must be a file of type: :values.',
    'min' => [
        'array' => 'The :attribute field must have at least :min items.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'min_digits' => 'The :attribute field must have at least :min digits.',
    'missing' => 'The :attribute field must be missing.',
    'missing_if' => 'The :attribute field must be missing when :other is :value.',
    'missing_unless' => 'The :attribute field must be missing unless :other is :value.',
    'missing_with' => 'The :attribute field must be missing when :values is present.',
    'missing_with_all' => 'The :attribute field must be missing when :values are present.',
    'multiple_of' => 'The :attribute field must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute field format is invalid.',
    'numeric' => 'The :attribute field must be a number.',
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'present' => 'The :attribute field must be present.',
    'present_if' => 'The :attribute field must be present when :other is :value.',
    'present_unless' => 'The :attribute field must be present unless :other is :value.',
    'present_with' => 'The :attribute field must be present when :values is present.',
    'present_with_all' => 'The :attribute field must be present when :values are present.',
    'prohibited' => 'The :attribute field is prohibited.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'The :attribute field is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'The :attribute field is prohibited when :other is declined.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
    'required_if_declined' => 'The :attribute field is required when :other is declined.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute field must match :other.',
    'size' => [
        'array' => 'The :attribute field must contain :size items.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'numeric' => 'The :attribute field must be :size.',
        'string' => 'The :attribute field must be :size characters.',
    ],
    'starts_with' => 'The :attribute field must start with one of the following: :values.',
    'string' => 'The :attribute field must be a string.',
    'timezone' => 'The :attribute field must be a valid timezone.',
    'unique' => 'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'uppercase' => 'The :attribute field must be uppercase.',
    'url' => 'The :attribute field must be a valid URL.',
    'ulid' => 'The :attribute field must be a valid ULID.',
    'uuid' => 'The :attribute field must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        // ── InitQuestionnaireRequest ──
        'first_name' => [
            'required' => 'First name is required.',
            'max' => 'Maximum 100 characters.',
        ],
        'last_name' => [
            'required' => 'Last name is required.',
            'max' => 'Maximum 100 characters.',
        ],
        'email' => [
            'required' => 'Email is required.',
            'email' => 'Invalid email format.',
            'max' => 'Maximum 255 characters.',
        ],
        'mobile' => [
            'required' => 'Mobile number is required.',
            'max' => 'Maximum 15 characters.',
        ],
        // ── VerifyQuestionnaireRequest ──
        'otp' => [
            'required' => 'Verification code is required.',
            'size' => 'Verification code must be 6 digits.',
        ],
        // ── Personal Info ──
        'personal_info.gender' => [
            'required' => 'Gender is required.',
            'in' => 'Invalid gender.',
        ],
        'personal_info.blood_group' => [
            'required' => 'Blood group is required.',
            'in' => 'Invalid blood group.',
        ],
        'personal_info.birth_date' => [
            'required' => 'Birth date is required.',
        ],
        'personal_info.birth_place' => [
            'required' => 'Birth place is required.',
        ],
        'personal_info.birth_certificate_number' => [
            'required' => 'Birth certificate number is required.',
        ],
        'personal_info.father_name' => [
            'required' => 'Father name is required.',
        ],
        'personal_info.religion' => [
            'required' => 'Religion is required.',
        ],
        'personal_info.marital_status' => [
            'required' => 'Marital status is required.',
            'in' => 'Invalid marital status.',
        ],
        'personal_info.spouse_employment_status' => [
            'required_if' => 'Spouse employment status is required.',
            'in' => 'Invalid spouse employment status.',
        ],
        'personal_info.spouse_job' => [
            'required_if' => 'Spouse job is required.',
        ],
        'personal_info.national_id' => [
            'required' => 'National ID is required.',
            'size' => 'National ID must be 10 digits.',
        ],
        // ── Contact Info ──
        'contact_info.phone' => [
            'required' => 'Phone number is required.',
        ],
        'contact_info.emergency_phone' => [
            'required' => 'Emergency phone is required.',
        ],
        'contact_info.address.postal_code' => [
            'required' => 'Postal code is required.',
        ],
        'contact_info.address.province' => [
            'required' => 'Province is required.',
        ],
        'contact_info.address.city' => [
            'required' => 'City is required.',
        ],
        'contact_info.address.address' => [
            'required' => 'Address is required.',
        ],
        'personal_info.military_status.status' => [
            'required_with' => 'Military status is required.',
            'in' => 'Invalid military status.',
        ],
        'personal_info.military_status.organization' => [
            'required_with' => 'Organization is required.',
        ],
        'personal_info.military_status.from' => [
            'required_with' => 'Start date is required.',
        ],
        'personal_info.military_status.to' => [
            'required_with' => 'End date is required.',
        ],
        'personal_info.military_status.reason' => [
            'required_with' => 'Reason is required.',
        ],
        // ── Education ──
        'education.education_records' => [
            'required' => 'At least one education record is required.',
            'min' => 'At least one education record is required.',
        ],
        'education.education_records.*.degree' => [
            'required' => 'Degree is required.',
        ],
        'education.education_records.*.field' => [
            'required' => 'Field of study is required.',
        ],
        'education.education_records.*.institution' => [
            'required' => 'Institution is required.',
        ],
        'education.education_records.*.from' => [
            'required' => 'Start date is required.',
        ],
        'education.education_records.*.to' => [
            'required' => 'End date is required.',
        ],
        'education.education_records.*.graduation_date' => [
            'required' => 'Graduation date is required.',
        ],
        'education.education_records.*.gpa' => [
            'required' => 'GPA is required.',
        ],
        'education.student_degree' => [
            'required_if' => 'Student degree is required.',
        ],
        'education.student_field' => [
            'required_if' => 'Student field is required.',
        ],
        'education.student_university' => [
            'required_if' => 'University is required.',
        ],
        'education.student_country' => [
            'required_if' => 'Country is required.',
        ],
        'education.student_city' => [
            'required_if' => 'City is required.',
        ],
        'education.student_gpa' => [
            'required_if' => 'Student GPA is required.',
        ],
        'education.study_start' => [
            'required_if' => 'Study start date is required.',
        ],
        'education.expected_graduation' => [
            'required_if' => 'Expected graduation date is required.',
        ],
        'education.student_thesis_title' => [
            'required_if' => 'Thesis title is required.',
        ],
        // ── Work Experience ──
        'work_experience.work_experiences.*.company' => [
            'required' => 'Company name is required.',
        ],
        'work_experience.work_experiences.*.position' => [
            'required' => 'Position is required.',
        ],
        'work_experience.work_experiences.*.from' => [
            'required' => 'Start date is required.',
        ],
        'work_experience.work_experiences.*.to' => [
            'required' => 'End date is required.',
        ],
        // ── Skills ──
        'skills.languages.*.language' => [
            'required' => 'Language name is required.',
        ],
        'skills.software_skills.specialized.*.name' => [
            'required' => 'Software name is required.',
        ],
        'skills.software_skills.general.*.name' => [
            'required' => 'Software name is required.',
        ],
        'skills.certificates.*.title' => [
            'required' => 'Certificate title is required.',
        ],
        // ── Training ──
        'training.training_courses.*.course_name' => [
            'required' => 'Course name is required.',
        ],
        'training.researches.*.title' => [
            'required' => 'Research title is required.',
        ],
        // ── Additional Info ──
        'additional_info.references.*.full_name' => [
            'required' => 'Reference full name is required.',
        ],
        'additional_info.references.*.relationship' => [
            'required' => 'Relationship is required.',
        ],
        'additional_info.references.*.workplace_phone' => [
            'required' => 'Workplace phone is required.',
        ],
        // ── Job Request ──
        'job_request.employment_type' => [
            'required' => 'Employment type is required.',
            'in' => 'Invalid employment type.',
        ],
        'job_request.accept_information' => [
            'required' => 'Information acceptance is required.',
            'accepted' => 'You must accept the information.',
        ],
        'job_request.job_priority_1' => [
            'required' => 'Job priority is required.',
        ],
        'job_request.available_start_date' => [
            'required' => 'Start date is required.',
        ],
        'job_request.preferred_workplace.*' => [
            'in' => 'Invalid workplace.',
        ],
        // ── Public document upload ──
        'document_category_id' => [
            'required' => 'A category is required.',
            'exists' => 'The selected category is invalid.',
        ],
        'file' => [
            'required' => 'A file is required.',
        ],
    ],

    'attributes' => [
        'first_name' => 'first name',
        'last_name' => 'last name',
        'email' => 'email',
        'mobile' => 'mobile',
        'otp' => 'verification code',
        'personal_info' => 'personal information',
        'personal_info.gender' => 'gender',
        'personal_info.blood_group' => 'blood group',
        'personal_info.birth_date' => 'birth date',
        'personal_info.birth_place' => 'birth place',
        'personal_info.birth_certificate_number' => 'birth certificate number',
        'personal_info.father_name' => 'father name',
        'personal_info.religion' => 'religion',
        'personal_info.marital_status' => 'marital status',
        'personal_info.first_name_en' => 'English first name',
        'personal_info.last_name_en' => 'English last name',
        'personal_info.dependents_count' => 'dependents count',
        'personal_info.children_count' => 'children count',
        'personal_info.spouse_employment_status' => 'spouse employment status',
        'personal_info.spouse_job' => 'spouse job',
        'personal_info.military_status' => 'military status',
        'personal_info.military_status.status' => 'status',
        'personal_info.military_status.organization' => 'organization',
        'personal_info.military_status.from' => 'start date',
        'personal_info.military_status.to' => 'end date',
        'personal_info.military_status.reason' => 'reason',
        'personal_info.photo' => 'photo',
        'personal_info.national_id' => 'national ID',
        'contact_info.phone' => 'phone',
        'contact_info.emergency_phone' => 'emergency phone',
        'contact_info.address.postal_code' => 'postal code',
        'contact_info.address.province' => 'province',
        'contact_info.address.city' => 'city',
        'contact_info.address.address' => 'address',
        'contact_info.address.plaque' => 'plaque',
        'contact_info.address.floor' => 'floor',
        'contact_info.address.unit' => 'unit',
        'education' => 'education',
        'education.education_records' => 'education records',
        'education.education_records.*.degree' => 'degree',
        'education.education_records.*.field' => 'field of study',
        'education.education_records.*.institution' => 'institution',
        'education.education_records.*.location' => 'location',
        'education.education_records.*.from' => 'start date',
        'education.education_records.*.to' => 'end date',
        'education.education_records.*.thesis_title' => 'thesis title',
        'education.education_records.*.graduation_date' => 'graduation date',
        'education.education_records.*.gpa' => 'GPA',
        'education.is_student' => 'student status',
        'education.student_degree' => 'student degree',
        'education.student_field' => 'student field',
        'education.student_university' => 'university',
        'education.student_country' => 'country',
        'education.student_city' => 'city',
        'education.student_semester' => 'current semester',
        'education.passed_units' => 'passed units',
        'education.remaining_units' => 'remaining units',
        'education.student_gpa' => 'student GPA',
        'education.study_start' => 'study start date',
        'education.expected_graduation' => 'expected graduation',
        'education.thesis_submitted' => 'thesis submitted',
        'education.student_thesis_title' => 'thesis title',
        'education.free_days_per_week' => 'free days per week',
        'education.education_description' => 'education description',
        'work_experience' => 'work experience',
        'work_experience.work_experiences' => 'work experiences',
        'work_experience.work_experiences.*.company' => 'company',
        'work_experience.work_experiences.*.location' => 'location',
        'work_experience.work_experiences.*.industry' => 'industry',
        'work_experience.work_experiences.*.position' => 'position',
        'work_experience.work_experiences.*.from' => 'start date',
        'work_experience.work_experiences.*.to' => 'end date',
        'work_experience.work_experiences.*.contract_type' => 'contract type',
        'work_experience.work_experiences.*.phone' => 'phone',
        'work_experience.work_experiences.*.manager_name' => 'manager name',
        'work_experience.work_experiences.*.last_salary' => 'last salary',
        'work_experience.work_experiences.*.leave_reason' => 'leave reason',
        'work_experience.achievements' => 'achievements',
        'work_experience.allow_contact_previous_managers' => 'allow contact with previous managers',
        'work_experience.contact_restriction_description' => 'contact restriction description',
        'skills' => 'skills',
        'skills.languages' => 'languages',
        'skills.languages.*.language' => 'language',
        'skills.languages.*.reading' => 'reading',
        'skills.languages.*.writing' => 'writing',
        'skills.languages.*.speaking' => 'speaking',
        'skills.languages.*.comprehension' => 'comprehension',
        'skills.software_skills' => 'software skills',
        'skills.software_skills.specialized' => 'specialized software',
        'skills.software_skills.specialized.*.name' => 'software name',
        'skills.software_skills.specialized.*.level' => 'level',
        'skills.software_skills.general' => 'general software',
        'skills.software_skills.general.*.name' => 'software name',
        'skills.software_skills.general.*.level' => 'level',
        'skills.certificates' => 'certificates',
        'skills.certificates.*.title' => 'title',
        'skills.certificates.*.expire_at' => 'expiration date',
        'skills.special_skills' => 'special skills',
        'training' => 'training',
        'training.training_courses' => 'training courses',
        'training.training_courses.*.course_name' => 'course name',
        'training.training_courses.*.duration' => 'duration',
        'training.training_courses.*.institution' => 'institution',
        'training.training_courses.*.held_at' => 'date held',
        'training.training_courses.*.certificate' => 'certificate',
        'training.professional_memberships' => 'professional memberships',
        'training.researches' => 'researches',
        'training.researches.*.title' => 'title',
        'additional_info' => 'additional information',
        'additional_info.has_chronic_disease' => 'chronic disease',
        'additional_info.chronic_disease_description' => 'chronic disease description',
        'additional_info.company_introduction_method' => 'company introduction method',
        'additional_info.has_major_surgery' => 'major surgery',
        'additional_info.major_surgery_description' => 'major surgery description',
        'additional_info.reason_for_joining' => 'reason for joining',
        'additional_info.has_disability' => 'disability',
        'additional_info.disability_description' => 'disability description',
        'additional_info.can_travel' => 'can travel',
        'additional_info.travel_description' => 'travel description',
        'additional_info.has_criminal_record' => 'criminal record',
        'additional_info.criminal_record_description' => 'criminal record description',
        'additional_info.hobbies' => 'hobbies',
        'additional_info.references' => 'references',
        'additional_info.references.*.full_name' => 'full name',
        'additional_info.references.*.relationship' => 'relationship',
        'additional_info.references.*.workplace_phone' => 'workplace phone',
        'additional_info.strengths_and_improvements' => 'strengths and improvements',
        'job_request' => 'job request',
        'job_request.employment_type' => 'employment type',
        'job_request.expected_monthly_salary' => 'expected monthly salary',
        'job_request.minimum_hours_per_month' => 'minimum hours per month',
        'job_request.expected_hourly_salary' => 'expected hourly salary',
        'job_request.submitted_resume_before' => 'submitted resume before',
        'job_request.interviewed_before' => 'interviewed before',
        'job_request.other_information' => 'other information',
        'job_request.accept_information' => 'information acceptance',
        'job_request.preferred_workplace' => 'preferred workplace',
        'job_request.job_priority_1' => 'job priority 1',
        'job_request.job_priority_2' => 'job priority 2',
        'job_request.currently_employed' => 'currently employed',
        'job_request.available_start_date' => 'available start date',
    ],

];
