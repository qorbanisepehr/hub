import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { FormTextarea, FormTextField } from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/additional-info.schema";

import { YesNoWithDescription } from "./yes-no-with-description";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    onPersist?: () => void;
};

export function AdditionalInfoSection({ form, onPersist }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تکمیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {/* ── Health ── */}
                <span className="text-sm font-medium">وضعیت جسمانی و پزشکی</span>

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_chronic_disease"
                    descriptionField="additional_info.chronic_disease_description"
                    booleanLabel="آیا بیماری مزمن دارید؟"
                    descriptionLabel="توضیحات بیماری مزمن"
                />

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_major_surgery"
                    descriptionField="additional_info.major_surgery_description"
                    booleanLabel="آیا عمل جراحی سنگین داشته‌اید؟"
                    descriptionLabel="توضیحات عمل جراحی"
                />

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_disability"
                    descriptionField="additional_info.disability_description"
                    booleanLabel="آیا معلولیت دارید؟"
                    descriptionLabel="توضیحات معلولیت"
                />

                <Separator />

                {/* ── Background ── */}
                <span className="text-sm font-medium">سابقه و انگیزه</span>

                <form.Field
                    name="additional_info.company_introduction_method"
                    validators={zodFieldValidators(fieldSchemas.company_introduction_method)}
                >
                    {(field) => (
                        <FormTextarea field={field} label="نحوه آشنایی با شرکت" />
                    )}
                </form.Field>

                <form.Field
                    name="additional_info.reason_for_joining"
                    validators={zodFieldValidators(fieldSchemas.reason_for_joining)}
                >
                    {(field) => <FormTextarea field={field} label="دلیل تمایل به همکاری" />}
                </form.Field>

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.has_criminal_record"
                    descriptionField="additional_info.criminal_record_description"
                    booleanLabel="آیا سوءسابقه کیفری دارید؟"
                    descriptionLabel="توضیحات سوءسابقه"
                />

                <Separator />

                {/* ── Conditions ── */}
                <span className="text-sm font-medium">شرایط و علایق</span>

                <YesNoWithDescription
                    form={form}
                    booleanField="additional_info.can_travel"
                    descriptionField="additional_info.travel_description"
                    booleanLabel="آیا امکان سفر دارید؟"
                    descriptionLabel="توضیحات سفر"
                />

                <form.Field
                    name="additional_info.hobbies"
                    validators={zodFieldValidators(fieldSchemas.hobbies)}
                >
                    {(field) => <FormTextarea field={field} label="علاقه‌مندی‌ها و سرگرمی‌ها" />}
                </form.Field>

                <form.Field
                    name="additional_info.strengths_and_improvements"
                    validators={zodFieldValidators(fieldSchemas.strengths_and_improvements)}
                >
                    {(field) => (
                        <FormTextarea
                            field={field}
                            label="نقاط قوت و زمینه‌های قابل بهبود"
                        />
                    )}
                </form.Field>

                <Separator />

                {/* ── References ── */}
                <form.Field name="additional_info.references">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="ارجاعات"
                            onPersist={onPersist}
                            columns={[
                                { key: "full_name", label: "نام" },
                                { key: "relationship", label: "رابطه" },
                                { key: "workplace_phone", label: "تلفن" },
                            ]}
                            getSummary={(item) => ({
                                full_name: item.full_name,
                                relationship: item.relationship,
                                workplace_phone: item.workplace_phone,
                            })}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <form.Field
                                        name={`additional_info.references.${index}.full_name`}
                                        validators={zodFieldValidators(fieldSchemas.reference_item.shape.full_name)}
                                    >
                                        {(f) => <FormTextField field={f} label="نام و نام خانوادگی" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.relationship`}
                                        validators={zodFieldValidators(fieldSchemas.reference_item.shape.relationship)}
                                    >
                                        {(f) => <FormTextField field={f} label="رابطه" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.workplace_phone`}
                                        validators={zodFieldValidators(fieldSchemas.reference_item.shape.workplace_phone)}
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
            </CardContent>
        </Card>
    );
}
