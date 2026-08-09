import { useEffect } from "react";
import { useStore, type ReactFormExtendedApi } from "@tanstack/react-form";

import { FormOptionSelectField } from "./form-option-fields";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { optionEnumOptional } from "@/features/form-options/schema";
import { DISABLED_PHYSICAL_CONDITIONS } from "@/features/recruitment/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";

export type PhysicalConditionFieldsForm = ReactFormExtendedApi<
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

type PhysicalConditionFieldsProps = {
    form: PhysicalConditionFieldsForm;
    conditionField: string;
    typeField: string;
    conditionLabel?: string;
    typeLabel?: string;
};

/**
 * Physical-condition select bound to the `physical_condition` form-options
 * group, revealing a `disability_type` select only when the chosen condition
 * counts as a disability. Clearing the condition also clears the type so
 * stale values never leak into the payload.
 */
export function PhysicalConditionFields({
    form,
    conditionField,
    typeField,
    conditionLabel = "وضعیت جسمانی",
    typeLabel = "نوع معلولیت",
}: PhysicalConditionFieldsProps) {
    const condition = useStore(form.store, (s) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        let current: any = s.values;
        for (const key of conditionField.split(".")) {
            if (current == null) return undefined;
            current = current[key];
        }
        return current as string | undefined;
    });

    const showDisability =
        condition !== undefined && DISABLED_PHYSICAL_CONDITIONS.has(condition);

    useEffect(() => {
        if (!showDisability) {
            form.setFieldValue(typeField, "");
        }
    }, [showDisability, form, typeField]);

    const { data: conditionOptions } = useFormOptionsByGroup("physical_condition");
    const { data: typeOptions } = useFormOptionsByGroup("disability_type");

    const optionsLoaded =
        conditionOptions !== undefined && typeOptions !== undefined;

    const conditionSchema = optionEnumOptional(
        conditionOptions ?? [],
        "وضعیت جسمانی انتخاب‌شده معتبر نیست.",
    );
    const typeSchema = optionEnumOptional(
        typeOptions ?? [],
        "نوع معلولیت انتخاب‌شده معتبر نیست.",
    );

    return (
        <>
            <form.Field
                name={conditionField}
                validators={
                    optionsLoaded
                        ? zodFieldValidators(conditionSchema)
                        : undefined
                }
            >
                {(field) => (
                    <FormOptionSelectField
                        field={field}
                        label={conditionLabel}
                        group="physical_condition"
                    />
                )}
            </form.Field>

            {showDisability && (
                <form.Field
                    name={typeField}
                    validators={
                        optionsLoaded ? zodFieldValidators(typeSchema) : undefined
                    }
                >
                    {(field) => (
                        <FormOptionSelectField
                            field={field}
                            label={typeLabel}
                            group="disability_type"
                        />
                    )}
                </form.Field>
            )}
        </>
    );
}
