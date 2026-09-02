import { useStore } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field } from "@/components/ui/field";
import { FieldLabel } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { FormTextField, FormSelectField } from "@/components/forms";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { ROLE_TYPES } from "@/features/rbac/constants";
import { roleSchema, type RoleFormApi } from "./role-form-schema";
import { zodFieldValidators } from "@/lib/validation-helpers";

type BaseInfoCardProps = {
    form: RoleFormApi;
    excludeParentIds: number[];
};

export function RoleBaseInfoCard({
    form,
    excludeParentIds,
}: BaseInfoCardProps) {
    const parentId = useStore(
        form.store,
        (state) => state.values.parent_id,
    );

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base">اطلاعات پایه</CardTitle>
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
                                    {field.state.value ? "فعال" : "غیرفعال"}
                                </span>
                            </div>
                        )}
                    </form.Field>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <form.Field
                    name="type"
                    validators={zodFieldValidators(roleSchema.shape.type)}
                >
                    {(field) => (
                        <FormSelectField
                            field={field}
                            label="نوع نقش"
                            options={Object.entries(ROLE_TYPES).map(
                                ([value, label]) => ({ value, label }),
                            )}
                        />
                    )}
                </form.Field>

                <form.Field
                    name="name"
                    validators={zodFieldValidators(roleSchema.shape.name)}
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
                                    field.handleChange(role?.id ?? null);
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
                                    disabled={!parentId}
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
    );
}