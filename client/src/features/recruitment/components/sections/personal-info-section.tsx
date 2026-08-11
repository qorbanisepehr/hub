import { useEffect, useMemo } from "react";
import { useStore } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormNumberField,
    FormDatePicker,
} from "@/components/shared/form-fields";
import {
    PlaceFields,
    FormOptionRadioGroup,
    FormOptionSelectField,
} from "@/components/shared/form-option-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { DOC_CATEGORY_SLUGS } from "@/features/recruitment/constants";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import {
    buildPersonalInfoSchemas,
    GENDER_MALE,
    GENDER_FEMALE,
    MARITAL_SINGLE,
    SPOUSE_EMPLOYED,
} from "@/features/recruitment/schemas/personal-info.schema";
import { zodFieldValidators } from "@/lib/validation-helpers";
import type {
    Questionnaire,
    QuestionnaireFormApi,
} from "@/features/recruitment/types";
import { MilitaryServiceFields } from "./military-service-fields";

type SectionProps = {
    form: QuestionnaireFormApi;
    questionnaire?: Questionnaire | null;
    uuid?: string;
    /** Grant entity the section's documents belong to. Defaults to "questionnaire". */
    entity?: string;
};

export function PersonalInfoSection({
    form,
    questionnaire,
    uuid,
    entity = "questionnaire",
}: SectionProps) {
    const maritalStatus = useStore(
        form.store,
        (s) => s.values.personal_info?.marital_status,
    );
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isSingle = maritalStatus === MARITAL_SINGLE;
    const isMale = gender === GENDER_MALE;

    const spouseField = useStore(
        form.store,
        (s) => s.values.personal_info?.spouse_employment_status,
    );

    const { data: genderOptions } = useFormOptionsByGroup("gender");
    const { data: bloodGroupOptions } = useFormOptionsByGroup("blood_group");
    const { data: maritalOptions } = useFormOptionsByGroup("marital_status");
    const { data: spouseOptions } = useFormOptionsByGroup("spouse_employment_status");
    const { data: militaryOptions } = useFormOptionsByGroup("military_status");
    const { data: religionOptions } = useFormOptionsByGroup("religion");
    const { data: religionSectOptions } = useFormOptionsByGroup("religion_sect");
    const { data: provinceOptions } = useFormOptionsByGroup("province");
    const { data: cityOptions } = useFormOptionsByGroup("city");

    const religion = useStore(
        form.store,
        (s) => s.values.personal_info?.religion,
    );

    const optionsLoaded =
        genderOptions !== undefined &&
        bloodGroupOptions !== undefined &&
        maritalOptions !== undefined &&
        spouseOptions !== undefined &&
        militaryOptions !== undefined &&
        religionOptions !== undefined &&
        religionSectOptions !== undefined &&
        provinceOptions !== undefined &&
        cityOptions !== undefined;

    const schemas = useMemo(() => {
        if (!optionsLoaded) return undefined;
        return buildPersonalInfoSchemas({
            gender: genderOptions,
            blood_group: bloodGroupOptions,
            marital_status: maritalOptions,
            spouse_employment_status: spouseOptions,
            military_status: militaryOptions,
            religion: religionOptions,
            religion_sect: religionSectOptions,
            province: provinceOptions,
            birth_place: cityOptions,
        });
    }, [
        optionsLoaded,
        genderOptions,
        bloodGroupOptions,
        maritalOptions,
        spouseOptions,
        militaryOptions,
        religionOptions,
        religionSectOptions,
        provinceOptions,
        cityOptions,
    ]);

    const religionValue = religionOptions?.find(
        (option) => option.label === religion,
    )?.value;

    const religionSectOptionsUi = useMemo(() => {
        if (!religionSectOptions) return [];
        return religionSectOptions
            .filter((option) => option.parent_value === religionValue)
            .map((option) => ({ value: option.label, label: option.label }));
    }, [religionSectOptions, religionValue]);

    useEffect(() => {
        if (isSingle && spouseField) {
            form.setFieldValue("personal_info.spouse_employment_status", "");
        } else if (!isSingle && !spouseField) {
            const defaultSpouseValue =
                spouseOptions?.find((option) => option.value === "housewife")?.label ??
                spouseOptions?.[0]?.label ??
                "";
            form.setFieldValue(
                "personal_info.spouse_employment_status",
                defaultSpouseValue,
            );
        }
    }, [isSingle, spouseField, spouseOptions, form]);

    useEffect(() => {
        if (
            gender === GENDER_FEMALE &&
            form.state.values.personal_info?.military_status
        ) {
            form.setFieldValue("personal_info.military_status", undefined);
        } else if (
            gender === GENDER_MALE &&
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

    const spouseIsEmployed = spouseField === SPOUSE_EMPLOYED;

    useEffect(() => {
        const currentSect = form.state.values.personal_info?.religion_sect;
        if (!currentSect) return;
        const validSects = religionSectOptionsUi.map((option) => option.value);
        if (!validSects.includes(currentSect)) {
            form.setFieldValue("personal_info.religion_sect", "");
        }
    }, [religion, religionSectOptionsUi, form]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>مشخصات فردی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.national_id"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.national_id)
                                : undefined
                        }
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
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.gender)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionRadioGroup
                                field={field}
                                label="جنسیت"
                                group="gender"
                            />
                        )}
                    </form.Field>
                    {uuid && (
                        <FileUploadField
                            uuid={uuid}
                            entity={entity}
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
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.first_name)
                                : undefined
                        }
                    >
                        {(field) => <FormTextField field={field} label="نام" />}
                    </form.Field>
                    <form.Field
                        name="last_name"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.last_name)
                                : undefined
                        }
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

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <form.Field
                        name="personal_info.birth_date"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.birth_date)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormDatePicker field={field} label="تاریخ تولد" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_place"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.birth_place)
                                : undefined
                        }
                    >
                        {(field) => <PlaceFields field={field} mode="city" />}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_certificate_number"
                        validators={
                            schemas
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.birth_certificate_number,
                                  )
                                : undefined
                        }
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
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.father_name)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormTextField field={field} label="نام پدر" />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.religion"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.religion)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionSelectField
                                field={field}
                                label="دین"
                                group="religion"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.religion_sect"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.religion_sect)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionSelectField
                                field={field}
                                label="مذهب"
                                group="religion_sect"
                                filter={(option) =>
                                    option.parent_value === religionValue
                                }
                                placeholder={
                                    religion
                                        ? "انتخاب مذهب"
                                        : "ابتدا دین را انتخاب کنید"
                                }
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.blood_group"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.blood_group)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionSelectField
                                field={field}
                                label="گروه خونی"
                                group="blood_group"
                            />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field
                        name="personal_info.marital_status"
                        validators={
                            schemas
                                ? zodFieldValidators(schemas.fieldSchemas.marital_status)
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionRadioGroup
                                field={field}
                                label="وضعیت تأهل"
                                group="marital_status"
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
                                <FormOptionRadioGroup
                                    field={field}
                                    label="وضعیت اشتغال همسر"
                                    group="spouse_employment_status"
                                />
                            )}
                        </form.Field>
                        {spouseIsEmployed && (
                            <form.Field
                                name="personal_info.spouse_job"
                                validators={
                                    schemas
                                        ? zodFieldValidators(schemas.fieldSchemas.spouse_job)
                                        : undefined
                                }
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
