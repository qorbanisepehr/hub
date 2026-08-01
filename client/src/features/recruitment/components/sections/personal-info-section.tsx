import { useEffect } from "react";
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
    DOC_CATEGORY_SLUGS,
} from "@/features/recruitment/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import type {
    Questionnaire,
    QuestionnaireFormApi,
} from "@/features/recruitment/types";
import { fieldSchemas } from "@/features/recruitment/schemas/personal-info.schema";
import { MilitaryServiceFields } from "./military-service-fields";

type SectionProps = {
    form: QuestionnaireFormApi;
    questionnaire?: Questionnaire | null;
    uuid?: string;
};

export function PersonalInfoSection({
    form,
    questionnaire,
    uuid,
}: SectionProps) {
    const maritalStatus = useStore(
        form.store,
        (s) => s.values.personal_info?.marital_status,
    );
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isSingle = maritalStatus === "single";
    const isMale = gender === "male";

    const spouseField = useStore(
        form.store,
        (s) => s.values.personal_info?.spouse_employment_status,
    );

    useEffect(() => {
        if (isSingle && spouseField) {
            form.setFieldValue("personal_info.spouse_employment_status", "");
        } else if (!isSingle && !spouseField) {
            form.setFieldValue(
                "personal_info.spouse_employment_status",
                "housewife",
            );
        }
    }, [isSingle, spouseField, form]);

    useEffect(() => {
        if (
            gender === "female" &&
            form.state.values.personal_info?.military_status
        ) {
            form.setFieldValue("personal_info.military_status", undefined);
        } else if (
            gender === "male" &&
            !form.state.values.personal_info?.military_status
        ) {
            form.setFieldValue("personal_info.military_status", {
                status: "",
                organization: "",
                from: "",
                to: "",
                reason: "",
            });
        }
    }, [gender, form]);

    const spouseIsEmployed = spouseField === "employed";

    return (
        <Card>
            <CardHeader>
                <CardTitle>مشخصات فردی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.national_id"
                        validators={zodFieldValidators(
                            fieldSchemas.national_id,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="کد ملی"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.gender"
                        validators={zodFieldValidators(fieldSchemas.gender)}
                    >
                        {(field) => (
                            <FormRadioGroup
                                field={field}
                                label="جنسیت"
                                options={GENDER_OPTIONS}
                            />
                        )}
                    </form.Field>
                    {uuid && (
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO}
                            label="تصویر پرسنلی"
                            recordKey="photo"
                            variant="avatar"
                            aspectRatio={3 / 4}
                            actionsPlacement="overlay"
                            // description="فرمت‌های مجاز: JPG، PNG، WebP"
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
                        {(field) => (
                            <FormTextField field={field} label="نام خانوادگی" />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="personal_info.first_name_en">
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="First Name"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.last_name_en">
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="Last Name"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.birth_date"
                        validators={zodFieldValidators(fieldSchemas.birth_date)}
                    >
                        {(field) => (
                            <FormDatePicker field={field} label="تاریخ تولد" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_place"
                        validators={zodFieldValidators(
                            fieldSchemas.birth_place,
                        )}
                    >
                        {(field) => (
                            <FormTextField field={field} label="محل تولد" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_certificate_number"
                        validators={zodFieldValidators(
                            fieldSchemas.birth_certificate_number,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="شماره شناسنامه"
                            />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.father_name"
                        validators={zodFieldValidators(
                            fieldSchemas.father_name,
                        )}
                    >
                        {(field) => (
                            <FormTextField field={field} label="نام پدر" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.religion"
                        validators={zodFieldValidators(fieldSchemas.religion)}
                    >
                        {(field) => (
                            <FormTextField field={field} label="مذهب" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.blood_group"
                        validators={zodFieldValidators(
                            fieldSchemas.blood_group,
                        )}
                    >
                        {(field) => (
                            <FormSelectField
                                field={field}
                                label="گروه خونی"
                                options={BLOOD_GROUPS}
                            />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.marital_status"
                        validators={zodFieldValidators(
                            fieldSchemas.marital_status,
                        )}
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
                        {(field) => (
                            <FormNumberField
                                field={field}
                                label="تعداد افراد تحت تکفل"
                            />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.children_count">
                        {(field) => (
                            <FormNumberField
                                field={field}
                                label="تعداد فرزندان"
                            />
                        )}
                    </form.Field>
                </div>

                {!isSingle && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <form.Field name="personal_info.spouse_employment_status">
                            {(field) => (
                                <FormRadioGroup
                                    field={field}
                                    label="وضعیت اشتغال همسر"
                                    options={SPOUSE_EMPLOYMENT_OPTIONS}
                                />
                            )}
                        </form.Field>
                        {spouseIsEmployed && (
                            <form.Field
                                name="personal_info.spouse_job"
                                validators={zodFieldValidators(
                                    fieldSchemas.spouse_job,
                                )}
                            >
                                {(field) => (
                                    <FormTextField
                                        field={field}
                                        label="شغل همسر"
                                    />
                                )}
                            </form.Field>
                        )}
                    </div>
                )}

                {isMale && <MilitaryServiceFields form={form} />}
            </CardContent>
        </Card>
    );
}
