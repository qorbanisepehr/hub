import type { ReactNode } from "react";

import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "./section-card";
import { boolLabel, dateValue } from "./shared";

type WorkExperienceViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function WorkExperienceView({
    data,
    title = "سوابق شغلی",
    action,
    extra,
}: WorkExperienceViewProps) {
    return (
        <SectionCard title={title} action={action}>
            <div className="space-y-6">
                <SectionRepeaterTable
                    items={data.work_experiences}
                    emptyLabel="سابقه شغلی ثبت نشده است."
                    columns={[
                        { label: "شرکت", render: (i) => i.company },
                        { label: "سمت", render: (i) => i.position },
                        { label: "صنعت", render: (i) => i.industry },
                        { label: "محل کار", render: (i) => i.location },
                        { label: "از تاریخ", render: (i) => dateValue(i.from) },
                        { label: "تا تاریخ", render: (i) => dateValue(i.to) },
                        {
                            label: "نوع قرارداد",
                            render: (i) => i.contract_type,
                        },
                        {
                            label: "آخرین حقوق",
                            render: (i) => i.last_salary,
                        },
                        {
                            label: "دلیل ترک",
                            render: (i) => i.leave_reason,
                        },
                        { label: "مدیر", render: (i) => i.manager_name },
                        { label: "تلفن", render: (i) => i.phone },
                    ]}
                />
                <SectionRow
                    hideEmpty
                    label="دستاوردها"
                    value={data.achievements}
                />
                <SectionRow
                    hideEmpty
                    label="مجاز به تماس با مدیران قبلی"
                    value={boolLabel(data.allow_contact_previous_managers)}
                />
                <SectionRow
                    hideEmpty
                    label="توضیحات محدودیت تماس"
                    value={data.contact_restriction_description}
                />
            </div>
            {extra}
        </SectionCard>
    );
}
