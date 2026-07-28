import { useState, useEffect, useCallback } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import { IconLoader2, IconSend, IconCheck, IconPencil } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";

const COUNTDOWN_SECONDS = 60;

type SourceInputWithOtpProps = {
    label: string;
    sourceField: AnyFieldApi;
    isVerified: boolean;
    sendMutation: { mutate: () => void; isPending: boolean };
    verifyMutation: { mutate: (otp: string) => void; isPending: boolean };
    otp: string;
    onOtpChange: (otp: string) => void;
    onVerify: () => void;
    sourcePlaceholder?: string;
    dir?: "ltr" | "rtl";
};

export function SourceInputWithOtp({
    label,
    sourceField,
    isVerified,
    sendMutation,
    verifyMutation,
    otp,
    onOtpChange,
    onVerify,
    sourcePlaceholder,
    dir = "ltr",
}: SourceInputWithOtpProps) {
    const [otpSent, setOtpSent] = useState(false);
    const [countdown, setCountdown] = useState(0);

    const sourceValue = (sourceField.state.value as string) ?? "";

    // Reset when verification succeeds
    useEffect(() => {
        if (isVerified) {
            setOtpSent(false);
            setCountdown(0);
        }
    }, [isVerified]);

    // Countdown timer
    useEffect(() => {
        if (countdown <= 0) return;
        const timer = setTimeout(() => setCountdown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [countdown]);

    const handleSend = useCallback(() => {
        sendMutation.mutate();
        setOtpSent(true);
        setCountdown(COUNTDOWN_SECONDS);
    }, [sendMutation]);

    function handleResend() {
        handleSend();
    }

    const showOtp = otpSent && !isVerified;

    return (
        <div className="space-y-3">
            {/* ── Source input + send/change button ── */}
            <Field>
                <FieldLabel>
                    <span>{label}</span>
                    <Badge variant={isVerified ? "default" : "secondary"}>
                        {isVerified ? "تأیید شده" : "تأیید نشده"}
                    </Badge>
                </FieldLabel>
                <div className="flex items-end gap-2">
                    <Input
                        value={sourceValue}
                        onChange={(e) => {
                            sourceField.handleChange(e.target.value);
                            // Reset OTP state when source changes
                            if (otpSent && !isVerified) {
                                setOtpSent(false);
                                setCountdown(0);
                            }
                        }}
                        dir={dir}
                        placeholder={sourcePlaceholder}
                        disabled={isVerified}
                        className="flex-1"
                    />
                    {isVerified ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setOtpSent(false)}
                            className="shrink-0"
                        >
                            <IconPencil className="size-3.5 ms-1" />
                            تغییر
                        </Button>
                    ) : countdown > 0 ? (
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
                            onClick={handleSend}
                            disabled={sendMutation.isPending || !sourceValue}
                            className="shrink-0"
                        >
                            {sendMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconSend className="size-4" />
                            )}
                            {otpSent ? "ارسال مجدد" : "ارسال کد"}
                        </Button>
                    )}
                </div>
            </Field>

            {/* ── Sent message ── */}
            {showOtp && (
                <p className="text-sm text-muted-foreground">
                    کد تأیید ۶ رقمی به <span className="font-medium text-foreground">{sourceValue}</span> ارسال شد.
                </p>
            )}

            {/* ── OTP input + verify button ── */}
            {showOtp && (
                <div className="flex items-end gap-2">
                    <Field className="flex-1">
                        <FieldLabel>کد تأیید</FieldLabel>
                        <InputOTP
                            maxLength={6}
                            value={otp}
                            onChange={onOtpChange}
                            dir="ltr"
                        >
                            <InputOTPGroup dir="ltr">
                                <InputOTPSlot index={0} />
                                <InputOTPSlot index={1} />
                                <InputOTPSlot index={2} />
                                <InputOTPSlot index={3} />
                                <InputOTPSlot index={4} />
                                <InputOTPSlot index={5} />
                            </InputOTPGroup>
                        </InputOTP>
                    </Field>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onVerify}
                        disabled={
                            verifyMutation.isPending ||
                            otp.length !== 6
                        }
                        className="shrink-0"
                    >
                        {verifyMutation.isPending ? (
                            <IconLoader2 className="size-4 animate-spin" />
                        ) : (
                            <IconCheck className="size-4" />
                        )}
                        تأیید
                    </Button>
                </div>
            )}
        </div>
    );
}
