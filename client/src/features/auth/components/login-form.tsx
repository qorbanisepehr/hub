"use client";

import * as React from "react";
import { useForm } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-store";
import { useNavigate } from "@tanstack/react-router";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    FieldError,
    FieldGroup,
} from "@/components/ui/field";
import { cn } from "@/lib/utils";
import { useAuth } from "@/features/auth/useAuth";
import { IdentifierField } from "@/features/auth/components/identifier-field";
import { CodeField } from "@/features/auth/components/code-field";
import { PasswordField } from "@/features/auth/components/password-field";
import type { LoginMode } from "@/features/auth/types";
import { getApiError } from "@/lib/error-utils";
import { useOtpVerification } from "@/hooks/use-otp-verification";

const identifierSchema = z.string().min(1, "این فیلد الزامی است");
const codeSchema = z.string().length(6, "لطفا کد تایید را وارد کنید");
const passwordSchema = z.string().min(1, "لطفا رمز عبور را وارد کنید");

export function LoginForm({
    className,
    redirectTo,
    ...props
}: React.ComponentProps<"div"> & { redirectTo?: string }) {
    const { loginOtp, loginPassword, verifyOtp } = useAuth();
    const navigate = useNavigate();
    const [mode, setMode] = React.useState<LoginMode>("otp");
    const [otpSent, setOtpSent] = React.useState(false);
    const [otpDestination, setOtpDestination] = React.useState<string | null>(
        null,
    );

    const form = useForm({
        defaultValues: {
            identifier: "",
            code: "",
            password: "",
        },
        onSubmit: async ({ value }) => {
            if (mode === "otp") {
                if (otpSent) {
                    await handleVerify();
                } else {
                    await handleSendOtp();
                }
            } else {
                if (!value.password) return;
                await loginPassword.mutateAsync({
                    identifier: value.identifier,
                    password: value.password,
                });
                navigate({ to: redirectTo ?? "/dashboard" });
            }
        },
    });

    const handleRequestOtp = async () => {
        const { identifier } = form.state.values;

        const result = await loginOtp.mutateAsync(identifier);

        if (result.data.destination) {
            setOtpDestination(result.data.destination);
            setOtpSent(true);
        }

        return result;
    };

    const {
        otp,
        setOtp,
        isSending,
        isVerifying,
        error: otpError,
        countdown,
        sendOtp: handleSendOtp,
        verifyOtp: handleVerify,
        reset: resetOtp,
    } = useOtpVerification({
        sendOtp: handleRequestOtp,
        verifyOtp: (code) =>
            verifyOtp.mutateAsync({
                identifier: form.state.values.identifier,
                code,
            }),
        onVerified: () => {
            form.setFieldValue("code", "");
            navigate({ to: redirectTo ?? "/dashboard" });
        },
        cooldownSeconds: 120,
        otpLength: 6,
    });

    // Keep the form's code field and the hook's otp state in sync.
    const codeValue = useStore(form.store, (s) => s.values.code);
    React.useEffect(() => {
        setOtp(codeValue);
    }, [codeValue, setOtp]);

    const handleBackToIdentifier = () => {
        setOtpSent(false);
        setOtpDestination(null);
        form.setFieldValue("code", "");
        resetOtp();
    };

    const error =
        otpError ?? (mode === "password" ? getApiError(loginPassword.error) : null);

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <Card>
                <CardHeader className="text-center">
                    <CardTitle>کـــانی مــس</CardTitle>
                    <CardDescription>
                        {otpSent
                            ? `کد تایید به ${otpDestination} ارسال شد`
                            : "ورود به حساب کاربری"}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            form.handleSubmit();
                        }}
                    >
                        <FieldGroup>
                            <form.Field
                                name="identifier"
                                validators={{ onSubmit: identifierSchema }}
                            >
                                {(field) => (
                                    <IdentifierField field={field} disabled={otpSent} />
                                )}
                            </form.Field>

                            {otpSent ? (
                                <>
                                    <form.Field
                                        name="code"
                                        validators={{ onSubmit: codeSchema }}
                                    >
                                        {(field) => (
                                            <CodeField field={field} />
                                        )}
                                    </form.Field>

                                    <Button
                                        type="submit"
                                        disabled={isVerifying}
                                        className="w-full"
                                    >
                                        {isVerifying
                                            ? "در حال بررسی..."
                                            : "تایید کد"}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={isSending || countdown > 0}
                                        onClick={handleSendOtp}
                                        className="w-full"
                                    >
                                        {isSending
                                            ? "در حال ارسال..."
                                            : countdown > 0
                                              ? `ارسال مجدد کد (${countdown} ثانیه)`
                                              : "ارسال مجدد کد"}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        className="text-muted-foreground"
                                        onClick={handleBackToIdentifier}
                                    >
                                        تغییر ایمیل یا شماره موبایل
                                    </Button>
                                </>
                            ) : (
                                <>
                                    {mode === "otp" && (
                                        <Button
                                            type="submit"
                                            disabled={isSending}
                                            className="w-full"
                                        >
                                            {isSending
                                                ? "در حال ارسال..."
                                                : "ارسال کد یکبار مصرف"}
                                        </Button>
                                    )}

                                    {mode === "password" && (
                                        <>
                                            <form.Field
                                                name="password"
                                                validators={{
                                                    onSubmit: passwordSchema,
                                                }}
                                            >
                                                {(field) => (
                                                    <PasswordField field={field} />
                                                )}
                                            </form.Field>

                                            <Button
                                                type="submit"
                                                disabled={loginPassword.isPending}
                                                className="w-full"
                                            >
                                                {loginPassword.isPending
                                                    ? "در حال ورود..."
                                                    : "ورود با رمز عبور"}
                                            </Button>
                                        </>
                                    )}

                                    <div className="relative my-4">
                                        <div className="absolute inset-0 flex items-center">
                                            <span className="w-full border-t" />
                                        </div>
                                        <div className="relative flex justify-center text-xs uppercase">
                                            <span className="bg-card px-2 text-muted-foreground">
                                                یا
                                            </span>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setMode(
                                                mode === "otp"
                                                    ? "password"
                                                    : "otp",
                                            )
                                        }
                                        className="w-full"
                                    >
                                        {mode === "otp"
                                            ? "ورود با رمز عبور"
                                            : "ارسال کد یکبار مصرف"}
                                    </Button>
                                </>
                            )}

                            {error && (
                                <FieldError>
                                    <p>{error}</p>
                                </FieldError>
                            )}
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
