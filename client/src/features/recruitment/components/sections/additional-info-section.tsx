import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormTextarea,
    FormRadioGroup,
} from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";
import { YES_NO_OPTIONS } from "@/features/recruitment/constants";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function AdditionalInfoSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تکمیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="additional_info.has_chronic_disease">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا بیماری مزمن دارید؟"
                                options={YES_NO_OPTIONS}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="additional_info.chronic_disease_description">
                    {(field) => <FormTextarea field={field} label="توضیحات بیماری مزمن" />}
                </form.Field>

                <form.Field name="additional_info.company_introduction_method">
                    {(field) => (
                        <FormTextarea field={field} label="نحوه آشنایی با شرکت" />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="additional_info.has_major_surgery">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا عمل جراحی سنگین داشته‌اید؟"
                                options={YES_NO_OPTIONS}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="additional_info.major_surgery_description">
                    {(field) => <FormTextarea field={field} label="توضیحات عمل جراحی" />}
                </form.Field>

                <form.Field name="additional_info.reason_for_joining">
                    {(field) => <FormTextarea field={field} label="دلیل تمایل به همکاری" />}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="additional_info.has_disability">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا معلولیت دارید؟"
                                options={YES_NO_OPTIONS}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="additional_info.disability_description">
                    {(field) => <FormTextarea field={field} label="توضیحات معلولیت" />}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="additional_info.can_travel">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا امکان سفر دارید؟"
                                options={YES_NO_OPTIONS}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="additional_info.travel_description">
                    {(field) => <FormTextarea field={field} label="توضیحات سفر" />}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="additional_info.has_criminal_record">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="آیا سوءسابقه کیفری دارید؟"
                                options={YES_NO_OPTIONS}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="additional_info.criminal_record_description">
                    {(field) => <FormTextarea field={field} label="توضیحات سوءسابقه" />}
                </form.Field>

                <form.Field name="additional_info.hobbies">
                    {(field) => <FormTextarea field={field} label="علاقه‌مندی‌ها و سرگرمی‌ها" />}
                </form.Field>

                <form.Field name="additional_info.references">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="ارجاعات"
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <form.Field
                                        name={`additional_info.references.${index}.full_name`}
                                    >
                                        {(f) => <FormTextField field={f} label="نام و نام خانوادگی" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.relationship`}
                                    >
                                        {(f) => <FormTextField field={f} label="رابطه" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.workplace_phone`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="تلفن محل کار"
                                                dir="ltr"
                                            />
                                        )}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <form.Field name="additional_info.strengths_and_improvements">
                    {(field) => (
                        <FormTextarea
                            field={field}
                            label="نقاط قوت و زمینه‌های قابل بهبود"
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
