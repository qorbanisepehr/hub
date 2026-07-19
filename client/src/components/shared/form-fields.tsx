import type { AnyFieldApi } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

// ── Text Input ──

type FormTextFieldProps = {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
    dir?: "ltr" | "rtl";
    autoComplete?: string;
};

export function FormTextField({
    field,
    label,
    placeholder,
    dir,
    autoComplete,
}: FormTextFieldProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Input
                id={field.name}
                name={field.name}
                type="text"
                value={field.state.value}
                onBlur={field.handleBlur}
                onChange={(e) => field.handleChange(e.target.value)}
                placeholder={placeholder}
                dir={dir}
                autoComplete={autoComplete}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Select ──

type SelectOption = {
    value: string;
    label: string;
};

type FormSelectFieldProps = {
    field: AnyFieldApi;
    label: string;
    options: SelectOption[];
    placeholder?: string;
};

export function FormSelectField({
    field,
    label,
    options,
    placeholder = "انتخاب کنید",
}: FormSelectFieldProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Select
                value={field.state.value || null}
                onValueChange={(val) => field.handleChange(val ?? "")}
                itemToStringLabel={(val) =>
                    val
                        ? (options.find((o) => o.value === val)?.label ?? val)
                        : ""
                }
            >
                <SelectTrigger id={field.name}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                            {opt.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Search Select Field ──

type FormSearchSelectFieldProps = {
    field: AnyFieldApi;
    label: string;
    renderSelect: (props: {
        value: number | string | null;
        onChange: (item: never) => void;
    }) => React.ReactNode;
    placeholder?: string;
};

export function FormSearchSelectField({
    field,
    label,
    renderSelect,
}: FormSearchSelectFieldProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel>{label}</FieldLabel>
            {renderSelect({
                value: field.state.value,
                onChange: (item: never) => {
                    const value =
                        item && typeof item === "object" && "id" in item
                            ? (item as { id: number }).id
                            : item;
                    field.handleChange(value);
                },
            })}
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}
