import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { FormTextField, FormSelectField, FormDatePicker } from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";
import {
    LANGUAGE_LEVEL_OPTIONS,
    SOFTWARE_LEVEL_OPTIONS,
} from "@/features/recruitment/constants";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

const LANGUAGE_COLUMNS = [
    { key: "language", label: "زبان" },
    { key: "reading", label: "خواندن" },
    { key: "writing", label: "نوشتن" },
    { key: "speaking", label: "صحبت کردن" },
    { key: "comprehension", label: "درک مطلب" },
];

const SOFTWARE_COLUMNS = [
    { key: "name", label: "نرم‌افزار" },
    { key: "level", label: "سطح مهارت", render: (v: unknown) => {
        const labels: Record<string, string> = { "1": "مبتدی", "2": "متوسط", "3": "خوب", "4": "عالی" };
        return labels[String(v)] ?? String(v ?? "—");
    }},
];

const CERTIFICATE_COLUMNS = [
    { key: "title", label: "عنوان" },
    { key: "expire_at", label: "تاریخ انقضا" },
];

function SoftwareItem({
    form,
    index,
    prefix,
}: {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    index: number;
    prefix: string;
}) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <form.Field name={`${prefix}.${index}.name`}>
                {(f) => <FormTextField field={f} label="نام نرم‌افزار" />}
            </form.Field>
            <form.Field name={`${prefix}.${index}.level`}>
                {(f) => (
                    <FormSelectField
                        field={f}
                        label="سطح مهارت"
                        options={SOFTWARE_LEVEL_OPTIONS}
                    />
                )}
            </form.Field>
        </div>
    );
}

export function SkillsSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>مهارت‌ها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {/* Languages */}
                <form.Field name="skills.languages">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="زبان‌ها"
                            columns={LANGUAGE_COLUMNS}
                            getSummary={(item) => ({
                                language: item.language,
                                reading: item.reading,
                                writing: item.writing,
                                speaking: item.speaking,
                                comprehension: item.comprehension,
                            })}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <form.Field name={`skills.languages.${index}.language`}>
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="زبان"
                                                placeholder="انگلیسی"
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field name={`skills.languages.${index}.reading`}>
                                        {(f) => (
                                            <FormSelectField
                                                field={f}
                                                label="خواندن"
                                                options={LANGUAGE_LEVEL_OPTIONS}
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field name={`skills.languages.${index}.writing`}>
                                        {(f) => (
                                            <FormSelectField
                                                field={f}
                                                label="نوشتن"
                                                options={LANGUAGE_LEVEL_OPTIONS}
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field name={`skills.languages.${index}.speaking`}>
                                        {(f) => (
                                            <FormSelectField
                                                field={f}
                                                label="صحبت کردن"
                                                options={LANGUAGE_LEVEL_OPTIONS}
                                            />
                                        )}
                                    </form.Field>
                                    <form.Field name={`skills.languages.${index}.comprehension`}>
                                        {(f) => (
                                            <FormSelectField
                                                field={f}
                                                label="درک مطلب"
                                                options={LANGUAGE_LEVEL_OPTIONS}
                                            />
                                        )}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <Separator />

                {/* Software Skills — grouped under one heading */}
                <div className="space-y-4">
                    <span className="text-sm font-medium">مهارت‌های نرم‌افزاری</span>

                    <form.Field name="skills.software_skills.specialized">
                        {(field) => (
                            <FormRepeater
                                field={field}
                                label="تخصصی"
                                columns={SOFTWARE_COLUMNS}
                                maxItems={8}
                                getSummary={(item) => ({
                                    name: item.name,
                                    level: item.level,
                                })}
                                renderItem={(index) => (
                                    <SoftwareItem
                                        form={form}
                                        index={index}
                                        prefix="skills.software_skills.specialized"
                                    />
                                )}
                            />
                        )}
                    </form.Field>

                    <form.Field name="skills.software_skills.general">
                        {(field) => (
                            <FormRepeater
                                field={field}
                                label="عمومی"
                                columns={SOFTWARE_COLUMNS}
                                maxItems={4}
                                getSummary={(item) => ({
                                    name: item.name,
                                    level: item.level,
                                })}
                                renderItem={(index) => (
                                    <SoftwareItem
                                        form={form}
                                        index={index}
                                        prefix="skills.software_skills.general"
                                    />
                                )}
                            />
                        )}
                    </form.Field>
                </div>

                <Separator />

                {/* Certificates */}
                <form.Field name="skills.certificates">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="گواهینامه‌ها"
                            columns={CERTIFICATE_COLUMNS}
                            getSummary={(item) => ({
                                title: item.title,
                                expire_at: item.expire_at,
                            })}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <form.Field name={`skills.certificates.${index}.title`}>
                                        {(f) => <FormTextField field={f} label="عنوان" />}
                                    </form.Field>
                                    <form.Field name={`skills.certificates.${index}.expire_at`}>
                                        {(f) => <FormDatePicker field={f} label="تاریخ انقضا" />}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <Separator />

                {/* Special Skills */}
                <form.Field name="skills.special_skills">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="مهارت‌های خاص"
                            columns={[{ key: "value", label: "مهارت" }]}
                            getSummary={(item) => ({
                                value: typeof item === "string" ? item : "",
                            })}
                            renderItem={(index) => (
                                <form.Field name={`skills.special_skills.${index}`}>
                                    {(f) => (
                                        <FormTextField
                                            field={f}
                                            label={`مهارت ${index + 1}`}
                                            placeholder="نام مهارت"
                                        />
                                    )}
                                </form.Field>
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
