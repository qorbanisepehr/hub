import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { FormTextField, FormTextarea } from "@/components/shared/form-fields";
import { ProvinceCityFields } from "@/components/shared/form-option-fields";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/contact-info.schema";

type AddressFormProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    prefix: string;
    /**
     * `"full"` (default): province/city are province + city option selects
     * (place form options) plus the full address fields. `"simple"`: the same
     * province/city option selects with just neighborhood and postal code.
     */
    mode?: "full" | "simple";
};

export function AddressForm({ form, prefix, mode = "full" }: AddressFormProps) {
    if (mode === "simple") {
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name={`${prefix}.province`}
                        validators={zodFieldValidators(fieldSchemas.address_province)}
                    >
                        {(provinceField) => (
                            <form.Field
                                name={`${prefix}.city`}
                                validators={zodFieldValidators(fieldSchemas.address_city)}
                            >
                                {(cityField) => (
                                    <ProvinceCityFields
                                        provinceField={provinceField}
                                        cityField={cityField}
                                    />
                                )}
                            </form.Field>
                        )}
                    </form.Field>
                    <form.Field
                        name={`${prefix}.neighborhood`}
                        validators={zodFieldValidators(fieldSchemas.address_neighborhood)}
                    >
                        {(field) => <FormTextField field={field} label="محله" />}
                    </form.Field>
                    <form.Field
                        name={`${prefix}.postal_code`}
                        validators={zodFieldValidators(fieldSchemas.address_postal_code)}
                    >
                        {(field) => <FormTextField field={field} label="کد پستی" dir="ltr" />}
                    </form.Field>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field
                    name={`${prefix}.postal_code`}
                    validators={zodFieldValidators(fieldSchemas.address_postal_code)}
                >
                    {(field) => <FormTextField field={field} label="کد پستی" dir="ltr" />}
                </form.Field>
                <form.Field
                    name={`${prefix}.province`}
                    validators={zodFieldValidators(fieldSchemas.address_province)}
                >
                    {(provinceField) => (
                        <form.Field
                            name={`${prefix}.city`}
                            validators={zodFieldValidators(fieldSchemas.address_city)}
                        >
                            {(cityField) => (
                                <ProvinceCityFields
                                    provinceField={provinceField}
                                    cityField={cityField}
                                />
                            )}
                        </form.Field>
                    )}
                </form.Field>
            </div>

            <form.Field
                name={`${prefix}.address`}
                validators={zodFieldValidators(fieldSchemas.address_address)}
            >
                {(field) => <FormTextarea field={field} label="آدرس" />}
            </form.Field>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <form.Field
                    name={`${prefix}.neighborhood`}
                    validators={zodFieldValidators(fieldSchemas.address_neighborhood)}
                >
                    {(field) => <FormTextField field={field} label="محله" />}
                </form.Field>
                <form.Field name={`${prefix}.plaque`}>
                    {(field) => <FormTextField field={field} label="پلاک" dir="ltr" />}
                </form.Field>
                <form.Field name={`${prefix}.floor`}>
                    {(field) => <FormTextField field={field} label="طبقه" dir="ltr" />}
                </form.Field>
                <form.Field name={`${prefix}.unit`}>
                    {(field) => <FormTextField field={field} label="واحد" dir="ltr" />}
                </form.Field>
            </div>
        </div>
    );
}
