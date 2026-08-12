import type { ReactFormExtendedApi } from "@tanstack/react-form";

import type { EntityDocument } from "@/hooks/use-entity-documents";
import type {
    Education,
    WorkExperience,
    Skills,
    Training,
    Address,
    RequestAccessResponse,
    VerifyAccessResponse,
} from "@/features/questionnaire/types";

export type { RequestAccessResponse, VerifyAccessResponse };

export interface CvPersonalInfo {
    gender: string;
    birth_date: string;
    marital_status: string;
    military_status: {
        status: string;
        organization: string;
        from: string;
        to: string;
        reason: string;
    };
    id_number: string;
    birth_place: string;
    birth_certificate_number: string;
}

export interface CvContactInfo {
    phone: string;
    emergency_phone: string;
    address: Address;
}

export interface Reference {
    full_name: string;
    relationship: string;
    workplace_phone: string;
}

export interface CvAdditionalInfo {
    hobbies: string;
    references: Reference[];
    strengths_and_improvements: string;
    physical_condition: string;
    disability_type: string;
}

export interface ReviewerSummary {
    id: number;
    name: string;
    role: string | null;
}

export interface CvLifecycleEvent {
    event: "submitted" | "approved" | "rejected";
    version: number;
    at: string;
    by?: number | null;
    by_user?: ReviewerSummary | null;
    reason?: string | null;
    snapshot?: Record<string, unknown>;
}

export type CvStatus = "draft" | "submitted" | "approved" | "rejected";

export interface Cv {
    id: number;
    uuid: string;
    status: CvStatus;
    version: number;
    first_name: string;
    last_name: string;
    email: string | null;
    mobile: string;
    personal_info: CvPersonalInfo | null;
    contact_info: CvContactInfo | null;
    education: Education | null;
    work_experience: WorkExperience | null;
    skills: Skills | null;
    training: Training | null;
    additional_info: CvAdditionalInfo | null;
    mobile_verified: boolean;
    email_verified: boolean;
    documents?: EntityDocument[];
    resume_document?: EntityDocument | null;
    questionnaire?: { uuid: string; status: string } | null;
    reviewer: ReviewerSummary | null;
    lifecycle: CvLifecycleEvent[] | null;
    created_at: string;
    updated_at: string;
}

export interface InitCvResponse {
    data: {
        uuid: string;
    };
    message: string;
    requires_otp: true;
    code_sent: boolean;
    expires_in: number;
}

export interface VerifyInitCvResponse {
    data: Cv;
    message: string;
    access_token?: string;
    expires_in?: number;
}

export interface SaveCvResponse {
    data: Cv;
    message: string;
}

export interface VerifyCvResponse {
    data: Cv;
    message: string;
}

export type CvFormApi = ReactFormExtendedApi<
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
