import type { ReactNode } from "react";

import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { SectionCard } from "./section-card";
import { asRecord, dateValue, stringValue } from "./shared";
import { useOptionLabel, usePlaceLabel } from "./use-option-label";

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
    const genderLabel = useOptionLabel("gender", data.gender as string);
    const maritalLabel = useOptionLabel("marital_status", data.marital_status as string);
    const religionLabel = useOptionLabel("religion", data.religion as string);
    const sectLabel = useOptionLabel("religion_sect", data.religion_sect as string);
    const bloodLabel = useOptionLabel("blood_group", data.blood_group as string);
    const spouseLabel = useOptionLabel("spouse_employment_status", data.spouse_employment_status as string);
    const militaryStatusLabel = useOptionLabel("military_status", military.status as string);
    const birthPlaceLabel = usePlaceLabel(data.birth_place as string);
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
                    <SectionRow hideEmpty label="جنسیت" value={genderLabel} />
                    <SectionRow
                        hideEmpty
                        label="تاریخ تولد"
                        value={dateValue(data.birth_date)}
                    />
                    <SectionRow
                        hideEmpty
                        label="وضعیت تأهل"
                        value={maritalLabel}
                    />
                    <SectionRow
                        hideEmpty
                        label="نام انگلیسی"
                        value={englishName}
                    />
                    <SectionRow
                        hideEmpty
                        label="محل تولد"
                        value={birthPlaceLabel}
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
                    <SectionRow hideEmpty label="دین" value={religionLabel} />
                    <SectionRow
                        hideEmpty
                        label="مذهب"
                        value={sectLabel}
                    />
                    <SectionRow
                        hideEmpty
                        label="گروه خونی"
                        value={bloodLabel}
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
                        value={spouseLabel}
                    />
                    {stringValue(data.spouse_employment_status) && (
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
                                value={militaryStatusLabel}
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
