import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/shared/form-fields";
import { AddressForm } from "@/components/shared/address-form";
import {
    sendMobileOtp,
    sendEmailOtp,
    verifyMobileOtp,
    verifyEmailOtp,
} from "@/features/recruitment/api";
import { getApiError } from "@/lib/error-utils";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/recruitment/schemas/contact-info.schema";
import type { Questionnaire, QuestionnaireFormApi } from "@/features/recruitment/types";

import { OtpVerifiedInput } from "@/components/shared/otp-verified-input";

type SectionProps = {
    form: QuestionnaireFormApi;
    questionnaire?: Questionnaire | null;
};

export function ContactInfoSection({ form, questionnaire }: SectionProps) {
    const queryClient = useQueryClient();

    const uuid = questionnaire?.uuid ?? "";
    const emailVerified = questionnaire?.email_verified ?? false;
    const mobileVerified = questionnaire?.mobile_verified ?? false;

    const sendMobileOtpMutation = useMutation({
        mutationFn: (value: string) => sendMobileOtp(uuid, value),
        onError: (err) => toast.error(getApiError(err)),
    });

    const sendEmailOtpMutation = useMutation({
        mutationFn: (value: string) => sendEmailOtp(uuid, value),
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyMobileOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyMobileOtp(uuid, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
            toast.success("موبایل تأیید شد.");
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyEmailOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyEmailOtp(uuid, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
            toast.success("ایمیل تأیید شد.");
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تماس</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field
                    name="email"
                    validators={zodFieldValidators(fieldSchemas.email)}
                >
                    {(field) => (
                    <OtpVerifiedInput
                        label="ایمیل"
                        sourceField={field}
                        placeholder="email@example.com"
                        isVerified={emailVerified}
                        sendOtp={(value) => sendEmailOtpMutation.mutateAsync(value)}
                        verifyOtp={(code) => verifyEmailOtpMutation.mutateAsync(code)}
                        onVerifiedChange={() => {
                            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
                        }}
                    />
                    )}
                </form.Field>

                <form.Field
                    name="mobile"
                    validators={zodFieldValidators(fieldSchemas.mobile)}
                >
                    {(field) => (
                    <OtpVerifiedInput
                        label="شماره موبایل"
                        sourceField={field}
                        placeholder="09121234567"
                        isVerified={mobileVerified}
                        sendOtp={(value) => sendMobileOtpMutation.mutateAsync(value)}
                        verifyOtp={(code) => verifyMobileOtpMutation.mutateAsync(code)}
                        onVerifiedChange={() => {
                            queryClient.invalidateQueries({ queryKey: ["questionnaire", uuid] });
                        }}
                    />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="contact_info.phone"
                        validators={zodFieldValidators(fieldSchemas.phone)}
                    >
                        {(field) => <FormTextField field={field} label="تلفن ثابت" dir="ltr" />}
                    </form.Field>
                    <form.Field
                        name="contact_info.emergency_phone"
                        validators={zodFieldValidators(fieldSchemas.emergency_phone)}
                    >
                        {(field) => <FormTextField field={field} label="تلفن اضطراری" dir="ltr" />}
                    </form.Field>
                </div>

                <div>
                    <span className="text-sm font-medium mb-3 block">آدرس</span>
                    <AddressForm form={form} prefix="contact_info.address" />
                </div>
            </CardContent>
        </Card>
    );
}
