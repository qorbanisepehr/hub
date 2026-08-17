import type { ReactNode } from "react";

import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { SectionCard } from "./section-card";
import { asRecord, dateValue, stringValue } from "./shared";

type PersonalInfoViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
    topRight?: ReactNode;
};

export function PersonalInfoView({
    data,
    title = "اطلاعات هویتی",
    action,
    extra,
    topRight,
}: PersonalInfoViewProps) {
    const military = asRecord(data.military_status);
    const spouseEmployed = stringValue(data.spouse_employment_status);
    const englishName = [
        stringValue(data.first_name_en),
        stringValue(data.last_name_en),
    ]
        .filter(Boolean)
        .join(" ");

    return (
        <SectionCard title={title} action={action}>
            <div className="flex flex-col gap-4 md:flex-row">
                <div className="flex-1 divide-y">
                    <SectionRow hideEmpty label="نام" value={data.first_name} />
                    <SectionRow
                        hideEmpty
                        label="نام خانوادگی"
                        value={data.last_name}
                    />
                    <SectionRow
                        hideEmpty
                        label="کد ملی"
                        value={data.id_number}
                    />
                    <SectionRow hideEmpty label="جنسیت" value={data.gender} />
                    <SectionRow
                        hideEmpty
                        label="تاریخ تولد"
                        value={dateValue(data.birth_date)}
                    />
                    <SectionRow
                        hideEmpty
                        label="وضعیت تأهل"
                        value={data.marital_status}
                    />
                    <SectionRow
                        hideEmpty
                        label="نام انگلیسی"
                        value={englishName}
                    />
                    <SectionRow
                        hideEmpty
                        label="محل تولد"
                        value={data.birth_place}
                    />
                    <SectionRow
                        hideEmpty
                        label="شماره شناسنامه"
                        value={data.birth_certificate_number}
                    />
                    <SectionRow
                        hideEmpty
                        label="نام پدر"
                        value={data.father_name}
                    />
                    <SectionRow hideEmpty label="دین" value={data.religion} />
                    <SectionRow
                        hideEmpty
                        label="مذهب"
                        value={data.religion_sect}
                    />
                    <SectionRow
                        hideEmpty
                        label="گروه خونی"
                        value={data.blood_group}
                    />
                    <SectionRow
                        hideEmpty
                        label="تعداد افراد تحت تکفل"
                        value={data.dependents_count}
                    />
                    <SectionRow
                        hideEmpty
                        label="تعداد فرزندان"
                        value={data.children_count}
                    />
                    <SectionRow
                        hideEmpty
                        label="وضعیت اشتغال همسر"
                        value={data.spouse_employment_status}
                    />
                    {spouseEmployed && (
                        <SectionRow
                            hideEmpty
                            label="شغل همسر"
                            value={data.spouse_job}
                        />
                    )}
                </div>
                {topRight}
            </div>
            {stringValue(military.status) && (
                <SectionCard title="وضعیت نظام وظیفه" className="mt-4">
                    <div className="flex flex-col gap-4 md:flex-row">
                        <div className="flex-1 divide-y">
                            <SectionRow
                                hideEmpty
                                label="وضعیت"
                                value={military.status}
                            />
                            <SectionRow
                                hideEmpty
                                label="محل خدمت"
                                value={military.organization}
                            />
                            <SectionRow
                                hideEmpty
                                label="از تاریخ"
                                value={dateValue(military.from)}
                            />
                            <SectionRow
                                hideEmpty
                                label="تا تاریخ"
                                value={dateValue(military.to)}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات"
                                value={military.reason}
                            />
                        </div>
                    </div>
                </SectionCard>
            )}
            {extra}
        </SectionCard>
    );
}
