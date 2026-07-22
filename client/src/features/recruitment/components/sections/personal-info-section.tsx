import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormSelectField,
    FormRadioGroup,
} from "@/components/shared/form-fields";
import {
    GENDER_OPTIONS,
    BLOOD_GROUPS,
    MARITAL_STATUS_OPTIONS,
    SPOUSE_EMPLOYMENT_OPTIONS,
    MILITARY_STATUS_OPTIONS,
} from "@/features/recruitment/constants";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function PersonalInfoSection({ form }: SectionProps) {
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
                    <form.Field name="personal_info.gender">
                        {(field) => (
                            <FormRadioGroup field={field} label="جنسیت" options={GENDER_OPTIONS} />
                        )}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="personal_info.blood_group">
                        {(field) => (
                            <FormSelectField field={field} label="گروه خونی" options={BLOOD_GROUPS} />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.birth_date">
                        {(field) => <FormTextField field={field} label="تاریخ تولد" />}
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
                    <form.Field name="personal_info.marital_status">
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
                            <FormRadioGroup
                                field={field}
                                label="وضعیت اشتغال همسر"
                                options={SPOUSE_EMPLOYMENT_OPTIONS}
                            />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.national_id">
                        {(field) => <FormTextField field={field} label="کد ملی" dir="ltr" />}
                    </form.Field>
                    <form.Field name="personal_info.phone">
                        {(field) => <FormTextField field={field} label="تلفن ثابت" dir="ltr" />}
                    </form.Field>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="personal_info.emergency_phone">
                        {(field) => (
                            <FormTextField field={field} label="تلفن اضطراری" dir="ltr" />
                        )}
                    </form.Field>
                    <form.Field name="personal_info.photo">
                        {(field) => <FormTextField field={field} label="تصویر پروفایل" />}
                    </form.Field>
                </div>

                <form.Field name="personal_info.address">
                    {(field) => <FormTextarea field={field} label="آدرس" />}
                </form.Field>

                <Card>
                    <CardHeader>
                        <CardTitle>وضعیت نظام وظیفه</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
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
                                {(field) => <FormTextField field={field} label="از تاریخ" />}
                            </form.Field>
                            <form.Field name="personal_info.military_status.to">
                                {(field) => <FormTextField field={field} label="تا تاریخ" />}
                            </form.Field>
                            <form.Field name="personal_info.military_status.reason">
                                {(field) => <FormTextField field={field} label="دلیل" />}
                            </form.Field>
                        </div>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>
    );
}
