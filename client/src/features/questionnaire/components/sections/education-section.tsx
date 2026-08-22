import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/forms";
import {
    FormOptionSelectField,
    FormOptionComboboxField,
} from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { repeaterAttachmentColumn } from "@/components/forms";
import { FormRepeater } from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import {
    YES_NO_OPTIONS,
    parseBoolean,
} from "@/features/questionnaire/constants";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import { optionEnum } from "@/features/form-options/schema";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/questionnaire/schemas/education.schema";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";

type SectionProps = {
    form: QuestionnaireFormApi;
    uuid?: string;
    onPersist?: () => void;
    /** Grant entity the section's documents belong to. Defaults to "questionnaire". */
    entity?: string;
};

export function EducationSection({ form, uuid, onPersist, entity = "questionnaire" }: SectionProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);

    const { data: degreeOptions } = useFormOptionsByGroup("degree");
    const { data: universityOptions } = useFormOptionsByGroup("university");

    const degreeLabel = (value: string | undefined) =>
        degreeOptions?.find((option) => option.value === value)?.label ?? value;

    const universityLabel = (value: string | undefined) =>
        universityOptions?.find((option) => option.value === value)?.label ?? value;

    const universitySchema = universityOptions
        ? optionEnum(universityOptions, "دانشگاه الزامی است.")
        : undefined;

    const educationColumns: TableColumn[] = [
        { key: "degree", label: "مدرک" },
        { key: "field", label: "رشته" },
        { key: "institution", label: "دانشگاه" },
        { key: "from", label: "از تاریخ", type: "date" },
        { key: "to", label: "تا تاریخ", type: "date" },
        { key: "gpa", label: "معدل" },
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
            fieldKeyPrefix: "edu-",
            getDocumentsBySlug,
        }),
    ];
    return (
        <Card>
            <CardHeader>
                <CardTitle>سوابق تحصیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field
                    name="education.education_records"
                    validators={zodFieldValidators(
                        fieldSchemas.education_records,
                    )}
                >
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="سوابق تحصیلی"
                            columns={educationColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                degree: degreeLabel(item.degree as string | undefined),
                                field: item.field,
                                institution: universityLabel(item.institution as string | undefined),
                                from: item.from,
                                to: item.to,
                                gpa: item.gpa,
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <form.Field
                                            name={`education.education_records.${index}.degree`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.degree,
                                            )}
                                        >
                                            {(f) => (
                                                <FormOptionSelectField
                                                    field={f}
                                                    label="مدرک"
                                                    group="degree"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.field`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.field,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="رشته تحصیلی"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.institution`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.institution,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="دانشگاه"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.location`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="محل"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.from`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.from,
                                            )}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="از تاریخ"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.to`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.to,
                                            )}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تا تاریخ"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.thesis_title`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="عنوان پایان‌نامه"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.graduation_date`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.graduation_date,
                                            )}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ فارغ‌التحصیلی"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`education.education_records.${index}.gpa`}
                                            validators={zodFieldValidators(
                                                fieldSchemas
                                                    .education_records_item
                                                    .shape.gpa,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="معدل"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE
                                            }
                                            label="مدرک تحصیلی"
                                            fieldKey={`edu-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <div className="rounded-lg border p-4 space-y-4">
                    <span className="text-sm font-medium">وضعیت دانشجویی</span>
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
                                        <form.Field
                                            name="education.student_degree"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_degree,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="مقطع تحصیلی"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name="education.student_field"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_field,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="رشته تحصیلی"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name="education.student_university"
                                            validators={
                                                universitySchema
                                                    ? zodFieldValidators(universitySchema)
                                                    : undefined
                                            }
                                        >
                                            {(f) => (
                                                <FormOptionComboboxField
                                                    field={f}
                                                    label="دانشگاه"
                                                    group="university"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <form.Field
                                            name="education.student_country"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_country,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="کشور"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name="education.student_city"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_city,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="شهر"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field name="education.student_semester">
                                            {(f) => (
                                                <FormNumberField
                                                    field={f}
                                                    label="ترم فعلی"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <form.Field name="education.passed_units">
                                            {(f) => (
                                                <FormNumberField
                                                    field={f}
                                                    label="تعداد واحدهای گذرانده"
                                                />
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
                                        <form.Field
                                            name="education.student_gpa"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_gpa,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="معدل"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <form.Field
                                            name="education.study_start"
                                            validators={zodFieldValidators(
                                                fieldSchemas.study_start,
                                            )}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ شروع تحصیل"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name="education.expected_graduation"
                                            validators={zodFieldValidators(
                                                fieldSchemas.expected_graduation,
                                            )}
                                        >
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
                                                    parseValue={parseBoolean}
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name="education.student_thesis_title"
                                            validators={zodFieldValidators(
                                                fieldSchemas.student_thesis_title,
                                            )}
                                        >
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
                                </div>
                            ) : null
                        }
                    </form.Field>

                    <form.Field name="education.education_description">
                        {(f) => (
                            <FormTextarea field={f} label="توضیحات تحصیلی" />
                        )}
                    </form.Field>
                </div>
            </CardContent>
        </Card>
    );
}
