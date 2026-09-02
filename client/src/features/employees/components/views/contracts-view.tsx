import type { ReactNode } from "react";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "@/components/section-views/section-card";
import { dateValue } from "@/components/section-views/shared";
import { DocumentFileItem } from "@/components/documents";
import type { Employee } from "@/features/employees/types";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";

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

    const rowDocs = (index: number) =>
        getDocumentsBySlug(DOC_CATEGORY_SLUGS.CONTRACT, `con-${index}`);

    const hasAnyDoc = contracts.some(
        (_row, index) => rowDocs(index).length > 0,
    );

    return (
        <SectionCard title={title} action={action}>
            <SectionRepeaterTable
                items={contracts}
                emptyLabel="دوره‌ای قراردادی ثبت نشده است."
                columns={[
                    { label: "از تاریخ", render: (i) => dateValue(i.start_date) },
                    { label: "تا تاریخ", render: (i) => dateValue(i.end_date) },
                ]}
            />

            {hasAnyDoc && (
                <div className="space-y-4">
                    <h3 className="text-sm font-medium">اسکن قراردادها</h3>

                    <div className="space-y-4">
                        {contracts.map((_row, index) => {
                            const documents = rowDocs(index);

                            if (documents.length === 0) return null;

                            return (
                                <div
                                    key={`con-${index}`}
                                    className="space-y-2"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {`قرارداد ${index + 1}`}
                                    </p>
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
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
            {extra}
        </SectionCard>
    );
}