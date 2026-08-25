import { type ReactNode } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Textarea } from "@/components/ui/textarea";
import { FormOptionSelectField } from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Backend capability: may the actor save this section at all? */
    canUpdate?: boolean;
};

type EducationRecordRow = {
    degree?: unknown;
};

/**
 * One inquiry node: status select + note + result upload. Uses the shared
 * FileUploadField (dropzone, previews, delete) pinned to the node's
 * `inq-*` placement and the inquiry-result category. Shared by the
 * per-degree education rows and the fixed nodes so every placement
 * renders — and gates — identically.
 */
function InquiryNode({
    form,
    uuid,
    name,
    fieldKey,
    title,
    canUpdate = true,
}: {
    form: EmployeeFormApi;
    uuid: string;
    /** Form path of the node, e.g. document_inquiries.inquiries.education.0 */
    name: string;
    fieldKey: string;
    title: string;
    canUpdate?: boolean;
}) {
    return (
        <div className="rounded-lg border p-4 space-y-4">
            <p className="text-sm font-medium">{title}</p>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <form.Field name={`${name}.status`}>
                    {(f) => (
                        <FormOptionSelectField
                            field={f}
                            label="وضعیت استعلام"
                            group="inquiry_status"
                            placeholder="انتخاب کنید"
                            disabled={!canUpdate}
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
                                onChange={(e) => f.handleChange(e.target.value)}
                                rows={2}
                                disabled={!canUpdate}
                            />
                        </Field>
                    )}
                </form.Field>
            </div>

            {canUpdate ? (
                <FileUploadField
                    uuid={uuid}
                    entity="employees"
                    categorySlug={DOC_CATEGORY_SLUGS.INQUIRY_RESULT}
                    label="نتایج استعلام"
                    variant="default"
                    multiple
                    fieldKey={fieldKey}
                    sectionKey="document_inquiries"
                />
            ) : (
                <p className="text-xs text-muted-foreground">
                    برای بارگذاری نتیجه، دسترسی ثبت استعلام لازم است.
                </p>
            )}
        </div>
    );
}

function InquiryGroup({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            {children}
        </div>
    );
}

/**
 * Document inquiries (استعلام مدارک) — HR-side tracking of external
 * verification requests. One node per education degree (keyed by its row
 * index so results attach to `inq-edu-{index}`), plus fixed criminal-record
 * and social-insurance nodes.
 */
export function DocumentInquiriesSection({
    form,
    uuid,
    canUpdate = true,
}: SectionProps) {
    const { data: degreeOptions } = useFormOptionsByGroup("degree");

    const records =
        (
            form.state.values.education as
                | { education_records?: EducationRecordRow[] }
                | undefined
        )?.education_records ?? [];

    const degreeLabel = (value: unknown, index: number) =>
        degreeOptions?.find((option) => option.value === value)?.label ??
        `مدرک ${index + 1}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle>استعلام مدارک</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                {!canUpdate && (
                    <p className="text-sm text-muted-foreground">
                        شما دسترسی ثبت استعلام را ندارید؛ موارد فقط قابل مشاهده
                        است.
                    </p>
                )}

                <InquiryGroup title="استعلام مدارک تحصیلی">
                    {records.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            سوابق تحصیلی ثبت نشده است؛ با افزودن سابقه در بخش
                            «سوابق تحصیلی»، استعلام هر مدرک اینجا ساخته می‌شود.
                        </p>
                    )}
                    <div className="space-y-4">
                        {records.map((record, index) => (
                            <InquiryNode
                                key={index}
                                form={form}
                                uuid={uuid}
                                name={`document_inquiries.inquiries.education.${index}`}
                                fieldKey={`inq-edu-${index}`}
                                title={`استعلام ${degreeLabel(record.degree, index)}`}
                                canUpdate={canUpdate}
                            />
                        ))}
                    </div>
                </InquiryGroup>

                <InquiryGroup title="گواهی عدم سوء پیشینه">
                    <InquiryNode
                        form={form}
                        uuid={uuid}
                        name="document_inquiries.inquiries.criminal_record"
                        fieldKey="inq-criminal-record"
                        title="عدم سوء پیشینه"
                        canUpdate={canUpdate}
                    />
                </InquiryGroup>

                <InquiryGroup title="استعلام بیمه تأمین اجتماعی">
                    <InquiryNode
                        form={form}
                        uuid={uuid}
                        name="document_inquiries.inquiries.social_insurance"
                        fieldKey="inq-social-insurance"
                        title="بیمه تأمین اجتماعی"
                        canUpdate={canUpdate}
                    />
                </InquiryGroup>
            </CardContent>
        </Card>
    );
}
