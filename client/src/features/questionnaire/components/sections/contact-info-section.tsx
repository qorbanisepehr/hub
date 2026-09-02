import { ContactInfoSection as SharedContactInfoSection } from "@/components/forms";
import {
    sendMobileOtp,
    sendEmailOtp,
    verifyMobileOtp,
    verifyEmailOtp,
} from "@/features/questionnaire/api";
import { questionnaireKeys } from "@/lib/query-keys";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/questionnaire/schemas/contact-info.schema";
import type {
    Questionnaire,
    QuestionnaireFormApi,
} from "@/features/questionnaire/types";

type SectionProps = {
    form: QuestionnaireFormApi;
    questionnaire?: Questionnaire | null;
};

export function ContactInfoSection({ form, questionnaire }: SectionProps) {
    const uuid = questionnaire?.uuid ?? "";

    return (
        <SharedContactInfoSection
            form={form}
            emailValidators={zodFieldValidators(fieldSchemas.email)}
            mobileValidators={zodFieldValidators(fieldSchemas.mobile)}
            otp={{
                uuid,
                emailVerified: questionnaire?.email_verified ?? false,
                mobileVerified: questionnaire?.mobile_verified ?? false,
                detailKey: questionnaireKeys.detail,
                sendEmailOtp,
                sendMobileOtp,
                verifyEmailOtp,
                verifyMobileOtp,
            }}
        />
    );
}