import type { ReactNode } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { DocumentFileItem } from "@/components/documents";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import type { Employee } from "@/features/employees/types";
import { toPersianDate } from "@/lib/date-format";

type InquiryEntry = {
    status?: unknown;
    note?: unknown;
    /** Audit metadata stamped server-side on every content change. */
    updated_by_name?: unknown;
    updated_by_role?: unknown;
    updated_at?: unknown;
};

type InquiriesData = {
    inquiries?: {
        education?: Record<string, InquiryEntry>;
        criminal_record?: InquiryEntry;
        social_insurance?: InquiryEntry;
    };
};

type DocumentInquiriesViewProps = {
    employee: Employee;
    data?: InquiriesData;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

const INQUIRY_RESULT_SLUG = "inquiry-result";

function inquiryStatusLabel(
    value: unknown,
    options: { value: string; label: string }[] | undefined,
): string | null {
    if (typeof value !== "string" || value === "") return null;
    return options?.find((option) => option.value === value)?.label ?? value;
}

/**
 * Read-only view of the document-inquiries section (review tab + profile
 * view). Education entries are listed in degree order; each node lists its
 * result documents from `inq-edu-{index}` / fixed placements.
 */
export function DocumentInquiriesView({
    employee,
    data,
    title = "استعلام مدارک",
    action,
    extra,
}: DocumentInquiriesViewProps) {
    const section = (data ?? (employee.section_document_inquiries ?? {})) as InquiriesData;
    const inquiries = section.inquiries ?? {};

    const { data: statusOptions } = useFormOptionsByGroup("inquiry_status");
    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);

    const resultDocsFor = (fieldKey: string) =>
        getDocumentsBySlug(INQUIRY_RESULT_SLUG, fieldKey);

    const renderEntry = (
        label: string,
        fieldKey: string,
        entry: InquiryEntry | undefined,
        uuid: string,
    ) => {
        const statusLabel = inquiryStatusLabel(entry?.status, statusOptions);
        const docs = resultDocsFor(fieldKey);
        const note = typeof entry?.note === "string" ? entry.note : "";
        const updatedBy = typeof entry?.updated_by_name === "string" ? entry.updated_by_name : "";
        const updatedRole =
            typeof entry?.updated_by_role === "string" && entry.updated_by_role !== ""
                ? ` (${entry.updated_by_role})`
                : "";
        const updatedAt =
            typeof entry?.updated_at === "string"
                ? toPersianDate(entry.updated_at)
                : null;

        return (
            <div key={fieldKey} className="py-3 first:pt-0">
                <p className="text-sm font-medium">{label}</p>
                <Card className="mt-2 border-0 shadow-none">
                    <CardContent className="space-y-1 p-0">
                        <SectionRow hideEmpty label="وضعیت" value={statusLabel} />
                        <SectionRow hideEmpty label="توضیحات" value={note || null} />
                        {(updatedBy || updatedAt) && (
                            <SectionRow
                                hideEmpty
                                label="آخرین به‌روزرسانی"
                                value={
                                    [`${updatedBy}${updatedRole}`, updatedAt]
                                        .filter(Boolean)
                                        .join(" — ") || null
                                }
                            />
                        )}
                        {docs.map((doc) => (
                            <DocumentFileItem
                                key={doc.usage_id}
                                uuid={uuid}
                                entity="employees"
                                doc={doc}
                                className="rounded-md border px-3 py-2"
                            />
                        ))}
                    </CardContent>
                </Card>
            </div>
        );
    };

    const educationEntries = Object.entries(inquiries.education ?? {}).sort(
        ([a], [b]) => Number(a) - Number(b),
    );
    const fixedEntries = [
        {
            label: "استعلام عدم سوء پیشینه",
            fieldKey: "inq-criminal-record",
            entry: inquiries.criminal_record,
        },
        {
            label: "استعلام بیمه تأمین اجتماعی",
            fieldKey: "inq-social-insurance",
            entry: inquiries.social_insurance,
        },
    ].filter(
        ({ entry }) =>
            entry != null &&
            ((typeof entry.status === "string" && entry.status !== "") ||
                (typeof entry.note === "string" && entry.note !== "")),
    );

    const isEmpty =
        educationEntries.length === 0 && fixedEntries.length === 0;

    return (
        <Card>
            <CardContent className="space-y-6">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {action}
                </div>

                {isEmpty ? (
                    <p className="text-sm text-muted-foreground">
                        استعلامی ثبت نشده است.
                    </p>
                ) : (
                    <div className="divide-y rounded-lg border p-4">
                        {educationEntries.map(([index, entry]) =>
                            renderEntry(
                                `استعلام مدرک تحصیلی ${Number(index) + 1}`,
                                `inq-edu-${index}`,
                                entry,
                                String(employee.id),
                            ),
                        )}
                        {fixedEntries.map(({ label, fieldKey, entry }) =>
                            renderEntry(
                                label,
                                fieldKey,
                                entry,
                                String(employee.id),
                            ),
                        )}
                    </div>
                )}
                {extra}
            </CardContent>
        </Card>
    );
}
