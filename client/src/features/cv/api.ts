import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import type {
    Cv,
    InitCvResponse,
    SaveCvResponse,
    VerifyInitCvResponse,
    VerifyCvResponse,
} from "./types";
import { CV_ENTITY } from "./constants";

export {
    requestAccess,
    checkAccess,
    verifyAccessOtp,
} from "@/features/recruitment/api";
export type {
    RequestAccessResponse,
    VerifyAccessResponse,
} from "@/features/recruitment/types";

export function initCv(data: {
    first_name: string;
    last_name: string;
    email?: string;
    mobile: string;
}) {
    return api.post<InitCvResponse>("/cv/init", data);
}

export function verifyCvInitOtp(uuid: string, otp: string) {
    return api.post<VerifyInitCvResponse>("/cv/verify-init-otp", {
        uuid,
        otp,
    });
}

export function resendCvInitOtp(uuid: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/cv/pending/${uuid}/send-otp`,
    );
}

export function getCv(uuid: string) {
    return publicApi.get<{ data: Cv }>(`/cv/${uuid}`, {
        grant: { entity: CV_ENTITY, uuid, purpose: "view" },
    });
}

export function saveCvSection(
    uuid: string,
    section: string,
    data: Record<string, unknown>,
) {
    return publicApi.put<SaveCvResponse>(`/cv/${uuid}/sections/${section}`, data, {
        grant: { entity: CV_ENTITY, uuid, purpose: "edit" },
    });
}

export function sendCvMobileOtp(uuid: string, mobile?: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/cv/${uuid}/send-mobile-otp`,
        mobile ? { mobile } : {},
    );
}

export function sendCvEmailOtp(uuid: string, email?: string) {
    return api.post<{ message: string; expires_in: number; code_sent?: boolean }>(
        `/cv/${uuid}/send-email-otp`,
        email ? { email } : {},
    );
}

export function verifyCvMobileOtp(uuid: string, otp: string) {
    return api.post<VerifyCvResponse>(`/cv/${uuid}/verify-mobile-otp`, { otp });
}

export function verifyCvEmailOtp(uuid: string, otp: string) {
    return api.post<VerifyCvResponse>(`/cv/${uuid}/verify-email-otp`, { otp });
}

export function submitCv(uuid: string) {
    return publicApi.post<SaveCvResponse>(
        `/cv/${uuid}/submit`,
        undefined,
        { grant: { entity: CV_ENTITY, uuid, purpose: "edit" } },
    );
}

export function fetchCvBank(params?: {
    page?: number;
    per_page?: number;
    sort?: string;
    order?: string;
    filter?: string;
    status?: string;
}) {
    return api.get("/cv/bank", { params });
}

export function getCvBankDetail(id: number | string) {
    return api.get<{ data: Cv }>(`/cv/bank/${id}`);
}

export function approveCv(uuid: string) {
    return api.post<SaveCvResponse>(`/cv/${uuid}/approve`);
}

export function rejectCv(uuid: string, reason: string) {
    return api.post<SaveCvResponse>(`/cv/${uuid}/reject`, { reason });
}

export function createQuestionnaireFromCv(uuid: string) {
    return api.post(`/cv/bank/${uuid}/questionnaire`);
}
