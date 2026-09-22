import { useSelector } from "@tanstack/react-form";
import { useEffect, useRef } from "react";
import { z } from "zod";

import { FormRadioGroup, FormTextarea } from "@/components/forms";
import {
    YES_NO_OPTIONS,
    parseBoolean,
} from "@/features/questionnaire/constants";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";
import { getFormValuePath } from "@/lib/form-utils";
import { zodFieldValidators } from "@/lib/validation-helpers";

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
    const isYes = useSelector(
        form.store,
        (s) => getFormValuePath(s.values, booleanField) === true,
    );

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
                    {(field) => (
                        <FormTextarea field={field} label={descriptionLabel} />
                    )}
                </form.Field>
            )}
        </>
    );
}
