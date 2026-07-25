import type { ReactFormExtendedApi } from "@tanstack/react-form";

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
    return (
        <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            </div>
            <form.Field name={descriptionField}>
                {(field) => <FormTextarea field={field} label={descriptionLabel} />}
            </form.Field>
        </>
    );
}
