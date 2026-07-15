import { useForm } from "@tanstack/react-form";
import { z } from "zod";
import { IconChecks, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { RoleSelect } from "@/features/rbac/components/role-select";
import { PermissionMatrix } from "@/features/rbac/components/permission-matrix";
import { FormTextField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";

export const roleSchema = z.object({
    name: z.string().trim().min(1, "نام سیستمی الزامی است").max(100, "حداکثر ۱۰۰ کاراکتر"),
    display_name: z.string().trim().min(1, "نام نمایشی الزامی است").max(100, "حداکثر ۱۰۰ کاراکتر"),
    description: z.string().max(500, "حداکثر ۵۰۰ کاراکتر").or(z.literal("")),
    parent_id: z.number().nullable(),
    inherits_permissions: z.boolean(),
    is_active: z.boolean(),
    permission_ids: z.array(z.number()),
    permission_group_ids: z.array(z.number()),
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

export function RoleForm({
    defaultValues,
    onSubmit,
    isPending = false,
    error = null,
    submitLabel = "ذخیره",
    excludeParentIds = [],
    inheritedPermissionIds = [],
}: RoleFormProps) {
    const form = useForm({
        defaultValues: {
            name: "",
            display_name: "",
            description: "",
            parent_id: null,
            inherits_permissions: false,
            is_active: true,
            permission_ids: [],
            permission_group_ids: [],
            ...defaultValues,
        } as RoleFormValues,
        validators: {
            onSubmit: roleSchema,
        },
        onSubmit: async ({ value }) => {
            onSubmit(value);
        },
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                e.stopPropagation();
                form.handleSubmit();
            }}
            className="space-y-6 max-w-2xl"
        >
            {error && <ErrorBanner message={error} />}

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">اطلاعات پایه</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form.Field name="name">
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="نام سیستمی"
                                placeholder="مثلا: admin"
                                dir="ltr"
                            />
                        )}
                    </form.Field>

                    <form.Field name="display_name">
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="نام نمایشی"
                                placeholder="مثلا: مدیر سامانه"
                            />
                        )}
                    </form.Field>

                    <form.Field name="description">
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
                                <RoleSelect
                                    value={field.state.value ? String(field.state.value) : undefined}
                                    onValueChange={(val) => {
                                        const newVal = val ? Number(val) : null;
                                        field.handleChange(newVal);
                                        if (!newVal) {
                                            form.setFieldValue("inherits_permissions", false);
                                        }
                                    }}
                                    placeholder="بدون نقش والد"
                                    clearable
                                    clearLabel="بدون نقش والد"
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
                                        disabled={!form.state.values.parent_id}
                                        onCheckedChange={(checked) =>
                                            field.handleChange(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor={field.name}
                                        className="cursor-pointer"
                                    >
                                        ارث‌بری مجوزها از نقش والد
                                    </Label>
                                </div>
                                <p className="text-xs text-muted-foreground mr-6">
                                    {form.state.values.parent_id
                                        ? "مجوزهای نقش والد به این نقش اضافه می‌شوند"
                                        : "نقش والدی تعیین نشده است"}
                                </p>
                            </Field>
                        )}
                    </form.Field>

                    <form.Field name="is_active">
                        {(field) => (
                            <Field>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={field.name}
                                        checked={field.state.value}
                                        onCheckedChange={(checked) =>
                                            field.handleChange(checked === true)
                                        }
                                    />
                                    <Label htmlFor={field.name} className="cursor-pointer">
                                        نقش فعال
                                    </Label>
                                </div>
                            </Field>
                        )}
                    </form.Field>
                </CardContent>
            </Card>

            <form.Field name="permission_group_ids">
                {(groupField) => (
                    <form.Field name="permission_ids">
                        {(permField) => (
                            <PermissionMatrix
                                selectedGroupIds={groupField.state.value}
                                selectedPermissionIds={permField.state.value}
                                inheritedPermissionIds={inheritedPermissionIds}
                                onGroupToggle={(groupId, permIds) => {
                                    const groups = groupField.state.value;
                                    const perms = permField.state.value;
                                    const isAdding = !groups.includes(groupId);

                                    groupField.handleChange(
                                        isAdding
                                            ? [...groups, groupId]
                                            : groups.filter((id) => id !== groupId),
                                    );

                                    permField.handleChange(
                                        isAdding
                                            ? [...new Set([...perms, ...permIds])]
                                            : perms.filter((id) => !permIds.includes(id)),
                                    );
                                }}
                                onPermissionToggle={(permId, _groupId) => {
                                    const perms = permField.state.value;

                                    if (perms.includes(permId)) {
                                        permField.handleChange(
                                            perms.filter((id) => id !== permId),
                                        );
                                    } else {
                                        permField.handleChange([...perms, permId]);
                                    }
                                }}
                            />
                        )}
                    </form.Field>
                )}
            </form.Field>

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={isPending}>
                    {isPending ? (
                        <IconLoader2 className="size-4 animate-spin ml-1" />
                    ) : (
                        <IconChecks className="size-4 ml-1" />
                    )}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
