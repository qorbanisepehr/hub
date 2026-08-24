import type { ReactNode } from "react";

import { SectionRow } from "@/components/shared/section-row";
import { SectionCard } from "./section-card";
import { asRecord } from "./shared";
import { useOptionLabel } from "./use-option-label";

type ContactInfoViewProps = {
    data: Record<string, unknown>;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function ContactInfoView({
    data,
    title = "اطلاعات تماس",
    action,
    extra,
}: ContactInfoViewProps) {
    const address = asRecord(data.address);
    const provinceLabel = useOptionLabel("province", address.province as string);
    const cityLabel = useOptionLabel("city", address.city as string);

    return (
        <SectionCard title={title} action={action}>
            <div className="divide-y">
                <SectionRow hideEmpty label="ایمیل" value={data.email} />
                <SectionRow
                    hideEmpty
                    label="شماره موبایل"
                    value={data.mobile}
                />
                <SectionRow hideEmpty label="تلفن ثابت" value={data.phone} />
                <SectionRow
                    hideEmpty
                    label="تلفن اضطراری"
                    value={data.emergency_phone}
                />
                <SectionRow
                    hideEmpty
                    label="کد پستی"
                    value={address.postal_code}
                />
                <SectionRow
                    hideEmpty
                    label="استان"
                    value={provinceLabel}
                />
                <SectionRow hideEmpty label="شهر" value={cityLabel} />
                <SectionRow
                    hideEmpty
                    label="محله"
                    value={address.neighborhood}
                />
                <SectionRow hideEmpty label="آدرس" value={address.address} />
                <SectionRow hideEmpty label="پلاک" value={address.plaque} />
                <SectionRow hideEmpty label="طبقه" value={address.floor} />
                <SectionRow hideEmpty label="واحد" value={address.unit} />
            </div>
            {extra}
        </SectionCard>
    );
}
