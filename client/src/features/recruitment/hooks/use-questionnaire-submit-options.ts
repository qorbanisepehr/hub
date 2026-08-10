import { useFormOptionsWithPlaces } from "@/features/form-options/hooks/use-form-options";
import type { FormOptionsMap } from "@/features/form-options/types";
import type { SubmitOptions } from "@/features/recruitment/validation";

const REQUIRED_GROUPS = [
    "gender",
    "blood_group",
    "marital_status",
    "spouse_employment_status",
    "military_status",
    "religion",
    "religion_sect",
    "employment_type",
    "preferred_workplace",
] as const;

export function buildQuestionnaireSubmitOptions(
    formOptions?: FormOptionsMap,
    province?: SubmitOptions["personal_info"]["province"],
    city?: SubmitOptions["personal_info"]["birth_place"],
): SubmitOptions | undefined {
    if (!formOptions || province === undefined || city === undefined) return undefined;
    if (REQUIRED_GROUPS.some((group) => !formOptions[group])) return undefined;

    return {
        personal_info: {
            gender: formOptions.gender,
            blood_group: formOptions.blood_group,
            marital_status: formOptions.marital_status,
            spouse_employment_status: formOptions.spouse_employment_status,
            military_status: formOptions.military_status,
            religion: formOptions.religion,
            religion_sect: formOptions.religion_sect,
            province,
            birth_place: city,
        },
        job_request: {
            employment_type: formOptions.employment_type,
            preferred_workplace: formOptions.preferred_workplace,
        },
    };
}

export function useQuestionnaireSubmitOptions() {
    return useFormOptionsWithPlaces(buildQuestionnaireSubmitOptions);
}
