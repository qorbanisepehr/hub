import type { AnyFieldApi } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type Props = {
    field: AnyFieldApi;
    disabled?: boolean;
};

export function IdentifierField({ field, disabled }: Props) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>
                ایمیل، موبایل یا نام کاربری
            </FieldLabel>
            <Input
                id={field.name}
                name={field.name}
                value={field.state.value}
                onBlur={field.handleBlur}
                onChange={(e) => field.handleChange(e.target.value)}
                disabled={disabled}
                placeholder="m@example.com"
                dir="ltr"
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}
