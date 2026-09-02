import type { ReactNode } from "react";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "@/components/section-views/section-card";
import { dateValue } from "@/components/section-views/shared";
import type { Employee } from "@/features/employees/types";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { DocumentFileItem } from "@/components/documents";

type ContractRow = {
    start_date?: string;
    end_date?: string;
};

type ContractsViewProps = {
    employee: Employee;
    data?: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function ContractsView({
    employee,
    data,
    title = "قراردادها",
    action,
    extra,
}: ContractsViewProps) {
    const section = data ?? (employee.section_contracts ?? {});
    const contracts = Array.isArray(section.contracts)
        ? (section.contracts as ContractRow[])
        : [];

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);
    const documents = getDocumentsBySlug("contract");

    return (
        <SectionCard title={title} action={action}>
            <SectionRepeaterTable
                items={contracts}
                emptyLabel="دوره‌ای قراردادی ثبت نشده است."
                columns={[
                    { label: "تاریخ شروع", render: (i) => dateValue(i.start_date) },
                    { label: "تاریخ پایان", render: (i) => dateValue(i.end_date) },
                ]}
            />

            <div className="space-y-3">
                <h3 className="text-sm font-medium">قراردادها</h3>

                {documents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        مدرکی برای قرارداد بارگذاری نشده است.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-4">
                        {documents.map((document) => (
                            <DocumentFileItem
                                key={document.usage_id}
                                uuid={String(employee.id)}
                                entity="employees"
                                doc={document}
                                layout="compact"
                                thumbnailSize="size-20"
                                actionsEnabled={false}
                                label={document.structure_name}
                            />
                        ))}
                    </div>
                )}
            </div>
            {extra}
        </SectionCard>
    );
}