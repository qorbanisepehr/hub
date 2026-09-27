import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DocumentFileItem } from "@/components/documents";
import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { useOptionLabelResolver } from "@/components/section-views/use-option-label";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { toPersianDate } from "@/lib/date-format";
import type { Employee } from "@/features/employees/types";
import {
    DOC_CATEGORY_SLUGS,
    JALALI_MONTH_OPTIONS,
} from "@/features/questionnaire/constants";

type SocialInsuranceHistory = {
    workshop_name?: string;
    workshop_code?: string;
    job_title?: string;
    start_date?: string;
    end_date?: string;
    description?: string;
    monthly_breakdown?: MonthlyBreakdownRow[];
};

type MonthlyBreakdownRow = {
    month?: string;
    days?: number | string;
    wage?: string;
};

type JobTitleRow = {
    insurance_number?: string;
    start_date?: string;
    job_title?: string;
    workshop_code?: string;
    workshop_name?: string;
};

type SocialInsuranceData = {
    social_insurance_number?: string;
    insurance_status?: string;
    insurance_start_date?: string;
    has_insurance_history?: boolean;
    branch_name?: string;
    days_count?: number | string;
    job_titles?: JobTitleRow[];
    histories?: SocialInsuranceHistory[];
};

type SocialInsuranceViewProps = {
    employee: Employee;
    data?: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

/** Display label for a stored Jalali month value (falls back to the raw value). */
function monthLabel(value: unknown): string {
    if (typeof value !== "string" || value === "") return "-";
    return (
        JALALI_MONTH_OPTIONS.find((option) => option.value === value)?.label ??
        value
    );
}

/** Expanded row: this history's monthly breakdown + description. */
const renderHistoryDetail = (item: Record<string, unknown>) => {
    const breakdown = Array.isArray(item.monthly_breakdown)
        ? item.monthly_breakdown
        : [];

    return (
        <div className="space-y-4 p-4">
            {typeof item.description === "string" &&
                item.description !== "" && (
                    <SectionRow
                        variant="between"
                        label="توضیحات"
                        value={item.description}
                    />
                )}

            <div className="space-y-2">
                <p className="text-xs font-medium text-muted-foreground">
                    تفکیک ماهانه
                </p>
                <SectionRepeaterTable
                    items={breakdown}
                    emptyLabel="ماهی ثبت نشده است."
                    columns={[
                        {
                            label: "ماه",
                            render: (row) => monthLabel(row.month),
                        },
                        {
                            label: "روز",
                            render: (row) =>
                                row.days === null || row.days === undefined
                                    ? "-"
                                    : String(row.days),
                        },
                        {
                            label: "دستمزد",
                            render: (row) => row.wage,
                        },
                    ]}
                />
            </div>
        </div>
    );
};

export function SocialInsuranceView({
    employee,
    data,
    title = "بیمه تأمین اجتماعی",
    action,
    extra,
}: SocialInsuranceViewProps) {
    const section = (data ??
        employee.section_social_insurance ??
        {}) as SocialInsuranceData;

    const histories = Array.isArray(section.histories) ? section.histories : [];

    const hasHistory = section.has_insurance_history === true;

    const resolveInsuranceStatus = useOptionLabelResolver("insurance_type");

    const jobTitles = Array.isArray(section.job_titles)
        ? section.job_titles
        : [];

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);

    const insuranceDocuments = [
        ...getDocumentsBySlug(DOC_CATEGORY_SLUGS.INSURANCE_HISTORY),
        ...getDocumentsBySlug(DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_RIAL),
        ...getDocumentsBySlug(DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_SUMMARY),
        ...getDocumentsBySlug(
            DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_RIAL_SUMMARY,
        ),
        ...getDocumentsBySlug(DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_OVERALL),
        ...getDocumentsBySlug(DOC_CATEGORY_SLUGS.INSURANCE_LAST_JOB_TITLES),
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="divide-y">
                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="شماره بیمه"
                        value={
                            section.social_insurance_number ??
                            employee.social_insurance_number
                        }
                    />

                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="وضعیت بیمه"
                        value={resolveInsuranceStatus(section.insurance_status)}
                    />

                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="تاریخ شروع بیمه"
                        value={toPersianDate(section.insurance_start_date)}
                    />

                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="شعبه بازنشستگی"
                        value={section.branch_name}
                    />

                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="تعداد روزهای بیمه"
                        value={
                            section.days_count === null ||
                            section.days_count === undefined ||
                            section.days_count === ""
                                ? null
                                : String(section.days_count)
                        }
                    />

                    <SectionRow
                        variant="between"
                        hideEmpty
                        label="سابقه بیمه"
                        value={hasHistory ? "دارد" : "ندارد"}
                    />
                </div>

                {hasHistory && histories.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">سوابق بیمه</h3>

                        <SectionRepeaterTable
                            items={histories}
                            emptyLabel="سابقه‌ای ثبت نشده است."
                            columns={[
                                {
                                    label: "کارگاه / کارفرما",
                                    render: (i) => i.workshop_name,
                                },
                                {
                                    label: "کد کارگاه",
                                    render: (i) => i.workshop_code,
                                },
                                {
                                    label: "عنوان شغلی",
                                    render: (i) => i.job_title,
                                },
                                {
                                    label: "از تاریخ",
                                    render: (i) =>
                                        toPersianDate(
                                            i.start_date as
                                                | string
                                                | null
                                                | undefined,
                                        ),
                                },
                                {
                                    label: "تا تاریخ",
                                    render: (i) =>
                                        i.end_date
                                            ? toPersianDate(
                                                  i.end_date as string,
                                              )
                                            : "ادامه دارد",
                                },
                            ]}
                            renderExpandedRow={renderHistoryDetail}
                        />
                    </div>
                )}

                {hasHistory && jobTitles.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">
                            سوابق عناوین شغلی
                        </h3>
                        <SectionRepeaterTable
                            items={jobTitles}
                            emptyLabel="-"
                            columns={[
                                {
                                    label: "شماره بیمه",
                                    render: (i) => i.insurance_number,
                                },
                                {
                                    label: "از تاریخ",
                                    render: (i) =>
                                        i.start_date === null ||
                                        i.start_date === undefined
                                            ? "-"
                                            : toPersianDate(
                                                  i.start_date as string,
                                              ),
                                },
                                {
                                    label: "عنوان شغلی",
                                    render: (i) => i.job_title,
                                },
                                {
                                    label: "کد کارگاه",
                                    render: (i) => i.workshop_code,
                                },
                                {
                                    label: "کارگاه / کارفرما",
                                    render: (i) => i.workshop_name,
                                },
                            ]}
                        />
                    </div>
                )}

                <div className="space-y-3">
                    <h3 className="text-sm font-medium">مدرک سابقه بیمه</h3>

                    {insuranceDocuments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            مدرکی برای سابقه بیمه بارگذاری نشده است.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-4">
                            {insuranceDocuments.map((document) => (
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
            </CardContent>
            {extra}
        </Card>
    );
}
