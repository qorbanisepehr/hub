import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField, FormTextarea, FormDatePicker } from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function TrainingSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>آموزشی و تحقیقاتی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field name="training.training_courses">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="دوره‌های آموزشی"
                            renderItem={(index) => (
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
                            field={field}
                            label="تحقیقات و پژوهش‌ها"
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
