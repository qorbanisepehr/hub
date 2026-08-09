import { useEffect, useMemo } from "react";
import { useStore } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { FormTextField, FormDatePicker } from "@/components/shared/form-fields";
import {
    PlaceFields,
    FormOptionRadioGroup,
} from "@/components/shared/form-option-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { buildPersonalInfoSchemas } from "@/features/cv/schemas/personal-info.schema";
import {
    GENDER_MALE,
    GENDER_FEMALE,
} from "@/features/recruitment/schemas/personal-info.schema";
import { CV_DOC_CATEGORY_SLUGS } from "@/features/cv/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import type { Cv, CvFormApi } from "@/features/cv/types";
import { MilitaryServiceFields } from "@/features/recruitment/components/sections/military-service-fields";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
    uuid?: string;
};

export function PersonalInfoSection({ form, cv, uuid }: SectionProps) {
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isMale = gender === GENDER_MALE;

    const { data: genderOptions } = useFormOptionsByGroup("gender");
    const { data: maritalOptions } = useFormOptionsByGroup("marital_status");
    const { data: militaryOptions } = useFormOptionsByGroup("military_status");
    const { data: provinceOptions } = useFormOptionsByGroup("province");
    const { data: cityOptions } = useFormOptionsByGroup("city");

    const optionsLoaded =
        genderOptions !== undefined &&
        maritalOptions !== undefined &&
        militaryOptions !== undefined &&
        provinceOptions !== undefined &&
        cityOptions !== undefined;

    const schemas = useMemo(() => {
        if (!optionsLoaded) return undefined;
        return buildPersonalInfoSchemas({
            gender: genderOptions,
            marital_status: maritalOptions,
            military_status: militaryOptions,
            province: provinceOptions,
            birth_place: cityOptions,
        });
    }, [
        optionsLoaded,
        genderOptions,
        maritalOptions,
        militaryOptions,
        provinceOptions,
        cityOptions,
    ]);

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

    void cv;

    if (!optionsLoaded) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>مشخصات فردی</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="space-y-2">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {Array.from({ length: 2 }).map((_, i) => (
                            <div key={i} className="space-y-2">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="space-y-2">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                </CardContent>
            </Card>
        );
    }

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
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.national_id,
                                  )
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
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.gender,
                                  )
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
                            categorySlug={CV_DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO}
                            entity="cv"
                            label="تصویر پرسنلی"
                            recordKey="photo"
                            variant="avatar"
                            aspectRatio={3 / 4}
                            actionsPlacement="overlay"
                        />
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="first_name"
                        validators={
                            schemas
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.first_name,
                                  )
                                : undefined
                        }
                    >
                        {(field) => <FormTextField field={field} label="نام" />}
                    </form.Field>
                    <form.Field
                        name="last_name"
                        validators={
                            schemas
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.last_name,
                                  )
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormTextField field={field} label="نام خانوادگی" />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <form.Field
                        name="personal_info.birth_date"
                        validators={
                            schemas
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.birth_date,
                                  )
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
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.birth_place,
                                  )
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
                                      schemas.fieldSchemas
                                          .birth_certificate_number,
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

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="personal_info.marital_status"
                        validators={
                            schemas
                                ? zodFieldValidators(
                                      schemas.fieldSchemas.marital_status,
                                  )
                                : undefined
                        }
                    >
                        {(field) => (
                            <FormOptionRadioGroup
                                field={field}
                                label="وضعیت تأهل"
                                group="marital_status"
                                filter={(option) =>
                                    option.label === "مجرد" ||
                                    option.label === "متاهل"
                                }
                            />
                        )}
                    </form.Field>
                </div>

                {isMale && (
                    <MilitaryServiceFields
                        form={form}
                        mode="simple"
                        basePath="personal_info.military_status"
                    />
                )}
            </CardContent>
        </Card>
    );
}
