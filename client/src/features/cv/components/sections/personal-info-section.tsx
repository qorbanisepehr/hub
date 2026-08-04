import { useEffect } from "react";
import { useStore } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormSelectField,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import {
    GENDER_OPTIONS,
    MARITAL_STATUS_OPTIONS,
} from "@/features/cv/constants";
import { zodFieldValidators } from "@/lib/validation-helpers";
import type { Cv, CvFormApi } from "@/features/cv/types";
import { fieldSchemas } from "@/features/cv/schemas/personal-info.schema";
import { MilitaryServiceFields } from "@/features/recruitment/components/sections/military-service-fields";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
};

export function PersonalInfoSection({ form, cv }: SectionProps) {
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isMale = gender === "male";

    useEffect(() => {
        if (gender === "female" && form.state.values.personal_info?.military_status) {
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

    void cv;

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
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        validators={zodFieldValidators(fieldSchemas.birth_place)}
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

                {isMale && <MilitaryServiceFields form={form} />}
            </CardContent>
        </Card>
    );
}
