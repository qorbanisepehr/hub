import type { ReactNode } from "react";

import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "./section-card";
import { asRecord, boolLabel, dateValue } from "./shared";

type EducationViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function EducationView({
    data,
    title = "سوابق تحصیلی",
    action,
    extra,
}: EducationViewProps) {
    const education = data;
    const isStudent = Boolean(education.is_student);

    return (
        <SectionCard title={title} action={action}>
            <div className="space-y-6">
                <SectionRepeaterTable
                    items={education.education_records}
                    emptyLabel="سابقه تحصیلی ثبت نشده است."
                    columns={[
                        { label: "مدرک", render: (i) => i.degree },
                        { label: "رشته", render: (i) => i.field },
                        { label: "دانشگاه", render: (i) => i.institution },
                        { label: "محل", render: (i) => i.location },
                        { label: "از تاریخ", render: (i) => dateValue(i.from) },
                        { label: "تا تاریخ", render: (i) => dateValue(i.to) },
                        {
                            label: "تاریخ فارغ‌التحصیلی",
                            render: (i) => dateValue(i.graduation_date),
                        },
                        { label: "معدل", render: (i) => i.gpa },
                        { label: "پایان‌نامه", render: (i) => i.thesis_title },
                    ]}
                />
                {isStudent && (
                    <div className="rounded-lg border p-4">
                        <p className="text-sm font-medium mb-3">
                            وضعیت دانشجویی
                        </p>
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card className="border-0 shadow-none">
                                <CardContent className="divide-y p-0">
                                    <SectionRow
                                        hideEmpty
                                        label="مقطع تحصیلی"
                                        value={education.student_degree}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="رشته تحصیلی"
                                        value={education.student_field}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="دانشگاه"
                                        value={education.student_university}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="کشور"
                                        value={education.student_country}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="شهر"
                                        value={education.student_city}
                                    />
                                </CardContent>
                            </Card>
                            <Card className="border-0 shadow-none">
                                <CardContent className="divide-y p-0">
                                    <SectionRow
                                        hideEmpty
                                        label="ترم فعلی"
                                        value={education.student_semester}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="واحدهای گذرانده"
                                        value={education.passed_units}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="واحدهای باقی‌مانده"
                                        value={education.remaining_units}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="معدل"
                                        value={education.student_gpa}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="تاریخ شروع"
                                        value={dateValue(education.study_start)}
                                    />
                                    <SectionRow
                                        hideEmpty
                                        label="تاریخ فارغ‌التحصیلی مورد انتظار"
                                        value={dateValue(
                                            education.expected_graduation,
                                        )}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                        <div className="mt-4">
                            <SectionRow
                                hideEmpty
                                label="ارائه پایان‌نامه"
                                value={boolLabel(education.thesis_submitted)}
                            />
                        </div>
                        <SectionRow
                            hideEmpty
                            label="عنوان پایان‌نامه"
                            value={education.student_thesis_title}
                        />
                        <SectionRow
                            hideEmpty
                            label="روزهای آزاد در هفته"
                            value={education.free_days_per_week}
                        />
                    </div>
                )}
                <SectionRow
                    hideEmpty
                    label="توضیحات تحصیلی"
                    value={education.education_description}
                />
            </div>
            {extra}
        </SectionCard>
    );
}
