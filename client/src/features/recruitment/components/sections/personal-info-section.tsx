import { useEffect } from "react";
import type { ReactFormExtendedApi } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormNumberField,
    FormSelectField,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import {
    GENDER_OPTIONS,
    BLOOD_GROUPS,
    MARITAL_STATUS_OPTIONS,
    SPOUSE_EMPLOYMENT_OPTIONS,
    MILITARY_STATUS_OPTIONS,
} from "@/features/recruitment/constants";
import { zodFieldValidator } from "@/lib/validation-helpers";
import type { Questionnaire } from "@/features/recruitment/types";
import { fieldSchemas } from "@/features/recruitment/schemas/personal-info.schema";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    questionnaire?: Questionnaire | null;
    uuid?: string;
};

export function PersonalInfoSection({ form, questionnaire, uuid }: SectionProps) {
    const maritalStatus = useStore(form.store, (s) => s.values.personal_info?.marital_status);
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isSingle = maritalStatus === "single";
    const isMale = gender === "male";

    const spouseField = useStore(form.store, (s) => s.values.personal_info?.spouse_employment_status);

    useEffect(() => {
        if (isSingle && spouseField) {
            form.setFieldValue("personal_info.spouse_employment_status", "");
        } else if (!isSingle && !spouseField) {
            form.setFieldValue("personal_info.spouse_employment_status", "housewife");
        }
    }, [isSingle, spouseField, form]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>مشخصات فردی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="first_name">
                        {(field) => <FormTextField field={field} label="نام" />}
                    </form.Field>
                    <form.Field name="last_name">
                        {(field) => <FormTextField field={field} label="نام خانوادگی" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.gender"
                        validators={{ onBlur: zodFieldValidator(fieldSchemas.gender) }}
                    >
                        {(field) => (
                            <FormRadioGroup field={field} label="جنسیت" options={GENDER_OPTIONS} />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="personal_info.first_name_en">
                        {(field) => <FormTextField field={field} label="First Name" dir="ltr" />}
                    </form.Field>
                    <form.Field name="personal_info.last_name_en">
                        {(field) => <FormTextField field={field} label="Last Name" dir="ltr" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="personal_info.blood_group">
                        {(field) => (
                            <FormSelectField field={field} label="گروه خونی" options={BLOOD_GROUPS} />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_date"
                        validators={{ onBlur: zodFieldValidator(fieldSchemas.birth_date) }}
                    >
                        {(field) => <FormDatePicker field={field} label="تاریخ تولد" />}
                    </form.Field>
                    <form.Field name="personal_info.birth_place">
                        {(field) => <FormTextField field={field} label="محل تولد" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="personal_info.birth_certificate_number">
                        {(field) => <FormTextField field={field} label="شماره شناسنامه" />}
                    </form.Field>
                    <form.Field name="personal_info.father_name">
                        {(field) => <FormTextField field={field} label="نام پدر" />}
                    </form.Field>
                    <form.Field name="personal_info.religion">
                        {(field) => <FormTextField field={field} label="مذهب" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.marital_status"
                        validators={{ onBlur: zodFieldValidator(fieldSchemas.marital_status) }}
                    >
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="وضعیت تأهل"
                                options={MARITAL_STATUS_OPTIONS}
                            />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.dependents_count">
                        {(field) => <FormNumberField field={field} label="تعداد افراد تحت تکفل" />}
                    </form.Field>
                    <form.Field name="personal_info.children_count">
                        {(field) => <FormNumberField field={field} label="تعداد فرزندان" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="personal_info.spouse_employment_status">
                        {(field) => (
                            <>
                                <FormRadioGroup
                                    field={field}
                                    label="وضعیت اشتغال همسر"
                                    options={SPOUSE_EMPLOYMENT_OPTIONS}
                                    disabled={isSingle}
                                />
                                {isSingle && (
                                    <p className="text-xs text-muted-foreground mt-1">
                                        فقط برای افراد متاهل
                                    </p>
                                )}
                            </>
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.national_id"
                        validators={{ onBlur: zodFieldValidator(fieldSchemas.national_id) }}
                    >
                        {(field) => <FormTextField field={field} label="کد ملی" dir="ltr" />}
                    </form.Field>
                    {uuid && (
                        <FileUploadField
                            uuid={uuid}
                            categoryId={9}
                            label="عکس پرسنلی"
                            accept="image/jpeg,image/png,image/webp"
                        />
                    )}
                </div>

                {isMale && (
                    <div className="rounded-lg border p-4 space-y-4">
                        <span className="text-sm font-medium">وضعیت نظام وظیفه</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <form.Field name="personal_info.military_status.status">
                                {(field) => (
                                    <FormRadioGroup
                                        field={field}
                                        label="وضعیت"
                                        options={MILITARY_STATUS_OPTIONS}
                                    />
                                )}
                            </form.Field>
                            <form.Field name="personal_info.military_status.organization">
                                {(field) => <FormTextField field={field} label="سازمان" />}
                            </form.Field>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field name="personal_info.military_status.from">
                                {(field) => <FormDatePicker field={field} label="از تاریخ" />}
                            </form.Field>
                            <form.Field name="personal_info.military_status.to">
                                {(field) => <FormDatePicker field={field} label="تا تاریخ" />}
                            </form.Field>
                            <form.Field name="personal_info.military_status.reason">
                                {(field) => <FormTextField field={field} label="دلیل" />}
                            </form.Field>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
