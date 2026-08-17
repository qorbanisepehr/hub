import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DocumentFileItem } from "@/components/documents";
import { SectionRow } from "@/components/shared/section-row";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { toPersianDate } from "@/lib/date-format";
import type { Employee } from "@/features/employees/types";

type SocialInsuranceHistory = {
    workshop_name?: string;
    workshop_code?: string;
    job_title?: string;
    start_date?: string;
    end_date?: string;
    description?: string;
};

type SocialInsuranceData = {
    social_insurance_number?: string;
    insurance_status?: string;
    insurance_start_date?: string;
    has_insurance_history?: boolean;
    histories?: SocialInsuranceHistory[];
};

type SocialInsuranceViewProps = {
    employee: Employee;
    data?: SocialInsuranceData;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function SocialInsuranceView({
    employee,
    data,
    title = "بیمه تأمین اجتماعی",
    action,
    extra,
}: SocialInsuranceViewProps) {
    const section =
        (data ?? (employee.section_social_insurance ?? {})) as SocialInsuranceData;

    const histories = Array.isArray(section.histories) ? section.histories : [];

    const hasHistory = section.has_insurance_history === true;

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);

    const insuranceDocuments = getDocumentsBySlug("insurance-history");

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
                        value={section.insurance_status}
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
                        label="سابقه بیمه"
                        value={hasHistory ? "دارد" : "ندارد"}
                    />
                </div>

                {hasHistory && histories.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium">سوابق بیمه</h3>

                        <div className="space-y-3">
                            {histories.map((history, index) => (
                                <Card
                                    key={`${history.start_date ?? "history"}-${index}`}
                                    className="border bg-muted/20"
                                >
                                    <CardContent className="p-4">
                                        <div className="divide-y">
                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="کارگاه / کارفرما"
                                                value={
                                                    history.workshop_name
                                                }
                                            />

                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="کد کارگاه"
                                                value={
                                                    history.workshop_code
                                                }
                                            />

                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="عنوان شغلی"
                                                value={history.job_title}
                                            />

                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="از تاریخ"
                                                value={toPersianDate(
                                                    history.start_date,
                                                )}
                                            />

                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="تا تاریخ"
                                                value={
                                                    history.end_date
                                                        ? toPersianDate(
                                                              history.end_date,
                                                          )
                                                        : "ادامه دارد"
                                                }
                                            />

                                            <SectionRow
                                                variant="between"
                                                hideEmpty
                                                label="توضیحات"
                                                value={
                                                    history.description
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
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
