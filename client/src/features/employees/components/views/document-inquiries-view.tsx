import type { ReactNode } from "react";
import { IconCalendarEvent, IconShieldCheck, IconUser } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { DocumentFileItem } from "@/components/documents";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import type { Employee } from "@/features/employees/types";
import { toPersianDate } from "@/lib/date-format";
import { cn } from "@/lib/utils";

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

/** Badge variant per inquiry status value; falls back to outline. */
const STATUS_VARIANTS: Record<string, "secondary" | "success" | "destructive"> = {
    pending: "secondary",
    received: "success",
    mismatch: "destructive",
};

function StatusBadge({
    value,
    options,
}: {
    value: unknown;
    options: { value: string; label: string }[] | undefined;
}) {
    if (typeof value !== "string" || value === "") {
        return <Badge variant="outline">بدون وضعیت</Badge>;
    }

    const label =
        options?.find((option) => option.value === value)?.label ?? value;

    return (
        <Badge variant={STATUS_VARIANTS[value] ?? "outline"}>{label}</Badge>
    );
}

function AuditLine({ entry }: { entry: InquiryEntry | undefined }) {
    const name =
        typeof entry?.updated_by_name === "string"
            ? entry.updated_by_name
            : "";
    const role =
        typeof entry?.updated_by_role === "string" &&
        entry.updated_by_role !== ""
            ? entry.updated_by_role
            : "";
    const date =
        typeof entry?.updated_at === "string" ? entry.updated_at : "";

    if (!name && !date) return null;

    return (
        <div className="rounded-md bg-muted/40 px-3 py-2 space-y-1.5">
            <p className="text-xs font-medium text-muted-foreground">
                آخرین به‌روزرسانی
            </p>
            <div className="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-3">
                {name && (
                    <span className="inline-flex items-center gap-1.5 text-xs">
                        <IconUser className="size-3.5 shrink-0" />
                        {name}
                    </span>
                )}
                {role && (
                    <span className="inline-flex items-center gap-1.5 text-xs">
                        <IconShieldCheck className="size-3.5 shrink-0" />
                        {role}
                    </span>
                )}
                {date && (
                    <span className="inline-flex items-center gap-1.5 text-xs" dir="ltr">
                        <IconCalendarEvent className="size-3.5 shrink-0" />
                        {toPersianDate(date)}
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * Read-only view of the document-inquiries section (review tab + profile
 * view). Education entries are listed in degree order with color-coded
 * status badges and the last-update audit line per node.
 */
export function DocumentInquiriesView({
    employee,
    data,
    title = "استعلام مدارک",
    action,
    extra,
}: DocumentInquiriesViewProps) {
    const section = (data ??
        (employee.section_document_inquiries ?? {})) as InquiriesData;
    const inquiries = section.inquiries ?? {};

    const { data: statusOptions } = useFormOptionsByGroup("inquiry_status");
    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);

    const resultDocsFor = (fieldKey: string) =>
        getDocumentsBySlug(INQUIRY_RESULT_SLUG, fieldKey);

    const renderEntry = (
        label: string,
        fieldKey: string,
        entry: InquiryEntry | undefined,
    ) => {
        const docs = resultDocsFor(fieldKey);
        const note = typeof entry?.note === "string" ? entry.note : "";
        const hasContent =
            (typeof entry?.status === "string" && entry.status !== "") ||
            note !== "" ||
            docs.length > 0 ||
            entry?.updated_at != null;

        if (!hasContent) return null;

        return (
            <div key={fieldKey} className="rounded-lg border p-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-medium">{label}</p>
                    <StatusBadge value={entry?.status} options={statusOptions} />
                </div>

                <SectionRow hideEmpty label="توضیحات" value={note || null} />

                <AuditLine entry={entry} />

                {docs.length > 0 && (
                    <div className="space-y-1 pt-1 border-t">
                        {docs.map((doc) => (
                            <DocumentFileItem
                                key={doc.usage_id}
                                uuid={String(employee.id)}
                                entity="employees"
                                doc={doc}
                                className="rounded-md px-3 py-2"
                            />
                        ))}
                    </div>
                )}
            </div>
        );
    };

    const educationEntries = Object.entries(inquiries.education ?? {}).sort(
        ([a], [b]) => Number(a) - Number(b),
    );

    const nodes = [
        ...educationEntries.map(([index, entry]) => ({
            label: `استعلام مدرک تحصیلی ${Number(index) + 1}`,
            fieldKey: `inq-edu-${index}`,
            entry,
        })),
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
    ];

    const rendered = nodes.map(({ label, fieldKey, entry }) =>
        renderEntry(label, fieldKey, entry),
    );

    return (
        <Card>
            <CardContent className="space-y-6">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {action}
                </div>

                {rendered.every((node) => node === null) ? (
                    <p className="text-sm text-muted-foreground">
                        استعلامی ثبت نشده است.
                    </p>
                ) : (
                    <div
                        className={cn(
                            "grid grid-cols-1 content-start gap-4",
                            nodes.length > 2 && "md:grid-cols-2",
                        )}
                    >
                        {rendered}
                    </div>
                )}
                {extra}
            </CardContent>
        </Card>
    );
}
