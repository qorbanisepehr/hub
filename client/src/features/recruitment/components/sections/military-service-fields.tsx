import { useMemo } from "react";
import { useStore } from "@tanstack/react-form";
import { FormTextField, FormDatePicker } from "@/components/shared/form-fields";
import { FormOptionSelectField } from "@/components/shared/form-option-fields";
import { optionEnum } from "@/features/form-options/schema";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import {
    MILITARY_STATUS_OTHER,
    MILITARY_STATUS_REQUIRES_START_DATE,
} from "@/features/recruitment/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { requiredText, text } from "@/lib/zod-primitives";
import type { QuestionnaireFormApi } from "@/features/recruitment/types";

type MilitaryServiceFieldsProps = {
    form: QuestionnaireFormApi;
    /** Dot-notation field path prefix; defaults to the recruitment personal-info path */
    basePath?: string;
    /**
     * `"full"` (default): status plus organization/from/to/reason for the
     * questionnaire. `"simple"`: only the status, used by the CV.
     */
    mode?: "full" | "simple";
};

export function MilitaryServiceFields({
    form,
    basePath = "personal_info.military_status",
    mode = "full",
}: MilitaryServiceFieldsProps) {
    const { data: militaryOptions } = useFormOptionsByGroup("military_status");

    const status = useStore(form.store, (s) => {
        const keys = `${basePath}.status`.split(".");
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        let current: any = s.values;
        for (const key of keys) {
            current = current?.[key];
        }
        return current as string | undefined;
    });

    const requiresStartDate =
        status !== undefined && MILITARY_STATUS_REQUIRES_START_DATE.has(status);
    const reasonRequired = status === MILITARY_STATUS_OTHER;

    const statusSchema = useMemo(
        () => optionEnum(militaryOptions ?? [], "وضعیت خدمت الزامی است."),
        [militaryOptions],
    );
    const organizationSchema = requiredText("سازمان الزامی است.", 100);
    const fromSchema = useMemo(
        () =>
            requiresStartDate
                ? requiredText("تاریخ شروع الزامی است.", 30)
                : text(30),
        [requiresStartDate],
    );
    const toSchema = requiredText("تاریخ پایان الزامی است.", 30);
    const reasonSchema = useMemo(
        () =>
            reasonRequired
                ? requiredText("توضیحات الزامی است.", 255)
                : text(255),
        [reasonRequired],
    );

    if (mode === "simple") {
        return (
            <div className="rounded-lg border p-4 space-y-4">
                {/* <span className="text-sm font-medium">وضعیت نظام وظیفه</span> */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name={`${basePath}.status`}
                        validators={
                            militaryOptions
                                ? zodFieldValidators(statusSchema)
                                : undefined
                        }
                    >
                        {(f) => (
                            <FormOptionSelectField
                                field={f}
                                label="وضعیت نظام وظیفه"
                                group="military_status"
                            />
                        )}
                    </form.Field>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-lg border p-4 space-y-4">
            <span className="text-sm font-medium">وضعیت نظام وظیفه</span>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field
                    name={`${basePath}.status`}
                    validators={
                        militaryOptions
                            ? zodFieldValidators(statusSchema)
                            : undefined
                    }
                >
                    {(f) => (
                        <FormOptionSelectField
                            field={f}
                            label="وضعیت"
                            group="military_status"
                        />
                    )}
                </form.Field>
                <form.Field
                    name={`${basePath}.organization`}
                    validators={zodFieldValidators(organizationSchema)}
                >
                    {(f) => <FormTextField field={f} label="سازمان" />}
                </form.Field>
                <form.Field
                    name={`${basePath}.from`}
                    validators={zodFieldValidators(fromSchema)}
                >
                    {(f) => <FormDatePicker field={f} label="تاریخ شروع" />}
                </form.Field>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field
                    name={`${basePath}.to`}
                    validators={zodFieldValidators(toSchema)}
                >
                    {(f) => <FormDatePicker field={f} label="تاریخ پایان" />}
                </form.Field>
                <form.Field
                    name={`${basePath}.reason`}
                    validators={zodFieldValidators(reasonSchema)}
                >
                    {(f) => <FormTextField field={f} label="توضیحات" />}
                </form.Field>
            </div>
        </div>
    );
}
