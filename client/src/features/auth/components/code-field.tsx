import type { FieldApi } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";

type Props = {
    field: FieldApi<any, any, any, any>;
};

export function CodeField({ field }: Props) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>کد تایید</FieldLabel>
            <div className="flex justify-center">
                <InputOTP
                    maxLength={6}
                    value={field.state.value}
                    onBlur={field.handleBlur}
                    onChange={(val) => field.handleChange(val)}
                    dir="ltr"
                >
                    <InputOTPGroup dir="ltr">
                        <InputOTPSlot index={0} />
                        <InputOTPSlot index={1} />
                        <InputOTPSlot index={2} />
                        <InputOTPSlot index={3} />
                        <InputOTPSlot index={4} />
                        <InputOTPSlot index={5} />
                    </InputOTPGroup>
                </InputOTP>
            </div>
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}
