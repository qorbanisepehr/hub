import { IconLoader2, IconSend, IconCheck } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { ErrorBanner } from "@/components/shared/error-banner";
import { useOtpVerification } from "@/hooks/use-otp-verification";

type OtpVerificationFormProps = {
    sendOtp: () => Promise<any>;
    verifyOtp: (otp: string) => Promise<any>;
    onVerified?: (data: any) => void;
    label?: string;
    description?: string;
    cooldownSeconds?: number;
    otpLength?: number;
    initialCountdown?: number;
    className?: string;
};

export function OtpVerificationForm({
    sendOtp,
    verifyOtp,
    onVerified,
    label = "کد تأیید",
    description,
    cooldownSeconds = 120,
    otpLength = 6,
    initialCountdown,
    className,
}: OtpVerificationFormProps) {
    const {
        otp,
        setOtp,
        isSending,
        isVerifying,
        error,
        countdown,
        sendOtp: handleSend,
        verifyOtp: handleVerify,
    } = useOtpVerification({
        sendOtp,
        verifyOtp,
        onVerified,
        cooldownSeconds,
        otpLength,
        initialCountdown,
    });

    return (
        <div className={className}>
            {description && (
                <p className="text-sm text-muted-foreground mb-4">
                    {description}
                </p>
            )}

            {error && <ErrorBanner message={error} className="mb-4" />}

            <Field>
                <FieldLabel>{label}</FieldLabel>
                <div dir="ltr">
                    <InputOTP
                        maxLength={otpLength}
                        value={otp}
                        onChange={(v) => setOtp(v)}
                        dir="ltr"
                        containerClassName="justify-around"
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
            </Field>

            <div className="flex gap-2 mt-4">
                <Button
                    type="button"
                    variant="outline"
                    onClick={handleSend}
                    disabled={isSending || countdown > 0}
                    className="flex-1"
                >
                    {isSending ? (
                        <IconLoader2 className="size-4 animate-spin ms-2" />
                    ) : countdown > 0 ? (
                        <IconSend className="size-4 ms-2" />
                    ) : (
                        <IconSend className="size-4 ms-2" />
                    )}
                    {countdown > 0 ? `${countdown} ثانیه` : "ارسال کد"}
                </Button>

                <Button
                    type="button"
                    onClick={handleVerify}
                    disabled={isVerifying || otp.length !== otpLength}
                >
                    {isVerifying ? (
                        <IconLoader2 className="size-4 animate-spin ms-2" />
                    ) : (
                        <IconCheck className="size-4 ms-2" />
                    )}
                    تأیید
                </Button>
            </div>
        </div>
    );
}
