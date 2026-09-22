import type { ReactNode } from "react";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "@/components/section-views/section-card";
import type { Employee } from "@/features/employees/types";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { DocumentFileItem } from "@/components/documents";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import { formatCardNumber } from "@/lib/date-format";

type BankAccountRow = {
    bank_name?: string;
    account_number?: string;
    card_number?: string;
    shaba_number?: string;
};

type FinancialViewProps = {
    employee: Employee;
    data?: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

const FINANCIAL_DOC_SLUGS = [
    DOC_CATEGORY_SLUGS.PAYSLIP,
    DOC_CATEGORY_SLUGS.SALARY_DEDUCTION_LETTER,
    DOC_CATEGORY_SLUGS.SALARY_DECREE,
    DOC_CATEGORY_SLUGS.INITIAL_SALARY,
    DOC_CATEGORY_SLUGS.SALARY_CHANGE,
    DOC_CATEGORY_SLUGS.FINANCIAL_AFFIDAVIT,
];

export function FinancialView({
    employee,
    data,
    title = "اطلاعات مالی",
    action,
    extra,
}: FinancialViewProps) {
    const section = data ?? (employee.section_financial ?? {});
    const accounts = Array.isArray(section.bank_accounts)
        ? (section.bank_accounts as BankAccountRow[])
        : [];

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);
    const documents = FINANCIAL_DOC_SLUGS.flatMap((slug) =>
        getDocumentsBySlug(slug),
    );

    return (
        <SectionCard title={title} action={action}>
            <SectionRepeaterTable
                items={accounts}
                emptyLabel="حساب بانکی ثبت نشده است."
                columns={[
                    { label: "بانک", render: (i) => i.bank_name },
                    { label: "شماره حساب", render: (i) => i.account_number },
                    {
                        label: "شماره کارت",
                        render: (i) =>
                            formatCardNumber(
                                typeof i.card_number === "string"
                                    ? i.card_number
                                    : null,
                            ),
                    },
                    { label: "شماره شبا", render: (i) => i.shaba_number },
                ]}
            />

            <div className="space-y-3">
                <h3 className="text-sm font-medium">مدارک مالی</h3>

                {documents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        مدرکی برای اطلاعات مالی بارگذاری نشده است.
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