import {
    FormTextField,
    FormSelectField,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { MILITARY_STATUS_OPTIONS } from "@/features/recruitment/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/personal-info.schema";
import type { QuestionnaireFormApi } from "@/features/recruitment/types";

type MilitaryServiceFieldsProps = {
    form: QuestionnaireFormApi;
    /** Dot-notation field path prefix; defaults to the recruitment personal-info path */
    basePath?: string;
};

export function MilitaryServiceFields({
    form,
    basePath = "personal_info.military_status",
}: MilitaryServiceFieldsProps) {
    const shape = fieldSchemas.military_status.shape;

    return (
        <div className="rounded-lg border p-4 space-y-4">
            <span className="text-sm font-medium">وضعیت نظام وظیفه</span>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field
                    name={`${basePath}.status`}
                    validators={zodFieldValidators(shape.status)}
                >
                    {(f) => (
                        <FormSelectField
                            field={f}
                            label="وضعیت"
                            options={MILITARY_STATUS_OPTIONS}
                        />
                    )}
                </form.Field>
                <form.Field
                    name={`${basePath}.organization`}
                    validators={zodFieldValidators(shape.organization)}
                >
                    {(f) => <FormTextField field={f} label="سازمان" />}
                </form.Field>
                <form.Field
                    name={`${basePath}.from`}
                    validators={zodFieldValidators(shape.from)}
                >
                    {(f) => <FormDatePicker field={f} label="تاریخ شروع" />}
                </form.Field>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field
                    name={`${basePath}.to`}
                    validators={zodFieldValidators(shape.to)}
                >
                    {(f) => <FormDatePicker field={f} label="تاریخ پایان" />}
                </form.Field>
                <form.Field
                    name={`${basePath}.reason`}
                    validators={zodFieldValidators(shape.reason)}
                >
                    {(f) => <FormTextField field={f} label="دلیل" />}
                </form.Field>
            </div>
        </div>
    );
}
