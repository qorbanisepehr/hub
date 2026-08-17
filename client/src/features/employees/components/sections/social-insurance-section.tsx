import { useSelector } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormRadioGroup,
    FormTextField,
    FormTextarea,
} from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { FormRepeater } from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import {
    YES_NO_OPTIONS,
    parseBoolean,
} from "@/features/questionnaire/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { socialInsuranceFieldSchema } from "@/features/employees/schemas/social-insurance.schema";
import type { EmployeeFormApi } from "@/features/employees/types";
import { useEffect } from "react";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
};

const HISTORY_COLUMNS: TableColumn[] = [
    { key: "workshop_name", label: "کارگاه / کارفرما" },
    { key: "job_title", label: "عنوان شغلی" },
    { key: "start_date", label: "از تاریخ", type: "date" },
    { key: "end_date", label: "تا تاریخ", type: "date" },
];

export function SocialInsuranceSection({ form, uuid }: SectionProps) {
    const hasHistory = useSelector(
        form.store,
        (state) =>
            state.values.social_insurance?.has_insurance_history === true,
    );

    useEffect(() => {
        if (!hasHistory) {
            form.setFieldValue("social_insurance.histories", [], { dontUpdateMeta: true });
        }
    }, [hasHistory, form]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>بیمه تأمین اجتماعی</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="social_insurance.social_insurance_number"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape
                                .social_insurance_number,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="شماره بیمه"
                                dir="ltr"
                            />
                        )}
                    </form.Field>

                    <form.Field
                        name="social_insurance.insurance_status"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape.insurance_status,
                        )}
                    >
                        {(field) => (
                            <FormTextField field={field} label="وضعیت بیمه" />
                        )}
                    </form.Field>

                    <form.Field
                        name="social_insurance.insurance_start_date"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape
                                .insurance_start_date,
                        )}
                    >
                        {(field) => (
                            <FormDatePicker
                                field={field}
                                label="تاریخ شروع بیمه"
                            />
                        )}
                    </form.Field>

                    <form.Field name="social_insurance.has_insurance_history">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا سابقه بیمه تأمین اجتماعی دارید؟"
                                options={YES_NO_OPTIONS}
                                parseValue={parseBoolean}
                            />
                        )}
                    </form.Field>
                </div>

                {hasHistory && (
                    <form.Field name="social_insurance.histories">
                        {(field) => (
                            <FormRepeater
                                defaultMode="table"
                                field={field}
                                label="سوابق بیمه"
                                columns={HISTORY_COLUMNS}
                                getSummary={(item) => ({
                                    workshop_name: item.workshop_name,
                                    job_title: item.job_title,
                                    start_date: item.start_date,
                                    end_date: item.end_date,
                                })}
                                renderItem={(index) => (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <form.Field
                                            name={`social_insurance.histories.${index}.workshop_name`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="کارگاه / کارفرما"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`social_insurance.histories.${index}.workshop_code`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="کد کارگاه"
                                                    dir="ltr"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`social_insurance.histories.${index}.job_title`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="عنوان شغلی"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`social_insurance.histories.${index}.start_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="از تاریخ"
                                                />
                                            )}
                                        </form.Field>

                                        <form.Field
                                            name={`social_insurance.histories.${index}.end_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تا تاریخ"
                                                />
                                            )}
                                        </form.Field>

                                        <div className="md:col-span-2">
                                            <form.Field
                                                name={`social_insurance.histories.${index}.description`}
                                            >
                                                {(f) => (
                                                    <FormTextarea
                                                        field={f}
                                                        label="توضیحات"
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                    </div>
                                )}
                            />
                        )}
                    </form.Field>
                )}

                <FileUploadField
                    uuid={uuid}
                    entity="employees"
                    categorySlug="insurance-history"
                    label="مدرک سابقه بیمه"
                    maxFiles={1}
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    description="در صورت وجود، تصویر یا فایل سابقه بیمه تأمین اجتماعی را بارگذاری کنید."
                />
            </CardContent>
        </Card>
    );
}
