import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormRadioGroup,
    FormCheckboxGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import {
    EMPLOYMENT_TYPE_OPTIONS,
    YES_NO_OPTIONS,
    CURRENTLY_EMPLOYED_OPTIONS,
    PREFERRED_WORKPLACE_OPTIONS,
    parseBoolean,
} from "@/features/recruitment/constants";
import { zodFieldValidator } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/job-request.schema";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function JobRequestSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>نوع درخواست همکاری</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field
                    name="job_request.employment_type"
                    validators={{
                        onBlur: zodFieldValidator(fieldSchemas.employment_type),
                    }}
                >
                    {(field) => (
                        <FormRadioGroup
                            field={field}
                            label="نوع اشتغال"
                            options={EMPLOYMENT_TYPE_OPTIONS}
                        />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="job_request.expected_monthly_salary">
                        {(field) => <FormNumberField field={field} label="حقوق ماهانه مورد انتظار" />}
                    </form.Field>
                    <form.Field name="job_request.minimum_hours_per_month">
                        {(field) => (
                            <FormNumberField field={field} label="حداقل ساعات کاری در ماه" />
                        )}
                    </form.Field>
                    <form.Field name="job_request.expected_hourly_salary">
                        {(field) => <FormNumberField field={field} label="حقوق ساعتی مورد انتظار" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="job_request.submitted_resume_before">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا قبلاً رزومه ارسال کرده‌اید؟"
                                options={YES_NO_OPTIONS}
                                parseValue={parseBoolean}
                            />
                        )}
                    </form.Field>
                    <form.Field name="job_request.interviewed_before">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا قبلاً مصاحبه داشته‌اید؟"
                                options={YES_NO_OPTIONS}
                                parseValue={parseBoolean}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="job_request.other_information">
                    {(field) => <FormTextarea field={field} label="سایر اطلاعات" />}
                </form.Field>

                <form.Field
                    name="job_request.accept_information"
                    validators={{
                        onBlur: zodFieldValidator(fieldSchemas.accept_information),
                    }}
                >
                    {(field) => (
                        <Field>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={field.name}
                                    checked={!!field.state.value}
                                    onCheckedChange={(checked) => field.handleChange(!!checked)}
                                />
                                <FieldLabel htmlFor={field.name}>
                                    اطلاعات وارد شده را تأیید می‌کنم
                                </FieldLabel>
                            </div>
                        </Field>
                    )}
                </form.Field>

                <form.Field name="job_request.preferred_workplace">
                    {(field) => (
                        <FormCheckboxGroup
                            field={field}
                            label="محل کار مورد نظر"
                            options={PREFERRED_WORKPLACE_OPTIONS}
                        />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="job_request.job_priority_1">
                        {(field) => <FormTextField field={field} label="اولویت شغلی ۱" />}
                    </form.Field>
                    <form.Field name="job_request.job_priority_2">
                        {(field) => <FormTextField field={field} label="اولویت شغلی ۲" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="job_request.currently_employed">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا در حال حاضر شاغل هستید؟"
                                options={CURRENTLY_EMPLOYED_OPTIONS}
                                parseValue={parseBoolean}
                            />
                        )}
                    </form.Field>
                    <form.Field name="job_request.available_start_date">
                        {(field) => <FormDatePicker field={field} label="تاریخ شروع به کار" />}
                    </form.Field>
                </div>
            </CardContent>
        </Card>
    );
}
