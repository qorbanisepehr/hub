import type { ReactNode } from "react";

import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "./section-card";
import { asRecord, dateValue } from "./shared";

type SkillsViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function SkillsView({
    data,
    title = "مهارت‌ها",
    action,
    extra,
}: SkillsViewProps) {
    const software = asRecord(data.software_skills);
    const specialSkills = Array.isArray(data.special_skills)
        ? data.special_skills.filter(
              (skill): skill is string =>
                  typeof skill === "string" && skill.trim() !== "",
          )
        : [];

    return (
        <SectionCard title={title} action={action}>
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium mb-2">زبان‌ها</p>
                    <SectionRepeaterTable
                        items={data.languages}
                        emptyLabel="زبانی ثبت نشده است."
                        columns={[
                            { label: "زبان", render: (i) => i.language },
                            { label: "خواندن", render: (i) => i.reading },
                            { label: "نوشتن", render: (i) => i.writing },
                            { label: "صحبت کردن", render: (i) => i.speaking },
                            {
                                label: "درک مطلب",
                                render: (i) => i.comprehension,
                            },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">گواهینامه‌ها</p>
                    <SectionRepeaterTable
                        items={data.certificates}
                        emptyLabel="گواهینامه‌ای ثبت نشده است."
                        columns={[
                            { label: "عنوان", render: (i) => i.title },
                            {
                                label: "اعتبار تا",
                                render: (i) => dateValue(i.expire_at),
                            },
                        ]}
                    />
                </div>
                {specialSkills.length > 0 && (
                    <div>
                        <p className="text-sm font-medium mb-2">
                            مهارت‌های خاص
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {specialSkills.map((skill, index) => (
                                <span
                                    key={index}
                                    className="rounded-md bg-muted px-2 py-1 text-sm"
                                >
                                    {skill}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
                <div>
                    <p className="text-sm font-medium mb-2">
                        مهارت‌های تخصصی نرم‌افزاری
                    </p>
                    <SectionRepeaterTable
                        items={software.specialized}
                        emptyLabel="مهارت تخصصی ثبت نشده است."
                        columns={[
                            { label: "نام", render: (i) => i.name },
                            { label: "سطح", render: (i) => i.level },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">
                        مهارت‌های عمومی نرم‌افزاری
                    </p>
                    <SectionRepeaterTable
                        items={software.general}
                        emptyLabel="مهارت عمومی ثبت نشده است."
                        columns={[
                            { label: "نام", render: (i) => i.name },
                            { label: "سطح", render: (i) => i.level },
                        ]}
                    />
                </div>
            </div>
            {extra}
        </SectionCard>
    );
}
