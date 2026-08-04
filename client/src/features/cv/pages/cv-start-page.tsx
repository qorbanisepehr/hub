import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "@tanstack/react-form";
import { z } from "zod";
import { toast } from "sonner";
import { IconLoader2, IconSend } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";
import { OtpVerificationForm } from "@/components/shared/otp-verification-form";
import {
    initCv,
    verifyCvInitOtp,
    resendCvInitOtp,
} from "@/features/cv/api";
import type { InitCvResponse } from "@/features/cv/types";
import { CV_ENTITY } from "@/features/cv/constants";
import { getApiError } from "@/lib/error-utils";
import { setGrantToken } from "@/lib/grant";
import { MOBILE_ACCEPTED_REGEX } from "@/lib/field-rules";

const initSchema = z.object({
    first_name: z
        .string()
        .trim()
        .min(1, "نام الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    last_name: z
        .string()
        .trim()
        .min(1, "نام خانوادگی الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    email: z
        .string()
        .trim()
        .max(255, "حداکثر ۲۵۵ کاراکتر")
        .refine(
            (v) =>
                v === "" ||
                z.string().email().safeParse(v).success,
            "فرمت ایمیل نامعتبر است",
        ),
    mobile: z
        .string()
        .trim()
        .min(1, "شماره موبایل الزامی است")
        .regex(
            MOBILE_ACCEPTED_REGEX,
            "فرمت شماره موبایل نامعتبر است (مثال: 09121234567)",
        )
        .max(15, "حداکثر ۱۵ کاراکتر"),
});

export function CvStartPage() {
    const navigate = useNavigate();
    const [otpUuid, setOtpUuid] = useState<string | null>(null);
    const [otpExpiresIn, setOtpExpiresIn] = useState(0);

    const initMutation = useMutation({
        mutationFn: initCv,
        onSuccess: (response) => {
            const initData = response.data as InitCvResponse;
            setOtpUuid(initData.data.uuid);
            setOtpExpiresIn(initData.expires_in);
            toast.success(initData.message);
        },
        onError: (err: any) => {
            toast.error(getApiError(err));
        },
    });

    const form = useForm({
        defaultValues: {
            first_name: "",
            last_name: "",
            email: "",
            mobile: "",
        },
        validators: { onSubmit: initSchema },
        onSubmit: async ({ value }) => {
            initMutation.mutate(value);
        },
    });

    return (
        <div className="min-h-screen bg-background pt-16">
            <div className="mx-auto max-w-2xl px-4 py-12">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold">رزومه</h1>
                    <p className="text-muted-foreground mt-2">
                        لطفاً اطلاعات زیر را تکمیل کنید تا رزومه برای شما ایجاد
                        شود.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {otpUuid ? "تأیید شماره موبایل" : "اطلاعات تماس"}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {otpUuid ? (
                            <div className="space-y-4">
                                <OtpVerificationForm
                                    sendOtp={() => resendCvInitOtp(otpUuid)}
                                    verifyOtp={(otp) =>
                                        verifyCvInitOtp(otpUuid, otp)
                                    }
                                    onVerified={(data) => {
                                        const newUuid = data.data?.uuid;
                                        if (!newUuid) return;

                                        if (data.access_token) {
                                            setGrantToken(
                                                CV_ENTITY,
                                                newUuid,
                                                "edit",
                                                data.access_token,
                                                data.expires_in ?? 3600,
                                            );
                                        }

                                        toast.success(
                                            "شماره موبایل تأیید شد.",
                                        );
                                        navigate({
                                            to: "/cv/$uuid",
                                            params: { uuid: newUuid },
                                        });
                                    }}
                                    description="کد تأیید ۶ رقمی به شماره موبایل شما ارسال شد. لطفاً کد را وارد کنید."
                                    label="کد تأیید"
                                    initialCountdown={otpExpiresIn}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full"
                                    onClick={() => {
                                        setOtpUuid(null);
                                        setOtpExpiresIn(0);
                                        initMutation.reset();
                                    }}
                                >
                                    بازگشت
                                </Button>
                            </div>
                        ) : (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    form.handleSubmit();
                                }}
                                className="space-y-4"
                            >
                                {initMutation.error && (
                                    <ErrorBanner
                                        message={
                                            getApiError(initMutation.error) ??
                                            "خطای ناشناخته"
                                        }
                                    />
                                )}

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <form.Field name="first_name">
                                        {(field) => (
                                            <FormTextField
                                                field={field}
                                                label="نام"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field name="last_name">
                                        {(field) => (
                                            <FormTextField
                                                field={field}
                                                label="نام خانوادگی"
                                            />
                                        )}
                                    </form.Field>
                                </div>

                                <form.Field name="email">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="ایمیل (اختیاری)"
                                            dir="ltr"
                                        />
                                    )}
                                </form.Field>

                                <form.Field name="mobile">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="شماره موبایل"
                                            dir="ltr"
                                        />
                                    )}
                                </form.Field>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={initMutation.isPending}
                                >
                                    {initMutation.isPending ? (
                                        <IconLoader2 className="size-4 animate-spin ms-2" />
                                    ) : (
                                        <IconSend className="size-4 ms-2" />
                                    )}
                                    شروع تکمیل رزومه
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
