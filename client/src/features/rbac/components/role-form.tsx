import { useForm, useStore } from "@tanstack/react-form";
import { z } from "zod";
import { IconChecks, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Switch } from "@/components/ui/switch";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { PermissionSelector } from "@/features/rbac/components/permission-selector";
import { FormTextField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";
import { UnsavedChangesDialog } from "@/components/shared/unsaved-changes-dialog";

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

                <form.Field name="permission_group_ids">
                    {(groupField) => (
                        <form.Field name="permission_ids">
                            {(permField) => (
                                <PermissionSelector
                                    selectedGroupIds={groupField.state.value}
                                    selectedPermissionIds={
                                        permField.state.value
                                    }
                                    inheritedPermissionIds={
                                        inheritedPermissionIds
                                    }
                                    onGroupToggle={(groupId, permIds) => {
                                        const groups = groupField.state.value;
                                        const perms = permField.state.value;
                                        const allSelected = permIds.every(
                                            (id) => perms.includes(id),
                                        );

                                        permField.handleChange(
                                            allSelected
                                                ? perms.filter(
                                                      (id) =>
                                                          !permIds.includes(id),
                                                  )
                                                : [
                                                      ...new Set([
                                                          ...perms,
                                                          ...permIds,
                                                      ]),
                                                  ],
                                        );

                                        if (
                                            !allSelected &&
                                            !groups.includes(groupId)
                                        ) {
                                            groupField.handleChange([
                                                ...groups,
                                                groupId,
                                            ]);
                                        }
                                    }}
                                    onGroupRemove={(groupId, permIds) => {
                                        const groups = groupField.state.value;
                                        const perms = permField.state.value;

                                        groupField.handleChange(
                                            groups.filter(
                                                (id) => id !== groupId,
                                            ),
                                        );
                                        permField.handleChange(
                                            perms.filter(
                                                (id) => !permIds.includes(id),
                                            ),
                                        );
                                    }}
                                    onPermissionToggle={(permId, _groupId) => {
                                        const perms = permField.state.value;

                                        if (perms.includes(permId)) {
                                            permField.handleChange(
                                                perms.filter(
                                                    (id) => id !== permId,
                                                ),
                                            );
                                        } else {
                                            permField.handleChange([
                                                ...perms,
                                                permId,
                                            ]);
                                        }
                                    }}
                                />
                            )}
                        </form.Field>
                    )}
                </form.Field>
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
