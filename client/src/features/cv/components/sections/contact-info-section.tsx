import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/forms";
import { AddressForm } from "@/components/forms";
import {
    sendCvMobileOtp,
    sendCvEmailOtp,
    verifyCvMobileOtp,
    verifyCvEmailOtp,
} from "@/features/cv/api";
import { cvKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/cv/schemas/contact-info.schema";
import type { Cv, CvFormApi } from "@/features/cv/types";
import { OtpVerifiedInput } from "@/components/forms";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
};

export function ContactInfoSection({ form, cv }: SectionProps) {
    const queryClient = useQueryClient();

    const uuid = cv?.uuid ?? "";
    const emailVerified = cv?.email_verified ?? false;
    const mobileVerified = cv?.mobile_verified ?? false;

    const sendMobileOtpMutation = useMutation({
        mutationFn: (value: string) => sendCvMobileOtp(uuid, value),
        onError: (err) => toast.error(getApiError(err)),
    });

    const sendEmailOtpMutation = useMutation({
        mutationFn: (value: string) => sendCvEmailOtp(uuid, value),
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyMobileOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyCvMobileOtp(uuid, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: cvKeys.detail(uuid) });
            toast.success("موبایل تأیید شد.");
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const verifyEmailOtpMutation = useMutation({
        mutationFn: (otp: string) => verifyCvEmailOtp(uuid, otp),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: cvKeys.detail(uuid) });
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
                            label="ایمیل (اختیاری)"
                            sourceField={field}
                            placeholder="email@example.com"
                            isVerified={emailVerified}
                            sendOtp={(value) =>
                                sendEmailOtpMutation.mutateAsync(value)
                            }
                            verifyOtp={(code) =>
                                verifyEmailOtpMutation.mutateAsync(code)
                            }
                            onVerifiedChange={() => {
                                queryClient.invalidateQueries({
                                    queryKey: cvKeys.detail(uuid),
                                });
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
                            sendOtp={(value) =>
                                sendMobileOtpMutation.mutateAsync(value)
                            }
                            verifyOtp={(code) =>
                                verifyMobileOtpMutation.mutateAsync(code)
                            }
                            onVerifiedChange={() => {
                                queryClient.invalidateQueries({
                                    queryKey: cvKeys.detail(uuid),
                                });
                            }}
                        />
                    )}
                </form.Field>

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
                        mode="simple"
                    />
                </div>
            </CardContent>
        </Card>
    );
}
