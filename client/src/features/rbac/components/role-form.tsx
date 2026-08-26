import { useMemo } from "react";
import { useForm, useStore } from "@tanstack/react-form";
import type { AnyFieldApi } from "@tanstack/react-form";
import { z } from "zod";
import { IconChecks, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel, FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Switch } from "@/components/ui/switch";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { PermissionSelector } from "@/features/rbac/components/permission-selector";
import { RuleBuilder } from "@/features/rbac/components/rule-builder";
import { FormRepeater } from "@/components/forms";
import {
    FormTextField,
    FormSelectField,
    FormNumberField,
    FormTextarea,
    FormOptionMultiComboboxField,
} from "@/components/forms";
import { ErrorBanner } from "@/components/layout";
import { UnsavedChangesDialog } from "@/components/layout";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { useRoles } from "@/features/rbac/hooks/use-roles";
import {
    MATRIX_MANAGER_TYPES,
    EDUCATION_LEVELS,
    ROLE_TYPES,
    type MatrixManagerType,
    type RoleType,
} from "@/features/rbac/constants";

const MATRIX_MANAGER_TYPES_KEYS = [
    "project",
    "functional",
    "technical",
] as const;

const EDUCATION_LEVELS_KEYS = [
    "diploma",
    "associate",
    "bachelor",
    "master",
    "doctorate",
] as const;

/**
 * Field-level validator that tolerates the empty string the select emits
 * when its value is cleared, mapping it back to null.
 */
const optionalSelectValidator = <T extends string>(
    values: readonly [T, ...T[]],
) =>
    z
        .enum(values)
        .nullable()
        .or(z.literal(""))
        .transform((value) => (value === "" ? null : value));

const minExperienceYearsValidator = z
    .number()
    .int()
    .min(0)
    .max(50)
    .nullable()
    .or(z.literal(""))
    .transform((value) => (value === "" ? null : value));

const fieldsOfStudyValidator = z.array(z.string().max(100));

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
    type: z.enum(Object.keys(ROLE_TYPES) as [keyof typeof ROLE_TYPES, ...Array<keyof typeof ROLE_TYPES>]),
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
                new Set(items.map((item) => item.role_id)).size === items.length,
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

interface RoleFormProps {
    defaultValues?: Partial<RoleFormValues>;
    onSubmit: (values: RoleFormValues) => void;
    isPending?: boolean;
    error?: string | null;
    submitLabel?: string;
    excludeParentIds?: number[];
    inheritedPermissionIds?: number[];
}

function CommaSeparatedField({
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

function NullableSelectField({
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

export function RoleForm({
    defaultValues,
    onSubmit,
    isPending = false,
    error = null,
    submitLabel = "ذخیره",
    excludeParentIds = [],
    inheritedPermissionIds = [],
}: RoleFormProps) {
    const { data: allRolesData } = useRoles();

    const roleLookup = useMemo(() => {
        const map = new Map<number, string>();
        for (const role of allRolesData ?? []) {
            map.set(role.id, role.display_name);
        }
        return map;
    }, [allRolesData]);

    const form = useForm({
        defaultValues: {
            name: "",
            display_name: "",
            description: "",
            parent_id: null,
            type: "organization" as RoleType,
            inherits_permissions: false,
            is_active: true,
            access_rules: [],
            matrix_managers: [],
            ...defaultValues,
            requirements: {
                min_education: null,
                min_related_experience_years: null,
                min_unrelated_experience_years: null,
                fields_of_study: [],
                required_skills: [],
                preferred_skills: [],
                certifications: [],
                description: null,
                ...defaultValues?.requirements,
            },
        } as RoleFormValues,
        validators: {
            onSubmit: roleSchema,
        },
        onSubmit: async ({ value }) => {
            onSubmit(value);
        },
    });

    const isDirty = useStore(form.store, (state) => state.isDirty);
    const parentId = useStore(form.store, (state) => state.values.parent_id);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                e.stopPropagation();
                form.handleSubmit();
            }}
            className="space-y-6"
        >
            <UnsavedChangesDialog isDirty={isDirty} isSubmitting={isPending} />
            {error && <ErrorBanner message={error} />}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-base">
                                اطلاعات پایه
                            </CardTitle>
                            <form.Field name="is_active">
                                {(field) => (
                                    <div className="flex items-center gap-2">
                                        <Switch
                                            size="sm"
                                            checked={field.state.value}
                                            onCheckedChange={(checked) =>
                                                field.handleChange(checked)
                                            }
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            {field.state.value
                                                ? "فعال"
                                                : "غیرفعال"}
                                        </span>
                                    </div>
                                )}
                            </form.Field>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form.Field
                            name="type"
                            validators={zodFieldValidators(
                                roleSchema.shape.type,
                            )}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="نوع نقش"
                                    options={Object.entries(
                                        ROLE_TYPES,
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="name"
                            validators={zodFieldValidators(
                                roleSchema.shape.name,
                            )}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام سیستمی"
                                    placeholder="مثلا: admin"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="display_name"
                            validators={zodFieldValidators(
                                roleSchema.shape.display_name,
                            )}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام نمایشی"
                                    placeholder="مثلا: مدیر سامانه"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="description"
                            validators={zodFieldValidators(
                                roleSchema.shape.description,
                            )}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="توضیحات"
                                    placeholder="توضیحات اختیاری"
                                />
                            )}
                        </form.Field>

                        <form.Field name="parent_id">
                            {(field) => (
                                <Field>
                                    <FieldLabel>نقش والد</FieldLabel>
                                    <RoleSearchSelect
                                        value={field.state.value}
                                        onChange={(role) => {
                                            field.handleChange(
                                                role?.id ?? null,
                                            );
                                            if (!role) {
                                                form.setFieldValue(
                                                    "inherits_permissions",
                                                    false,
                                                );
                                            }
                                        }}
                                        placeholder="انتخاب نقش والد..."
                                        excludeIds={excludeParentIds}
                                    />
                                </Field>
                            )}
                        </form.Field>

                        <form.Field name="inherits_permissions">
                            {(field) => (
                                <Field>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={field.name}
                                            checked={field.state.value}
                                            disabled={
                                                !parentId
                                            }
                                            onCheckedChange={(checked) =>
                                                field.handleChange(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={field.name}
                                            className="cursor-pointer"
                                        >
                                            ارث‌بری مجوزها از نقش والد
                                        </Label>
                                    </div>
                                    <p className="text-xs text-muted-foreground ms-6">
                                        {parentId
                                            ? "مجوزهای نقش والد به این نقش اضافه می‌شوند"
                                            : "نقش والدی تعیین نشده است"}
                                    </p>
                                </Field>
                            )}
                        </form.Field>
                    </CardContent>
                </Card>

                <form.Field name="access_rules">
                    {(rulesField) => {
                        const allowedPermissionIds = rulesField.state.value
                            .filter((rule) => rule.effect === "allow")
                            .filter((rule) => rule.is_active !== false)
                            .map((rule) => rule.permission_id);                        const togglePermission = (permId: number) => {
                            const rules = rulesField.state.value;
                            const existing = rules.find(
                                (rule) =>
                                    rule.permission_id === permId &&
                                    rule.effect === "allow",
                            );

                            rulesField.handleChange(
                                existing
                                    ? rules.filter(
                                          (rule) =>
                                              !(
                                                  rule.permission_id ===
                                                      permId &&
                                                  rule.effect === "allow"
                                              ),
                                      )
                                    : [
                                          ...rules,
                                          {
                                              permission_id: permId,
                                              effect: "allow" as const,
                                              is_active: true,
                                          },
                                      ],
                            );
                        };

                        const toggleGroup = (
                            _groupId: number,
                            permIds: number[],
                        ) => {
                            const rules = rulesField.state.value;
                            const allAllowed = permIds.every((id) =>
                                rules.some(
                                    (rule) =>
                                        rule.permission_id === id &&
                                        rule.effect === "allow",
                                ),
                            );

                            if (allAllowed) {
                                const removed = new Set(permIds);
                                rulesField.handleChange(
                                    rules.filter(
                                        (rule) =>
                                            !(
                                                rule.effect === "allow" &&
                                                removed.has(
                                                    rule.permission_id,
                                                )
                                            ),
                                    ),
                                );
                            } else {
                                const existingAllowIds = new Set(
                                    rules
                                        .filter(
                                            (rule) =>
                                                rule.effect === "allow",
                                        )
                                        .map((rule) => rule.permission_id),
                                );
                                const toAdd = permIds.filter(
                                    (id) => !existingAllowIds.has(id),
                                );
                                rulesField.handleChange([
                                    ...rules,
                                    ...toAdd.map((id) => ({
                                        permission_id: id,
                                        effect: "allow" as const,
                                        is_active: true,
                                    })),
                                ]);
                            }
                        };

                        return (
                            <Card>
                                <CardContent className="space-y-3">
                                    <Tabs defaultValue="permissions">
                                        <TabsList className="w-full">
                                            <TabsTrigger value="permissions">
                                                مجوزها
                                            </TabsTrigger>
                                            <TabsTrigger value="access-rules">
                                                قوانین دسترسی شرطی
                                                {rulesField.state.value.length >
                                                    0 && ` (${String(rulesField.state.value.length)})`}
                                            </TabsTrigger>
                                        </TabsList>
                                        <TabsContent value="permissions">
                                            <PermissionSelector
                                                selectedPermissionIds={
                                                    allowedPermissionIds
                                                }
                                                inheritedPermissionIds={
                                                    inheritedPermissionIds
                                                }
                                                onGroupToggle={toggleGroup}
                                                onPermissionToggle={
                                                    togglePermission
                                                }
                                            />
                                        </TabsContent>
                                        <TabsContent value="access-rules">
                                            <RuleBuilder
                                                value={rulesField.state.value}
                                                onChange={(rules) =>
                                                    rulesField.handleChange(
                                                        rules,
                                                    )
                                                }
                                            />
                                        </TabsContent>
                                    </Tabs>
                                </CardContent>
                            </Card>
                        );
                    }}
                </form.Field>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">شرایط احراز</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field
                                name="requirements.min_education"
                                validators={zodFieldValidators(
                                    optionalSelectValidator(
                                        EDUCATION_LEVELS_KEYS,
                                    ),
                                )}
                            >
                                {(f) => (
                                    <NullableSelectField
                                        field={f}
                                        label="حداقل مقطع تحصیلی"
                                        placeholder="تعیین نشده"
                                        options={Object.entries(
                                            EDUCATION_LEVELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                    />
                                )}
                            </form.Field>
                            <form.Field
                                name="requirements.min_related_experience_years"
                                validators={zodFieldValidators(
                                    minExperienceYearsValidator,
                                )}
                            >
                                {(f) => (
                                    <FormNumberField
                                        field={f}
                                        label="حداقل سابقه کار مرتبط (سال)"
                                        min={0}
                                        max={50}
                                    />
                                )}
                            </form.Field>
                            <form.Field
                                name="requirements.min_unrelated_experience_years"
                                validators={zodFieldValidators(
                                    minExperienceYearsValidator,
                                )}
                            >
                                {(f) => (
                                    <FormNumberField
                                        field={f}
                                        label="حداقل سابقه کار غیرمرتبط (سال)"
                                        min={0}
                                        max={50}
                                    />
                                )}
                            </form.Field>
                        </div>

                        <form.Field
                            name="requirements.fields_of_study"
                            validators={zodFieldValidators(
                                fieldsOfStudyValidator,
                            )}
                        >
                            {(f) => (
                                <FormOptionMultiComboboxField
                                    field={f}
                                    label="رشته تحصیلی"
                                    group="field_of_study"
                                    placeholder="انتخاب رشته‌های تحصیلی…"
                                />
                            )}
                        </form.Field>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field
                                name="requirements.required_skills"
                                validators={zodFieldValidators(
                                    roleSchema.shape.requirements.shape
                                        .required_skills,
                                )}
                            >
                                {(f) => (
                                    <CommaSeparatedField
                                        field={f}
                                        label="مهارت‌های لازم"
                                        placeholder="مهارت‌ها را با ویرگول جدا کنید"
                                    />
                                )}
                            </form.Field>
                            <form.Field
                                name="requirements.preferred_skills"
                                validators={zodFieldValidators(
                                    roleSchema.shape.requirements.shape
                                        .preferred_skills,
                                )}
                            >
                                {(f) => (
                                    <CommaSeparatedField
                                        field={f}
                                        label="مهارت‌های ترجیحی"
                                        placeholder="مهارت‌ها را با ویرگول جدا کنید"
                                    />
                                )}
                            </form.Field>
                            <form.Field
                                name="requirements.certifications"
                                validators={zodFieldValidators(
                                    roleSchema.shape.requirements.shape
                                        .certifications,
                                )}
                            >
                                {(f) => (
                                    <CommaSeparatedField
                                        field={f}
                                        label="گواهینامه‌ها"
                                        placeholder="گواهی‌ها را با ویرگول جدا کنید"
                                    />
                                )}
                            </form.Field>
                        </div>

                        <form.Field
                            name="requirements.description"
                            validators={zodFieldValidators(
                                roleSchema.shape.requirements.shape.description,
                            )}
                        >
                            {(f) => (
                                <FormTextarea
                                    field={f}
                                    label="توضیحات"
                                    placeholder="توضیحات تکمیلی شرایط احراز (اختیاری)"
                                />
                            )}
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">مدیران ماتریسی</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form.Field name="matrix_managers">
                            {(field) => (
                                <FormRepeater
                                    defaultMode="card"
                                    field={field}
                                    label="فهرست مدیران ماتریسی"
                                    emptyMessage="مدیر ماتریسی تعریف نشده است."
                                    renderHeader={(item) => (
                                        <span className="flex items-center gap-2">
                                            <span>
                                                {item.role_id
                                                    ? (roleLookup.get(
                                                          item.role_id as number,
                                                      ) ??
                                                      `نقش #${String(item.role_id)}`)
                                                    : "نقش نامشخص"}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {MATRIX_MANAGER_TYPES[
                                                    item.manager_type as MatrixManagerType
                                                ] ?? ""}
                                            </span>
                                        </span>
                                    )}
                                    renderItem={(index) => (
                                        <div className="space-y-4">
                                            <form.Field
                                                name={`matrix_managers[${index}].manager_type`}
                                            >
                                                {(f) => (
                                                    <FormSelectField
                                                        field={f}
                                                        label="نوع مدیریت"
                                                        options={Object.entries(
                                                            MATRIX_MANAGER_TYPES,
                                                        ).map(([value, label]) => ({
                                                            value,
                                                            label,
                                                        }))}
                                                    />
                                                )}
                                            </form.Field>
                                            <form.Field
                                                name={`matrix_managers[${index}].role_id`}
                                                validators={zodFieldValidators(
                                                    roleSchema.shape
                                                        .matrix_managers.element
                                                        .shape.role_id,
                                                )}
                                            >
                                                {(f) => (
                                                    <Field
                                                        data-invalid={
                                                            f.state.meta.isTouched &&
                                                            !f.state.meta.isValid
                                                        }
                                                    >
                                                        <FieldLabel>
                                                            نقش مدیر
                                                        </FieldLabel>
                                                        <RoleSearchSelect
                                                            value={f.state.value}
                                                            onChange={(role) =>
                                                                f.handleChange(
                                                                    (role?.id ??
                                                                        null) as number,
                                                                )
                                                            }
                                                            excludeIds={
                                                                excludeParentIds
                                                            }
                                                            placeholder="انتخاب نقش مدیر..."
                                                        />
                                                        {f.state.meta.isTouched &&
                                                            !f.state.meta.isValid && (
                                                                <FieldError
                                                                    errors={
                                                                        f.state.meta
                                                                            .errors
                                                                    }
                                                                />
                                                            )}
                                                    </Field>
                                                )}
                                            </form.Field>
                                        </div>
                                    )}
                                />
                            )}
                        </form.Field>
                    </CardContent>
                </Card>
            </div>

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={isPending || !isDirty}>
                    {isPending ? (
                        <IconLoader2 className="size-4 animate-spin ms-1" />
                    ) : (
                        <IconChecks className="size-4 ms-1" />
                    )}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
