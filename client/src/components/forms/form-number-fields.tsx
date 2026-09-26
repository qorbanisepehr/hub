import type { AnyFieldApi } from "@tanstack/react-form";
import { NumericFormat, PatternFormat } from "react-number-format";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

/**
 * Formatted numeric fields built on react-number-format.
 *
 * All variants store the RAW digit string in the form value (no separators,
 * no prefix) so backend/zod validation is unchanged; formatting is display
 * only. `dir="ltr"` everywhere since digits read left-to-right even in fa.
 */

type BaseProps = {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
    disabled?: boolean;
};

function useInvalid(field: AnyFieldApi) {
    return field.state.meta.isTouched && !field.state.meta.isValid;
}

/** Bank card: 16 digits grouped 4-4-4-4. Value stays digits-only. */
export function FormCardNumberField({
    field,
    label,
    placeholder = "0000 0000 0000 0000",
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <PatternFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onBlur={field.handleBlur}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                format="#### #### #### ####"
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/** Bank account / sheba: free-length digits, thousand-free, LTR. */
export function FormAccountNumberField({
    field,
    label,
    placeholder,
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <NumericFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                allowLeadingZeros
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/**
 * Amount: thousands separator (default fa-IR grouping via `,`), stored as a
 * plain digit string; use `toAmountNumber` when a numeric payload is needed.
 */
export function FormAmountField({
    field,
    label,
    placeholder,
    disabled,
    suffix = "ریال",
}: BaseProps & { suffix?: string }) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <NumericFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                thousandSeparator
                suffix={suffix ? ` ${suffix}` : undefined}
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/**
 * Landline / emergency phone: 11 digits grouped 3-4-4 (021 1234 5678).
 * Emergency phones accept mobiles too, which are also 11 digits.
 */
export function FormPhoneField({
    field,
    label,
    placeholder = "0xx xxxx xxxx",
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <PatternFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                format="### #### ####"
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/** Iranian postal code: 10 digits grouped 4-3-3. Value stays digits-only. */
export function FormPostalCodeField({
    field,
    label,
    placeholder = "xxxx xxx xxx",
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <PatternFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                format="#### ### ###"
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/**
 * Iranian IBAN (شماره شبا): fixed "IR" prefix + 24 digits shown in 4-char
 * groups. The form value stays the compact `IR…` string the backend stores;
 * the prefix is added/removed around the digits-only PatternFormat value.
 */
export function FormIbanField({
    field,
    label,
    placeholder = "IR xxxx xxxx xxxx xxxx xxxx xxxx",
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);
    const raw: string = field.state.value ?? "";
    const digits = raw.toUpperCase().replace(/^IR/, "");

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <PatternFormat
                id={field.name}
                name={field.name}
                value={digits}
                onValueChange={(values) =>
                    field.handleChange(
                        values.value ? `IR${values.value}` : "",
                    )
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                format="IR #### #### #### #### #### ####"
                mask="_"
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/** Phone: 11-digit mobile grouped 4-3-4 (09xx xxx xxxx). */
export function FormMobileNumberField({
    field,
    label,
    placeholder = "09xx xxx xxxx",
    disabled,
}: BaseProps) {
    const isInvalid = useInvalid(field);

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <PatternFormat
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onValueChange={(values) =>
                    field.handleChange(values.value ?? "")
                }
                onBlur={field.handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                dir="ltr"
                format="#### ### ####"
                customInput={Input}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/** Helper for payload building: formatted string → number (null when empty). */
export function toAmountNumber(
    value: string | null | undefined,
): number | null {
    if (!value) return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}
