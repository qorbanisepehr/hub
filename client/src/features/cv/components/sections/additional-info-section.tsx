import { useMemo } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextarea, FormTextField } from "@/components/forms";
import { FormRepeater } from "@/components/forms";
import { PhysicalConditionFields } from "@/components/forms";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import {
    buildAdditionalInfoSchemas,
    fieldSchemas,
} from "@/features/cv/schemas/additional-info.schema";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    onPersist?: () => void;
};

export function AdditionalInfoSection({ form, onPersist }: SectionProps) {
    const { data: physicalConditionOptions } =
        useFormOptionsByGroup("physical_condition");
    const { data: disabilityTypeOptions } =
        useFormOptionsByGroup("disability_type");

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
            <CardContent className="space-y-6">
                <form.Field
                    name="additional_info.hobbies"
                    validators={zodFieldValidators(schemas.hobbies)}
                >
                    {(field) => (
                        <FormTextarea
                            field={field}
                            label="علاقه‌مندی‌ها و سرگرمی‌ها"
                        />
                    )}
                </form.Field>

                <form.Field
                    name="additional_info.strengths_and_improvements"
                    validators={zodFieldValidators(
                        schemas.strengths_and_improvements,
                    )}
                >
                    {(field) => (
                        <FormTextarea
                            field={field}
                            label="نقاط قوت و زمینه‌های قابل بهبود"
                        />
                    )}
                </form.Field>

                <PhysicalConditionFields
                    form={form}
                    conditionField="additional_info.physical_condition"
                    typeField="additional_info.disability_type"
                />

                <form.Field name="additional_info.references">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="معرفها"
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
                                        validators={zodFieldValidators(
                                            schemas.reference_item.shape
                                                .full_name,
                                        )}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="نام و نام خانوادگی"
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.relationship`}
                                        validators={zodFieldValidators(
                                            schemas.reference_item.shape
                                                .relationship,
                                        )}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="رابطه"
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field
                                        name={`additional_info.references.${index}.workplace_phone`}
                                        validators={zodFieldValidators(
                                            schemas.reference_item.shape
                                                .workplace_phone,
                                        )}
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
