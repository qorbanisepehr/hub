import type { ReactNode } from "react";

import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "./section-card";

type TrainingViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function TrainingView({
    data,
    title = "دوره‌ها و پژوهش‌ها",
    action,
    extra,
}: TrainingViewProps) {
    return (
        <SectionCard title={title} action={action}>
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium mb-2">دوره‌های آموزشی</p>
                    <SectionRepeaterTable
                        items={data.training_courses}
                        emptyLabel="دوره آموزشی ثبت نشده است."
                        columns={[
                            { label: "نام دوره", render: (i) => i.course_name },
                            { label: "مدت", render: (i) => i.duration },
                            { label: "موسسه", render: (i) => i.institution },
                        ]}
                    />
                </div>
                <SectionRow
                    hideEmpty
                    label="عضویت‌های حرفه‌ای"
                    value={data.professional_memberships}
                />
                <div>
                    <p className="text-sm font-medium mb-2">پژوهش‌ها</p>
                    <SectionRepeaterTable
                        items={data.researches}
                        emptyLabel="پژوهشی ثبت نشده است."
                        columns={[
                            { label: "عنوان", render: (i) => i.title },
                            { label: "نوع", render: (i) => i.type },
                            { label: "سال", render: (i) => i.year },
                        ]}
                    />
                </div>
            </div>
            {extra}
        </SectionCard>
    );
}
