import { useEffect, useRef } from "react";
import type { ReactFormExtendedApi } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";

import { FormTextarea, FormRadioGroup } from "@/components/shared/form-fields";
import { YES_NO_OPTIONS, parseBoolean } from "@/features/recruitment/constants";

type YesNoWithDescriptionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
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
            form.setFieldValue(descriptionField, "");
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
                <form.Field name={descriptionField}>
                    {(field) => <FormTextarea field={field} label={descriptionLabel} />}
                </form.Field>
            )}
        </>
    );
}
