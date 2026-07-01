import type { FieldApi } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type Props = {
    field: FieldApi<any, any, any, any>;
};

export function PasswordField({ field }: Props) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>رمز عبور</FieldLabel>
            <Input
                id={field.name}
                name={field.name}
                type="password"
                value={field.state.value}
                onBlur={field.handleBlur}
                onChange={(e) => field.handleChange(e.target.value)}
                placeholder="••••••••"
                dir="ltr"
                autoComplete="on"
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}
