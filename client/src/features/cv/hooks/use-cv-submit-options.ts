import { useFormOptionsWithPlaces } from "@/features/form-options/hooks/use-form-options";
import type { FormOptionsMap } from "@/features/form-options/types";
import type { SubmitOptions } from "@/features/cv/validation";

const REQUIRED_GROUPS = ["gender", "marital_status", "military_status"] as const;

export function buildCvSubmitOptions(
    formOptions?: FormOptionsMap,
    province?: SubmitOptions["personal_info"]["province"],
    city?: SubmitOptions["personal_info"]["birth_place"],
): SubmitOptions | undefined {
    if (!formOptions || province === undefined || city === undefined) return undefined;
    if (REQUIRED_GROUPS.some((group) => !formOptions[group])) return undefined;

    return {
        personal_info: {
            gender: formOptions.gender,
            marital_status: formOptions.marital_status,
            military_status: formOptions.military_status,
            province,
            birth_place: city,
        },
    };
}

export function useCvSubmitOptions() {
    return useFormOptionsWithPlaces(buildCvSubmitOptions);
}
