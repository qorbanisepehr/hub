import type { ReactFormExtendedApi } from "@tanstack/react-form";
import { IconPaperclip } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField, FormTextarea, FormDatePicker } from "@/components/shared/form-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { FormRepeater } from "@/components/shared/form-repeater";
import { useQuestionnaireDocuments } from "@/features/recruitment/hooks/use-questionnaire-documents";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    uuid?: string;
};

const COURSE_COLUMNS_FN = (hasDoc: (index: number) => boolean) => [
    { key: "course_name", label: "نام دوره" },
    { key: "institution", label: "سازمان" },
    { key: "duration", label: "مدت زمان" },
    { key: "held_at", label: "تاریخ برگزاری" },
    {
        key: "_attachment",
        label: "پیوست",
        render: (_value: unknown, _item: unknown, index: number) => {
            const has = hasDoc(index);
            return has ? (
                <span className="inline-flex items-center gap-1 text-primary">
                    <IconPaperclip className="size-3.5" />
                </span>
            ) : (
                <span className="text-muted-foreground/40">
                    <IconPaperclip className="size-3.5" />
                </span>
            );
        },
    },
];

export function TrainingSection({ form, uuid }: SectionProps) {
    const { hasDocument } = useQuestionnaireDocuments(uuid);
    const courseColumns = COURSE_COLUMNS_FN((index) => hasDocument(18, `train-${index}`));

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
                                        >
                                            {(f) => <FormTextField field={f} label="نام دوره" />}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.duration`}
                                        >
                                            {(f) => <FormTextField field={f} label="مدت زمان" />}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.institution`}
                                        >
                                            {(f) => <FormTextField field={f} label="سازمان برگزارکننده" />}
                                        </form.Field>
                                        <form.Field name={`training.training_courses.${index}.held_at`}>
                                            {(f) => <FormDatePicker field={f} label="تاریخ برگزاری" />}
                                        </form.Field>
                                        <form.Field
                                            name={`training.training_courses.${index}.certificate`}
                                        >
                                            {(f) => <FormTextField field={f} label="گواهینامه" />}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            categoryId={18}
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
                    {(field) => <FormTextarea field={field} label="عضویت‌های حرفه‌ای" />}
                </form.Field>

                <form.Field name="training.researches">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="تحقیقات و پژوهش‌ها"
                            renderHeader={(item, index) => (
                                <span>{String(item.title || `پژوهش ${index + 1}`)}</span>
                            )}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 gap-4">
                                    <form.Field name={`training.researches.${index}.title`}>
                                        {(f) => <FormTextField field={f} label="عنوان" />}
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
