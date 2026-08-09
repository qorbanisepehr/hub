import { useMemo } from "react";

import { useFormOptions, useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import type { FormOptionsMap } from "@/features/form-options/types";
import type { SubmitOptions } from "@/features/cv/validation";

const REQUIRED_GROUPS = ["gender", "marital_status", "military_status"] as const;

export function buildCvSubmitOptions(
    formOptions?: FormOptionsMap,
    cityOptions?: SubmitOptions["personal_info"]["birth_place"],
    provinceOptions?: SubmitOptions["personal_info"]["province"],
): SubmitOptions | undefined {
    if (!formOptions || cityOptions === undefined || provinceOptions === undefined) return undefined;
    if (REQUIRED_GROUPS.some((group) => !formOptions[group])) return undefined;

    return {
        personal_info: {
            gender: formOptions.gender,
            marital_status: formOptions.marital_status,
            military_status: formOptions.military_status,
            province: provinceOptions,
            birth_place: cityOptions,
        },
    };
}

export function useCvSubmitOptions() {
    const { data: formOptions } = useFormOptions();
    const { data: cityOptions } = useFormOptionsByGroup("city");
    const { data: provinceOptions } = useFormOptionsByGroup("province");

    const submitOptions = useMemo(
        () => buildCvSubmitOptions(formOptions, cityOptions, provinceOptions),
        [formOptions, cityOptions, provinceOptions],
    );

    return { submitOptions, optionsReady: submitOptions !== undefined };
}
