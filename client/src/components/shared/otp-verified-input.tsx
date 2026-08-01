import { useState, useEffect } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import {
    IconLoader2,
    IconSend,
    IconCheck,
    IconPencil,
    IconArrowRight,
    IconX,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel, FieldError } from "@/components/ui/field";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { useOtpVerification } from "@/hooks/use-otp-verification";

export type OtpVerifiedInputProps = {
    label: string;
    sourceField: AnyFieldApi;
    isVerified: boolean;
    sendOtp: () => Promise<any>;
    verifyOtp: (otp: string) => Promise<any>;
    onVerifiedChange?: () => void;
    placeholder?: string;
    dir?: "ltr" | "rtl";
    otpLength?: number;
    cooldownSeconds?: number;
    initialCountdown?: number;
};

export function OtpVerifiedInput({
    label,
    sourceField,
    isVerified,
    sendOtp: sendOtpFn,
    verifyOtp: verifyOtpFn,
    onVerifiedChange,
    placeholder,
    dir = "ltr",
    otpLength = 6,
    cooldownSeconds = 120,
    initialCountdown = 0,
}: OtpVerifiedInputProps) {
    const [otpSent, setOtpSent] = useState(false);
    const [isEditing, setIsEditing] = useState(!isVerified);
    const [initialVerifiedValue, setInitialVerifiedValue] = useState(
        isVerified ? ((sourceField.state.value as string) ?? "") : "",
    );

    const sourceValue = (sourceField.state.value as string) ?? "";

    useEffect(() => {
        if (isVerified) {
            setInitialVerifiedValue(sourceValue);
            setIsEditing(false);
            setOtpSent(false);
        }
    }, [isVerified]);

    const {
        otp,
        setOtp,
        isSending,
        isVerifying,
        error,
        setError,
        countdown,
        sendOtp,
        verifyOtp,
        reset: resetOtp,
    } = useOtpVerification({
        sendOtp: sendOtpFn,
        verifyOtp: verifyOtpFn,
        onVerified: () => {
            setOtpSent(false);
            setIsEditing(false);
            setInitialVerifiedValue((sourceField.state.value as string) ?? "");
            onVerifiedChange?.();
        },
        cooldownSeconds,
        otpLength,
        initialCountdown,
    });

    const hasValueChanged = sourceValue !== initialVerifiedValue;
    const isCurrentlyVerified = isVerified && !hasValueChanged;

    const showOtpMode = otpSent && isEditing;
    const showVerifiedBadge = isCurrentlyVerified;

    async function handleSendClick(e: React.MouseEvent) {
        e.preventDefault();
        const ok = await sendOtp();
        if (ok) setOtpSent(true);
    }

    async function handleResendClick(e: React.MouseEvent) {
        e.preventDefault();
        resetOtp();
        const ok = await sendOtp();
        if (ok) setOtpSent(true);
    }

    function handleBackFromOtp() {
        setOtpSent(false);
        setOtp("");
        setError(null);
    }

    function handleCancelEditing() {
        if (isVerified) {
            sourceField.handleChange(initialVerifiedValue);
        }
        setOtpSent(false);
        setOtp("");
        setError(null);
        if (isVerified) {
            setIsEditing(false);
        }
    }

    const canSend =
        !isSending &&
        !!sourceValue &&
        sourceField.state.meta.isValid &&
        !(isVerified && !hasValueChanged);

    return (
        <div className="space-y-3">
            <Field>
                <FieldLabel className="flex items-center justify-between">
                    <span>{label}</span>
                    <Badge
                        variant={showVerifiedBadge ? "default" : "secondary"}
                    >
                        {showVerifiedBadge ? "تأیید شده" : "تأیید نشده"}
                    </Badge>
                </FieldLabel>

                {showOtpMode ? (
                    <div className="space-y-4 md:space-y-0 md:flex items-center justify-between md:gap-2">
                        <div dir="ltr">
                            <InputOTP
                                maxLength={otpLength}
                                value={otp}
                                onChange={(v) => setOtp(v)}
                                dir="ltr"
                                containerClassName="justify-center gap-2"
                                autoFocus
                            >
                                {Array.from({ length: otpLength }, (_, i) => (
                                    <InputOTPGroup dir="ltr" key={i}>
                                        <InputOTPSlot
                                            index={i}
                                            className="rounded-lg border first:rounded-lg first:border last:rounded-lg"
                                        />
                                    </InputOTPGroup>
                                ))}
                            </InputOTP>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={verifyOtp}
                                disabled={
                                    isVerifying || otp.length !== otpLength
                                }
                                className="flex-1"
                            >
                                {isVerifying ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconCheck className="size-4" />
                                )}
                                تأیید
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleResendClick}
                                disabled={isSending || countdown > 0}
                                className="shrink-0"
                            >
                                {isSending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : countdown > 0 ? (
                                    <>
                                        <IconSend className="size-4" />
                                        {countdown} ثانیه
                                    </>
                                ) : (
                                    <>
                                        <IconSend className="size-4" />
                                        ارسال مجدد
                                    </>
                                )}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleBackFromOtp}
                                className="shrink-0"
                                title="بازگشت"
                            >
                                <IconArrowRight className="size-4 ms-1 rtl:rotate-180" />
                            </Button>
                        </div>
                    </div>
                ) : isEditing ? (
                    <>
                        <div className="flex items-end gap-2">
                            <Input
                                id={sourceField.name}
                                name={sourceField.name}
                                value={sourceField.state.value ?? ""}
                                onBlur={sourceField.handleBlur}
                                onChange={(e) => {
                                    sourceField.handleChange(e.target.value);
                                    resetOtp();
                                }}
                                dir={dir}
                                placeholder={placeholder}
                                className="flex-1"
                            />
                            {countdown > 0 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled
                                    className="shrink-0"
                                >
                                    <IconSend className="size-4" />
                                    {countdown} ثانیه
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleSendClick}
                                    disabled={!canSend}
                                    className="shrink-0"
                                >
                                    {isSending ? (
                                        <IconLoader2 className="size-4 animate-spin" />
                                    ) : (
                                        <IconSend className="size-4" />
                                    )}
                                    {otpSent ? "ارسال مجدد" : "ارسال کد"}
                                </Button>
                            )}
                            {isVerified && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleCancelEditing}
                                    className="shrink-0"
                                    title="انصراف"
                                >
                                    <IconX className="size-4" />
                                    <span className="sr-only">انصراف</span>
                                </Button>
                            )}
                        </div>
                        {!sourceField.state.meta.isValid && (
                            <FieldError
                                errors={sourceField.state.meta.errors}
                            />
                        )}
                    </>
                ) : (
                    <div className="flex items-end gap-2">
                        <Input
                            value={sourceValue}
                            dir={dir}
                            disabled
                            className="flex-1"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setIsEditing(true)}
                            className="shrink-0"
                        >
                            <IconPencil className="size-3.5 ms-1" />
                            تغییر
                        </Button>
                    </div>
                )}
            </Field>

            {showOtpMode && (
                <p className="text-sm text-muted-foreground">
                    کد تأیید {otpLength} رقمی به{" "}
                    <span className="font-medium text-foreground" dir="ltr">
                        {sourceValue}
                    </span>{" "}
                    ارسال شد.
                </p>
            )}

            {error && showOtpMode && (
                <p className="text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
