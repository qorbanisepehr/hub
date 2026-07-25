import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextarea, FormTextField } from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";

import { YesNoWithDescription } from "./yes-no-with-description";

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
                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_chronic_disease"
                    descriptionField="additional_info.chronic_disease_description"
                    booleanLabel="آیا بیماری مزمن دارید؟"
                    descriptionLabel="توضیحات بیماری مزمن"
                />

                <form.Field name="additional_info.company_introduction_method">
                    {(field) => (
                        <FormTextarea field={field} label="نحوه آشنایی با شرکت" />
                    )}
                </form.Field>

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_major_surgery"
                    descriptionField="additional_info.major_surgery_description"
                    booleanLabel="آیا عمل جراحی سنگین داشته‌اید؟"
                    descriptionLabel="توضیحات عمل جراحی"
                />

                <form.Field name="additional_info.reason_for_joining">
                    {(field) => <FormTextarea field={field} label="دلیل تمایل به همکاری" />}
                </form.Field>

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_disability"
                    descriptionField="additional_info.disability_description"
                    booleanLabel="آیا معلولیت دارید؟"
                    descriptionLabel="توضیحات معلولیت"
                />

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.can_travel"
                    descriptionField="additional_info.travel_description"
                    booleanLabel="آیا امکان سفر دارید؟"
                    descriptionLabel="توضیحات سفر"
                />

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_criminal_record"
                    descriptionField="additional_info.criminal_record_description"
                    booleanLabel="آیا سوءسابقه کیفری دارید؟"
                    descriptionLabel="توضیحات سوءسابقه"
                />

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
