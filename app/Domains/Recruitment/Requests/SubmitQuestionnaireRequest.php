<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|callable|int|array>> */
    public function rules(): array
    {
        return [
            // ── Top-level fields ──
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:15'],

            // ── Personal Info ──
            'personal_info' => ['required', 'array'],
            'personal_info.gender' => ['required', 'string', 'in:male,female'],
            'personal_info.blood_group' => ['required', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'personal_info.birth_date' => ['required', 'string', 'max:255'],
            'personal_info.birth_place' => ['required', 'string', 'max:100'],
            'personal_info.birth_certificate_number' => ['required', 'string', 'max:20'],
            'personal_info.father_name' => ['required', 'string', 'max:100'],
            'personal_info.religion' => ['required', 'string', 'max:50'],
            'personal_info.marital_status' => ['required', 'string', 'in:single,married'],
            'personal_info.first_name_en' => ['sometimes', 'string', 'max:100'],
            'personal_info.last_name_en' => ['sometimes', 'string', 'max:100'],
            'personal_info.dependents_count' => ['nullable', 'integer', 'min:0'],
            'personal_info.children_count' => ['nullable', 'integer', 'min:0'],
            'personal_info.spouse_employment_status' => ['required_if:personal_info.marital_status,married', 'string', 'in:employed,housewife'],
            'personal_info.military_status' => ['required_if:personal_info.gender,male', 'array'],
            'personal_info.military_status.status' => ['required_with:personal_info.military_status', 'string', 'in:completed,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon,service_purchase,other'],
            'personal_info.military_status.organization' => ['required_with:personal_info.military_status', 'string', 'max:100'],
            'personal_info.military_status.from' => ['required_with:personal_info.military_status', 'string', 'max:255'],
            'personal_info.military_status.to' => ['required_with:personal_info.military_status', 'string', 'max:255'],
            'personal_info.military_status.reason' => ['required_with:personal_info.military_status', 'string', 'max:255'],
            'personal_info.national_id' => ['required', 'string', 'size:10'],

            // ── Contact Info ──
            'contact_info' => ['required', 'array'],
            'contact_info.phone' => ['required', 'string', 'max:15'],
            'contact_info.emergency_phone' => ['required', 'string', 'max:15'],
            'contact_info.address' => ['required', 'array'],
            'contact_info.address.postal_code' => ['required', 'string', 'max:10'],
            'contact_info.address.province' => ['required', 'string', 'max:50'],
            'contact_info.address.city' => ['required', 'string', 'max:50'],
            'contact_info.address.address' => ['required', 'string', 'max:500'],
            'contact_info.address.plaque' => ['sometimes', 'nullable', 'string', 'max:10'],
            'contact_info.address.floor' => ['sometimes', 'nullable', 'string', 'max:10'],
            'contact_info.address.unit' => ['sometimes', 'nullable', 'string', 'max:10'],

            // ── Education ──
            'education' => ['required', 'array'],
            'education.education_records' => ['required', 'array', 'min:1'],
            'education.education_records.*.degree' => ['required', 'string', 'max:50'],
            'education.education_records.*.field' => ['required', 'string', 'max:100'],
            'education.education_records.*.institution' => ['required', 'string', 'max:100'],
            'education.education_records.*.location' => ['sometimes', 'string', 'max:100'],
            'education.education_records.*.from' => ['required', 'string', 'max:255'],
            'education.education_records.*.to' => ['required', 'string', 'max:255'],
            'education.education_records.*.thesis_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education.education_records.*.graduation_date' => ['required', 'string', 'max:255'],
            'education.education_records.*.gpa' => ['required', 'string', 'max:10'],
            'education.is_student' => ['sometimes', 'boolean'],
            'education.student_degree' => ['required_if:education.is_student,true', 'string', 'max:50'],
            'education.student_field' => ['required_if:education.is_student,true', 'string', 'max:100'],
            'education.student_university' => ['required_if:education.is_student,true', 'string', 'max:100'],
            'education.student_country' => ['required_if:education.is_student,true', 'string', 'max:100'],
            'education.student_city' => ['required_if:education.is_student,true', 'string', 'max:100'],
            'education.student_semester' => ['nullable', 'integer', 'min:1'],
            'education.passed_units' => ['nullable', 'integer', 'min:0'],
            'education.remaining_units' => ['nullable', 'integer', 'min:0'],
            'education.student_gpa' => ['required_if:education.is_student,true', 'string', 'max:10'],
            'education.study_start' => ['required_if:education.is_student,true', 'string', 'max:255'],
            'education.expected_graduation' => ['required_if:education.is_student,true', 'string', 'max:255'],
            'education.thesis_submitted' => ['sometimes', 'boolean'],
            'education.student_thesis_title' => ['required_if:education.thesis_submitted,true', 'string', 'max:255'],
            'education.free_days_per_week' => ['nullable', 'integer', 'min:0', 'max:7'],
            'education.education_description' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // ── Work Experience ──
            'work_experience' => ['required', 'array'],
            'work_experience.work_experiences' => ['sometimes', 'array'],
            'work_experience.work_experiences.*.company' => ['required', 'string', 'max:100'],
            'work_experience.work_experiences.*.location' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.industry' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.position' => ['required', 'string', 'max:100'],
            'work_experience.work_experiences.*.from' => ['required', 'string', 'max:255'],
            'work_experience.work_experiences.*.to' => ['required', 'string', 'max:255'],
            'work_experience.work_experiences.*.contract_type' => ['sometimes', 'string', 'max:50'],
            'work_experience.work_experiences.*.phone' => ['sometimes', 'string', 'max:15'],
            'work_experience.work_experiences.*.manager_name' => ['sometimes', 'string', 'max:100'],
            'work_experience.work_experiences.*.last_salary' => ['nullable', 'integer', 'min:0'],
            'work_experience.work_experiences.*.leave_reason' => ['sometimes', 'string', 'max:255'],
            'work_experience.achievements' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'work_experience.allow_contact_previous_managers' => ['sometimes', 'boolean'],
            'work_experience.contact_restriction_description' => ['sometimes', 'nullable', 'string', 'max:500'],

            // ── Skills ──
            'skills' => ['required', 'array'],
            'skills.languages' => ['sometimes', 'array'],
            'skills.languages.*.language' => ['required', 'string', 'max:50'],
            'skills.languages.*.reading' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.writing' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.speaking' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.languages.*.comprehension' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.software_skills' => ['sometimes', 'array'],
            'skills.software_skills.specialized' => ['sometimes', 'array'],
            'skills.software_skills.specialized.*.name' => ['required', 'string', 'max:100'],
            'skills.software_skills.specialized.*.level' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.software_skills.general' => ['sometimes', 'array'],
            'skills.software_skills.general.*.name' => ['required', 'string', 'max:100'],
            'skills.software_skills.general.*.level' => ['nullable', 'integer', 'min:1', 'max:4'],
            'skills.certificates' => ['sometimes', 'array'],
            'skills.certificates.*.title' => ['required', 'string', 'max:100'],
            'skills.certificates.*.expire_at' => ['nullable', 'string', 'max:255'],
            'skills.special_skills' => ['sometimes', 'array'],
            'skills.special_skills.*' => ['sometimes', 'string', 'max:100'],

            // ── Training ──
            'training' => ['required', 'array'],
            'training.training_courses' => ['sometimes', 'array'],
            'training.training_courses.*.course_name' => ['required', 'string', 'max:100'],
            'training.training_courses.*.duration' => ['sometimes', 'string', 'max:50'],
            'training.training_courses.*.institution' => ['sometimes', 'string', 'max:100'],
            'training.training_courses.*.held_at' => ['sometimes', 'string', 'max:255'],
            'training.training_courses.*.certificate' => ['sometimes', 'nullable', 'string', 'max:100'],
            'training.professional_memberships' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'training.researches' => ['sometimes', 'array'],
            'training.researches.*.title' => ['required', 'string', 'max:255'],

            // ── Additional Info ──
            'additional_info' => ['required', 'array'],
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
            'additional_info.references.*.full_name' => ['required', 'string', 'max:100'],
            'additional_info.references.*.relationship' => ['required', 'string', 'max:50'],
            'additional_info.references.*.workplace_phone' => ['required', 'string', 'max:15'],
            'additional_info.strengths_and_improvements' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // ── Job Request ──
            'job_request' => ['required', 'array'],
            'job_request.employment_type' => ['required', 'string', 'in:full_time,part_time'],
            'job_request.expected_monthly_salary' => ['nullable', 'integer', 'min:0'],
            'job_request.minimum_hours_per_month' => ['nullable', 'integer', 'min:0'],
            'job_request.expected_hourly_salary' => ['nullable', 'integer', 'min:0'],
            'job_request.submitted_resume_before' => ['sometimes', 'boolean'],
            'job_request.interviewed_before' => ['sometimes', 'boolean'],
            'job_request.other_information' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'job_request.accept_information' => ['required', 'accepted'],
            'job_request.preferred_workplace' => ['sometimes', 'array'],
            'job_request.preferred_workplace.*' => ['sometimes', 'string', 'in:tehran,kerman,site,other'],
            'job_request.job_priority_1' => ['required', 'string', 'max:100'],
            'job_request.job_priority_2' => ['sometimes', 'string', 'max:100'],
            'job_request.currently_employed' => ['sometimes', 'boolean'],
            'job_request.available_start_date' => ['required', 'string', 'max:255'],
        ];
    }
}
