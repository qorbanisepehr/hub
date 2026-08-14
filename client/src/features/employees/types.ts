export type EmployeeCapabilities = {
    view: boolean;
    edit: boolean;
    delete: boolean;
    documents_view: boolean;
    documents_upload: boolean;
    documents_delete: boolean;
};

export type Employee = {
    id: number;
    personnel_code: string;
    first_name: string;
    last_name: string;
    gender: string | null;
    birth_date: string | null;
    id_number: string | null;
    marital_status: string | null;
    email: string | null;
    mobile: string | null;
    employment_type: "official" | "contractual" | "project-based" | null;
    hire_date: string | null;
    employment_status: "active" | "inactive" | "suspended" | null;
    section_personal: Record<string, unknown> | null;
    section_contact_address: Record<string, unknown> | null;
    section_education: Record<string, unknown> | null;
    section_work_experience: Record<string, unknown> | null;
    section_skills: Record<string, unknown> | null;
    section_training: Record<string, unknown> | null;
    section_additional_info: Record<string, unknown> | null;
    social_insurance_number: string | null;
    section_social_insurance: Record<string, unknown> | null;
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        username: string | null;
        active_role: { id: number; display_name: string } | null;
    } | null;
    capabilities: EmployeeCapabilities;
    created_at: string;
    updated_at: string;
};

import type { ReactFormExtendedApi } from "@tanstack/react-form";

/**
 * Form values for the tabbed profile form. The section keys mirror the server
 * section definitions; real-column identity/contact fields are held at the top
 * level (and merged into the section payload on save), exactly like the CV.
 */
export type EmployeeProfileFormData = {
    first_name?: string;
    last_name?: string;
    email?: string;
    mobile?: string;
    personal_info?: Record<string, unknown>;
    contact_info?: Record<string, unknown>;
    employment?: Record<string, unknown>;
    education?: Record<string, unknown>;
    work_experience?: Record<string, unknown>;
    skills?: Record<string, unknown>;
    training?: Record<string, unknown>;
    additional_info?: Record<string, unknown>;
    social_insurance?: Record<string, unknown>;
};

export type EmployeeFormApi = ReactFormExtendedApi<
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

/**
 * Base fields collected at creation time. Everything else lives in the section
 * tabs of the profile form and is saved per-section via `saveEmployeeSection`.
 */
export type EmployeeBaseFormData = {
    personnel_code: string;
    first_name: string;
    last_name: string;
    employment_type: "official" | "contractual" | "project-based" | "";
    hire_date: string;
    employment_status: "active" | "inactive" | "suspended" | "";
    user_id?: number | null;
};
