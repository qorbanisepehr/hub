import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormRepeater,
} from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { repeaterAttachmentColumn } from "@/components/forms";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { toPersianDate } from "@/lib/date-format";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

const PERIOD_LABEL = "—";

/** Row title from the contract period, e.g. «۱۴۰۲/۰۳/۱۱ — ۱۴۰۳/۰۳/۱۰». */
function contractRowTitle(item: Record<string, unknown>): string {
    const start = toPersianDate(String(item.start_date ?? ""));
    const end = toPersianDate(String(item.end_date ?? ""));

    if (item.start_date && item.end_date) {
        return `${start} ${PERIOD_LABEL} ${end}`;
    }
    if (item.start_date) {
        return `از ${start}`;
    }
    if (item.end_date) {
        return `تا ${end}`;
    }
    return "قرارداد";
}

/**
 * Contracts (قراردادها) section. Each row holds a contract start/end period
 * and its own document group at `con-{index}` under the `contract` category
 * (per-row scans, capped at 5).
 */
export function ContractsSection({ form, uuid, onPersist }: SectionProps) {
    const { getDocumentsBySlug } = useEntityDocuments("employees", uuid);

    const columns: TableColumn[] = [
        { key: "start_date", label: "از تاریخ", type: "date" },
        { key: "end_date", label: "تا تاریخ", type: "date" },
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.CONTRACT,
            fieldKeyPrefix: "con-",
            getDocumentsBySlug,
        }),
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>قراردادها</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <form.Field name="contracts.contracts">
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="قراردادها"
                            columns={columns}
                            emptyMessage="هنوز دوره‌ای قراردادی اضافه نشده است."
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                start_date: item.start_date,
                                end_date: item.end_date,
                            })}
                            renderHeader={(item) => (
                                <span className="font-medium">
                                    {contractRowTitle(item)}
                                </span>
                            )}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <form.Field
                                            name={`contracts.contracts.${index}.start_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ شروع"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`contracts.contracts.${index}.end_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ پایان"
                                                />
                                            )}
                                        </form.Field>
                                    </div>

                                    <FileUploadField
                                        uuid={uuid}
                                        entity="employees"
                                        categorySlug={DOC_CATEGORY_SLUGS.CONTRACT}
                                        label="اسکن قرارداد"
                                        variant="card"
                                        multiple
                                        maxFiles={5}
                                        fieldKey={`con-${index}`}
                                        sectionKey="contracts"
                                        accept="application/pdf,image/jpeg,image/png,image/webp"
                                    />
                                </div>
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}