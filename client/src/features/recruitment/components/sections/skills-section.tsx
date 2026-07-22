import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField, FormTextarea, FormSelectField } from "@/components/shared/form-fields";
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
                            field={field}
                            label="زبان‌ها"
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
                                    field={field}
                                    label="نرم‌افزارهای تخصصی"
                                    maxItems={8}
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
                                    field={field}
                                    label="نرم‌افزارهای عمومی"
                                    maxItems={4}
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
                            field={field}
                            label="گواهینامه‌ها"
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <form.Field name={`skills.certificates.${index}.title`}>
                                        {(f) => <FormTextField field={f} label="عنوان" />}
                                    </form.Field>
                                    <form.Field name={`skills.certificates.${index}.expire_at`}>
                                        {(f) => <FormTextField field={f} label="تاریخ انقضا" />}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <form.Field name="skills.special_skills">
                    {(field) => <FormTextarea field={field} label="مهارت‌های خاص" />}
                </form.Field>
            </CardContent>
        </Card>
    );
}
