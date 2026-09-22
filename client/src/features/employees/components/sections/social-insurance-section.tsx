import { useSelector } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormCountField,
    FormOptionSelectField,
    FormRadioGroup,
    FormRepeater,
    FormSelectField,
    FormTextField,
    FormTextarea,
} from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import {
    JALALI_MONTH_OPTIONS,
    YES_NO_OPTIONS,
    parseBoolean,
} from "@/features/questionnaire/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { socialInsuranceFieldSchema } from "@/features/employees/schemas/social-insurance.schema";
import type { EmployeeFormApi } from "@/features/employees/types";
import { useEffect } from "react";
import { toPersianDate } from "@/lib/date-format";

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

const JOB_TITLE_COLUMNS: TableColumn[] = [
    { key: "insurance_number", label: "شماره بیمه" },
    { key: "job_title", label: "عنوان شغلی" },
    { key: "start_date", label: "از تاریخ", type: "date" },
];

const MONTHLY_BREAKDOWN_COLUMNS: TableColumn[] = [
    { key: "month", label: "ماه" },
    { key: "days", label: "روز" },
    { key: "wage", label: "دستمزد" },
];

/** Card title for a job-title row: «عنوان شغلی — از تاریخ». */
function jobTitleRowTitle(item: Record<string, unknown>): string {
    const title = String(item.job_title ?? "").trim();
    const start = toPersianDate(String(item.start_date ?? ""));

    if (title && item.start_date) {
        return `${title} — از ${start}`;
    }
    if (title) {
        return title;
    }
    if (item.start_date) {
        return `از ${start}`;
    }
    return "سابقه عنوان شغلی";
}

export function SocialInsuranceSection({ form, uuid }: SectionProps) {
    const hasHistory = useSelector(
        form.store,
        (state) =>
            state.values.social_insurance?.has_insurance_history === true,
    );

    useEffect(() => {
        if (!hasHistory) {
            const social = form.state.values.social_insurance;
            const current = social?.histories;
            if (current && current.length > 0) {
                form.setFieldValue("social_insurance.histories", [], {
                    dontUpdateMeta: true,
                });
            }
            const jobTitles = social?.job_titles;
            if (jobTitles && jobTitles.length > 0) {
                form.setFieldValue("social_insurance.job_titles", [], {
                    dontUpdateMeta: true,
                });
            }
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

                    <form.Field name="social_insurance.insurance_status">
                        {(field) => (
                            <FormOptionSelectField
                                field={field}
                                label="وضعیت بیمه"
                                group="insurance_type"
                                placeholder="انتخاب کنید"
                            />
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

                    <form.Field
                        name="social_insurance.branch_name"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape.branch_name,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="شعبه بازنشستگی"
                            />
                        )}
                    </form.Field>

                    <form.Field
                        name="social_insurance.days_count"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape.days_count,
                        )}
                    >
                        {(field) => (
                            <FormCountField
                                field={field}
                                label="تعداد روزهای بیمه"
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
                                emptyMessage="هنوز سابقه بیمه‌ای اضافه نشده است."
                                getSummary={(item) => ({
                                    workshop_name: item.workshop_name,
                                    job_title: item.job_title,
                                    start_date: item.start_date,
                                    end_date: item.end_date,
                                })}
                                renderItem={(index) => (
                                    <div className="space-y-4">
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

                                        <form.Field
                                            name={`social_insurance.histories.${index}.monthly_breakdown`}
                                        >
                                            {(breakdownField) => (
                                                <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
                                                    <p className="text-sm font-medium text-muted-foreground">
                                                        تفکیک ماهانه این سابقه
                                                    </p>
                                                    <FormRepeater
                                                        defaultMode="table"
                                                        field={breakdownField}
                                                        label="ماه‌ها"
                                                        columns={
                                                            MONTHLY_BREAKDOWN_COLUMNS
                                                        }
                                                        emptyMessage="هنوز ماهی اضافه نشده است."
                                                        getSummary={(item) => ({
                                                            month:
                                                                JALALI_MONTH_OPTIONS.find(
                                                                    (option) =>
                                                                        option.value ===
                                                                        item.month,
                                                                )?.label ??
                                                                item.month,
                                                            days: item.days,
                                                            wage: item.wage,
                                                        })}
                                                        renderItem={(
                                                            monthIndex,
                                                        ) => (
                                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                                <form.Field
                                                                    name={`social_insurance.histories.${index}.monthly_breakdown.${monthIndex}.month`}
                                                                >
                                                                    {(f) => (
                                                                        <FormSelectField
                                                                            field={
                                                                                f
                                                                            }
                                                                            label="ماه"
                                                                            options={
                                                                                JALALI_MONTH_OPTIONS
                                                                            }
                                                                            placeholder="انتخاب کنید"
                                                                        />
                                                                    )}
                                                                </form.Field>
                                                                <form.Field
                                                                    name={`social_insurance.histories.${index}.monthly_breakdown.${monthIndex}.days`}
                                                                >
                                                                    {(f) => (
                                                                        <FormCountField
                                                                            field={
                                                                                f
                                                                            }
                                                                            label="روز"
                                                                        />
                                                                    )}
                                                                </form.Field>
                                                                <form.Field
                                                                    name={`social_insurance.histories.${index}.monthly_breakdown.${monthIndex}.wage`}
                                                                >
                                                                    {(f) => (
                                                                        <FormTextField
                                                                            field={
                                                                                f
                                                                            }
                                                                            label="دستمزد"
                                                                        />
                                                                    )}
                                                                </form.Field>
                                                            </div>
                                                        )}
                                                    />
                                                </div>
                                            )}
                                        </form.Field>
                                    </div>
                                )}
                            />
                        )}
                    </form.Field>
                )}

                {hasHistory && (
                    <form.Field
                        name="social_insurance.job_titles"
                        validators={zodFieldValidators(
                            socialInsuranceFieldSchema.shape.job_titles,
                        )}
                    >
                        {(field) => (
                            <FormRepeater
                                defaultMode="table"
                                field={field}
                                label="سوابق عناوین شغلی"
                                columns={JOB_TITLE_COLUMNS}
                                emptyMessage="هنوز عنوان شغلی ثبت نشده است."
                                getSummary={(item) => ({
                                    insurance_number: item.insurance_number,
                                    job_title: item.job_title,
                                    start_date: item.start_date,
                                })}
                                renderHeader={(item) => (
                                    <span className="font-medium">
                                        {jobTitleRowTitle(item)}
                                    </span>
                                )}
                                renderItem={(index) => (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <form.Field
                                            name={`social_insurance.job_titles.${index}.insurance_number`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="شماره بیمه"
                                                    dir="ltr"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`social_insurance.job_titles.${index}.start_date`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="از تاریخ"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`social_insurance.job_titles.${index}.job_title`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="عنوان شغلی"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`social_insurance.job_titles.${index}.workshop_code`}
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
                                            name={`social_insurance.job_titles.${index}.workshop_name`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="نام کارگاه / کارفرما"
                                                />
                                            )}
                                        </form.Field>
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
