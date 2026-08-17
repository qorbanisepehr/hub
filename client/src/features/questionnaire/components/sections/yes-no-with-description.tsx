import { useEffect, useRef } from "react";
import { useStore } from "@tanstack/react-form";
import { z } from "zod";

import { FormTextarea, FormRadioGroup } from "@/components/forms";
import { YES_NO_OPTIONS, parseBoolean } from "@/features/questionnaire/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";

const requiredDescription = z.string().min(1, "این فیلد الزامی است.").max(500);

type YesNoWithDescriptionProps = {
    form: QuestionnaireFormApi;
    booleanField: string;
    descriptionField: string;
    booleanLabel: string;
    descriptionLabel: string;
};

export function YesNoWithDescription({
    form,
    booleanField,
    descriptionField,
    booleanLabel,
    descriptionLabel,
}: YesNoWithDescriptionProps) {
    const isYes = useStore(form.store, (s) => {
        const val = s.values;
        const keys = booleanField.split(".");
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        let current: any = val;
        for (const key of keys) {
            current = current?.[key];
        }
        return current === true;
    });

    const prevIsYesRef = useRef(isYes);

    useEffect(() => {
        if (prevIsYesRef.current && !isYes) {
            form.setFieldValue(descriptionField, "", { dontUpdateMeta: true });
        }
        prevIsYesRef.current = isYes;
    }, [isYes, form, descriptionField]);

    return (
        <>
            <form.Field name={booleanField}>
                {(field) => (
                    <FormRadioGroup
                        field={field}
                        label={booleanLabel}
                        options={YES_NO_OPTIONS}
                        parseValue={parseBoolean}
                    />
                )}
            </form.Field>
            {isYes && (
                <form.Field
                    name={descriptionField}
                    validators={zodFieldValidators(requiredDescription)}
                >
                    {(field) => <FormTextarea field={field} label={descriptionLabel} />}
                </form.Field>
            )}
        </>
    );
}
