import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField, FormSelectField, FormDatePicker } from "@/components/shared/form-fields";
import { FormRepeater } from "@/components/shared/form-repeater";
import {
    LANGUAGE_LEVEL_OPTIONS,
    SOFTWARE_LEVEL_OPTIONS,
} from "@/features/recruitment/constants";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function SkillsSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>مهارت‌ها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field name="skills.languages">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="زبان‌ها"
                            renderHeader={(item, index) => (
                                <span>{String(item.language || `زبان ${index + 1}`)}</span>
                            )}
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

                <Card>
                    <CardHeader>
                        <CardTitle>مهارت‌های نرم‌افزاری تخصصی</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form.Field name="skills.software_skills.specialized">
                            {(field) => (
                                <FormRepeater
                                    defaultMode="card"
                                    field={field}
                                    label="نرم‌افزارهای تخصصی"
                                    maxItems={8}
                                    renderHeader={(item, index) => (
                                        <span>{String(item.name || `نرم‌افزار ${index + 1}`)}</span>
                                    )}
                                    renderItem={(index) => (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <form.Field
                                                name={`skills.software_skills.specialized.${index}.name`}
                                            >
                                                {(f) => <FormTextField field={f} label="نام نرم‌افزار" />}
                                            </form.Field>
                                            <form.Field
                                                name={`skills.software_skills.specialized.${index}.level`}
                                            >
                                                {(f) => (
                                                    <FormSelectField
                                                        field={f}
                                                        label="سطح مهارت"
                                                        options={SOFTWARE_LEVEL_OPTIONS}
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                    )}
                                />
                            )}
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>مهارت‌های نرم‌افزاری عمومی</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form.Field name="skills.software_skills.general">
                            {(field) => (
                                <FormRepeater
                                    defaultMode="card"
                                    field={field}
                                    label="نرم‌افزارهای عمومی"
                                    maxItems={4}
                                    renderHeader={(item, index) => (
                                        <span>{String(item.name || `نرم‌افزار ${index + 1}`)}</span>
                                    )}
                                    renderItem={(index) => (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <form.Field
                                                name={`skills.software_skills.general.${index}.name`}
                                            >
                                                {(f) => <FormTextField field={f} label="نام نرم‌افزار" />}
                                            </form.Field>
                                            <form.Field
                                                name={`skills.software_skills.general.${index}.level`}
                                            >
                                                {(f) => (
                                                    <FormSelectField
                                                        field={f}
                                                        label="سطح مهارت"
                                                        options={SOFTWARE_LEVEL_OPTIONS}
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                    )}
                                />
                            )}
                        </form.Field>
                    </CardContent>
                </Card>

                <form.Field name="skills.certificates">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="گواهینامه‌ها"
                            renderHeader={(item, index) => (
                                <span>{String(item.title || `گواهینامه ${index + 1}`)}</span>
                            )}
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

                <Card>
                    <CardHeader>
                        <CardTitle>مهارت‌های خاص</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form.Field name="skills.special_skills">
                            {(field) => (
                                <FormRepeater
                                    defaultMode="card"
                                    field={field}
                                    label="مهارت‌های خاص"
                                    renderHeader={(item, index) => (
                                        <span>{String((typeof item === "string" ? item : null) || `مهارت ${index + 1}`)}</span>
                                    )}
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
            </CardContent>
        </Card>
    );
}
