import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormTextarea,
    FormDatePicker,
} from "@/components/forms";
import { FileUploadField } from "@/components/documents";
import { repeaterAttachmentColumn } from "@/components/forms";
import { FormRepeater } from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/questionnaire/schemas/training.schema";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";

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
            fieldKeyPrefix: "train-",
            getDocumentsBySlug,
        }),
    ];
    const researchColumns: TableColumn[] = [
        ...RESEARCH_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS,
            fieldKeyPrefix: "res-",
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
                                            fieldKey={`train-${index}`}
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
                                            fieldKey={`res-${index}`}
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
