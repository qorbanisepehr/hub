import type { ReactNode } from "react";

import { Card, CardContent } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { SectionCard } from "./section-card";
import { boolLabel } from "./shared";
import { useOptionLabel } from "./use-option-label";

type AdditionalInfoViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function AdditionalInfoView({
    data,
    title = "اطلاعات تکمیلی",
    action,
    extra,
}: AdditionalInfoViewProps) {
    const physicalConditionLabel = useOptionLabel("physical_condition", data.physical_condition as string);
    const disabilityTypeLabel = useOptionLabel("disability_type", data.disability_type as string);

    return (
        <SectionCard title={title} action={action}>
            <div className="space-y-6">
                <SectionRepeaterTable
                    items={data.references}
                    emptyLabel="ارجاعی ثبت نشده است."
                    columns={[
                        { label: "نام کامل", render: (i) => i.full_name },
                        { label: "رابطه", render: (i) => i.relationship },
                        {
                            label: "تلفن محل کار",
                            render: (i) => i.workplace_phone,
                        },
                    ]}
                />
                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-0 shadow-none">
                        <CardContent className="divide-y p-0">
                            <SectionRow
                                hideEmpty
                                label="بیماری مزمن"
                                value={boolLabel(data.has_chronic_disease)}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات بیماری مزمن"
                                value={data.chronic_disease_description}
                            />
                            <SectionRow
                                hideEmpty
                                label="جراحی عمده"
                                value={boolLabel(data.has_major_surgery)}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات جراحی"
                                value={data.major_surgery_description}
                            />
                            <SectionRow
                                hideEmpty
                                label="ناتوانی"
                                value={boolLabel(data.has_disability)}
                            />
                            <SectionRow
                                hideEmpty
                                label="نوع ناتوانی"
                                value={disabilityTypeLabel}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات ناتوانی"
                                value={data.disability_description}
                            />
                            <SectionRow
                                hideEmpty
                                label="وضعیت جسمانی"
                                value={physicalConditionLabel}
                            />
                        </CardContent>
                    </Card>
                    <Card className="border-0 shadow-none">
                        <CardContent className="divide-y p-0">
                            <SectionRow
                                hideEmpty
                                label="سابقه کیفری"
                                value={boolLabel(data.has_criminal_record)}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات کیفری"
                                value={data.criminal_record_description}
                            />
                            <SectionRow
                                hideEmpty
                                label="امکان سفر"
                                value={boolLabel(data.can_travel)}
                            />
                            <SectionRow
                                hideEmpty
                                label="توضیحات سفر"
                                value={data.travel_description}
                            />
                            <SectionRow
                                hideEmpty
                                label="دلیل تمایل به همکاری"
                                value={data.reason_for_joining}
                            />
                            <SectionRow
                                hideEmpty
                                label="نحوه آشنایی با شرکت"
                                value={data.company_introduction_method}
                            />
                            <SectionRow
                                hideEmpty
                                label="علایق"
                                value={data.hobbies}
                            />
                            <SectionRow
                                hideEmpty
                                label="نقاط قوت و بهبود"
                                value={data.strengths_and_improvements}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
            {extra}
        </SectionCard>
    );
}
