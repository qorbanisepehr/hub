import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormSelectField,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";
import { DEGREE_OPTIONS, YES_NO_OPTIONS } from "@/features/recruitment/constants";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function EducationSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>سوابق تحصیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field name="education.education_records">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="سوابق تحصیلی"
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <form.Field name={`education.education_records.${index}.degree`}>
                                        {(f) => (
                                            <FormSelectField
                                                field={f}
                                                label="مدرک"
                                                options={DEGREE_OPTIONS}
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field name={`education.education_records.${index}.field`}>
                                        {(f) => <FormTextField field={f} label="رشته تحصیلی" />}
                                    </form.Field>
                                    <form.Field
                                        name={`education.education_records.${index}.institution`}
                                    >
                                        {(f) => <FormTextField field={f} label="دانشگاه" />}
                                    </form.Field>
                                    <form.Field name={`education.education_records.${index}.location`}>
                                        {(f) => <FormTextField field={f} label="محل" />}
                                    </form.Field>
                                    <form.Field name={`education.education_records.${index}.from`}>
                                        {(f) => <FormDatePicker field={f} label="از تاریخ" />}
                                    </form.Field>
                                    <form.Field name={`education.education_records.${index}.to`}>
                                        {(f) => <FormDatePicker field={f} label="تا تاریخ" />}
                                    </form.Field>
                                    <form.Field
                                        name={`education.education_records.${index}.thesis_title`}
                                    >
                                        {(f) => <FormTextField field={f} label="عنوان پایان‌نامه" />}
                                    </form.Field>
                                    <form.Field
                                        name={`education.education_records.${index}.graduation_date`}
                                    >
                                        {(f) => <FormDatePicker field={f} label="تاریخ فارغ‌التحصیلی" />}
                                    </form.Field>
                                    <form.Field name={`education.education_records.${index}.gpa`}>
                                        {(f) => <FormTextField field={f} label="معدل" />}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <Card>
                    <CardHeader>
                        <CardTitle>وضعیت دانشجویی</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form.Field name="education.is_student">
                            {(field) => (
                                <Field>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={field.name}
                                            checked={!!field.state.value}
                                            onCheckedChange={(checked) =>
                                                field.handleChange(!!checked)
                                            }
                                        />
                                        <FieldLabel htmlFor={field.name}>
                                            در حال حاضر دانشجو هستم
                                        </FieldLabel>
                                    </div>
                                </Field>
                            )}
                        </form.Field>

                        <form.Field name="education.is_student">
                            {(field) =>
                                field.state.value ? (
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <form.Field name="education.student_degree">
                                                {(f) => (
                                                    <FormTextField field={f} label="مقطع تحصیلی" />
                                                )}
                                            </form.Field>
                                            <form.Field name="education.student_field">
                                                {(f) => (
                                                    <FormTextField field={f} label="رشته تحصیلی" />
                                                )}
                                            </form.Field>
                                            <form.Field name="education.student_university">
                                                {(f) => <FormTextField field={f} label="دانشگاه" />}
                                            </form.Field>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <form.Field name="education.student_country">
                                                {(f) => <FormTextField field={f} label="کشور" />}
                                            </form.Field>
                                            <form.Field name="education.student_city">
                                                {(f) => <FormTextField field={f} label="شهر" />}
                                            </form.Field>
                                            <form.Field name="education.student_semester">
                                                {(f) => <FormNumberField field={f} label="ترم فعلی" />}
                                            </form.Field>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <form.Field name="education.passed_units">
                                                {(f) => (
                                                    <FormNumberField field={f} label="تعداد واحدهای گذرانده" />
                                                )}
                                            </form.Field>
                                            <form.Field name="education.remaining_units">
                                                {(f) => (
                                                    <FormNumberField
                                                        field={f}
                                                        label="تعداد واحدهای باقی‌مانده"
                                                    />
                                                )}
                                            </form.Field>
                                            <form.Field name="education.student_gpa">
                                                {(f) => <FormTextField field={f} label="معدل" />}
                                            </form.Field>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <form.Field name="education.study_start">
                                                {(f) => <FormDatePicker field={f} label="تاریخ شروع تحصیل" />}
                                            </form.Field>
                                            <form.Field name="education.expected_graduation">
                                                {(f) => (
                                                    <FormDatePicker
                                                        field={f}
                                                        label="تاریخ انتظار فارغ‌التحصیلی"
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <form.Field name="education.thesis_submitted">
                                                {(f) => (
                                                    <FormRadioGroup
                                                        field={f}
                                                        label="آیا پایان‌نامه ارائه شده است؟"
                                                        options={YES_NO_OPTIONS}
                                                    />
                                                )}
                                            </form.Field>
                                            <form.Field name="education.student_thesis_title">
                                                {(f) => (
                                                    <FormTextField
                                                        field={f}
                                                        label="عنوان پایان‌نامه"
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <form.Field name="education.free_days_per_week">
                                                {(f) => (
                                                    <FormNumberField
                                                        field={f}
                                                        label="روزهای آزاد در هفته"
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                        <form.Field name="education.education_description">
                                            {(f) => (
                                                <FormTextarea
                                                    field={f}
                                                    label="توضیحات تحصیلی"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                ) : null
                            }
                        </form.Field>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>
    );
}
