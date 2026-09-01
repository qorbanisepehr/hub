import { useForm, useStore } from "@tanstack/react-form";
import { IconChecks, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ErrorBanner } from "@/components/layout";
import { UnsavedChangesDialog } from "@/components/layout";
import { roleSchema, type RoleFormValues } from "./role-form-schema";
import { RoleBaseInfoCard } from "./role-form-base-info";
import { RoleAccessCard } from "./role-form-access";
import { RoleRequirementsCard } from "./role-form-requirements";
import { RoleMatrixManagersCard } from "./role-form-matrix-managers";

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
            type: "organization" as RoleFormValues["type"],
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
                <RoleBaseInfoCard
                    form={form}
                    excludeParentIds={excludeParentIds}
                />
                <RoleAccessCard
                    form={form}
                    inheritedPermissionIds={inheritedPermissionIds}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <RoleRequirementsCard form={form} />
                <RoleMatrixManagersCard
                    form={form}
                    excludeParentIds={excludeParentIds}
                />
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