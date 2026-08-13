import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DocumentFileItem } from "@/components/shared/document-file-item";
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
    insurance_status?: string;
    insurance_start_date?: string;
    has_insurance_history?: boolean;
    histories?: SocialInsuranceHistory[];
};

type SocialInsuranceViewProps = {
    employee: Employee;
};

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    if (
        value === null ||
        value === undefined ||
        value === "" ||
        value === "-"
    ) {
        return null;
    }

    return (
        <div className="flex items-start justify-between gap-6 py-3">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm text-end">{value}</span>
        </div>
    );
}

function formatDate(value?: string): string {
    if (!value) return "-";

    try {
        return toPersianDate(value);
    } catch {
        return value;
    }
}

export function SocialInsuranceView({ employee }: SocialInsuranceViewProps) {
    const section = (employee.section_social_insurance ??
        {}) as SocialInsuranceData;

    const histories = Array.isArray(section.histories) ? section.histories : [];

    const hasHistory = section.has_insurance_history === true;

    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);

    const insuranceDocuments = getDocumentsBySlug("insurance-history");

    return (
        <Card>
            <CardHeader>
                <CardTitle>بیمه تأمین اجتماعی</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="divide-y">
                    <Row
                        label="شماره بیمه"
                        value={employee.social_insurance_number ?? "-"}
                    />

                    <Row
                        label="وضعیت بیمه"
                        value={section.insurance_status ?? "-"}
                    />

                    <Row
                        label="تاریخ شروع بیمه"
                        value={formatDate(section.insurance_start_date)}
                    />

                    <Row
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
                                            <Row
                                                label="کارگاه / کارفرما"
                                                value={
                                                    history.workshop_name ?? "-"
                                                }
                                            />

                                            <Row
                                                label="کد کارگاه"
                                                value={
                                                    history.workshop_code ?? "-"
                                                }
                                            />

                                            <Row
                                                label="عنوان شغلی"
                                                value={history.job_title ?? "-"}
                                            />

                                            <Row
                                                label="از تاریخ"
                                                value={formatDate(
                                                    history.start_date,
                                                )}
                                            />

                                            <Row
                                                label="تا تاریخ"
                                                value={
                                                    history.end_date
                                                        ? formatDate(
                                                              history.end_date,
                                                          )
                                                        : "ادامه دارد"
                                                }
                                            />

                                            <Row
                                                label="توضیحات"
                                                value={
                                                    history.description ?? "-"
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
        </Card>
    );
}
