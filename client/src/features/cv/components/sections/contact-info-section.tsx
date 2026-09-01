import { ContactInfoSection as SharedContactInfoSection } from "@/components/forms";
import {
    sendCvMobileOtp,
    sendCvEmailOtp,
    verifyCvMobileOtp,
    verifyCvEmailOtp,
} from "@/features/cv/api";
import { cvKeys } from "@/lib/query-keys";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/cv/schemas/contact-info.schema";
import type { Cv, CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
};

export function ContactInfoSection({ form, cv }: SectionProps) {
    const uuid = cv?.uuid ?? "";

    return (
        <SharedContactInfoSection
            form={form}
            emailLabel="ایمیل (اختیاری)"
            emailValidators={zodFieldValidators(fieldSchemas.email)}
            mobileValidators={zodFieldValidators(fieldSchemas.mobile)}
            otp={{
                uuid,
                emailVerified: cv?.email_verified ?? false,
                mobileVerified: cv?.mobile_verified ?? false,
                detailKey: cvKeys.detail,
                sendEmailOtp: sendCvEmailOtp,
                sendMobileOtp: sendCvMobileOtp,
                verifyEmailOtp: verifyCvEmailOtp,
                verifyMobileOtp: verifyCvMobileOtp,
            }}
            addressMode="simple"
        />
    );
}