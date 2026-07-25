import { IconLoader2, IconSend, IconCheck } from "@tabler/icons-react";

import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Field, FieldLabel } from "@/components/ui/field";
import { getApiError } from "@/lib/error-utils";

type OtpVerificationBlockProps = {
    uuid: string;
    label: string;
    value: string;
    isVerified: boolean;
    sendMutation: { mutate: () => void; isPending: boolean };
    verifyMutation: { mutate: (otp: string) => void; isPending: boolean };
    otp: string;
    onOtpChange: (otp: string) => void;
    onVerify: () => void;
};

export function OtpVerificationBlock({
    uuid,
    label,
    value,
    isVerified,
    sendMutation,
    verifyMutation,
    otp,
    onOtpChange,
    onVerify,
}: OtpVerificationBlockProps) {
    return (
        <div className="px-4 pb-4 space-y-3">
            {/* <div className="flex items-center gap-2">
                <span className="text-sm font-medium">{label}</span>
                <Badge variant={isVerified ? "default" : "secondary"}>
                    {isVerified ? "تأیید شده" : "تأیید نشده"}
                </Badge>
            </div> */}
            <div className="flex items-end gap-2">
                <Field className="flex-1">
                    <FieldLabel htmlFor={`otp_${label}`}>
                        <span>کد تأیید {label}</span>
                        <Badge variant={isVerified ? "default" : "secondary"}>
                            {isVerified ? "تأیید شده" : "تأیید نشده"}
                        </Badge>
                    </FieldLabel>
                    <Input
                        id={`otp_${label}`}
                        value={otp}
                        onChange={(e) => onOtpChange(e.target.value)}
                        dir="ltr"
                        placeholder="۶ رقمی"
                        maxLength={6}
                        disabled={isVerified}
                        autoComplete="one-time-code"
                    />
                </Field>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => sendMutation.mutate()}
                    disabled={sendMutation.isPending || isVerified}
                >
                    {sendMutation.isPending ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconSend className="size-4" />
                    )}
                    ارسال کد
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onVerify}
                    disabled={
                        verifyMutation.isPending ||
                        otp.length !== 6 ||
                        isVerified
                    }
                >
                    {verifyMutation.isPending ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconCheck className="size-4" />
                    )}
                    تأیید
                </Button>
            </div>
        </div>
    );
}
