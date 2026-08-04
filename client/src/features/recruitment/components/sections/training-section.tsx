import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormTextarea,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { repeaterAttachmentColumn } from "@/components/shared/repeater-attachment-cell";
import { FormRepeater } from "@/components/shared/form-repeater";
import type { TableColumn } from "@/components/shared/form-repeater";
import { DOC_CATEGORY_SLUGS } from "@/features/recruitment/constants";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/training.schema";
import type { QuestionnaireFormApi } from "@/features/recruitment/types";

type SectionProps = {
    form: QuestionnaireFormApi;
    uuid?: string;
    onPersist?: () => void;
    /** Grant entity the section's documents belong to. Defaults to "questionnaire". */
    entity?: string;
};

const COURSE_COLUMNS: TableColumn[] = [
    { key: "course_name", label: "نام دوره" },
    { key: "institution", label: "سازمان" },
    { key: "duration", label: "مدت زمان" },
    { key: "held_at", label: "تاریخ برگزاری", type: "date" },
];

const RESEARCH_COLUMNS: TableColumn[] = [
    { key: "title", label: "عنوان" },
    { key: "_attachment", label: "پیوست" },
];

export function TrainingSection({ form, uuid, onPersist, entity = "questionnaire" }: SectionProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);
    const courseColumns: TableColumn[] = [
        ...COURSE_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES,
            recordKeyPrefix: "train-",
            getDocumentsBySlug,
        }),
    ];
    const researchColumns: TableColumn[] = [
        ...RESEARCH_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS,
            recordKeyPrefix: "res-",
            getDocumentsBySlug,
        }),
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>آموزشی و تحقیقاتی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field name="training.training_courses">
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="دوره‌های آموزشی"
                            columns={courseColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                course_name: item.course_name,
                                institution: item.institution,
                                duration: item.duration,
                                held_at: item.held_at,
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <form.Field
                                            name={`training.training_courses.${index}.course_name`}
                                            validators={zodFieldValidators(
                                                fieldSchemas.course_name,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="نام دوره"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.duration`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="مدت زمان"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.institution`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="سازمان برگزارکننده"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.held_at`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ برگزاری"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.certificate`}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="گواهینامه"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES
                                            }
                                            label="گواهینامه دوره"
                                            recordKey={`train-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <form.Field name="training.professional_memberships">
                    {(field) => (
                        <FormTextarea field={field} label="عضویت‌های حرفه‌ای" />
                    )}
                </form.Field>

                <form.Field name="training.researches">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="تحقیقات و پژوهش‌ها"
                            columns={researchColumns}
                            onPersist={onPersist}
                            renderHeader={(item, index) => (
                                <span>
                                    {String(item.title || `پژوهش ${index + 1}`)}
                                </span>
                            )}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4">
                                        <form.Field
                                            name={`training.researches.${index}.title`}
                                            validators={zodFieldValidators(
                                                fieldSchemas.research_title,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="عنوان"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS
                                            }
                                            label="مدرک پژوهشی"
                                            recordKey={`res-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
