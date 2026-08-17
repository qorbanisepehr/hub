import { useMemo } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { FormTextarea, FormTextField } from "@/components/forms";
import { FormRepeater } from "@/components/forms";
import { PhysicalConditionFields } from "@/components/forms";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { buildAdditionalInfoSchemas, fieldSchemas } from "@/features/questionnaire/schemas/additional-info.schema";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";

import { YesNoWithDescription } from "./yes-no-with-description";

type SectionProps = {
    form: QuestionnaireFormApi;
    onPersist?: () => void;
};

export function AdditionalInfoSection({ form, onPersist }: SectionProps) {
    const { data: physicalConditionOptions } = useFormOptionsByGroup("physical_condition");
    const { data: disabilityTypeOptions } = useFormOptionsByGroup("disability_type");

    const optionsLoaded =
        physicalConditionOptions !== undefined &&
        disabilityTypeOptions !== undefined;

    const schemas = useMemo(() => {
        if (!optionsLoaded) return fieldSchemas;
        return buildAdditionalInfoSchemas({
            physical_condition: physicalConditionOptions,
            disability_type: disabilityTypeOptions,
        });
    }, [optionsLoaded, physicalConditionOptions, disabilityTypeOptions]);

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

                <PhysicalConditionFields
                    form={form}
                    conditionField="additional_info.physical_condition"
                    typeField="additional_info.disability_type"
                />

                <Separator />

                {/* ── Background ── */}
                <span className="text-sm font-medium">سابقه و انگیزه</span>

                <form.Field
                    name="additional_info.company_introduction_method"
                    validators={zodFieldValidators(schemas.company_introduction_method)}
                >
                    {(field) => (
                        <FormTextarea field={field} label="نحوه آشنایی با شرکت" />
                    )}
                </form.Field>

                <form.Field
                    name="additional_info.reason_for_joining"
                    validators={zodFieldValidators(schemas.reason_for_joining)}
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
                    validators={zodFieldValidators(schemas.hobbies)}
                >
                    {(field) => <FormTextarea field={field} label="علاقه‌مندی‌ها و سرگرمی‌ها" />}
                </form.Field>

                <form.Field
                    name="additional_info.strengths_and_improvements"
                    validators={zodFieldValidators(schemas.strengths_and_improvements)}
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
                                        validators={zodFieldValidators(schemas.reference_item.shape.full_name)}
                                    >
                                        {(f) => <FormTextField field={f} label="نام و نام خانوادگی" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.relationship`}
                                        validators={zodFieldValidators(schemas.reference_item.shape.relationship)}
                                    >
                                        {(f) => <FormTextField field={f} label="رابطه" />}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.workplace_phone`}
                                        validators={zodFieldValidators(schemas.reference_item.shape.workplace_phone)}
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
