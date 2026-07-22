import { api } from "@/lib/api";
import type {
    Questionnaire,
    InitQuestionnaireResponse,
    SaveQuestionnaireResponse,
} from "./types";

export function initQuestionnaire(data: {
    first_name: string;
    last_name: string;
    email: string;
    mobile: string;
}) {
    return api.post<InitQuestionnaireResponse>("/questionnaire/init", data);
}

export function getQuestionnaire(uuid: string) {
    return api.get<{ data: Questionnaire }>(`/questionnaire/${uuid}`);
}

export function saveQuestionnaire(
    uuid: string,
    data: {
        email?: string;
        mobile?: string;
        personal_info?: Questionnaire["personal_info"];
        education?: Questionnaire["education"];
        work_experience?: Questionnaire["work_experience"];
        skills?: Questionnaire["skills"];
        training?: Questionnaire["training"];
        additional_info?: Questionnaire["additional_info"];
        job_request?: Questionnaire["job_request"];
    }
) {
    return api.put<SaveQuestionnaireResponse>(`/questionnaire/${uuid}`, data);
}

export function sendMobileOtp(uuid: string) {
    return api.post<{ message: string }>(`/questionnaire/${uuid}/send-mobile-otp`);
}

export function sendEmailOtp(uuid: string) {
    return api.post<{ message: string }>(`/questionnaire/${uuid}/send-email-otp`);
}

export function verifyMobileOtp(uuid: string, otp: string) {
    return api.post<{ data: Questionnaire; message: string }>(
        `/questionnaire/${uuid}/verify-mobile-otp`,
        { otp }
    );
}

export function verifyEmailOtp(uuid: string, otp: string) {
    return api.post<{ data: Questionnaire; message: string }>(
        `/questionnaire/${uuid}/verify-email-otp`,
        { otp }
    );
}

export function submitQuestionnaire(uuid: string, data: Record<string, unknown>) {
    return api.post<{ data: Questionnaire; message: string }>(
        `/questionnaire/${uuid}/submit`,
        data
    );
}

export function fetchQuestionnaires(params?: {
    page?: number;
    per_page?: number;
    sort?: string;
    order?: string;
    filter?: string;
    status?: string;
}) {
    return api.get("/recruitment/questionnaires", { params });
}

export function getQuestionnaireDetail(id: number) {
    return api.get<{ data: Questionnaire }>(
        `/recruitment/questionnaires/${id}`
    );
}
