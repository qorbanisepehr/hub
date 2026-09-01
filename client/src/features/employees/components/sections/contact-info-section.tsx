import { ContactInfoSection as SharedContactInfoSection } from "@/components/forms";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { optionalEmail } from "@/lib/field-rules";
import { fieldSchemas } from "@/features/questionnaire/schemas/contact-info.schema";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
};

/**
 * Employee variant of the contact section: same fields as the questionnaire/CV
 * (email, mobile, landline, emergency phone and address) but WITHOUT the OTP
 * verification inputs — employees persist email/mobile directly to the real
 * columns on save, so the inputs are plain text fields. Email stays optional
 * while drafting; the submit schema enforces it.
 */
export function ContactInfoSection({ form }: SectionProps) {
    return (
        <SharedContactInfoSection
            form={form}
            emailValidators={zodFieldValidators(optionalEmail())}
            mobileValidators={zodFieldValidators(fieldSchemas.mobile)}
        />
    );
}