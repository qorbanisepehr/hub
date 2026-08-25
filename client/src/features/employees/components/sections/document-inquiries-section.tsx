import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Textarea } from "@/components/ui/textarea";
import {
    FormOptionSelectField,
} from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
};

type EducationRecordRow = {
    degree?: unknown;
};

const RESULT_UPLOAD = {
    categorySlug: DOC_CATEGORY_SLUGS.INQUIRY_RESULT,
} as const;

function InquiryResultUpload({ uuid, fieldKey }: { uuid: string; fieldKey: string }) {
    return (
        <FileUploadField
            uuid={uuid}
            entity="employees"
            {...RESULT_UPLOAD}
            label="نتیجه استعلام"
            variant="card"
            multiple
            fieldKey={fieldKey}
            sectionKey="document_inquiries"
        />
    );
}

/**
 * Document inquiries (استعلام مدارک) — HR-side tracking of external
 * verification requests. One sub-card per education degree (keyed by its row
 * index so results attach to `inq-edu-{index}`), plus fixed criminal-record
 * and social-insurance nodes. Fields are intentionally minimal; the shape is
 * expected to evolve.
 */
export function DocumentInquiriesSection({ form, uuid }: SectionProps) {
    const { data: degreeOptions } = useFormOptionsByGroup("degree");

    const records =
        (
            form.state.values.education as
                | { education_records?: EducationRecordRow[] }
                | undefined
        )?.education_records ?? [];

    const degreeLabel = (value: unknown, index: number) => {
        const label = degreeOptions?.find(
            (option) => option.value === value,
        )?.label;
        return label ?? `مدرک ${index + 1}`;
    };

    const renderEntryFields = (
        name: string,
        placeholder: string,
    ) => (
        <>
            <form.Field name={`${name}.status`}>
                {(f) => (
                    <FormOptionSelectField
                        field={f}
                        label="وضعیت استعلام"
                        group="inquiry_status"
                        placeholder={placeholder}
                    />
                )}
            </form.Field>
            <form.Field name={`${name}.note`}>
                {(f) => (
                    <Field>
                        <FieldLabel htmlFor={f.name}>توضیحات</FieldLabel>
                        <Textarea
                            id={f.name}
                            value={(f.state.value as string) ?? ""}
                            onBlur={f.handleBlur}
                            onChange={(e) =>
                                f.handleChange(e.target.value)
                            }
                            rows={2}
                        />
                    </Field>
                )}
            </form.Field>
        </>
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>استعلام مدارک</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
                    <p className="text-sm font-medium text-muted-foreground">
                        استعلام مدارک تحصیلی
                    </p>
                    {records.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            سوابق تحصیلی ثبت نشده است؛ با افزودن سابقه در بخش
                            «سوابق تحصیلی»، استعلام هر مدرک اینجا ساخته می‌شود.
                        </p>
                    )}
                    {records.map((record, index) => (
                        <div
                            key={index}
                            className="rounded-lg border p-4 space-y-4"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">
                                    {`استعلام ${degreeLabel(record.degree, index)}`}
                                </span>
                            </div>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {renderEntryFields(
                                    `document_inquiries.inquiries.education.${index}`,
                                    "انتخاب کنید",
                                )}
                                <InquiryResultUpload
                                    uuid={uuid}
                                    fieldKey={`inq-edu-${index}`}
                                />
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
                    <p className="text-sm font-medium text-muted-foreground">
                        گواهی عدم سوء پیشینه
                    </p>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {renderEntryFields(
                            "document_inquiries.inquiries.criminal_record",
                            "انتخاب کنید",
                        )}
                        <InquiryResultUpload
                            uuid={uuid}
                            fieldKey="inq-criminal-record"
                        />
                    </div>
                </div>

                <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
                    <p className="text-sm font-medium text-muted-foreground">
                        استعلام بیمه تأمین اجتماعی
                    </p>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {renderEntryFields(
                            "document_inquiries.inquiries.social_insurance",
                            "انتخاب کنید",
                        )}
                        <InquiryResultUpload
                            uuid={uuid}
                            fieldKey="inq-social-insurance"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
