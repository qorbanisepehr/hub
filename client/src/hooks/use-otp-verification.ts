import { useState, useCallback, useEffect, useRef } from "react";
import { isAxiosError } from "axios";
import { toast } from "sonner";

export type OtpSendPayload = {
    message?: string;
    expires_in?: number;
    code_sent?: boolean;
};

export type OtpVerifyPayload = {
    message?: string;
    uuid?: string;
    access_token?: string;
    expires_in?: number;
    data?: { uuid?: string };
};

function parseRetryAfterHeader(err: unknown): number | null {
    if (!isAxiosError(err) || !err.response?.headers) return null;
    const value = err.response.headers["retry-after"];
    if (typeof value !== "string" && typeof value !== "number") return null;
    const seconds = parseInt(String(value), 10);
    return Number.isFinite(seconds) && seconds > 0 ? seconds : null;
}

type UseOtpVerificationOptions = {
    sendOtp: () => Promise<{ data?: OtpSendPayload }>;
    verifyOtp: (otp: string) => Promise<{ data: OtpVerifyPayload }>;
    onVerified?: (data: OtpVerifyPayload) => void;
    cooldownSeconds?: number;
    otpLength?: number;
    initialCountdown?: number;
};

type UseOtpVerificationReturn = {
    otp: string;
    setOtp: (val: string) => void;
    isSending: boolean;
    isVerifying: boolean;
    error: string | null;
    setError: (val: string | null) => void;
    countdown: number;
    sendOtp: () => Promise<boolean>;
    verifyOtp: () => Promise<void>;
    reset: () => void;
};

export function useOtpVerification({
    sendOtp: sendOtpFn,
    verifyOtp: verifyOtpFn,
    onVerified,
    cooldownSeconds = 120,
    otpLength = 6,
    initialCountdown = 0,
}: UseOtpVerificationOptions): UseOtpVerificationReturn {
    const [otp, setOtp] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [isSending, setIsSending] = useState(false);
    const [isVerifying, setIsVerifying] = useState(false);
    const isVerifyingRef = useRef(false);
    const [countdown, setCountdown] = useState(initialCountdown);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const verifyOtpRef = useRef(verifyOtpFn);
    verifyOtpRef.current = verifyOtpFn;
    const onVerifiedRef = useRef(onVerified);
    onVerifiedRef.current = onVerified;
    const clearCountdownRef = useRef<(() => void) | null>(null);

    // Countdown timer
    useEffect(() => {
        if (countdown <= 0) return;
        timerRef.current = setTimeout(() => setCountdown((c) => c - 1), 1000);
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [countdown]);

    const doVerify = useCallback(async () => {
        if (!otp || otp.length !== otpLength || isVerifyingRef.current) return;

        isVerifyingRef.current = true;
        setIsVerifying(true);
        setError(null);

        try {
            const result = await verifyOtpRef.current(otp);
            setOtp("");
            clearCountdownRef.current?.();
            onVerifiedRef.current?.(result.data);
        } catch (err: unknown) {
            if (isAxiosError(err) && err.response) {
                const data = err.response.data as Record<string, unknown>;
                const message =
                    (data?.message as string) ?? "کد تأیید نامعتبر است.";
                const retryAfter =
                    (data?.retry_after as number | undefined) ??
                    parseRetryAfterHeader(err);

                if (err.response.status === 429 && retryAfter) {
                    setCountdown(retryAfter);
                }

                setError(message);
            } else {
                setError("کد تأیید نامعتبر است.");
            }
        } finally {
            isVerifyingRef.current = false;
            setIsVerifying(false);
        }
    }, [otp, otpLength]);

    // Auto-submit when last digit entered
    useEffect(() => {
        if (otp.length !== otpLength) return;
        void doVerify();
    }, [otp, otpLength, doVerify]);

    const clearCountdown = useCallback(() => {
        if (timerRef.current) clearTimeout(timerRef.current);
        setCountdown(0);
    }, []);

    useEffect(() => {
        clearCountdownRef.current = clearCountdown;
    }, [clearCountdown]);

    const reset = useCallback(() => {
        setOtp("");
        setError(null);
        clearCountdown();
    }, [clearCountdown]);

    const handleSendOtp = useCallback(async (): Promise<boolean> => {
        if (countdown > 0) return false;

        setIsSending(true);
        setError(null);

        try {
            const result = await sendOtpFn();
            const expiresIn = result.data?.expires_in ?? cooldownSeconds;
            setCountdown(expiresIn);

            const message = result.data?.message;
            if (message) {
                if (result.data?.code_sent === false) {
                    toast.info(message);
                } else {
                    toast.success(message);
                }
            }

            return true;
        } catch (err: unknown) {
            if (isAxiosError(err) && err.response) {
                const data = err.response.data as Record<string, unknown>;
                const retryAfter =
                    (data?.retry_after as number | undefined) ??
                    parseRetryAfterHeader(err);
                const message =
                    (data?.message as string) ?? "خطا در ارسال کد تأیید.";

                if (err.response.status === 429 && retryAfter) {
                    setCountdown(retryAfter);
                }

                setError(message);
            } else {
                setError("خطا در ارسال کد تأیید.");
            }

            return false;
        } finally {
            setIsSending(false);
        }
    }, [sendOtpFn, countdown, cooldownSeconds]);

    return {
        otp,
        setOtp,
        isSending,
        isVerifying,
        error,
        setError,
        countdown,
        sendOtp: handleSendOtp,
        verifyOtp: doVerify,
        reset,
    };
}
