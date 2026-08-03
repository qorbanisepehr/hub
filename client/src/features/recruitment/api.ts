import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import type { GrantPurpose } from "@/lib/grant";
import type {
    Questionnaire,
    InitQuestionnaireResponse,
    SaveQuestionnaireResponse,
    RequestAccessResponse,
    VerifyAccessResponse,
} from "./types";

const QUESTIONNAIRE_ENTITY = "questionnaire";

export function requestAccess(entity: string, uuid: string) {
    return publicApi.post<RequestAccessResponse>(
        `/${entity}/${uuid}/request-access`,
    );
}

export function checkAccess(entity: string, uuid: string) {
    return publicApi.get(`/${entity}/${uuid}/exists`);
}

export function verifyAccessOtp(
    entity: string,
    uuid: string,
    otp: string,
    purpose: GrantPurpose = "view",
) {
    return publicApi.post<VerifyAccessResponse>(
        `/${entity}/${uuid}/verify-access-otp`,
        { otp, purpose },
    );
}

export function initQuestionnaire(data: {
    first_name: string;
    last_name: string;
    email: string;
    mobile: string;
}) {
    return api.post<InitQuestionnaireResponse>("/questionnaire/init", data);
}

export function verifyInitOtp(uuid: string, otp: string) {
    return api.post<{
        data: Questionnaire;
        message: string;
        access_token?: string;
        expires_in?: number;
    }>(
        "/questionnaire/verify-init-otp",
        { uuid, otp },
    );
}

export function resendInitOtp(uuid: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/questionnaire/pending/${uuid}/send-otp`,
    );
}

export function getQuestionnaire(uuid: string) {
    return publicApi.get<{ data: Questionnaire }>(`/questionnaire/${uuid}`, {
        grant: { entity: QUESTIONNAIRE_ENTITY, uuid, purpose: "view" },
    });
}

export function saveQuestionnaireSection(
    uuid: string,
    section: string,
    data: Record<string, unknown>,
) {
    return publicApi.put<SaveQuestionnaireResponse>(
        `/questionnaire/${uuid}/sections/${section}`,
        data,
        {
            grant: { entity: QUESTIONNAIRE_ENTITY, uuid, purpose: "edit" },
        },
    );
}

export function sendMobileOtp(uuid: string, mobile: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/questionnaire/${uuid}/send-mobile-otp`,
        { mobile },
    );
}

export function sendEmailOtp(uuid: string, email: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/questionnaire/${uuid}/send-email-otp`,
        { email },
    );
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

export function submitQuestionnaire(uuid: string) {
    return publicApi.post<{ data: Questionnaire; message: string }>(
        `/questionnaire/${uuid}/submit`,
        undefined,
        {
            grant: { entity: QUESTIONNAIRE_ENTITY, uuid, purpose: "edit" },
        },
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
