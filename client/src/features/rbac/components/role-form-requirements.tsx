import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormNumberField,
    FormTextarea,
    FormOptionMultiComboboxField,
} from "@/components/forms";
import {
    CommaSeparatedField,
    NullableSelectField,
    optionalSelectValidator,
    minExperienceYearsValidator,
    fieldsOfStudyValidator,
    roleSchema,
    type RoleFormApi,
} from "./role-form-schema";
import { EDUCATION_LEVELS } from "@/features/rbac/constants";
import { EDUCATION_LEVELS_KEYS } from "@/features/rbac/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";

type RequirementsCardProps = {
    form: RoleFormApi;
};

export function RoleRequirementsCard({ form }: RequirementsCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">شرایط احراز</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="requirements.min_education"
                        validators={zodFieldValidators(
                            optionalSelectValidator(EDUCATION_LEVELS_KEYS),
                        )}
                    >
                        {(f) => (
                            <NullableSelectField
                                field={f}
                                label="حداقل مقطع تحصیلی"
                                placeholder="تعیین نشده"
                                options={Object.entries(
                                    EDUCATION_LEVELS,
                                ).map(([value, label]) => ({
                                    value,
                                    label,
                                }))}
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="requirements.min_related_experience_years"
                        validators={zodFieldValidators(
                            minExperienceYearsValidator,
                        )}
                    >
                        {(f) => (
                            <FormNumberField
                                field={f}
                                label="حداقل سابقه کار مرتبط (سال)"
                                min={0}
                                max={50}
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="requirements.min_unrelated_experience_years"
                        validators={zodFieldValidators(
                            minExperienceYearsValidator,
                        )}
                    >
                        {(f) => (
                            <FormNumberField
                                field={f}
                                label="حداقل سابقه کار غیرمرتبط (سال)"
                                min={0}
                                max={50}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field
                    name="requirements.fields_of_study"
                    validators={zodFieldValidators(fieldsOfStudyValidator)}
                >
                    {(f) => (
                        <FormOptionMultiComboboxField
                            field={f}
                            label="رشته تحصیلی"
                            group="field_of_study"
                            placeholder="انتخاب رشته‌های تحصیلی…"
                        />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="requirements.required_skills"
                        validators={zodFieldValidators(
                            roleSchema.shape.requirements.shape
                                .required_skills,
                        )}
                    >
                        {(f) => (
                            <CommaSeparatedField
                                field={f}
                                label="مهارت‌های لازم"
                                placeholder="مهارت‌ها را با ویرگول جدا کنید"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="requirements.preferred_skills"
                        validators={zodFieldValidators(
                            roleSchema.shape.requirements.shape
                                .preferred_skills,
                        )}
                    >
                        {(f) => (
                            <CommaSeparatedField
                                field={f}
                                label="مهارت‌های ترجیحی"
                                placeholder="مهارت‌ها را با ویرگول جدا کنید"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="requirements.certifications"
                        validators={zodFieldValidators(
                            roleSchema.shape.requirements.shape
                                .certifications,
                        )}
                    >
                        {(f) => (
                            <CommaSeparatedField
                                field={f}
                                label="گواهینامه‌ها"
                                placeholder="گواهی‌ها را با ویرگول جدا کنید"
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field
                    name="requirements.description"
                    validators={zodFieldValidators(
                        roleSchema.shape.requirements.shape.description,
                    )}
                >
                    {(f) => (
                        <FormTextarea
                            field={f}
                            label="توضیحات"
                            placeholder="توضیحات تکمیلی شرایط احراز (اختیاری)"
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}