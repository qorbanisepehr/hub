import type { AnyFieldApi } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { IconPlus, IconTrash } from "@tabler/icons-react";
import { DatePicker } from "@/components/ui/date-picker";

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
                value={field.state.value ?? ""}
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

// ── Number Input ──

type FormNumberFieldProps = {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
    min?: number;
    max?: number;
};

export function FormNumberField({
    field,
    label,
    placeholder,
    min,
    max,
}: FormNumberFieldProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Input
                id={field.name}
                name={field.name}
                type="number"
                value={field.state.value ?? ""}
                onBlur={field.handleBlur}
                onChange={(e) => {
                    const val = e.target.value;
                    field.handleChange(val === "" ? null : Number(val));
                }}
                placeholder={placeholder}
                min={min}
                max={max}
                dir="ltr"
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Date Picker ──

type FormDatePickerProps = {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
    disabled?: boolean;
};

export function FormDatePicker({ field, label, placeholder, disabled }: FormDatePickerProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;
    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <DatePicker
                value={field.state.value || null}
                onChange={(val) => field.handleChange(val)}
                placeholder={placeholder}
                disabled={disabled}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Color Input ──

const HEX_COLOR_6 = /^#[0-9a-fA-F]{6}$/;

type FormColorFieldProps = {
    field: AnyFieldApi;
    label: string;
    hint?: string;
};

export function FormColorField({ field, label, hint }: FormColorFieldProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;
    const value = (field.state.value ?? "") as string;
    const pickerValue = HEX_COLOR_6.test(value) ? value : "#000000";

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <div className="flex items-center gap-2">
                <Input
                    id={field.name}
                    name={field.name}
                    type="color"
                    aria-label={label}
                    value={pickerValue}
                    onBlur={field.handleBlur}
                    onChange={(e) => field.handleChange(e.target.value.toUpperCase())}
                    className="size-10 cursor-pointer rounded-md border p-1"
                />
                <Input
                    type="text"
                    value={value}
                    onBlur={field.handleBlur}
                    onChange={(e) => field.handleChange(e.target.value)}
                    placeholder="#db7868"
                    dir="ltr"
                    className="font-mono"
                />
            </div>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Textarea ──

type FormTextareaProps = {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
    rows?: number;
};

export function FormTextarea({
    field,
    label,
    placeholder,
    rows = 3,
}: FormTextareaProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Textarea
                id={field.name}
                name={field.name}
                value={field.state.value ?? ""}
                onBlur={field.handleBlur}
                onChange={(e) => field.handleChange(e.target.value)}
                placeholder={placeholder}
                rows={rows}
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

// ── Radio Group ──

type RadioOption = {
    value: string;
    label: string;
};

type FormRadioGroupProps = {
    field: AnyFieldApi;
    label: string;
    options: RadioOption[];
    disabled?: boolean;
    parseValue?: (value: string) => unknown;
};

export function FormRadioGroup({
    field,
    label,
    options,
    disabled,
    parseValue,
}: FormRadioGroupProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;
    const stringValue = String(field.state.value ?? "");

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel>{label}</FieldLabel>
            <RadioGroup
                value={stringValue}
                onValueChange={(val) => field.handleChange(parseValue ? parseValue(val) : val)}
                disabled={disabled}
                className="flex flex-row flex-wrap gap-4"
            >
                {options.map((opt) => (
                    <div key={opt.value} className="flex items-center gap-2">
                        <RadioGroupItem value={opt.value} id={`${field.name}-${opt.value}`} />
                        <label
                            htmlFor={`${field.name}-${opt.value}`}
                            className="text-sm font-normal cursor-pointer"
                        >
                            {opt.label}
                        </label>
                    </div>
                ))}
            </RadioGroup>
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

// ── Checkbox Group ──

type CheckboxOption = {
    value: string;
    label: string;
};

type FormCheckboxGroupProps = {
    field: AnyFieldApi;
    label: string;
    options: CheckboxOption[];
};

export function FormCheckboxGroup({
    field,
    label,
    options,
}: FormCheckboxGroupProps) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;
    const selected: string[] = field.state.value ?? [];

    const toggle = (val: string) => {
        const next = selected.includes(val)
            ? selected.filter((v) => v !== val)
            : [...selected, val];
        field.handleChange(next);
    };

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel>{label}</FieldLabel>
            <div className="flex flex-row flex-wrap gap-4">
                {options.map((opt) => (
                    <div key={opt.value} className="flex items-center gap-2">
                        <Checkbox
                            id={`${field.name}-${opt.value}`}
                            checked={selected.includes(opt.value)}
                            onCheckedChange={() => toggle(opt.value)}
                        />
                        <label
                            htmlFor={`${field.name}-${opt.value}`}
                            className="text-sm font-normal cursor-pointer"
                        >
                            {opt.label}
                        </label>
                    </div>
                ))}
            </div>
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
