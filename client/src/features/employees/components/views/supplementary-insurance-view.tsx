import type { ReactNode } from "react";
import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "@/components/section-views/section-card";
import { DocumentFileItem } from "@/components/documents";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import type { Employee } from "@/features/employees/types";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { useOptionLabelResolver } from "@/components/section-views/use-option-label";

type InsuranceDependentRow = {
    first_name?: string;
    last_name?: string;
    relationship?: string;
    note?: string;
};

type SupplementaryInsuranceData = {
    selected_bank_account?: string;
    insurance_dependents?: InsuranceDependentRow[];
};

type SupplementaryInsuranceViewProps = {
    employee: Employee;
    data?: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function SupplementaryInsuranceView({
    employee,
    data,
    title = "بیمه تکمیلی",
    action,
    extra,
}: SupplementaryInsuranceViewProps) {
    const section = (data ??
        (employee.section_supplementary_insurance ?? {})) as SupplementaryInsuranceData;

    const dependents = Array.isArray(section.insurance_dependents)
        ? section.insurance_dependents
        : [];

    const resolveRelationship = useOptionLabelResolver("relationship_type");

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);
    const documents = getDocumentsBySlug(
        DOC_CATEGORY_SLUGS.SUPPLEMENTARY_INSURANCE_FORM,
    );

    // Resolve the stored account number back to a readable
    // "{bank} — {account}" label from the financial section.
    const accounts = Array.isArray(
        (employee.section_financial ?? {}).bank_accounts,
    )
        ? ((employee.section_financial as Record<string, unknown>)
              .bank_accounts as {
              bank_name?: string;
              account_number?: string;
          }[])
        : [];

    const selectedAccount = accounts.find(
        (account) =>
            String(account.account_number) ===
            String(section.selected_bank_account),
    );

    const selectedAccountLabel = selectedAccount
        ? `${selectedAccount.bank_name ?? ""} — ${selectedAccount.account_number ?? ""}`.trim()
        : (section.selected_bank_account ?? "");

    return (
        <SectionCard title={title} action={action}>
            <div className="divide-y">
                <SectionRow
                    variant="between"
                    hideEmpty
                    label="حساب بانکی بیمه"
                    value={selectedAccountLabel}
                />
            </div>

            <SectionRepeaterTable
                items={dependents}
                emptyLabel="فردی تحت پوشش ثبت نشده است."
                columns={[
                    {
                        label: "نام",
                        render: (i) =>
                            `${String(i.first_name ?? "")} ${String(i.last_name ?? "")}`.trim(),
                    },
                    {
                        label: "نسبت",
                        render: (i) =>
                            resolveRelationship(
                                typeof i.relationship === "string"
                                    ? i.relationship
                                    : undefined,
                            ),
                    },
                    {
                        label: "توضیحات",
                        render: (i) =>
                            typeof i.note === "string" && i.note !== ""
                                ? i.note
                                : null,
                    },
                ]}
            />

            <div className="space-y-3">
                <h3 className="text-sm font-medium">فرم بیمه تکمیلی</h3>

                {documents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        مدرکی برای بیمه تکمیلی بارگذاری نشده است.
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