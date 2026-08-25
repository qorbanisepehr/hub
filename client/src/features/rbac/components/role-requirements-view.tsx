import { useMemo } from "react";
import type { ReactNode } from "react";
import {
    IconAward,
    IconBook2,
    IconBriefcase,
    IconChecklist,
    IconCircleX,
    IconNotes,
    IconSchool,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    useFormOptionsByGroup,
} from "@/features/form-options/hooks/use-form-options";
import { EDUCATION_LEVELS } from "@/features/rbac/constants";
import type { RoleRequirements } from "@/features/rbac/types";

function RequirementRow({
    icon,
    label,
    children,
}: {
    icon: ReactNode;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border bg-card p-3">
            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary [&_svg]:size-4">
                {icon}
            </div>
            <div className="min-w-0 flex-1 space-y-1">
                <p className="text-[11px] font-medium text-muted-foreground">
                    {label}
                </p>
                <div className="text-sm font-medium text-card-foreground">
                    {children}
                </div>
            </div>
        </div>
    );
}

function ChipList({ items }: { items: string[] }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {items.map((item) => (
                <Badge
                    key={item}
                    variant="secondary"
                    className="max-w-full truncate font-normal"
                >
                    {item}
                </Badge>
            ))}
        </div>
    );
}

/**
 * Read-only rendering of a role's eligibility requirements (شرایط احراز).
 * Falls back to the raw stored key when an option label is unavailable.
 */
export function RoleRequirementsView({
    requirements,
}: {
    requirements?: RoleRequirements | null;
}) {
    const { data: fieldOfStudyOptions } = useFormOptionsByGroup(
        "field_of_study",
    );

    const fieldOfStudyLabels = useMemo(() => {
        const byValue = new Map(
            (fieldOfStudyOptions ?? []).map((option) => [
                option.value,
                option.label,
            ]),
        );

        return (requirements?.fields_of_study ?? []).map(
            (value) => byValue.get(value) ?? value,
        );
    }, [fieldOfStudyOptions, requirements?.fields_of_study]);

    const hasRequirements =
        requirements != null &&
        (requirements.min_education != null ||
            requirements.min_related_experience_years != null ||
            requirements.min_unrelated_experience_years != null ||
            (requirements.fields_of_study?.length ?? 0) > 0 ||
            (requirements.required_skills?.length ?? 0) > 0 ||
            (requirements.preferred_skills?.length ?? 0) > 0 ||
            (requirements.certifications?.length ?? 0) > 0 ||
            (requirements.description?.trim().length ?? 0) > 0);

    if (!hasRequirements) {
        return (
            <div className="flex flex-col items-center justify-center py-10 text-center">
                <IconCircleX className="mb-2 size-8 text-muted-foreground/40" />
                <p className="text-sm font-medium text-card-foreground">
                    شرایط احرازی ثبت نشده است.
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    این نقش محدودیت تحصیلی، سابقه یا مهارتی ندارد.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <RequirementRow
                icon={<IconSchool />}
                label="حداقل مقطع تحصیلی"
            >
                {requirements?.min_education != null
                    ? (EDUCATION_LEVELS[requirements.min_education] ??
                      requirements.min_education)
                    : "تعیین نشده"}
            </RequirementRow>

            <RequirementRow
                icon={<IconBriefcase />}
                label="حداقل سابقه کار"
            >
                <span>
                    مرتبط:{" "}
                    {requirements?.min_related_experience_years != null
                        ? `${requirements.min_related_experience_years} سال`
                        : "تعیین نشده"}
                </span>
                <span className="mx-2 text-muted-foreground">•</span>
                <span>
                    غیرمرتبط:{" "}
                    {requirements?.min_unrelated_experience_years != null
                        ? `${requirements.min_unrelated_experience_years} سال`
                        : "تعیین نشده"}
                </span>
            </RequirementRow>

            {(fieldOfStudyLabels.length > 0 || requirements?.min_education != null) && (
                <RequirementRow
                    icon={<IconBook2 />}
                    label="رشته تحصیلی"
                >
                    {fieldOfStudyLabels.length > 0 ? (
                        <ChipList items={fieldOfStudyLabels} />
                    ) : (
                        "همه رشته‌ها"
                    )}
                </RequirementRow>
            )}

            {(requirements?.required_skills?.length ?? 0) > 0 && (
                <RequirementRow
                    icon={<IconChecklist />}
                    label="مهارت‌های لازم"
                >
                    <ChipList items={requirements!.required_skills!} />
                </RequirementRow>
            )}

            {(requirements?.preferred_skills?.length ?? 0) > 0 && (
                <RequirementRow
                    icon={<IconChecklist />}
                    label="مهارت‌های ترجیحی"
                >
                    <ChipList items={requirements!.preferred_skills!} />
                </RequirementRow>
            )}

            {(requirements?.certifications?.length ?? 0) > 0 && (
                <RequirementRow
                    icon={<IconAward />}
                    label="گواهینامه‌ها"
                >
                    <ChipList items={requirements!.certifications!} />
                </RequirementRow>
            )}

            {(requirements?.description?.trim().length ?? 0) > 0 && (
                <>
                    <Separator />
                    <div className="flex items-start gap-2">
                        <IconNotes className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                            {requirements!.description}
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}
