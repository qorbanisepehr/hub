import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormOptionSelectField,
    FormRepeater,
    FormTextField,
} from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { dependentRowLabel } from "@/features/employees/dependents-docs";
import { useDependentDocsFeedback } from "@/features/employees/hooks/use-dependent-docs-feedback";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

/**
 * Dependents (بستگان و افراد تحت تکفل) section. Each row carries its own
 * document uploads placed under `section_key="dependents"` with
 * `field_key="dependent-{index}"`; page counts come from the requirements
 * endpoint, never hardcoded here.
 */
export function DependentsSection({ form, uuid, onPersist }: SectionProps) {
    const { isLoading: docsLoading, relationshipOptions, getMissing } =
        useDependentDocsFeedback(uuid, []);

    return (
        <Card>
            <CardHeader>
                <CardTitle>بستگان و افراد تحت تکفل</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <form.Field name="dependents.dependents">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="بستگان"
                            emptyMessage="هنوز وابسته‌ای اضافه نشده است."
                            onPersist={onPersist}
                            renderHeader={(item, index) => (
                                <span className="font-medium">
                                    {dependentRowLabel(
                                        item.relationship_type,
                                        index,
                                        relationshipOptions,
                                    )}
                                    {item.first_name || item.last_name
                                        ? `: ${String(item.first_name ?? "")} ${String(item.last_name ?? "")}`.trim()
                                        : ""}
                                </span>
                            )}
                            renderItem={(index) => {
                                const missing = getMissing(index);

                                return (
                                    <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <form.Field
                                            name={`dependents.dependents.${index}.relationship_type`}
                                        >
                                            {(f) => (
                                                <FormOptionSelectField
                                                    field={f}
                                                    label="نسبت"
                                                    group="relationship_type"
                                                    placeholder="انتخاب کنید"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`dependents.dependents.${index}.first_name`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="نام"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`dependents.dependents.${index}.last_name`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="نام خانوادگی"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`dependents.dependents.${index}.id_number`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="کد ملی"
                                                    dir="ltr"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`dependents.dependents.${index}.gender`}
                                        >
                                            {(f) => (
                                                <FormOptionSelectField
                                                    field={f}
                                                    label="جنسیت"
                                                    group="gender"
                                                    placeholder="انتخاب کنید"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`dependents.dependents.${index}.birth_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ تولد"
                                                />
                                            )}
                                        </form.Field>
                                    </div>

                                    <div className="rounded-lg border bg-muted/30 p-4">
                                        <p className="mb-3 text-sm font-medium text-muted-foreground">
                                            مدارک این وابسته
                                        </p>
                                        {!docsLoading && missing.length > 0 && (
                                            <p className="mb-3 text-xs font-medium text-destructive">
                                                مدارک ناقص:{" "}
                                                {missing
                                                    .map(
                                                        ({ label, count, min }) =>
                                                            `${label} (${count} از ${min})`,
                                                    )
                                                    .join("، ")}
                                            </p>
                                        )}
                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <FileUploadField
                                                uuid={uuid}
                                                entity="employees"
                                                categorySlug="national-card"
                                                label="کارت ملی"
                                                variant="card"
                                                multiple
                                                fieldKey={`dependent-${index}`}
                                                sectionKey="dependents"
                                            />
                                            <FileUploadField
                                                uuid={uuid}
                                                entity="employees"
                                                categorySlug="birth-certificate"
                                                label="شناسنامه"
                                                variant="card"
                                                multiple
                                                fieldKey={`dependent-${index}`}
                                                sectionKey="dependents"
                                            />
                                        </div>
                                    </div>
                                </div>
                            );
                        }}
                    />
                )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
