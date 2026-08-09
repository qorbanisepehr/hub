import type { ReactFormExtendedApi } from "@tanstack/react-form";

import type { GrantPurpose } from "@/lib/grant";

export type { GrantPurpose };

export interface RequestAccessResponse {
    message: string;
    expires_in: number;
    code_sent?: boolean;
}

export interface VerifyAccessResponse {
    access_token: string;
    expires_in: number;
    message: string;
}

export interface Questionnaire {
    id: number;
    uuid: string;
    status: "draft" | "submitted" | "reviewed";
    version: number;
    first_name: string;
    last_name: string;
    email: string;
    mobile: string;
    personal_info: PersonalInfo | null;
    contact_info: ContactInfo | null;
    education: Education | null;
    work_experience: WorkExperience | null;
    skills: Skills | null;
    training: Training | null;
    additional_info: AdditionalInfo | null;
    job_request: JobRequestData | null;
    mobile_verified: boolean;
    email_verified: boolean;
    created_at: string;
    updated_at: string;
}

export interface PersonalInfo {
    gender: string;
    blood_group: string;
    birth_date: string;
    birth_place: string;
    birth_certificate_number: string;
    father_name: string;
    religion: string;
    marital_status: string;
    first_name_en: string;
    last_name_en: string;
    dependents_count: number | null;
    children_count: number | null;
    spouse_employment_status: string;
    spouse_job: string;
    military_status: {
        status: string;
        organization: string;
        from: string;
        to: string;
        reason: string;
    };
    national_id: string;
}

export interface Address {
    postal_code: string;
    province: string;
    city: string;
    address: string;
    plaque: string;
    floor: string;
    unit: string;
}

export interface ContactInfo {
    phone: string;
    emergency_phone: string;
    address: Address;
}

export interface EducationRecord {
    degree: string;
    field: string;
    institution: string;
    location: string;
    from: string;
    to: string;
    thesis_title: string;
    graduation_date: string;
    gpa: string;
}

export interface Education {
    education_records: EducationRecord[];
    is_student: boolean;
    student_degree: string;
    student_field: string;
    student_university: string;
    student_country: string;
    student_city: string;
    student_semester: number | null;
    passed_units: number | null;
    remaining_units: number | null;
    student_gpa: string;
    study_start: string;
    expected_graduation: string;
    thesis_submitted: boolean;
    student_thesis_title: string;
    free_days_per_week: number | null;
    education_description: string;
}

export interface WorkExperienceRecord {
    company: string;
    location: string;
    industry: string;
    position: string;
    from: string;
    to: string;
    contract_type: string;
    phone: string;
    manager_name: string;
    last_salary: number | null;
    leave_reason: string;
}

export interface WorkExperience {
    work_experiences: WorkExperienceRecord[];
    achievements: string;
    allow_contact_previous_managers: boolean;
    contact_restriction_description: string;
}

export interface LanguageSkill {
    language: string;
    reading: number | null;
    writing: number | null;
    speaking: number | null;
    comprehension: number | null;
}

export interface SoftwareSkill {
    name: string;
    level: number | null;
}

export interface Certificate {
    title: string;
    expire_at: string;
}

export interface Skills {
    languages: LanguageSkill[];
    software_skills: {
        specialized: SoftwareSkill[];
        general: SoftwareSkill[];
    };
    certificates: Certificate[];
    special_skills: string[];
}

export interface TrainingCourse {
    course_name: string;
    duration: string;
    institution: string;
    held_at: string;
    certificate: string;
}

export interface Research {
    title: string;
}

export interface Training {
    training_courses: TrainingCourse[];
    professional_memberships: string;
    researches: Research[];
}

export interface Reference {
    full_name: string;
    relationship: string;
    workplace_phone: string;
}

export interface AdditionalInfo {
    has_chronic_disease: boolean;
    chronic_disease_description: string;
    company_introduction_method: string;
    has_major_surgery: boolean;
    major_surgery_description: string;
    reason_for_joining: string;
    has_disability: boolean;
    disability_description: string;
    physical_condition: string;
    disability_type: string;
    can_travel: boolean;
    travel_description: string;
    has_criminal_record: boolean;
    criminal_record_description: string;
    hobbies: string;
    references: Reference[];
    strengths_and_improvements: string;
}

export interface JobRequestData {
    employment_type: string;
    expected_monthly_salary: number | null;
    minimum_hours_per_month: number | null;
    expected_hourly_salary: number | null;
    submitted_resume_before: boolean;
    interviewed_before: boolean;
    other_information: string;
    accept_information: boolean;
    preferred_workplace: string[];
    job_priority_1: string;
    job_priority_2: string;
    currently_employed: boolean;
    available_start_date: string;
}

export interface ReviewData {
    review_department: string;
    reviewer_name: string;
    reviewer_title: string;
    review_date: string;
    initial_review_result: string;
}

export interface InitQuestionnaireResponse {
    data: {
        uuid: string;
    };
    message: string;
    requires_otp: true;
    code_sent: boolean;
    expires_in: number;
}

export interface SaveQuestionnaireResponse {
    data: Questionnaire;
    message: string;
}

export interface VerifyQuestionnaireResponse {
    data: Questionnaire;
    message: string;
}

export type QuestionnaireFormApi = ReactFormExtendedApi<
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any
>;
