import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { repeaterAttachmentColumn } from "@/components/shared/repeater-attachment-cell";
import { FormRepeater } from "@/components/shared/form-repeater";
import type { TableColumn } from "@/components/shared/form-repeater";
import {
    YES_NO_OPTIONS,
    parseBoolean,
    DOC_CATEGORY_SLUGS,
} from "@/features/recruitment/constants";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/work-experience.schema";
import type { QuestionnaireFormApi } from "@/features/recruitment/types";

type SectionProps = {
    form: QuestionnaireFormApi;
    uuid?: string;
    onPersist?: () => void;
    /** Grant entity the section's documents belong to. Defaults to "questionnaire". */
    entity?: string;
};

const WORK_COLUMNS: TableColumn[] = [
    { key: "company", label: "شرکت" },
    { key: "position", label: "سمت" },
    { key: "industry", label: "صنعت" },
    { key: "from", label: "از تاریخ", type: "date" },
    { key: "to", label: "تا تاریخ", type: "date" },
    { key: "contract_type", label: "نوع قرارداد" },
];

export function WorkExperienceSection({ form, uuid, onPersist, entity = "questionnaire" }: SectionProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);
    const workColumns: TableColumn[] = [
        ...WORK_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE,
            recordKeyPrefix: "work-",
            getDocumentsBySlug,
        }),
    ];
    return (
        <Card>
            <CardHeader>
                <CardTitle>سوابق شغلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field name="work_experience.work_experiences">
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="سوابق شغلی"
                            columns={workColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                company: item.company,
                                position: item.position,
                                industry: item.industry,
                                from: item.from,
                                to: item.to,
                                contract_type: item.contract_type,
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.company`}
                                            validators={zodFieldValidators(fieldSchemas.company)}
                                        >
                                            {(f) => <FormTextField field={f} label="شرکت" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.location`}
                                        >
                                            {(f) => <FormTextField field={f} label="محل کار" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.industry`}
                                        >
                                            {(f) => <FormTextField field={f} label="صنعت" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.position`}
                                            validators={zodFieldValidators(fieldSchemas.position)}
                                        >
                                            {(f) => <FormTextField field={f} label="سمت شغلی" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.from`}
                                            validators={zodFieldValidators(fieldSchemas.from)}
                                        >
                                            {(f) => <FormDatePicker field={f} label="از تاریخ" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.to`}
                                            validators={zodFieldValidators(fieldSchemas.to)}
                                        >
                                            {(f) => <FormDatePicker field={f} label="تا تاریخ" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.contract_type`}
                                        >
                                            {(f) => <FormTextField field={f} label="نوع قرارداد" />}
                                        </form.Field>
                                        <form.Field name={`work_experience.work_experiences.${index}.phone`}>
                                            {(f) => <FormTextField field={f} label="تلفن" dir="ltr" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.manager_name`}
                                        >
                                            {(f) => <FormTextField field={f} label="نام مدیر" />}
                                        </form.Field>
                                        <form.Field
                                            name={`work_experience.work_experiences.${index}.last_salary`}
                                        >
                                            {(f) => <FormNumberField field={f} label="آخرین حقوق" />}
                                        </form.Field>
                                        <div className="md:col-span-3">
                                            <form.Field
                                                name={`work_experience.work_experiences.${index}.leave_reason`}
                                            >
                                                {(f) => <FormTextarea field={f} label="دلیل ترک" />}
                                            </form.Field>
                                        </div>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE
                                            }
                                            label="گواهی اشتغال به کار"
                                            recordKey={`work-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <form.Field name="work_experience.achievements">
                    {(field) => <FormTextarea field={field} label="دستاوردها" />}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="work_experience.allow_contact_previous_managers">
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="اجازه تماس با مدیران قبلی"
                                options={YES_NO_OPTIONS}
                                parseValue={parseBoolean}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="work_experience.contact_restriction_description">
                    {(field) => (
                        <FormTextarea field={field} label="توضیحات محدودیت تماس" />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
