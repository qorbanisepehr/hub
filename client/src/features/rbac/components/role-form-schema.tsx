import type { AnyFieldApi, ReactFormExtendedApi } from "@tanstack/react-form";
import { z } from "zod";

import { Field, FieldLabel, FieldError } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    MATRIX_MANAGER_TYPES_KEYS,
    EDUCATION_LEVELS_KEYS,
    ROLE_TYPES,
} from "@/features/rbac/constants";

/**
 * Field-level validator that tolerates the empty string the select emits
 * when its value is cleared, mapping it back to null.
 */
export const optionalSelectValidator = <T extends string>(
    values: readonly [T, ...T[]],
) =>
    z
        .enum(values)
        .nullable()
        .or(z.literal(""))
        .transform((value) => (value === "" ? null : value));

export const minExperienceYearsValidator = z
    .number()
    .int()
    .min(0)
    .max(50)
    .nullable()
    .or(z.literal(""))
    .transform((value) => (value === "" ? null : value));

export const fieldsOfStudyValidator = z.array(z.string().max(100));

const accessRuleSchema = z.object({
    permission_id: z.number().int().positive(),
    effect: z.enum(["allow", "deny"]),
    priority: z.number().int().min(0).nullable().optional(),
    is_active: z.boolean().optional(),
    policy: z.any().nullable().optional(),
});

export const roleSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, "نام سیستمی الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    display_name: z
        .string()
        .trim()
        .min(1, "نام نمایشی الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    description: z.string().max(500, "حداکثر ۵۰۰ کاراکتر").or(z.literal("")),
    parent_id: z.number().nullable(),
    type: z.enum(
        Object.keys(ROLE_TYPES) as [
            keyof typeof ROLE_TYPES,
            ...Array<keyof typeof ROLE_TYPES>,
        ],
    ),
    inherits_permissions: z.boolean(),
    is_active: z.boolean(),
    access_rules: z.array(accessRuleSchema),
    matrix_managers: z
        .array(
            z.object({
                role_id: z.number().int().positive(),
                manager_type: z.enum(MATRIX_MANAGER_TYPES_KEYS),
            }),
        )
        .refine(
            (items) =>
                new Set(items.map((item) => item.role_id)).size ===
                items.length,
            "هر نقش فقط یک بار می‌تواند مدیر ماتریسی باشد",
        ),
    requirements: z.object({
        min_education: z.enum(EDUCATION_LEVELS_KEYS).nullable(),
        min_related_experience_years: z
            .number()
            .int()
            .min(0)
            .max(50)
            .nullable(),
        min_unrelated_experience_years: z
            .number()
            .int()
            .min(0)
            .max(50)
            .nullable(),
        fields_of_study: z.array(z.string().max(100)),
        required_skills: z.array(z.string().max(100)),
        preferred_skills: z.array(z.string().max(100)),
        certifications: z.array(z.string().max(100)),
        description: z.string().max(1000).nullable(),
    }),
});

export type RoleFormValues = z.infer<typeof roleSchema>;

/** Concrete form API for the role form, keeping full field typing intact. */
export type RoleFormApi = ReactFormExtendedApi<
    RoleFormValues,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any,
    any
>;

/** Comma-separated multi-value text field (skills, certifications, fields). */
export function CommaSeparatedField({
    field,
    label,
    placeholder,
}: {
    field: AnyFieldApi;
    label: string;
    placeholder?: string;
}) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;
    const value = (field.state.value ?? []) as string[];

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Input
                id={field.name}
                name={field.name}
                value={value.join("، ")}
                onBlur={field.handleBlur}
                onChange={(e) =>
                    field.handleChange(
                        e.target.value
                            .split(/[،,]/)
                            .map((s) => s.trim())
                            .filter(Boolean),
                    )
                }
                placeholder={placeholder}
            />
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/** Select that allows a null (cleared) value via an empty option. */
export function NullableSelectField({
    field,
    label,
    options,
    placeholder = "انتخاب کنید",
}: {
    field: AnyFieldApi;
    label: string;
    options: { value: string; label: string }[];
    placeholder?: string;
}) {
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Select
                value={field.state.value || null}
                onValueChange={(val) =>
                    field.handleChange(val === "" ? null : val)
                }
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