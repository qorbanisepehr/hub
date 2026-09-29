"use client";

import { useForm } from "@tanstack/react-form";
import { useNavigate } from "@tanstack/react-router";
import { useStore } from "@tanstack/react-store";
import * as React from "react";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import { ButtonGroup } from "@/components/ui/button-group";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { FieldError, FieldGroup } from "@/components/ui/field";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/features/auth";
import { CodeField } from "@/features/auth/components/code-field";
import { IdentifierField } from "@/features/auth/components/identifier-field";
import { PasswordField } from "@/features/auth/components/password-field";
import type { LoginChannel, LoginMode } from "@/features/auth/types";
import { useBranding } from "@/features/settings/hooks/use-branding";
import { useOtpVerification } from "@/hooks/use-otp-verification";
import { COMPANY_NAME } from "@/lib/brand";
import { getApiError } from "@/lib/error-utils";
import { cn } from "@/lib/utils";
import {
    IconLoader2,
    IconMail,
    IconPhone,
} from "@tabler/icons-react";

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
    const { data: branding } = useBranding();
    const [mode, setMode] = React.useState<LoginMode>("otp");
    const [otpChannel, setOtpChannel] = React.useState<LoginChannel>("email");
    const [otpSent, setOtpSent] = React.useState(false);
    const [signedIn, setSignedIn] = React.useState(false);
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
                setSignedIn(true);
                navigate({ to: redirectTo ?? "/dashboard" });
            }
        },
    });

    const handleRequestOtp = async () => {
        const { identifier } = form.state.values;

        const result = await loginOtp.mutateAsync({
            identifier,
            channel: otpChannel,
        });

        if (result.data.destination) {
            setOtpDestination(result.data.destination);
            setOtpSent(true);
        }

        return result;
    };

    const {
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
                channel: otpChannel,
            }),
        onVerified: () => {
            setSignedIn(true);
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
        otpError ??
        (mode === "password" ? getApiError(loginPassword.error) : null);

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <Card>
                <CardHeader className="text-center">
                    <CardTitle>{branding?.name ?? COMPANY_NAME}</CardTitle>
                    <CardDescription>
                        {otpSent
                            ? `کد تایید به ${otpDestination} ارسال شد`
                            : "ورود به حساب کاربری"}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {signedIn ? (
                        <div className="flex flex-col items-center justify-center gap-4 py-10">
                            <IconLoader2 className="size-8 animate-spin text-muted-foreground" />
                            <div className="flex w-full flex-col gap-3">
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                در حال ورود به حساب...
                            </p>
                        </div>
                    ) : (
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
                                        <IdentifierField
                                            field={field}
                                            disabled={otpSent}
                                        />
                                    )}
                                </form.Field>

                                {otpSent ? (
                                    <>
                                        <form.Field
                                            name="code"
                                            validators={{
                                                onSubmit: codeSchema,
                                            }}
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
                                            disabled={
                                                isSending || countdown > 0
                                            }
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
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="submit"
                                                    disabled={isSending}
                                                    className="flex-1"
                                                >
                                                    {isSending
                                                        ? "در حال ارسال..."
                                                        : "ارسال کد یکبار مصرف"}
                                                </Button>

                                                <ButtonGroup className="shrink-0 bg-secondary rounded border">
                                                    <Button
                                                        size="icon-sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            otpChannel !==
                                                                "email" &&
                                                            setOtpChannel(
                                                                "email",
                                                            )
                                                        }
                                                    >
                                                        {otpChannel ===
                                                        "email" ? (
                                                            <div className="relative">
                                                                <IconMail className="size-4 text-primary" />
                                                                <span className="h-0.75 w-full inline-block rounded-full absolute -bottom-1.5 right-0 bg-primary" />
                                                            </div>
                                                        ) : (
                                                            <IconMail className="size-4 text-primary/50" />
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="icon-sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            otpChannel !==
                                                                "mobile" &&
                                                            setOtpChannel(
                                                                "mobile",
                                                            )
                                                        }
                                                    >
                                                        {otpChannel ===
                                                        "mobile" ? (
                                                            <div className="relative">
                                                                <IconPhone className="size-4 text-primary" />
                                                                <span className="h-0.75 w-full inline-block rounded-full absolute -bottom-1.5 right-0 bg-primary" />
                                                            </div>
                                                        ) : (
                                                            <IconPhone className="size-4 text-primary/50" />
                                                        )}
                                                    </Button>
                                                </ButtonGroup>
                                            </div>
                                        )}

                                        {mode === "password" && (
                                            <>
                                                <form.Field
                                                    name="password"
                                                    validators={{
                                                        onSubmit:
                                                            passwordSchema,
                                                    }}
                                                >
                                                    {(field) => (
                                                        <PasswordField
                                                            field={field}
                                                        />
                                                    )}
                                                </form.Field>

                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        loginPassword.isPending
                                                    }
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
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
