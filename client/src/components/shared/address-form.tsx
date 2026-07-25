import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { FormTextField, FormTextarea } from "@/components/shared/form-fields";

type AddressFormProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    prefix: string;
};

export function AddressForm({ form, prefix }: AddressFormProps) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form.Field name={`${prefix}.postal_code`}>
                    {(field) => <FormTextField field={field} label="کد پستی" dir="ltr" />}
                </form.Field>
                <form.Field name={`${prefix}.province`}>
                    {(field) => <FormTextField field={field} label="استان" />}
                </form.Field>
                <form.Field name={`${prefix}.city`}>
                    {(field) => <FormTextField field={field} label="شهر" />}
                </form.Field>
            </div>

            <form.Field name={`${prefix}.address`}>
                {(field) => <FormTextarea field={field} label="آدرس" />}
            </form.Field>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
