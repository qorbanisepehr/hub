import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DocumentFileItem, MissingDocsBadge } from "@/components/documents";
import { SectionRow } from "@/components/shared/section-row";
import { useDependentDocsFeedback } from "@/features/employees/hooks/use-dependent-docs-feedback";
import { dependentRowLabel } from "@/features/employees/dependents-docs";
import { useOptionLabelResolver } from "@/components/section-views/use-option-label";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { toPersianDate } from "@/lib/date-format";
import type { Employee } from "@/features/employees/types";

type DependentRow = {
    relationship_type?: string;
    first_name?: string;
    last_name?: string;
    id_number?: string;
    gender?: string;
    birth_date?: string;
};

type DependentsData = {
    dependents?: DependentRow[];
};

type DependentsViewProps = {
    employee: Employee;
    data?: DependentsData;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

const DEPENDENT_DOC_SLUGS = ["national-card", "birth-certificate"] as const;

export function DependentsView({
    employee,
    data,
    title = "بستگان و افراد تحت تکفل",
    action,
    extra,
}: DependentsViewProps) {
    const section =
        (data ?? (employee.section_dependents ?? {})) as DependentsData;

    const dependents = Array.isArray(section.dependents)
        ? section.dependents
        : [];

    const { isLoading: documentsLoading, getDocumentsBySlug } =
        useEmployeeDocuments(employee.id);

    // Shared feedback state — same query caches the section and submit
    // guard read, so no extra requests.
    const { isLoading: docsLoading, relationshipOptions, getMissing } =
        useDependentDocsFeedback(employee.id, dependents);

    const resolveRelationship = useOptionLabelResolver("relationship_type");
    const resolveGender = useOptionLabelResolver("gender");

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>

            <CardContent className="space-y-6">
                {dependents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        وابسته‌ای ثبت نشده است.
                    </p>
                ) : (
                    <div className="divide-y">
                        {dependents.map((dependent, index) => {
                            const missing = getMissing(index);

                            return (
                                <div key={index} className="py-4 first:pt-0">
                                    <div className="mb-2 flex items-center justify-between">
                                        <p className="text-sm font-medium">
                                            {dependentRowLabel(
                                                dependent.relationship_type,
                                                index,
                                                relationshipOptions,
                                            )}
                                        </p>
                                        {!docsLoading && (
                                            <MissingDocsBadge
                                                missing={missing}
                                            />
                                        )}
                                    </div>
                                    <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
                                        <SectionRow
                                            label="نسبت"
                                            value={resolveRelationship(
                                                dependent.relationship_type,
                                            )}
                                        />
                                        <SectionRow
                                            label="نام و نام خانوادگی"
                                            value={`${dependent.first_name ?? ""} ${dependent.last_name ?? ""}`.trim()}
                                        />
                                        <SectionRow
                                            label="کد ملی"
                                            value={dependent.id_number}
                                        />
                                        <SectionRow
                                            label="جنسیت"
                                            value={resolveGender(
                                                dependent.gender,
                                            )}
                                        />
                                        <SectionRow
                                            label="تاریخ تولد"
                                            value={
                                                dependent.birth_date
                                                    ? toPersianDate(dependent.birth_date)
                                                    : ""
                                            }
                                        />
                                    </div>

                                    <div className="mt-3 space-y-1">
                                        {DEPENDENT_DOC_SLUGS.flatMap((slug) =>
                                            getDocumentsBySlug(
                                                slug,
                                                `dependent-${index}`,
                                            ),
                                        ).map((doc) => (
                                            <DocumentFileItem
                                                key={doc.usage_id}
                                                uuid={String(employee.id)}
                                                entity="employees"
                                                doc={doc}
                                                className="rounded-md border px-3 py-2"
                                            />
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
                {extra}
            </CardContent>
        </Card>
    );
}
