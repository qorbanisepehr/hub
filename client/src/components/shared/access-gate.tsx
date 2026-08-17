import { useCallback, useEffect, useState } from "react";
import type { ReactNode } from "react";
import { isAxiosError } from "axios";
import { IconLoader2, IconLock } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorPage } from "@/components/layout";
import { OtpVerificationForm } from "@/components/forms";
import { onGrantUnauthorized } from "@/lib/public-api";
import { hasGrant, setGrantToken } from "@/lib/grant";
import type { GrantPurpose } from "@/lib/grant";
import { checkAccess, requestAccess, verifyAccessOtp } from "@/features/questionnaire/api";
import type { OtpVerifyPayload } from "@/hooks/use-otp-verification";

type AccessGateProps = {
    entity: string;
    uuid: string;
    purpose: GrantPurpose;
    children: ReactNode;
    title?: string;
    description?: string;
    cooldownSeconds?: number;
};

type AccessStatus = "checking" | "exists" | "not-found" | "error";

export function AccessGate({
    entity,
    uuid,
    purpose,
    children,
    title = "دسترسی محافظت‌شده",
    description,
    cooldownSeconds = 120,
}: AccessGateProps) {
    const [granted, setGranted] = useState(() =>
        hasGrant(entity, uuid, purpose),
    );
    const [status, setStatus] = useState<AccessStatus>(() =>
        granted ? "exists" : "checking",
    );

    useEffect(() => {
        if (granted) return;
        if (status !== "checking") return;

        let cancelled = false;

        checkAccess(entity, uuid)
            .then(() => {
                if (!cancelled) setStatus("exists");
            })
            .catch((err: unknown) => {
                if (cancelled) return;
                if (isAxiosError(err) && err.response?.status === 404) {
                    setStatus("not-found");
                } else {
                    setStatus("error");
                }
            });

        return () => {
            cancelled = true;
        };
    }, [entity, uuid, granted, status]);

    useEffect(() => {
        return onGrantUnauthorized(
            ({ entity: eventEntity, uuid: eventUuid }) => {
                if (eventEntity === entity && eventUuid === uuid) {
                    setGranted(false);
                }
            },
        );
    }, [entity, uuid]);

    const handleVerified = useCallback(
        (data: OtpVerifyPayload) => {
            if (!data.access_token) return;

            setGrantToken(
                entity,
                uuid,
                purpose,
                data.access_token,
                data.expires_in ?? 3600,
            );
            setGranted(true);
        },
        [entity, uuid, purpose],
    );

    if (status === "not-found") {
        return <ErrorPage status={404} homeTo="/" />;
    }

    if (status === "error") {
        return (
            <ErrorPage
                title="خطا در بررسی دسترسی"
                description="خطایی در بررسی دسترسی رخ داده است. لطفاً بعداً دوباره تلاش کنید."
                homeTo="/"
            />
        );
    }

    if (status === "checking") {
        return (
            <div className="flex min-h-dvh items-center justify-center bg-background">
                <IconLoader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        );
    }

    if (granted) {
        return <>{children}</>;
    }

    return (
        <div className="mx-auto max-w-2xl px-4 py-16">
            <Card className="my-8">
                <CardHeader className="text-center">
                    <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-muted">
                        <IconLock className="size-7 text-muted-foreground" />
                    </div>
                    <CardTitle>{title}</CardTitle>
                </CardHeader>
                <CardContent>
                    {description && (
                        <p className="mb-6 text-center text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}

                    <OtpVerificationForm
                        sendOtp={() => requestAccess(entity, uuid)}
                        verifyOtp={(otp) =>
                            verifyAccessOtp(entity, uuid, otp, purpose)
                        }
                        onVerified={handleVerified}
                        label="کد تأیید"
                        cooldownSeconds={cooldownSeconds}
                        autoSend
                    />
                </CardContent>
            </Card>
        </div>
    );
}
