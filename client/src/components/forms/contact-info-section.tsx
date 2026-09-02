"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/forms";
import { AddressForm } from "@/components/forms";
import { OtpVerifiedInput } from "@/components/forms";
import type { SectionFormApi } from "@/types/form-types";
import { getApiError } from "@/lib/error-utils";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/questionnaire/schemas/contact-info.schema";

/**
 * OTP verification wiring injected by features that gate email/mobile behind
 * OTP (questionnaire + cv). Features that persist plain fields (employee)
 * omit the bundle entirely and render plain text inputs.
 */
export type ContactOtpConfig = {
    uuid: string;
    emailVerified: boolean;
    mobileVerified: boolean;
    /** Query-key factory for the entity detail, used for invalidation. */
    detailKey: (uuid: string) => readonly unknown[];
    sendEmailOtp: (uuid: string, value: string) => Promise<unknown>;
    sendMobileOtp: (uuid: string, value: string) => Promise<unknown>;
    verifyEmailOtp: (uuid: string, otp: string) => Promise<unknown>;
    verifyMobileOtp: (uuid: string, otp: string) => Promise<unknown>;
};

type ContactValidators = ReturnType<typeof zodFieldValidators>;

type ContactInfoSectionProps = {
    form: SectionFormApi;
    emailLabel?: string;
    emailValidators: ContactValidators;
    mobileValidators: ContactValidators;
    /** When provided email/mobile render OTP verification; otherwise plain text fields. */
    otp?: ContactOtpConfig;
    addressMode?: "full" | "simple";
};

function ContactOtpField({
    form,
    uuid,
    fieldName,
    label,
    validators,
    isVerified,
    detailKey,
    sendOtp,
    verifyOtp,
    placeholder,
    successMessage,
}: {
    form: SectionFormApi;
    uuid: string;
    fieldName: "email" | "mobile";
    label: string;
    validators: ContactValidators;
    isVerified: boolean;
    detailKey: (uuid: string) => readonly unknown[];
    sendOtp: (uuid: string, value: string) => Promise<unknown>;
    verifyOtp: (uuid: string, otp: string) => Promise<unknown>;
    placeholder: string;
    successMessage: string;
}) {
    const queryClient = useQueryClient();

    const invalidate = () =>
        queryClient.invalidateQueries({ queryKey: detailKey(uuid) });

    const sendOtpMutation = useMutation({
        mutationFn: (value: string) => sendOtp(uuid, value),
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyOtpMutation = useMutation({
        mutationFn: (otpCode: string) => verifyOtp(uuid, otpCode),
        onSuccess: () => {
            invalidate();
            toast.success(successMessage);
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    return (
        <form.Field name={fieldName} validators={validators}>
            {(field) => (
                <OtpVerifiedInput
                    label={label}
                    sourceField={field}
                    placeholder={placeholder}
                    isVerified={isVerified}
                    sendOtp={(value) => sendOtpMutation.mutateAsync(value)}
                    verifyOtp={(otpCode) =>
                        verifyOtpMutation.mutateAsync(otpCode)
                    }
                    onVerifiedChange={invalidate}
                />
            )}
        </form.Field>
    );
}

export function ContactInfoSection({
    form,
    emailLabel = "ایمیل",
    emailValidators,
    mobileValidators,
    otp,
    addressMode = "full",
}: ContactInfoSectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تماس</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {otp ? (
                    <>
                        <ContactOtpField
                            form={form}
                            uuid={otp.uuid}
                            fieldName="email"
                            label={emailLabel}
                            validators={emailValidators}
                            isVerified={otp.emailVerified}
                            detailKey={otp.detailKey}
                            sendOtp={otp.sendEmailOtp}
                            verifyOtp={otp.verifyEmailOtp}
                            placeholder="email@example.com"
                            successMessage="ایمیل تأیید شد."
                        />
                        <ContactOtpField
                            form={form}
                            uuid={otp.uuid}
                            fieldName="mobile"
                            label="شماره موبایل"
                            validators={mobileValidators}
                            isVerified={otp.mobileVerified}
                            detailKey={otp.detailKey}
                            sendOtp={otp.sendMobileOtp}
                            verifyOtp={otp.verifyMobileOtp}
                            placeholder="09121234567"
                            successMessage="موبایل تأیید شد."
                        />
                    </>
                ) : (
                    <>
                        <form.Field
                            name="email"
                            validators={emailValidators}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label={emailLabel}
                                    placeholder="email@example.com"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="mobile"
                            validators={mobileValidators}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="شماره موبایل"
                                    placeholder="09121234567"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>
                    </>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="contact_info.phone"
                        validators={zodFieldValidators(fieldSchemas.phone)}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="تلفن ثابت"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="contact_info.emergency_phone"
                        validators={zodFieldValidators(
                            fieldSchemas.emergency_phone,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="تلفن اضطراری"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                </div>

                <div>
                    <span className="text-sm font-medium mb-3 block">
                        آدرس
                    </span>
                    <AddressForm
                        form={form}
                        prefix="contact_info.address"
                        mode={addressMode}
                    />
                </div>
            </CardContent>
        </Card>
    );
}