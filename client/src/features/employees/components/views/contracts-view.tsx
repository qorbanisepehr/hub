import type { ReactNode } from "react";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "@/components/section-views/section-card";
import { dateValue } from "@/components/section-views/shared";
import { RepeaterAttachmentCell } from "@/components/forms";
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

    return (
        <SectionCard title={title} action={action}>
            <SectionRepeaterTable
                items={contracts}
                emptyLabel="دوره‌ای قراردادی ثبت نشده است."
                columns={[
                    {
                        label: "از تاریخ",
                        render: (item) => dateValue(item.start_date),
                    },
                    {
                        label: "تا تاریخ",
                        render: (item) => dateValue(item.end_date),
                    },
                    {
                        label: "پیوست",
                        render: (_item, index) => (
                            <RepeaterAttachmentCell
                                docs={rowDocs(index)}
                                enablePreview
                            />
                        ),
                    },
                ]}
            />
            {extra}
        </SectionCard>
    );
}
