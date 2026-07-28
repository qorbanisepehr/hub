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
    DOC_CATEGORY_SLUGS,
} from "@/features/recruitment/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
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
                    <form.Field
                        name="personal_info.national_id"
                        validators={zodFieldValidators(fieldSchemas.national_id)}
                    >
                        {(field) => <FormTextField field={field} label="کد ملی" dir="ltr" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.gender"
                        validators={zodFieldValidators(fieldSchemas.gender)}
                    >
                        {(field) => (
                            <FormRadioGroup field={field} label="جنسیت" options={GENDER_OPTIONS} />
                        )}
                    </form.Field>
                    {uuid && (
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO}
                            label="تصویر پرسنلی"
                            recordKey="photo"
                            aspectRatio={3/4}
                        />
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="first_name"
                        validators={zodFieldValidators(fieldSchemas.first_name)}
                    >
                        {(field) => <FormTextField field={field} label="نام" />}
                    </form.Field>
                    <form.Field
                        name="last_name"
                        validators={zodFieldValidators(fieldSchemas.last_name)}
                    >
                        {(field) => <FormTextField field={field} label="نام خانوادگی" />}
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
                    <form.Field
                        name="personal_info.birth_date"
                        validators={zodFieldValidators(fieldSchemas.birth_date)}
                    >
                        {(field) => <FormDatePicker field={field} label="تاریخ تولد" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_place"
                        validators={zodFieldValidators(fieldSchemas.birth_place)}
                    >
                        {(field) => <FormTextField field={field} label="محل تولد" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_certificate_number"
                        validators={zodFieldValidators(fieldSchemas.birth_certificate_number)}
                    >
                        {(field) => <FormTextField field={field} label="شماره شناسنامه" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.father_name"
                        validators={zodFieldValidators(fieldSchemas.father_name)}
                    >
                        {(field) => <FormTextField field={field} label="نام پدر" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.religion"
                        validators={zodFieldValidators(fieldSchemas.religion)}
                    >
                        {(field) => <FormTextField field={field} label="مذهب" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.blood_group"
                        validators={zodFieldValidators(fieldSchemas.blood_group)}
                    >
                        {(field) => (
                            <FormSelectField field={field} label="گروه خونی" options={BLOOD_GROUPS} />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.marital_status"
                        validators={zodFieldValidators(fieldSchemas.marital_status)}
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

                {!isSingle && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <form.Field name="personal_info.spouse_employment_status">
                            {(field) => (
                                <>
                                    <FormRadioGroup
                                        field={field}
                                        label="وضعیت اشتغال همسر"
                                        options={SPOUSE_EMPLOYMENT_OPTIONS}
                                    />
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {spouseField ? "تأیید شده" : "انتخاب کنید"}
                                    </p>
                                </>
                            )}
                        </form.Field>
                    </div>
                )}

                {isMale && (
                    <div className="rounded-lg border p-4 space-y-4">
                        <span className="text-sm font-medium">وضعیت نظام وظیفه</span>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field
                                name="personal_info.military_status.status"
                                validators={zodFieldValidators(fieldSchemas.military_status.shape.status)}
                            >
                                {(f) => (
                                    <FormSelectField
                                        field={f}
                                        label="وضعیت"
                                        options={MILITARY_STATUS_OPTIONS}
                                    />
                                )}
                            </form.Field>
                            <form.Field
                                name="personal_info.military_status.organization"
                                validators={zodFieldValidators(
                                        fieldSchemas.military_status.shape.organization,
                                    )}
                            >
                                {(f) => <FormTextField field={f} label="سازمان" />}
                            </form.Field>
                            <form.Field
                                name="personal_info.military_status.from"
                                validators={zodFieldValidators(fieldSchemas.military_status.shape.from)}
                            >
                                {(f) => <FormDatePicker field={f} label="تاریخ شروع" />}
                            </form.Field>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field
                                name="personal_info.military_status.to"
                                validators={zodFieldValidators(fieldSchemas.military_status.shape.to)}
                            >
                                {(f) => <FormDatePicker field={f} label="تاریخ پایان" />}
                            </form.Field>
                            <form.Field
                                name="personal_info.military_status.reason"
                                validators={zodFieldValidators(
                                        fieldSchemas.military_status.shape.reason,
                                    )}
                            >
                                {(f) => <FormTextField field={f} label="دلیل" />}
                            </form.Field>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
