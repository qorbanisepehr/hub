import { useState } from "react";
import type { ReactFormExtendedApi } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { z } from "zod";
import { IconLoader2, IconSend, IconCheck } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    FormTextField,
    FormNumberField,
    FormTextarea,
    FormSelectField,
    FormRadioGroup,
    FormDatePicker,
} from "@/components/shared/form-fields";
import {
    GENDER_OPTIONS,
    BLOOD_GROUPS,
    MARITAL_STATUS_OPTIONS,
    SPOUSE_EMPLOYMENT_OPTIONS,
    MILITARY_STATUS_OPTIONS,
} from "@/features/recruitment/constants";
import {
    sendMobileOtp,
    sendEmailOtp,
    verifyMobileOtp,
    verifyEmailOtp,
} from "@/features/recruitment/api";
import { getApiError } from "@/lib/error-utils";
import { zodFieldValidator } from "@/lib/validation-helpers";
import type { Questionnaire } from "@/features/recruitment/types";

const requiredString = z.string().min(1, "این فیلد الزامی است.");

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    questionnaire?: Questionnaire | null;
};

export function PersonalInfoSection({ form, questionnaire }: SectionProps) {
    const queryClient = useQueryClient();
    const [mobileOtp, setMobileOtp] = useState("");
    const [emailOtp, setEmailOtp] = useState("");

    const uuid = questionnaire?.uuid;
    const emailVerified = questionnaire?.email_verified ?? false;
    const mobileVerified = questionnaire?.mobile_verified ?? false;

    const maritalStatus = useStore(form.store, (s) => s.values.personal_info?.marital_status);
    const gender = useStore(form.store, (s) => s.values.personal_info?.gender);

    const isSingle = maritalStatus === "single";
    const isMale = gender === "male";

    const sendMobileOtpMutation = useMutation({
        mutationFn: () => sendMobileOtp(uuid!),
        onSuccess: () => toast.success("کد تأیید موبایل ارسال شد."),
        onError: (err) => toast.error(getApiError(err)),
    });

    const sendEmailOtpMutation = useMutation({
        mutationFn: () => sendEmailOtp(uuid!),
        onSuccess: () => toast.success("کد تأیید ایمیل ارسال شد."),
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyMobileOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyMobileOtp(uuid!, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
            toast.success("موبایل تأیید شد.");
            setMobileOtp("");
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyEmailOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyEmailOtp(uuid!, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
            toast.success("ایمیل تأیید شد.");
            setEmailOtp("");
        },
        onError: (err) => toast.error(getApiError(err)),
    });

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
                        validators={{ onBlur: zodFieldValidator(requiredString) }}
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

                <div className="rounded-lg border p-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">ایمیل</span>
                        <Badge variant={emailVerified ? "default" : "secondary"}>
                            {emailVerified ? "تأیید شده" : "تأیید نشده"}
                        </Badge>
                    </div>
                    <form.Field name="email">
                        {(field) => (
                            <FormTextField field={field} label="ایمیل" dir="ltr" />
                        )}
                    </form.Field>
                    <div className="flex items-end gap-2">
                        <Field className="flex-1">
                            <FieldLabel htmlFor="email_otp">کد تأیید ایمیل</FieldLabel>
                            <Input
                                id="email_otp"
                                value={emailOtp}
                                onChange={(e) => setEmailOtp(e.target.value)}
                                dir="ltr"
                                placeholder="۶ رقمی"
                                maxLength={6}
                            />
                        </Field>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => sendEmailOtpMutation.mutate()}
                            disabled={sendEmailOtpMutation.isPending}
                        >
                            {sendEmailOtpMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconSend className="size-4" />
                            )}
                            ارسال کد
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => verifyEmailOtpMutation.mutate(emailOtp)}
                            disabled={verifyEmailOtpMutation.isPending || emailOtp.length !== 6}
                        >
                            {verifyEmailOtpMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconCheck className="size-4" />
                            )}
                            تأیید
                        </Button>
                    </div>
                </div>

                <div className="rounded-lg border p-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">شماره موبایل</span>
                        <Badge variant={mobileVerified ? "default" : "secondary"}>
                            {mobileVerified ? "تأیید شده" : "تأیید نشده"}
                        </Badge>
                    </div>
                    <form.Field name="mobile">
                        {(field) => (
                            <FormTextField field={field} label="شماره موبایل" dir="ltr" />
                        )}
                    </form.Field>
                    <div className="flex items-end gap-2">
                        <Field className="flex-1">
                            <FieldLabel htmlFor="mobile_otp">کد تأیید موبایل</FieldLabel>
                            <Input
                                id="mobile_otp"
                                value={mobileOtp}
                                onChange={(e) => setMobileOtp(e.target.value)}
                                dir="ltr"
                                placeholder="۶ رقمی"
                                maxLength={6}
                            />
                        </Field>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => sendMobileOtpMutation.mutate()}
                            disabled={sendMobileOtpMutation.isPending}
                        >
                            {sendMobileOtpMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconSend className="size-4" />
                            )}
                            ارسال کد
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => verifyMobileOtpMutation.mutate(mobileOtp)}
                            disabled={verifyMobileOtpMutation.isPending || mobileOtp.length !== 6}
                        >
                            {verifyMobileOtpMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconCheck className="size-4" />
                            )}
                            تأیید
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form.Field name="personal_info.blood_group">
                        {(field) => (
                            <FormSelectField field={field} label="گروه خونی" options={BLOOD_GROUPS} />
                        )}
                    </form.Field>
                    <form.Field
                        name="personal_info.birth_date"
                        validators={{ onBlur: zodFieldValidator(requiredString) }}
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
                        validators={{ onBlur: zodFieldValidator(requiredString) }}
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
                        {(field) => {
                            if (isSingle && field.state.value) {
                                field.handleChange("");
                            }
                            if (!isSingle && !field.state.value) {
                                field.handleChange("housewife");
                            }
                            return (
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
                            );
                        }}
                    </form.Field>
                    <form.Field
                        name="personal_info.national_id"
                        validators={{ onBlur: zodFieldValidator(z.string().min(1, "کد ملی الزامی است.")) }}
                    >
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
