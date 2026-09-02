import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field } from "@/components/ui/field";
import { FieldLabel } from "@/components/ui/field";
import { FieldError } from "@/components/ui/field";
import { FormRepeater, FormSelectField } from "@/components/forms";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { useRoles } from "@/features/rbac/hooks/use-roles";
import {
    MATRIX_MANAGER_TYPES,
    type MatrixManagerType,
} from "@/features/rbac/constants";
import { roleSchema, type RoleFormApi } from "./role-form-schema";
import { zodFieldValidators } from "@/lib/validation-helpers";

type MatrixManagersCardProps = {
    form: RoleFormApi;
    excludeParentIds: number[];
};

export function RoleMatrixManagersCard({
    form,
    excludeParentIds,
}: MatrixManagersCardProps) {
    const { data: allRolesData } = useRoles();

    const roleLookup = useMemo(() => {
        const map = new Map<number, string>();
        for (const role of allRolesData ?? []) {
            map.set(role.id, role.display_name);
        }
        return map;
    }, [allRolesData]);

    return (
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
                                            roleSchema.shape.matrix_managers
                                                .element.shape.role_id,
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
    );
}