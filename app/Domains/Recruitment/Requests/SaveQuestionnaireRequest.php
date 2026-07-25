<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|callable|int|array>> */
    public function rules(): array
    {
        return [
            // ── Top-level fields (step 0) ──
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:15'],

            // ── Personal Info (step 0) ──
            'personal_info' => ['sometimes', 'nullable', 'array'],
            'personal_info.gender' => ['sometimes', 'string', 'in:male,female'],
            'personal_info.blood_group' => ['sometimes', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'personal_info.birth_date' => ['sometimes', 'string', 'max:255'],
            'personal_info.birth_place' => ['sometimes', 'string', 'max:100'],
            'personal_info.birth_certificate_number' => ['sometimes', 'string', 'max:20'],
            'personal_info.father_name' => ['sometimes', 'string', 'max:100'],
            'personal_info.religion' => ['sometimes', 'string', 'max:50'],
            'personal_info.marital_status' => ['sometimes', 'string', 'in:single,married'],
            'personal_info.first_name_en' => ['sometimes', 'string', 'max:100'],
            'personal_info.last_name_en' => ['sometimes', 'string', 'max:100'],
            'personal_info.dependents_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'personal_info.children_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'personal_info.spouse_employment_status' => ['sometimes', 'string', 'in:employed,housewife'],
            'personal_info.military_status' => ['sometimes', 'array'],
            'personal_info.military_status.status' => ['sometimes', 'string', 'in:completed,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon,service_purchase,other'],
            'personal_info.military_status.organization' => ['sometimes', 'string', 'max:100'],
            'personal_info.military_status.from' => ['sometimes', 'string', 'max:255'],
            'personal_info.military_status.to' => ['sometimes', 'string', 'max:255'],
            'personal_info.military_status.reason' => ['sometimes', 'string', 'max:255'],
            'personal_info.photo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'personal_info.national_id' => ['sometimes', 'string', 'max:10'],
            'personal_info.address' => ['sometimes', 'string', 'max:500'],
            'personal_info.phone' => ['sometimes', 'string', 'max:15'],
            'personal_info.emergency_phone' => ['sometimes', 'string', 'max:15'],

            // ── Education (step 1) ──
            'education' => ['sometimes', 'nullable', 'array'],
            'education.education_records' => ['sometimes', 'array'],
            'education.education_records.*.degree' => ['sometimes', 'string', 'max:50'],
            'education.education_records.*.field' => ['sometimes', 'string', 'max:100'],
            'education.education_records.*.institution' => ['sometimes', 'string', 'max:100'],
            'education.education_records.*.location' => ['sometimes', 'string', 'max:100'],
            'education.education_records.*.from' => ['sometimes', 'string', 'max:255'],
            'education.education_records.*.to' => ['sometimes', 'string', 'max:255'],
            'education.education_records.*.thesis_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education.education_records.*.graduation_date' => ['sometimes', 'string', 'max:255'],
            'education.education_records.*.gpa' => ['sometimes', 'string', 'max:10'],
            'education.is_student' => ['sometimes', 'boolean'],
            'education.student_degree' => ['sometimes', 'string', 'max:50'],
            'education.student_field' => ['sometimes', 'string', 'max:100'],
            'education.student_university' => ['sometimes', 'string', 'max:100'],
            'education.student_country' => ['sometimes', 'string', 'max:100'],
            'education.student_city' => ['sometimes', 'string', 'max:100'],
            'education.student_semester' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'education.passed_units' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'education.remaining_units' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'education.student_gpa' => ['sometimes', 'string', 'max:10'],
            'education.study_start' => ['sometimes', 'string', 'max:255'],
            'education.expected_graduation' => ['sometimes', 'string', 'max:255'],
            'education.thesis_submitted' => ['sometimes', 'boolean'],
            'education.student_thesis_title' => ['sometimes', 'string', 'max:255'],
            'education.free_days_per_week' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:7'],
            'education.education_description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // ── Work Experience (step 2) ──
            'work_experience' => ['sometimes', 'nullable', 'array'],
            'work_experience.work_experiences' => ['sometimes', 'array'],
            'work_experience.work_experiences.*.company' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.location' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.industry' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.position' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.from' => ['sometimes', 'string', 'max:255'],
            'work_experience.work_experiences.*.to' => ['sometimes', 'string', 'max:255'],
            'work_experience.work_experiences.*.contract_type' => ['sometimes', 'string', 'max:50'],
            'work_experience.work_experiences.*.phone' => ['sometimes', 'string', 'max:15'],
            'work_experience.work_experiences.*.manager_name' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.last_salary' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'work_experience.work_experiences.*.leave_reason' => ['sometimes', 'string', 'max:255'],
            'work_experience.achievements' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'work_experience.allow_contact_previous_managers' => ['sometimes', 'boolean'],
            'work_experience.contact_restriction_description' => ['sometimes', 'nullable', 'string', 'max:500'],

            // ── Skills (step 3) ──
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.languages' => ['sometimes', 'array'],
            'skills.languages.*.language' => ['sometimes', 'string', 'max:50'],
            'skills.languages.*.reading' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.writing' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.speaking' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.comprehension' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.software_skills' => ['sometimes', 'array'],
            'skills.software_skills.specialized' => ['sometimes', 'array'],
            'skills.software_skills.specialized.*.name' => ['sometimes', 'string', 'max:100'],
            'skills.software_skills.specialized.*.level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.software_skills.general' => ['sometimes', 'array'],
            'skills.software_skills.general.*.name' => ['sometimes', 'string', 'max:100'],
            'skills.software_skills.general.*.level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'skills.certificates' => ['sometimes', 'array'],
            'skills.certificates.*.title' => ['sometimes', 'string', 'max:100'],
            'skills.certificates.*.expire_at' => ['sometimes', 'nullable', 'string', 'max:255'],
            'skills.special_skills' => ['sometimes', 'array'],
            'skills.special_skills.*' => ['sometimes', 'string', 'max:100'],

            // ── Training (step 4) ──
            'training' => ['sometimes', 'nullable', 'array'],
            'training.training_courses' => ['sometimes', 'array'],
            'training.training_courses.*.course_name' => ['sometimes', 'string', 'max:100'],
            'training.training_courses.*.duration' => ['sometimes', 'string', 'max:50'],
            'training.training_courses.*.institution' => ['sometimes', 'string', 'max:100'],
            'training.training_courses.*.held_at' => ['sometimes', 'string', 'max:255'],
            'training.training_courses.*.certificate' => ['sometimes', 'nullable', 'string', 'max:100'],
            'training.professional_memberships' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'training.researches' => ['sometimes', 'array'],
            'training.researches.*.title' => ['sometimes', 'string', 'max:255'],

            // ── Additional Info (step 5) ──
            'additional_info' => ['sometimes', 'nullable', 'array'],
            'additional_info.has_chronic_disease' => ['sometimes', 'boolean'],
            'additional_info.chronic_disease_description' => Rule::when(
                fn () => $this->boolean('additional_info.has_chronic_disease'),
                ['required', 'string', 'max:500'],
                ['sometimes', 'nullable', 'string', 'max:500']
            ),
            'additional_info.company_introduction_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'additional_info.has_major_surgery' => ['sometimes', 'boolean'],
            'additional_info.major_surgery_description' => Rule::when(
                fn () => $this->boolean('additional_info.has_major_surgery'),
                ['required', 'string', 'max:500'],
                ['sometimes', 'nullable', 'string', 'max:500']
            ),
            'additional_info.reason_for_joining' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'additional_info.has_disability' => ['sometimes', 'boolean'],
            'additional_info.disability_description' => Rule::when(
                fn () => $this->boolean('additional_info.has_disability'),
                ['required', 'string', 'max:500'],
                ['sometimes', 'nullable', 'string', 'max:500']
            ),
            'additional_info.can_travel' => ['sometimes', 'boolean'],
            'additional_info.travel_description' => Rule::when(
                fn () => $this->boolean('additional_info.can_travel'),
                ['required', 'string', 'max:500'],
                ['sometimes', 'nullable', 'string', 'max:500']
            ),
            'additional_info.has_criminal_record' => ['sometimes', 'boolean'],
            'additional_info.criminal_record_description' => Rule::when(
                fn () => $this->boolean('additional_info.has_criminal_record'),
                ['required', 'string', 'max:500'],
                ['sometimes', 'nullable', 'string', 'max:500']
            ),
            'additional_info.hobbies' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'additional_info.references' => ['sometimes', 'array'],
            'additional_info.references.*.full_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'additional_info.references.*.relationship' => ['sometimes', 'nullable', 'string', 'max:50'],
            'additional_info.references.*.workplace_phone' => ['sometimes', 'nullable', 'string', 'max:15'],
            'additional_info.strengths_and_improvements' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // ── Job Request (step 6) ──
            'job_request' => ['sometimes', 'nullable', 'array'],
            'job_request.employment_type' => ['sometimes', 'string', 'in:full_time,part_time'],
            'job_request.expected_monthly_salary' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job_request.minimum_hours_per_month' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job_request.expected_hourly_salary' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job_request.submitted_resume_before' => ['sometimes', 'boolean'],
            'job_request.interviewed_before' => ['sometimes', 'boolean'],
            'job_request.other_information' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'job_request.accept_information' => ['sometimes', 'boolean'],
            'job_request.preferred_workplace' => ['sometimes', 'array'],
            'job_request.preferred_workplace.*' => ['sometimes', 'string', 'in:tehran,kerman,site,other'],
            'job_request.job_priority_1' => ['sometimes', 'string', 'max:100'],
            'job_request.job_priority_2' => ['sometimes', 'string', 'max:100'],
            'job_request.currently_employed' => ['sometimes', 'boolean'],
            'job_request.available_start_date' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
